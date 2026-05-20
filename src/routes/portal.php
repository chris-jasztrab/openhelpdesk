<?php

declare(strict_types=1);

/* ==================================================================
 * PORTAL – Ticket List (user's own tickets)
 * ================================================================== */

$router->get('/portal/tickets', function () {
    Auth::requireAuth();
    $db = Database::connect();

    // Read filter params
    $fStatus   = trim($_GET['status'] ?? 'open');
    $fPriority = trim($_GET['priority'] ?? '');
    $fSearch   = trim($_GET['q'] ?? '');
    $fScope    = trim($_GET['scope'] ?? 'mine'); // 'mine' or 'location'

    $uid = Auth::id();

    // Check if this user has location-ticket visibility enabled
    $permStmt = $db->prepare('SELECT can_view_location_tickets, location_id FROM users WHERE id = ?');
    $permStmt->execute([$uid]);
    $userPerms = $permStmt->fetch();

    $canViewLocation = (bool) ($userPerms['can_view_location_tickets'] && $userPerms['location_id']);

    // Base: own tickets (not merged away) plus master tickets whose merged child belongs to this user
    $ownCond = '(t.created_by = ? AND t.merged_into_ticket_id IS NULL)
                OR t.id IN (SELECT DISTINCT merged_into_ticket_id FROM tickets WHERE created_by = ? AND merged_into_ticket_id IS NOT NULL)';

    if ($canViewLocation && $fScope === 'location') {
        // Show all non-merged tickets at the user's location, but never surface
        // confidential-type tickets or types flagged as hidden from location
        // visibility (e.g. Collections, HR) — those stay restricted to agents
        // and the requester's own view of their own ticket.
        $locationCond = 't.location_id = ? AND t.merged_into_ticket_id IS NULL
                        AND NOT EXISTS (
                            SELECT 1 FROM ticket_types ct
                            WHERE ct.id = t.type_id
                              AND (ct.is_confidential = 1 OR ct.show_to_location_visibility = 0)
                        )';
        $where  = ['(' . $ownCond . ' OR (' . $locationCond . '))'];
        $params = [$uid, $uid, (int) $userPerms['location_id']];
    } else {
        $where  = ['(' . $ownCond . ')'];
        $params = [$uid, $uid];
    }

    if ($fStatus !== '') {
        $where[]  = 't.status = ?';
        $params[] = $fStatus;
    }
    if ($fPriority !== '') {
        $where[]  = 't.priority_id = ?';
        $params[] = (int) $fPriority;
    }
    if ($fSearch !== '') {
        $where[]  = 't.subject LIKE ?';
        $params[] = '%' . $fSearch . '%';
    }

    $whereClause = ' WHERE ' . implode(' AND ', $where);

    // Count total matching tickets
    $countSql = "SELECT COUNT(*) FROM tickets t" . $whereClause;
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalTickets = (int) $countStmt->fetchColumn();

    // Count all accessible tickets (scope only, no status/priority/search — for filtered badge)
    if ($canViewLocation && $fScope === 'location') {
        $allCountSql = "SELECT COUNT(*) FROM tickets t WHERE (" . $ownCond . " OR (" . $locationCond . "))";
        $allCountStmt = $db->prepare($allCountSql);
        $allCountStmt->execute([$uid, $uid, (int) $userPerms['location_id']]);
    } else {
        $allCountSql = "SELECT COUNT(*) FROM tickets t WHERE (" . $ownCond . ")";
        $allCountStmt = $db->prepare($allCountSql);
        $allCountStmt->execute([$uid, $uid]);
    }
    $allTickets        = (int) $allCountStmt->fetchColumn();
    $userHasAnyTickets = $allTickets > 0;

    // Sorting
    $sortableColumns = [
        'id'         => 't.id',
        'subject'    => 't.subject',
        'status'     => 't.status',
        'priority'   => 'tp.sort_order',
        'type'       => 'tt.name',
        'agent'      => 'a.first_name',
        'created_at' => 't.created_at',
    ];
    $sort = $_GET['sort'] ?? 'created_at';
    $dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $orderCol = $sortableColumns[$sort] ?? 't.created_at';

    // Pagination
    $perPage    = 30;
    $totalPages = max(1, (int) ceil($totalTickets / $perPage));
    $page       = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
    $offset     = ($page - 1) * $perPage;

    $sql = "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name, tt.color AS type_color,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users a             ON t.assigned_to  = a.id"
         . $whereClause . " ORDER BY {$orderCol} {$dir} LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    // Load filter dropdown options
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();

    $filters = [
        'status'   => $fStatus,
        'priority' => $fPriority,
        'q'        => $fSearch,
        'scope'    => $fScope,
    ];

    render('portal/tickets/index', [
        'tickets'           => $tickets,
        'priorities'        => $priorities,
        'filters'           => $filters,
        'page'              => $page,
        'totalPages'        => $totalPages,
        'totalTickets'      => $totalTickets,
        'allTickets'        => $allTickets,
        'sort'              => $sort,
        'dir'               => strtolower($dir),
        'userHasAnyTickets' => $userHasAnyTickets,
        'canViewLocation'   => $canViewLocation,
    ]);
});

/* ==================================================================
 * PORTAL – Create Ticket
 * ================================================================== */

$router->get('/portal/tickets/create', function () {
    Auth::requireAuth();
    $db = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $tags       = $db->query('SELECT * FROM ticket_tags ORDER BY name')->fetchAll();

    // Deep-link support: ?type_id=N (numeric ID) or ?type=Name (case-insensitive,
    // hyphens/underscores treated as spaces). ID wins; name is the shareable fallback.
    $preselectedTypeId = null;
    if (isset($_GET['type_id']) && ctype_digit((string) $_GET['type_id'])) {
        $wantId = (int) $_GET['type_id'];
        foreach ($types as $t) {
            if ((int) $t['id'] === $wantId) { $preselectedTypeId = $wantId; break; }
        }
    }
    if ($preselectedTypeId === null && !empty($_GET['type'])) {
        $needle = strtolower(trim(str_replace(['-', '_'], ' ', (string) $_GET['type'])));
        foreach ($types as $t) {
            if (strtolower($t['name']) === $needle) {
                $preselectedTypeId = (int) $t['id'];
                break;
            }
        }
    }

    // Determine the user's location from their profile
    $userStmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
    $userStmt->execute([Auth::id()]);
    $userLocationId  = $userStmt->fetchColumn() ?: null;
    $userHasNoLocation = ($userLocationId === null || $userLocationId === false);
    $userLocationName = '';
    foreach ($locations as $loc) {
        if ((int) $loc['id'] === (int) $userLocationId) {
            $userLocationName = $loc['name'];
            break;
        }
    }

    // Build the per-type form layouts. The portal page renders every field
    // that appears on any ticket type once, then JS reorders + toggles
    // required/hidden as the user picks a type.
    $formLayouts  = [];   // typeId => [['kind','key','sort_order','visibility','label'], ...]
    $customFields = [];   // unique custom-field definitions for rendering
    $seenCustomIds = [];
    foreach ($types as $t) {
        $layout = getFormLayoutForType($db, (int) $t['id'], false);
        $slim = [];
        foreach ($layout as $row) {
            $slim[] = [
                'kind'       => $row['kind'],
                'key'        => $row['key'],
                'sort_order' => $row['sort_order'],
                'visibility' => $row['visibility'],
                'label'      => $row['label'],
            ];
            if ($row['kind'] === 'custom' && $row['field'] && !isset($seenCustomIds[$row['field']['id']])) {
                $seenCustomIds[$row['field']['id']] = true;
                $customFields[] = $row['field'];
            }
        }
        $formLayouts[(int) $t['id']] = $slim;
    }

    $fieldOptions = [];
    foreach ($customFields as $f) {
        if (in_array($f['field_type'], ['dropdown', 'dependent'], true)) {
            $s = $db->prepare(
                'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
            );
            $s->execute([$f['id']]);
            $fieldOptions[$f['id']] = $s->fetchAll();
        }
    }

    // Shared ticket templates for the template picker
    $sharedTemplates = $db->query(
        'SELECT t.*, tp.name AS type_name, pri.name AS priority_name
         FROM ticket_templates t
         LEFT JOIN ticket_types tp ON t.type_id = tp.id
         LEFT JOIN ticket_priorities pri ON t.priority_id = pri.id
         WHERE t.is_shared = 1
         ORDER BY t.name'
    )->fetchAll();

    // Embed mode: chrome-less, submit-disabled rendering for the admin form-builder
    // live-preview iframe. Read-only on purpose — never affects the real form behaviour
    // for portal users.
    $embedMode = !empty($_GET['embed']);

    // The site-wide baseline (src/bootstrap.php) sets X-Frame-Options: DENY and
    // CSP frame-ancestors 'none', which blocks even same-origin framing. For embed
    // mode only, downgrade both to permit same-origin framing so the form-builder
    // live preview can iframe this page. Headers replace by default in PHP.
    if ($embedMode && !headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header(
            "Content-Security-Policy: default-src 'self'; "
          . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.ckeditor.com; "
          . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.ckeditor.com; "
          . "font-src 'self' data: https://cdn.jsdelivr.net https://cdn.ckeditor.com; "
          . "img-src 'self' data: blob:; "
          . "connect-src 'self' https://cdn.ckeditor.com; "
          . "frame-ancestors 'self'; "
          . "base-uri 'self'; "
          . "form-action 'self'"
        );
    }

    render('portal/tickets/create', [
        'types'             => $types,
        'locations'         => $locations,
        'priorities'        => $priorities,
        'tags'              => $tags,
        'userLocationId'    => $userLocationId,
        'userLocationName'  => $userLocationName,
        'userHasNoLocation' => $userHasNoLocation,
        'customFields'      => $customFields,
        'fieldOptions'      => $fieldOptions,
        'sharedTemplates'   => $sharedTemplates,
        'formLayouts'       => $formLayouts,
        'preselectedTypeId' => $preselectedTypeId,
        'embedMode'         => $embedMode,
    ]);
});

$router->post('/portal/tickets/create', function () {
    Auth::requireAuth();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/portal/tickets/create');
    }

    $subject     = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $typeId      = !empty($_POST['type_id']) ? (int) $_POST['type_id'] : null;
    $priorityId  = !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null;
    $browserInfo = trim($_POST['browser_info'] ?? '');
    $osInfo      = trim($_POST['os_info'] ?? '');
    $tagNames    = $_POST['tags'] ?? [];

    // Determine location: use the submitted value if provided, otherwise fall back to profile location
    $db = Database::connect();
    $userLocStmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
    $userLocStmt->execute([Auth::id()]);
    $profileLocationId = $userLocStmt->fetchColumn() ?: null;

    $chosenLocId = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
    if ($chosenLocId) {
        $locCheck = $db->prepare('SELECT id FROM locations WHERE id = ?');
        $locCheck->execute([$chosenLocId]);
        $locationId = $locCheck->fetchColumn() ? $chosenLocId : $profileLocationId;
    } else {
        $locationId = $profileLocationId;
    }

    // Assignment is handled by agents/admins, not portal users.
    // The auto-assign helper (called after INSERT) may pick an agent based on
    // the destination group's strategy; until then leave unassigned.
    $assignedTo = null;

    // Derive group from the ticket type so the auto-assign helper has a
    // group to consult. Portal users never pick a group directly.
    // resolveTicketGroup() handles the type-group lookup and falls
    // through to the system-wide default if the type has none.
    $groupId = resolveTicketGroup($db, null, $typeId);

    if ($subject === '' || $description === '' || $typeId === null) {
        flashInput($_POST);
        flash('error', 'Subject, description, and type are required.');
        redirect('/portal/tickets/create');
    }

    // Resolve this ticket type's form layout server-side and validate
    // against it, ignoring whatever the client did or didn't render.
    $typeLayout = $typeId ? getFormLayoutForType($db, $typeId, false) : [];
    $visByKey = [];
    foreach ($typeLayout as $r) {
        $visByKey[$r['kind'] . '|' . $r['key']] = $r['visibility'];
    }
    $priorityVis = $visByKey['system|priority'] ?? 'optional';
    if ($priorityVis === 'hidden') {
        $priorityId = getDefaultPriorityId($db);
    } elseif ($priorityVis === 'required' && $priorityId === null) {
        flashInput($_POST);
        flash('error', getSetting('sys_field_label_priority', 'Priority') . ' is required.');
        redirect('/portal/tickets/create');
    }

    $tagsVis = $visByKey['system|tags'] ?? 'optional';
    if ($tagsVis === 'required' && empty($tagNames)) {
        flashInput($_POST);
        flash('error', getSetting('sys_field_label_tags', 'Tags') . ' is required.');
        redirect('/portal/tickets/create');
    }
    if ($tagsVis === 'hidden') {
        $tagNames = [];
    }

    // Validate custom fields per this type's layout
    $customRowsForType = array_values(array_filter($typeLayout, fn($r) => $r['kind'] === 'custom' && $r['field'] !== null));
    foreach ($customRowsForType as $row) {
        $cf = $row['field'];
        if ($row['visibility'] !== 'required') continue;
        if (in_array($cf['field_type'], ['text_block', 'image'], true)) continue; // display-only, no value
        $key = 'field_' . $cf['id'];
        if ($cf['field_type'] === 'cc') {
            $ccIds = array_filter(array_map('intval', (array) ($_POST['cc_field_' . $cf['id']] ?? [])));
            $missing = empty($ccIds);
        } elseif ($cf['field_type'] === 'dependent') {
            $missing = empty($_POST[$key . '_l1']);
        } elseif ($cf['field_type'] === 'checkbox') {
            $missing = false; // checkboxes are never "missing"
        } else {
            $missing = !isset($_POST[$key]) || trim($_POST[$key]) === '';
        }
        if ($missing) {
            flashInput($_POST);
            flash('error', e($cf['label']) . ' is required.');
            redirect('/portal/tickets/create');
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO tickets (subject, description, browser_info, os_info, created_by, type_id, location_id, status, priority_id, assigned_to, group_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $subject, $description,
        $browserInfo ?: null, $osInfo ?: null,
        Auth::id(), $typeId, $locationId, 'open', $priorityId, $assignedTo, $groupId,
    ]);
    $ticketId = (int) $db->lastInsertId();

    // AI classification (if enabled & non-confidential type) + strategy-based
    // auto-assignment. Both no-op cleanly when their preconditions aren't met.
    $autoAssignedTo = runPostTicketCreateHooks($db, $ticketId);

    // Attach tags (create if they don't exist)
    if (!empty($tagNames)) {
        $findTag   = $db->prepare('SELECT id FROM ticket_tags WHERE name = ?');
        $createTag = $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)');
        $mapStmt   = $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)');
        foreach ($tagNames as $rawName) {
            $name = trim(preg_replace('/[^a-zA-Z0-9_\-\s]/', '', strtolower($rawName)));
            if ($name === '') continue;
            $findTag->execute([$name]);
            $tagId = $findTag->fetchColumn();
            if (!$tagId) {
                $createTag->execute([$name]);
                $tagId = (int) $db->lastInsertId();
            }
            $mapStmt->execute([$ticketId, (int) $tagId]);
        }
    }

    // Timeline: ticket created
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details) VALUES (?, ?, ?, ?)'
    )->execute([$ticketId, Auth::id(), 'created', 'Ticket created.']);

    // If the AI dup-check warned the user and they overrode it, record that
    // on the new ticket so admins can see the override happened.
    $dupOverrideCsv = (string) ($_POST['_dup_matched_ids'] ?? '');
    if ($dupOverrideCsv !== '') {
        recordDupOverrideOnNewTicket($db, $ticketId, (int) Auth::id(), $dupOverrideCsv);
    }

    // Save custom field values
    if (!empty($visibleCustomFields)) {
        $cfSaveStmt = $db->prepare(
            'INSERT INTO ticket_field_values (ticket_id, field_id, value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        foreach ($visibleCustomFields as $cf) {
            if (in_array($cf['field_type'], ['text_block', 'image', 'cc'], true)) continue; // display-only or handled separately
            $key = 'field_' . $cf['id'];
            if ($cf['field_type'] === 'dependent') {
                $val = json_encode([
                    'l1' => $_POST[$key . '_l1'] ?? null,
                    'l2' => $_POST[$key . '_l2'] ?? null,
                    'l3' => $_POST[$key . '_l3'] ?? null,
                ]);
            } elseif ($cf['field_type'] === 'date_range') {
                $from = $_POST[$key . '_from'] ?? '';
                $to   = $_POST[$key . '_to']   ?? '';
                if ($from === '' && $to === '') continue;
                $val = json_encode(['from' => $from, 'to' => $to]);
            } elseif ($cf['field_type'] === 'checkbox') {
                $val = isset($_POST[$key]) ? '1' : '0';
            } else {
                $val = $_POST[$key] ?? null;
                if ($val === null || trim($val) === '') continue;
            }
            $cfSaveStmt->execute([$ticketId, $cf['id'], $val]);
        }

        // Process CC fields — insert into ticket_cc
        $ccInsert = $db->prepare('INSERT IGNORE INTO ticket_cc (ticket_id, user_id, added_by) VALUES (?, ?, ?)');
        foreach ($visibleCustomFields as $cf) {
            if ($cf['field_type'] !== 'cc') continue;
            $ccIds = array_values(array_unique(array_map('intval', (array) ($_POST['cc_field_' . $cf['id']] ?? []))));
            foreach ($ccIds as $uid) {
                if ($uid > 0) {
                    $ccInsert->execute([$ticketId, $uid, Auth::id()]);
                }
            }
        }
    }

    // Handle file attachments
    $attachments = handleAttachmentUploads('attachments');
    saveAttachments($db, $ticketId, null, Auth::id(), $attachments);

    // Send confirmation email to ticket creator (gated by global + user prefs)
    notifyRequesterTicketCreated($db, $ticketId);

    // Notify group members watching new tickets
    notifyGroupMembers($db, $ticketId);

    // If auto-assignment picked an agent, send the standard "ticket assigned"
    // notifications so the chosen agent and the requester are in the loop.
    if ($autoAssignedTo !== null) {
        notifyAssignedAgent($db, $ticketId, $autoAssignedTo);
        notifyRequesterTicketAssigned($db, $ticketId, $autoAssignedTo);
    }

    // Initialize SLA timers if priority is set and SLA is configured
    if ($priorityId) {
        Sla::initializeForTicket($db, $ticketId, $priorityId, $typeId);
    }

    $msg = 'Help request #' . $ticketId . ' submitted.';
    if (!empty($attachments)) {
        $msg .= ' ' . count($attachments) . ' file(s) attached.';
    }
    flash('success', $msg);
    redirect('/portal/tickets/' . $ticketId);
});

/* ==================================================================
 * PORTAL – View Ticket (must be own ticket)
 * ================================================================== */

$router->get('/portal/tickets/{id}', function (array $p) {
    Auth::requireAuth();
    $db  = Database::connect();
    $uid = Auth::id();
    $tid = (int) $p['id'];

    // If this is the user's own ticket and it was merged, redirect to the master ticket
    $mergedCheck = $db->prepare('SELECT merged_into_ticket_id FROM tickets WHERE id = ? AND created_by = ?');
    $mergedCheck->execute([$tid, $uid]);
    $mergedRow = $mergedCheck->fetch();
    if ($mergedRow && $mergedRow['merged_into_ticket_id']) {
        redirect('/portal/tickets/' . (int) $mergedRow['merged_into_ticket_id']);
    }

    // Check location-ticket visibility permission
    $permStmt = $db->prepare('SELECT can_view_location_tickets, location_id FROM users WHERE id = ?');
    $permStmt->execute([$uid]);
    $userPerms = $permStmt->fetch();

    $accessCond   = '(t.created_by = ? OR t.id IN (SELECT DISTINCT merged_into_ticket_id FROM tickets WHERE created_by = ? AND merged_into_ticket_id IS NOT NULL))';
    $accessParams = [$tid, $uid, $uid];
    if ($userPerms['can_view_location_tickets'] && $userPerms['location_id']) {
        // Location visibility never includes confidential-type tickets, nor
        // types whose admin has opted out of location-visibility sharing
        // (e.g. Collections, HR). Requesters still see their own via created_by.
        $accessCond   = '(' . $accessCond . ' OR (t.location_id = ? AND NOT EXISTS (
                            SELECT 1 FROM ticket_types ct
                            WHERE ct.id = t.type_id
                              AND (ct.is_confidential = 1 OR ct.show_to_location_visibility = 0)
                        )))';
        $accessParams[] = (int) $userPerms['location_id'];
    }

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name, tt.color AS type_color,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         WHERE t.id = ? AND {$accessCond}"
    );
    $stmt->execute($accessParams);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/portal/tickets');
    }

    // Tags
    $tags = $db->prepare(
        'SELECT tt.name FROM ticket_tags tt
         INNER JOIN ticket_tag_map ttm ON tt.id = ttm.tag_id
         WHERE ttm.ticket_id = ?'
    );
    $tags->execute([$ticket['id']]);
    $ticket['tags'] = $tags->fetchAll(PDO::FETCH_COLUMN);

    // Timeline (exclude internal notes for portal users)
    $tl = $db->prepare(
        "SELECT tl.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_timeline tl
         LEFT JOIN users u ON tl.user_id = u.id
         WHERE tl.ticket_id = ? AND tl.is_internal = 0
         ORDER BY tl.created_at DESC"
    );
    $tl->execute([$ticket['id']]);
    $timeline = $tl->fetchAll();

    // Attachments (exclude those on internal notes)
    $attStmt = $db->prepare(
        'SELECT ta.* FROM ticket_attachments ta
         LEFT JOIN ticket_timeline tl ON ta.timeline_id = tl.id
         WHERE ta.ticket_id = ? AND (tl.is_internal IS NULL OR tl.is_internal = 0)
         ORDER BY ta.created_at ASC'
    );
    $attStmt->execute([$ticket['id']]);
    $attachments = $attStmt->fetchAll();

    // CC'd users
    $ccStmt = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role
         FROM ticket_cc tc
         JOIN users u ON tc.user_id = u.id
         WHERE tc.ticket_id = ?
         ORDER BY u.first_name'
    );
    $ccStmt->execute([$ticket['id']]);
    $ccUsers = $ccStmt->fetchAll();

    // Custom field values — only show fields on this ticket type's form,
    // plus any field that already has a stored value, so a request still
    // shows the data the requester submitted even if the field's visibility
    // has since been changed for the type's form.
    $fieldValues = [];
    $fvStmt = $db->prepare('SELECT field_id, value FROM ticket_field_values WHERE ticket_id = ?');
    $fvStmt->execute([$ticket['id']]);
    foreach ($fvStmt->fetchAll() as $fv) {
        $fieldValues[(int) $fv['field_id']] = $fv['value'];
    }

    $customFields = [];
    $seenFieldIds = [];
    foreach (getFormLayoutForType($db, (int) $ticket['type_id'], true) as $row) {
        if ($row['kind'] === 'custom' && $row['field']) {
            $customFields[] = $row['field'];
            $seenFieldIds[(int) $row['field']['id']] = true;
        }
    }
    // Fields with a stored value but no longer on this type's form.
    $orphanIds = array_diff(array_keys($fieldValues), array_keys($seenFieldIds));
    if ($orphanIds) {
        $ph = implode(',', array_fill(0, count($orphanIds), '?'));
        $oStmt = $db->prepare(
            "SELECT id, field_type, label, placeholder, config
             FROM ticket_form_fields
             WHERE id IN ($ph) AND deleted_at IS NULL ORDER BY id"
        );
        $oStmt->execute(array_values($orphanIds));
        $customFields = array_merge($customFields, $oStmt->fetchAll());
    }

    $fieldOptions = [];
    foreach ($customFields as $f) {
        if (in_array($f['field_type'], ['dropdown', 'dependent'], true)) {
            $s = $db->prepare(
                'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
            );
            $s->execute([$f['id']]);
            $fieldOptions[$f['id']] = $s->fetchAll();
        }
    }

    // Drop fields with nothing to show — the view would render them as a
    // bare "—" placeholder, which is just noise on the ticket.
    $customFields = array_values(array_filter(
        $customFields,
        fn($f) => customFieldHasDisplayValue(
            $f['field_type'],
            $fieldValues[$f['id']] ?? '',
            $fieldOptions[$f['id']] ?? []
        )
    ));

    $isOwner = (int) $ticket['created_by'] === (int) $uid;

    // Escalation context (owners only — only the requester can escalate their own ticket)
    $hasEscalationPath  = false;
    $nextEscalationStep = null;
    if ($isOwner && $ticket['type_id']) {
        $hStmt = $db->prepare('SELECT COUNT(*) FROM ticket_escalation_steps WHERE ticket_type_id = ?');
        $hStmt->execute([$ticket['type_id']]);
        $hasEscalationPath = (int) $hStmt->fetchColumn() > 0;
        if ($hasEscalationPath) {
            $nextEscalationStep = nextEscalationStep(
                $db,
                (int) $ticket['type_id'],
                (int) ($ticket['escalation_level'] ?? 0),
                (int) $uid,
                !empty($ticket['assigned_to']) ? (int) $ticket['assigned_to'] : null
            );
        }
    }

    // Floor-mode entry: hide app chrome and overlay an ✕ that returns
    // to /portal/floor/tickets/{id}. Picked up by portal/tickets/view.php.
    $fromFloor = ($_GET['from'] ?? '') === 'floor';

    render('portal/tickets/view', [
        'ticket'             => $ticket,
        'timeline'           => $timeline,
        'attachments'        => $attachments,
        'ccUsers'            => $ccUsers,
        'customFields'       => $customFields,
        'fieldValues'        => $fieldValues,
        'fieldOptions'       => $fieldOptions,
        'isOwner'            => $isOwner,
        'hasEscalationPath'  => $hasEscalationPath,
        'nextEscalationStep' => $nextEscalationStep,
        'fromFloor'          => $fromFloor,
        'embedMode'          => $fromFloor,
    ]);
});

/* ==================================================================
 * PORTAL – Add Comment to Ticket
 * ================================================================== */

$router->post('/portal/tickets/{id}/comment', function (array $p) {
    Auth::requireAuth();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/portal/tickets/{$id}");
    }

    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        flash('error', 'Comment cannot be empty.');
        redirect("/portal/tickets/{$id}");
    }

    $db = Database::connect();

    // Verify access: own ticket, merged master, or location-visible ticket
    $uid      = Auth::id();
    $permStmt = $db->prepare('SELECT can_view_location_tickets, location_id FROM users WHERE id = ?');
    $permStmt->execute([$uid]);
    $userPerms = $permStmt->fetch();

    $accessCond   = '(tickets.created_by = ? OR tickets.id IN (SELECT DISTINCT merged_into_ticket_id FROM tickets t2 WHERE t2.created_by = ? AND t2.merged_into_ticket_id IS NOT NULL))';
    $accessParams = [$id, $uid, $uid];
    if ($userPerms['can_view_location_tickets'] && $userPerms['location_id']) {
        // Mirror the view-access rule: location visibility excludes confidential
        // types and types opted out of location-visibility sharing.
        $accessCond   = '(' . $accessCond . ' OR (tickets.location_id = ? AND NOT EXISTS (
                            SELECT 1 FROM ticket_types ct
                            WHERE ct.id = tickets.type_id
                              AND (ct.is_confidential = 1 OR ct.show_to_location_visibility = 0)
                        )))';
        $accessParams[] = (int) $userPerms['location_id'];
    }

    $stmt = $db->prepare("SELECT id FROM tickets WHERE tickets.id = ? AND {$accessCond}");
    $stmt->execute($accessParams);
    if (!$stmt->fetch()) {
        flash('error', 'Ticket not found.');
        redirect('/portal/tickets');
    }

    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
    )->execute([$id, Auth::id(), 'comment', $message]);
    $timelineId = (int) $db->lastInsertId();

    // Handle file attachments
    $attachments = handleAttachmentUploads('attachments');
    saveAttachments($db, $id, $timelineId, Auth::id(), $attachments);

    // Notify CC'd users and assigned agent when portal user replies
    notifyCcUsers($db, $id, $message, Auth::fullName());
    notifyAgentRequesterReplied($db, $id, $message);

    $msg = 'Comment added.';
    if (!empty($attachments)) {
        $msg = 'Comment added with ' . count($attachments) . ' file(s).';
    }
    flash('success', $msg);
    redirect("/portal/tickets/{$id}");
});

/* ==================================================================
 * PORTAL – Close Ticket (requester self-close)
 * ================================================================== */

$router->post('/portal/tickets/{id}/close', function (array $p) {
    Auth::requireAuth();
    $id  = (int) $p['id'];
    $uid = Auth::id();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/portal/tickets/{$id}");
    }

    $db = Database::connect();

    // Only the ticket creator can close it
    $stmt = $db->prepare('SELECT id, status FROM tickets WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $uid]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/portal/tickets');
    }

    if ($ticket['status'] === 'closed') {
        flash('info', 'Ticket is already closed.');
        redirect("/portal/tickets/{$id}");
    }

    $oldStatus = $ticket['status'];
    $db->prepare('UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?')
       ->execute(['closed', $id]);

    // Internal audit trail entry — visible to admins/agents only
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 1)'
    )->execute([$id, $uid, 'status_changed', "Requester closed ticket (was: {$oldStatus})"]);

    flash('success', 'Your ticket has been closed.');
    redirect("/portal/tickets/{$id}");
});

/* ==================================================================
 * PORTAL – Edit Ticket (requester self-edit)
 * ================================================================== */

$router->get('/portal/tickets/{id}/edit', function (array $p) {
    Auth::requireAuth();
    $id  = (int) $p['id'];
    $uid = Auth::id();

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $uid]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/portal/tickets');
    }

    if ($ticket['status'] === 'closed') {
        flash('error', 'Closed tickets cannot be edited.');
        redirect("/portal/tickets/{$id}");
    }

    render('portal/tickets/edit', ['ticket' => $ticket]);
});

$router->post('/portal/tickets/{id}/edit', function (array $p) {
    Auth::requireAuth();
    $id  = (int) $p['id'];
    $uid = Auth::id();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/portal/tickets/{$id}");
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $uid]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/portal/tickets');
    }

    if ($ticket['status'] === 'closed') {
        flash('error', 'Closed tickets cannot be edited.');
        redirect("/portal/tickets/{$id}");
    }

    $subject     = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($subject === '' || $description === '') {
        flash('error', 'Subject and description are required.');
        redirect("/portal/tickets/{$id}/edit");
    }

    // Build audit trail before updating
    $changes = [];
    if ($subject !== $ticket['subject']) {
        $changes[] = "Subject changed from \"{$ticket['subject']}\" to \"{$subject}\"";
    }
    if ($description !== $ticket['description']) {
        $changes[] = 'Description updated';
    }

    if (empty($changes)) {
        flash('info', 'No changes were made.');
        redirect("/portal/tickets/{$id}");
    }

    $db->prepare('UPDATE tickets SET subject = ?, description = ?, updated_at = NOW() WHERE id = ?')
       ->execute([$subject, $description, $id]);

    // Internal audit trail entry — visible to admins/agents only
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 1)'
    )->execute([$id, $uid, 'edited', implode("\n", $changes)]);

    flash('success', 'Ticket updated.');
    redirect("/portal/tickets/{$id}");
});

/* ==================================================================
 * PORTAL – Help / Documentation
 *
 * Patron-facing docs. Same structure as the agent help section
 * (overview index + per-page templates) but written for non-staff.
 * ================================================================== */

$validPortalHelpPages = ['floor'];

$router->get('/portal/help', function () {
    Auth::requireAuth();
    render('portal/docs/index', [
        'sidebarItems' => portalSidebar('help'),
        'layout'       => 'app',
        'pageTitle'    => 'Help',
        'breadcrumbs'  => [['label' => 'Help']],
    ]);
});

$router->get('/portal/help/{page}', function (array $p) use ($validPortalHelpPages) {
    Auth::requireAuth();
    $page = $p['page'] ?? '';
    if (!in_array($page, $validPortalHelpPages, true)) {
        redirect('/portal/help');
    }
    $titles = ['floor' => 'Floor Mode'];
    render('portal/docs/' . $page, [
        'sidebarItems' => portalSidebar('help'),
        'layout'       => 'app',
        'pageTitle'    => 'Help: ' . ($titles[$page] ?? $page),
        'breadcrumbs'  => [
            ['label' => 'Help', 'url' => '/portal/help'],
            ['label' => $titles[$page] ?? $page],
        ],
    ]);
});

/* ==================================================================
 * PORTAL – Knowledge Base
 * ================================================================== */

$router->get('/portal/kb', function () {
    Auth::requireAuth();
    $categories = Database::connect()->query(
        'SELECT c.*, COUNT(DISTINCT f.id) AS folder_count
         FROM kb_categories c
         LEFT JOIN kb_folders f ON f.category_id = c.id
         GROUP BY c.id
         ORDER BY c.sort_order, c.name'
    )->fetchAll();
    render('portal/kb/index', ['categories' => $categories]);
});

$router->get('/portal/tickets/dup-preview', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');
    $tid = (int) ($_GET['id'] ?? 0);
    $row = getDupPreviewTicket((int) Auth::id(), $tid, false);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false]);
        exit;
    }
    echo json_encode(['ok' => true, 'ticket' => $row]);
    exit;
});

$router->post('/portal/tickets/check-duplicates', function () {
    Auth::requireAuth();
    header('Content-Type: application/json');

    if (!verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'matches' => []]);
        exit;
    }

    $subject    = (string) ($_POST['subject']     ?? '');
    $body       = (string) ($_POST['description'] ?? '');
    $typeId     = (int)    ($_POST['type_id']     ?? 0);
    $locationId = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;

    if ($locationId === null) {
        $db   = Database::connect();
        $stmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
        $stmt->execute([Auth::id()]);
        $locationId = $stmt->fetchColumn() ?: null;
        $locationId = $locationId ? (int) $locationId : null;
    }

    $result = checkTicketDuplicates(Auth::id(), $typeId, $locationId, $subject, $body);
    echo json_encode([
        'ok'        => true,
        'matches'   => $result['matches'],
        'threshold' => $result['threshold'],
    ]);
    exit;
});

$router->get('/portal/kb/search', function () {
    Auth::requireAuth();
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    $db   = Database::connect();
    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        "SELECT a.title, a.slug, f.name AS folder_name, c.name AS category_name
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         WHERE a.status = 'published' AND (a.title LIKE ? OR a.body_markdown LIKE ?)
         ORDER BY a.updated_at DESC
         LIMIT 10"
    );
    $stmt->execute([$like, $like]);
    $results = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
});

$router->get('/portal/kb/articles/{slug}', function (array $p) {
    Auth::requireAuth();
    $db   = Database::connect();
    $stmt = $db->prepare(
        "SELECT a.*, f.name AS folder_name, f.slug AS folder_slug,
                c.name AS category_name, c.slug AS category_slug
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         WHERE a.slug = ? AND a.status = 'published'"
    );
    $stmt->execute([$p['slug']]);
    $article = $stmt->fetch();
    if (!$article) {
        flash('error', 'Article not found.');
        redirect('/portal/kb');
    }
    $article['body_html'] = renderMarkdown($article['body_markdown']);

    // Feedback counts
    $fc = $db->prepare(
        "SELECT
            SUM(CASE WHEN rating =  1 THEN 1 ELSE 0 END) AS helpful,
            SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) AS not_helpful
         FROM kb_article_ratings WHERE article_id = ?"
    );
    $fc->execute([$article['id']]);
    $counts = $fc->fetch();

    // Current user's vote
    $vq = $db->prepare(
        'SELECT rating FROM kb_article_ratings WHERE article_id = ? AND user_id = ?'
    );
    $vq->execute([$article['id'], Auth::id()]);
    $myVote = $vq->fetchColumn() ?: null;

    $feedback = [
        'helpful'     => (int)($counts['helpful']     ?? 0),
        'not_helpful' => (int)($counts['not_helpful'] ?? 0),
        'my_vote'     => $myVote !== null ? (int)$myVote : null,
    ];

    render('portal/kb/article', ['article' => $article, 'feedback' => $feedback]);
});

$router->post('/portal/kb/articles/{slug}/feedback', function (array $p) {
    Auth::requireAuth();
    header('Content-Type: application/json');

    $rating = (int)($_POST['rating'] ?? 0);
    if (!in_array($rating, [1, -1], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid rating.']);
        exit;
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT id FROM kb_articles WHERE slug = ? AND status = ?');
    $stmt->execute([$p['slug'], 'published']);
    $article = $stmt->fetch();
    if (!$article) {
        echo json_encode(['status' => 'error', 'message' => 'Article not found.']);
        exit;
    }
    $articleId = (int)$article['id'];
    $userId    = Auth::id();

    // Upsert — UNIQUE(article_id, user_id) prevents double-votes
    try {
        $db->prepare(
            'INSERT INTO kb_article_ratings (article_id, user_id, rating)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating)'
        )->execute([$articleId, $userId, $rating]);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Could not save vote.']);
        exit;
    }

    // Return updated counts
    $fc = $db->prepare(
        "SELECT
            SUM(CASE WHEN rating =  1 THEN 1 ELSE 0 END) AS helpful,
            SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) AS not_helpful
         FROM kb_article_ratings WHERE article_id = ?"
    );
    $fc->execute([$articleId]);
    $counts = $fc->fetch();

    echo json_encode([
        'status'      => 'ok',
        'helpful'     => (int)($counts['helpful']     ?? 0),
        'not_helpful' => (int)($counts['not_helpful'] ?? 0),
    ]);
    exit;
});

$router->get('/portal/kb/{slug}/{folder_slug}', function (array $p) {
    Auth::requireAuth();
    $db = Database::connect();

    // Get category
    $catStmt = $db->prepare('SELECT * FROM kb_categories WHERE slug = ?');
    $catStmt->execute([$p['slug']]);
    $category = $catStmt->fetch();
    if (!$category) {
        flash('error', 'Category not found.');
        redirect('/portal/kb');
    }

    // Get folder
    $folderStmt = $db->prepare('SELECT * FROM kb_folders WHERE slug = ? AND category_id = ?');
    $folderStmt->execute([$p['folder_slug'], $category['id']]);
    $folder = $folderStmt->fetch();
    if (!$folder) {
        flash('error', 'Folder not found.');
        redirect('/portal/kb/' . $p['slug']);
    }

    // Get published articles in this folder
    $artStmt = $db->prepare(
        "SELECT id, title, slug, published_at, sort_order
         FROM kb_articles
         WHERE folder_id = ? AND status = 'published'
         ORDER BY sort_order, title"
    );
    $artStmt->execute([$folder['id']]);
    $articles = $artStmt->fetchAll();

    render('portal/kb/folder', ['category' => $category, 'folder' => $folder, 'articles' => $articles]);
});

$router->get('/portal/kb/{slug}', function (array $p) {
    Auth::requireAuth();
    $db = Database::connect();

    $catStmt = $db->prepare('SELECT * FROM kb_categories WHERE slug = ?');
    $catStmt->execute([$p['slug']]);
    $category = $catStmt->fetch();
    if (!$category) {
        flash('error', 'Category not found.');
        redirect('/portal/kb');
    }

    $folders = $db->prepare(
        'SELECT f.*, COUNT(a.id) AS article_count
         FROM kb_folders f
         LEFT JOIN kb_articles a ON a.folder_id = f.id AND a.status = \'published\'
         WHERE f.category_id = ?
         GROUP BY f.id
         ORDER BY f.sort_order, f.name'
    );
    $folders->execute([$category['id']]);
    $folders = $folders->fetchAll();

    render('portal/kb/category', ['category' => $category, 'folders' => $folders]);
});

/* ==================================================================
 * PORTAL – Download Attachment (must be own ticket)
 * ================================================================== */

$router->get('/portal/attachments/{id}/download', function (array $p) {
    Auth::requireAuth();
    $db  = Database::connect();
    $uid = Auth::id();

    // Match the ticket-view access rule: own ticket, or a non-confidential ticket
    // at the user's location if they have can_view_location_tickets.
    $permStmt = $db->prepare('SELECT can_view_location_tickets, location_id FROM users WHERE id = ?');
    $permStmt->execute([$uid]);
    $userPerms = $permStmt->fetch();

    $accessCond   = 't.created_by = ?';
    $accessParams = [(int) $p['id'], $uid];
    if ($userPerms['can_view_location_tickets'] && $userPerms['location_id']) {
        $accessCond   = '(' . $accessCond . ' OR (t.location_id = ? AND NOT EXISTS (
                            SELECT 1 FROM ticket_types ct
                            WHERE ct.id = t.type_id
                              AND (ct.is_confidential = 1 OR ct.show_to_location_visibility = 0)
                        )))';
        $accessParams[] = (int) $userPerms['location_id'];
    }

    $stmt = $db->prepare(
        "SELECT ta.*, t.created_by
         FROM ticket_attachments ta
         INNER JOIN tickets t ON ta.ticket_id = t.id
         LEFT JOIN ticket_timeline tl ON ta.timeline_id = tl.id
         WHERE ta.id = ? AND {$accessCond} AND (tl.is_internal IS NULL OR tl.is_internal = 0)"
    );
    $stmt->execute($accessParams);
    $att = $stmt->fetch();

    if (!$att) {
        flash('error', 'Attachment not found.');
        redirect('/portal/tickets');
    }

    $filePath = ATTACHMENT_STORAGE_PATH . $att['stored_name'];
    if (!file_exists($filePath)) {
        flash('error', 'File not found on server.');
        redirect('/portal/tickets/' . $att['ticket_id']);
    }

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $att['original_name']) . '"');
    header('Content-Length: ' . $att['file_size']);
    readfile($filePath);
    exit;
});
