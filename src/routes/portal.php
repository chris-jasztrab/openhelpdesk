<?php

declare(strict_types=1);

/* ==================================================================
 * PORTAL – Ticket List (user's own tickets)
 * ================================================================== */

$router->get('/portal/tickets', function () {
    Auth::requireAuth();
    $db = Database::connect();

    // Read filter params
    $fStatus   = trim($_GET['status'] ?? '');
    $fPriority = trim($_GET['priority'] ?? '');
    $fSearch   = trim($_GET['q'] ?? '');

    // Show tickets the user created (not merged away) plus any master tickets their merged tickets point to
    $where  = ['(t.created_by = ? AND t.merged_into_ticket_id IS NULL) OR t.id IN (SELECT DISTINCT merged_into_ticket_id FROM tickets WHERE created_by = ? AND merged_into_ticket_id IS NOT NULL)'];
    $params = [Auth::id(), Auth::id()];

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
                tt.name AS type_name,
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
    ];

    render('portal/tickets/index', [
        'tickets'      => $tickets,
        'priorities'   => $priorities,
        'filters'      => $filters,
        'page'         => $page,
        'totalPages'   => $totalPages,
        'totalTickets' => $totalTickets,
        'sort'         => $sort,
        'dir'          => strtolower($dir),
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

    // Determine the user's location (from profile, or first location as fallback)
    $userStmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
    $userStmt->execute([Auth::id()]);
    $userLocationId = $userStmt->fetchColumn() ?: null;
    if (!$userLocationId && !empty($locations)) {
        $userLocationId = $locations[0]['id'];
    }
    $userLocationName = '';
    foreach ($locations as $loc) {
        if ((int) $loc['id'] === (int) $userLocationId) {
            $userLocationName = $loc['name'];
            break;
        }
    }

    // Load visible custom form fields
    $customFields = $db->query(
        'SELECT * FROM ticket_form_fields WHERE is_visible = 1 AND deleted_at IS NULL ORDER BY sort_order'
    )->fetchAll();
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

    render('portal/tickets/create', [
        'types'            => $types,
        'locations'        => $locations,
        'priorities'       => $priorities,
        'tags'             => $tags,
        'userLocationId'   => $userLocationId,
        'userLocationName' => $userLocationName,
        'customFields'     => $customFields,
        'fieldOptions'     => $fieldOptions,
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

    // Auto-assign location from user profile (fallback to first location)
    $db = Database::connect();
    $userLocStmt = $db->prepare('SELECT location_id FROM users WHERE id = ?');
    $userLocStmt->execute([Auth::id()]);
    $locationId = $userLocStmt->fetchColumn() ?: null;
    if (!$locationId) {
        $firstLoc = $db->query('SELECT id FROM locations ORDER BY name LIMIT 1')->fetchColumn();
        $locationId = $firstLoc ?: null;
    }

    // Assignment is handled by agents/admins, not portal users
    $assignedTo = null;

    if ($subject === '' || $description === '' || $typeId === null) {
        flashInput($_POST);
        flash('error', 'Subject, description, and ticket type are required.');
        redirect('/portal/tickets/create');
    }

    // Validate required custom fields
    $visibleCustomFields = $db->query(
        'SELECT * FROM ticket_form_fields WHERE is_visible = 1 AND deleted_at IS NULL ORDER BY sort_order'
    )->fetchAll();
    foreach ($visibleCustomFields as $cf) {
        if (!$cf['is_required']) continue;
        $key = 'field_' . $cf['id'];
        if ($cf['field_type'] === 'dependent') {
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
        'INSERT INTO tickets (subject, description, browser_info, os_info, created_by, type_id, location_id, status, priority_id, assigned_to)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $subject, $description,
        $browserInfo ?: null, $osInfo ?: null,
        Auth::id(), $typeId, $locationId, 'open', $priorityId, $assignedTo,
    ]);
    $ticketId = (int) $db->lastInsertId();

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

    // Save custom field values
    if (!empty($visibleCustomFields)) {
        $cfSaveStmt = $db->prepare(
            'INSERT INTO ticket_field_values (ticket_id, field_id, value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        foreach ($visibleCustomFields as $cf) {
            $key = 'field_' . $cf['id'];
            if ($cf['field_type'] === 'dependent') {
                $val = json_encode([
                    'l1' => $_POST[$key . '_l1'] ?? null,
                    'l2' => $_POST[$key . '_l2'] ?? null,
                    'l3' => $_POST[$key . '_l3'] ?? null,
                ]);
            } elseif ($cf['field_type'] === 'checkbox') {
                $val = isset($_POST[$key]) ? '1' : '0';
            } else {
                $val = $_POST[$key] ?? null;
                if ($val === null || trim($val) === '') continue;
            }
            $cfSaveStmt->execute([$ticketId, $cf['id'], $val]);
        }
    }

    // Run automations for new ticket
    runAutomations($db, $ticketId, 'ticket_created');

    // Handle file attachments
    $attachments = handleAttachmentUploads('attachments');
    saveAttachments($db, $ticketId, null, Auth::id(), $attachments);

    // Send confirmation email to ticket creator
    $creator   = Auth::user();
    $appUrl    = env('APP_URL', 'http://localhost:8000');
    $ticketUrl = $appUrl . '/portal/tickets/' . $ticketId;

    // Look up names for type, location, priority
    $typeName     = '';
    $locationName = '';
    $priorityName = '';
    if ($typeId) {
        $tStmt = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
        $tStmt->execute([$typeId]);
        $typeName = $tStmt->fetchColumn() ?: '';
    }
    if ($locationId) {
        $lStmt = $db->prepare('SELECT name FROM locations WHERE id = ?');
        $lStmt->execute([$locationId]);
        $locationName = $lStmt->fetchColumn() ?: '';
    }
    if ($priorityId) {
        $pStmt = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
        $pStmt->execute([$priorityId]);
        $priorityName = $pStmt->fetchColumn() ?: '';
    }

    $tpl = getEmailTpl('ticket-created', [
        'ticket_id'  => $ticketId,
        'subject'    => $subject,
        'type'       => $typeName,
        'location'   => $locationName,
        'priority'   => $priorityName,
        'user_name'  => $creator['first_name'] . ' ' . $creator['last_name'],
        'first_name' => $creator['first_name'],
        'last_name'  => $creator['last_name'],
    ]);

    $emailHtml = renderEmail('ticket-created', [
        'ticketId'     => $ticketId,
        'subject'      => $subject,
        'description'  => $description,
        'typeName'     => $typeName,
        'locationName' => $locationName,
        'priorityName' => $priorityName,
        'ticketUrl'    => $ticketUrl,
        'introText'    => $tpl['intro'],
        'buttonLabel'  => $tpl['button'],
        'footerText'   => $tpl['footer'],
    ]);

    // Only send if user hasn't opted out of ticket-created emails
    $notifyPref = $db->prepare('SELECT notify_ticket_created FROM users WHERE id = ?');
    $notifyPref->execute([Auth::id()]);
    if ((bool)($notifyPref->fetchColumn() ?? 1)) {
        sendMail(
            $creator['email'],
            $creator['first_name'] . ' ' . $creator['last_name'],
            $tpl['subject'],
            $emailHtml,
            '',
            $ticketId
        );
    }

    // Initialize SLA timers if priority is set and SLA is configured
    if ($priorityId) {
        Sla::initializeForTicket($db, $ticketId, $priorityId);
    }

    $msg = 'Ticket #' . $ticketId . ' created successfully.';
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

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         WHERE t.id = ?
           AND (t.created_by = ? OR t.id IN (SELECT DISTINCT merged_into_ticket_id FROM tickets WHERE created_by = ? AND merged_into_ticket_id IS NOT NULL))"
    );
    $stmt->execute([$tid, $uid, $uid]);
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
         ORDER BY tl.created_at ASC"
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

    // Custom field values (visible fields only)
    $customFields = $db->query(
        'SELECT * FROM ticket_form_fields WHERE is_visible = 1 AND deleted_at IS NULL ORDER BY sort_order'
    )->fetchAll();
    $fieldValues  = [];
    $fieldOptions = [];
    if ($customFields) {
        $fvStmt = $db->prepare('SELECT field_id, value FROM ticket_field_values WHERE ticket_id = ?');
        $fvStmt->execute([$ticket['id']]);
        foreach ($fvStmt->fetchAll() as $fv) {
            $fieldValues[$fv['field_id']] = $fv['value'];
        }
        foreach ($customFields as $f) {
            if (in_array($f['field_type'], ['dropdown', 'dependent'], true)) {
                $s = $db->prepare(
                    'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
                );
                $s->execute([$f['id']]);
                $fieldOptions[$f['id']] = $s->fetchAll();
            }
        }
    }

    render('portal/tickets/view', [
        'ticket'       => $ticket,
        'timeline'     => $timeline,
        'attachments'  => $attachments,
        'ccUsers'      => $ccUsers,
        'customFields' => $customFields,
        'fieldValues'  => $fieldValues,
        'fieldOptions' => $fieldOptions,
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

    // Verify ownership (own ticket or a master ticket one of their tickets was merged into)
    $uid = Auth::id();
    $stmt = $db->prepare(
        'SELECT id FROM tickets WHERE id = ?
         AND (created_by = ? OR id IN (SELECT DISTINCT merged_into_ticket_id FROM tickets WHERE created_by = ? AND merged_into_ticket_id IS NOT NULL))'
    );
    $stmt->execute([$id, $uid, $uid]);
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

    // Notify CC'd users when portal user replies
    notifyCcUsers($db, $id, $message, Auth::fullName());

    $msg = 'Comment added.';
    if (!empty($attachments)) {
        $msg = 'Comment added with ' . count($attachments) . ' file(s).';
    }
    flash('success', $msg);
    redirect("/portal/tickets/{$id}");
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
    $db = Database::connect();

    $stmt = $db->prepare(
        'SELECT ta.*, t.created_by
         FROM ticket_attachments ta
         INNER JOIN tickets t ON ta.ticket_id = t.id
         LEFT JOIN ticket_timeline tl ON ta.timeline_id = tl.id
         WHERE ta.id = ? AND t.created_by = ? AND (tl.is_internal IS NULL OR tl.is_internal = 0)'
    );
    $stmt->execute([(int) $p['id'], Auth::id()]);
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
