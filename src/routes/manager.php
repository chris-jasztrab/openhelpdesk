<?php

declare(strict_types=1);

/* ==================================================================
 * MANAGER – Delegated skill management for group managers
 *
 * Group members flagged is_manager = 1 in group_user_map can edit
 * agent skills owned by their group (agent_skills.group_id = ?) and
 * assign those skills (plus global skills) to other members of the
 * same group, all without admin access.
 *
 * Admins automatically pass canManageGroupSkills() so they can use
 * the same UI to spot-check or fill in for a manager.
 * ================================================================== */

/**
 * Internal helper: load the groups the current user manages, or 403
 * with a friendly message when they manage none. Used as the gate at
 * the top of every manager route.
 */
function _managerGate(): array
{
    Auth::requireAuth();
    $userId = (int) Auth::id();
    $managed = userManagedGroupIds($userId);
    if (Auth::role() !== 'admin' && empty($managed)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        echo '<title>403 Forbidden</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '</head><body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f1f5f9">';
        echo '<div class="text-center" style="max-width:480px;"><h1 class="display-1 fw-bold text-danger">403</h1>';
        echo '<p class="lead text-muted">You aren\'t flagged as a manager of any group, so the Manage My Team area isn\'t available to you. Ask an admin to delegate group manager rights from <strong>Admin → Settings → Groups</strong>.</p>';
        echo '<a href="/" class="btn btn-primary">Go Home</a></div></body></html>';
        exit;
    }
    return $managed;
}

/**
 * Fetch the groups visible on the manager landing page. Admins see
 * all groups; normal managers see only the groups they manage.
 */
function _managerVisibleGroups(): array
{
    $db = Database::connect();
    if (Auth::role() === 'admin') {
        return $db->query(
            "SELECT g.id, g.name, g.description,
                    (SELECT COUNT(*) FROM group_user_map gum WHERE gum.group_id = g.id) AS member_count,
                    (SELECT COUNT(*) FROM agent_skills s WHERE s.group_id = g.id)        AS skill_count
             FROM `groups` g
             ORDER BY g.sort_order, g.name"
        )->fetchAll();
    }
    $managed = userManagedGroupIds((int) Auth::id());
    if (empty($managed)) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($managed), '?'));
    $stmt = $db->prepare(
        "SELECT g.id, g.name, g.description,
                (SELECT COUNT(*) FROM group_user_map gum WHERE gum.group_id = g.id) AS member_count,
                (SELECT COUNT(*) FROM agent_skills s WHERE s.group_id = g.id)        AS skill_count
         FROM `groups` g
         WHERE g.id IN ($ph)
         ORDER BY g.sort_order, g.name"
    );
    $stmt->execute($managed);
    return $stmt->fetchAll();
}

/* ── Landing page ────────────────────────────────────────────────── */

$router->get('/manager', function () {
    _managerGate();
    $groups = _managerVisibleGroups();
    render('manager/index', ['groups' => $groups]);
});

/* ── Team roster: assign skills to members ──────────────────────── */

$router->get('/manager/groups/{id}/team', function (array $p) {
    _managerGate();
    $groupId = (int) $p['id'];
    if (!canManageGroupSkills((int) Auth::id(), $groupId)) {
        flash('error', 'You do not manage that group.');
        redirect('/manager');
    }
    $db = Database::connect();

    $gStmt = $db->prepare('SELECT id, name, description FROM `groups` WHERE id = ?');
    $gStmt->execute([$groupId]);
    $group = $gStmt->fetch();
    if (!$group) {
        flash('error', 'Group not found.');
        redirect('/manager');
    }

    // Members of this group (eligible-roles only, mirrors auto-assign filter)
    $mStmt = $db->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.role
         FROM group_user_map gum
         JOIN users u ON gum.user_id = u.id
         WHERE gum.group_id = ? AND u.role IN ('agent','admin','power_user')
         ORDER BY u.first_name, u.last_name"
    );
    $mStmt->execute([$groupId]);
    $members = $mStmt->fetchAll();

    // Skills the manager is allowed to assign:
    //   - global skills (group_id IS NULL) — visible everywhere
    //   - skills owned by THIS group
    // Skills owned by OTHER groups are intentionally hidden — managers
    // shouldn't see another team's vocabulary.
    $sStmt = $db->prepare(
        'SELECT id, name, description, group_id FROM agent_skills
         WHERE group_id IS NULL OR group_id = ?
         ORDER BY (group_id IS NULL) DESC, sort_order, name'
    );
    $sStmt->execute([$groupId]);
    $skills = $sStmt->fetchAll();

    // Skill-holdings for every member at once
    $holdings = [];
    if (!empty($members) && !empty($skills)) {
        $memberIds = array_map(static fn($m) => (int) $m['id'], $members);
        $skillIds  = array_map(static fn($s) => (int) $s['id'], $skills);
        $mPh = implode(',', array_fill(0, count($memberIds), '?'));
        $sPh = implode(',', array_fill(0, count($skillIds), '?'));
        $hStmt = $db->prepare(
            "SELECT user_id, skill_id FROM user_skill_map
             WHERE user_id IN ($mPh) AND skill_id IN ($sPh)"
        );
        $hStmt->execute(array_merge($memberIds, $skillIds));
        foreach ($hStmt->fetchAll() as $row) {
            $holdings[(int) $row['user_id']][(int) $row['skill_id']] = true;
        }
    }

    render('manager/team', [
        'group'    => $group,
        'members'  => $members,
        'skills'   => $skills,
        'holdings' => $holdings,
    ]);
});

$router->post('/manager/groups/{id}/team', function (array $p) {
    _managerGate();
    $groupId = (int) $p['id'];
    if (!canManageGroupSkills((int) Auth::id(), $groupId)) {
        flash('error', 'You do not manage that group.');
        redirect('/manager');
    }
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/manager/groups/{$groupId}/team");
    }

    $db = Database::connect();

    // Re-derive the assignable skill set (must match GET) so a manager
    // can't sneak in skill IDs they don't own. The manager-allowed set
    // is: global skills + skills owned by this group.
    $sStmt = $db->prepare(
        'SELECT id FROM agent_skills WHERE group_id IS NULL OR group_id = ?'
    );
    $sStmt->execute([$groupId]);
    $allowedSkillIds = array_map('intval', $sStmt->fetchAll(PDO::FETCH_COLUMN));
    $allowedSkillSet = array_flip($allowedSkillIds);

    // Re-derive allowed members (must be in this group and an agent-tier role)
    $mStmt = $db->prepare(
        "SELECT u.id FROM group_user_map gum JOIN users u ON gum.user_id = u.id
         WHERE gum.group_id = ? AND u.role IN ('agent','admin','power_user')"
    );
    $mStmt->execute([$groupId]);
    $allowedMemberIds = array_map('intval', $mStmt->fetchAll(PDO::FETCH_COLUMN));
    $allowedMemberSet = array_flip($allowedMemberIds);

    // POST shape: skills[user_id][] = skill_id, skill_id, ...
    $posted = $_POST['skills'] ?? [];
    if (!is_array($posted)) { $posted = []; }

    $changeCount = 0;
    foreach ($allowedMemberIds as $uid) {
        $newSkills = isset($posted[$uid]) && is_array($posted[$uid])
            ? array_values(array_unique(array_filter(array_map('intval', $posted[$uid]))))
            : [];
        // Reject skill IDs the manager isn't allowed to touch
        $newSkills = array_values(array_filter($newSkills, static fn($sid) => isset($allowedSkillSet[$sid])));

        // Snapshot prior skills the manager is allowed to see (others are out of scope)
        $priorStmt = $db->prepare(
            'SELECT skill_id FROM user_skill_map WHERE user_id = ?'
        );
        $priorStmt->execute([$uid]);
        $priorAll = array_map('intval', $priorStmt->fetchAll(PDO::FETCH_COLUMN));
        $priorVisible = array_values(array_filter($priorAll, static fn($sid) => isset($allowedSkillSet[$sid])));

        $toAdd    = array_values(array_diff($newSkills,    $priorVisible));
        $toRemove = array_values(array_diff($priorVisible, $newSkills));

        if (!empty($toAdd)) {
            $insStmt = $db->prepare('INSERT IGNORE INTO user_skill_map (user_id, skill_id) VALUES (?, ?)');
            foreach ($toAdd as $sid) { $insStmt->execute([$uid, $sid]); }
        }
        if (!empty($toRemove)) {
            $rmPh = implode(',', array_fill(0, count($toRemove), '?'));
            $delStmt = $db->prepare(
                "DELETE FROM user_skill_map WHERE user_id = ? AND skill_id IN ($rmPh)"
            );
            $delStmt->execute(array_merge([$uid], $toRemove));
        }

        if (!empty($toAdd) || !empty($toRemove)) {
            $changeCount++;
            $detail = "Group #{$groupId}, user #{$uid}.";
            if (!empty($toAdd))    { $detail .= ' Added: '   . implode(',', $toAdd)    . '.'; }
            if (!empty($toRemove)) { $detail .= ' Removed: ' . implode(',', $toRemove) . '.'; }
            logAudit('manager.skill_assignments_changed', $uid, 'user', $detail);
        }

        // Suppress "unused" linter warning for the var still in scope
        unset($allowedMemberSet);
    }

    if ($changeCount === 0) {
        flash('info', 'No changes to save.');
    } else {
        flash('success', 'Skill assignments updated for ' . $changeCount . ' member' . ($changeCount === 1 ? '' : 's') . '.');
    }
    redirect("/manager/groups/{$groupId}/team");
});

/* ── Group-scoped skill catalogue ────────────────────────────────── */

$router->get('/manager/groups/{id}/skills', function (array $p) {
    _managerGate();
    $groupId = (int) $p['id'];
    if (!canManageGroupSkills((int) Auth::id(), $groupId)) {
        flash('error', 'You do not manage that group.');
        redirect('/manager');
    }
    $db = Database::connect();

    $gStmt = $db->prepare('SELECT id, name FROM `groups` WHERE id = ?');
    $gStmt->execute([$groupId]);
    $group = $gStmt->fetch();
    if (!$group) {
        flash('error', 'Group not found.');
        redirect('/manager');
    }

    // Skills the manager is allowed to see in this view:
    //   - skills they own (group_id = $groupId)  — editable
    //   - global skills                         — read-only reference
    $skills = $db->prepare(
        "SELECT s.*,
                COALESCE(uc.cnt, 0) AS agent_count,
                COALESCE(tc.cnt, 0) AS type_count
         FROM agent_skills s
         LEFT JOIN (SELECT skill_id, COUNT(*) AS cnt FROM user_skill_map         GROUP BY skill_id) uc ON uc.skill_id = s.id
         LEFT JOIN (SELECT skill_id, COUNT(*) AS cnt FROM ticket_type_skill_map GROUP BY skill_id) tc ON tc.skill_id = s.id
         WHERE s.group_id IS NULL OR s.group_id = ?
         ORDER BY (s.group_id IS NULL) ASC, s.sort_order, s.name"
    );
    $skills->execute([$groupId]);
    $skills = $skills->fetchAll();

    render('manager/skills/index', ['group' => $group, 'skills' => $skills]);
});

$router->get('/manager/groups/{id}/skills/create', function (array $p) {
    _managerGate();
    $groupId = (int) $p['id'];
    if (!canManageGroupSkills((int) Auth::id(), $groupId)) {
        flash('error', 'You do not manage that group.');
        redirect('/manager');
    }
    $db = Database::connect();
    $gStmt = $db->prepare('SELECT id, name FROM `groups` WHERE id = ?');
    $gStmt->execute([$groupId]);
    $group = $gStmt->fetch();
    if (!$group) { flash('error', 'Group not found.'); redirect('/manager'); }

    render('manager/skills/form', ['group' => $group, 'editing' => null]);
});

$router->post('/manager/groups/{id}/skills/create', function (array $p) {
    _managerGate();
    $groupId = (int) $p['id'];
    if (!canManageGroupSkills((int) Auth::id(), $groupId)) {
        flash('error', 'You do not manage that group.');
        redirect('/manager');
    }
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/manager/groups/{$groupId}/skills/create");
    }
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Skill name is required.');
        redirect("/manager/groups/{$groupId}/skills/create");
    }
    $db = Database::connect();
    try {
        $db->prepare('INSERT INTO agent_skills (name, description, sort_order, group_id) VALUES (?, ?, ?, ?)')
           ->execute([$name, $desc !== '' ? $desc : null, $order, $groupId]);
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate entry')
            ? 'A skill with that name already exists.'
            : 'Database error.');
        flashInput($_POST);
        redirect("/manager/groups/{$groupId}/skills/create");
    }
    $skillId = (int) $db->lastInsertId();
    logAudit('manager.skill_created', $skillId, 'agent_skill', "Group #{$groupId} created skill \"{$name}\"");
    flash('success', 'Skill created.');
    redirect("/manager/groups/{$groupId}/skills");
});

$router->get('/manager/groups/{id}/skills/{skillId}/edit', function (array $p) {
    _managerGate();
    $groupId = (int) $p['id'];
    $skillId = (int) $p['skillId'];
    if (!canManageGroupSkills((int) Auth::id(), $groupId)) {
        flash('error', 'You do not manage that group.');
        redirect('/manager');
    }
    if (!canEditSkill((int) Auth::id(), $skillId)) {
        flash('error', 'You can only edit skills owned by your group.');
        redirect("/manager/groups/{$groupId}/skills");
    }
    $db = Database::connect();
    $gStmt = $db->prepare('SELECT id, name FROM `groups` WHERE id = ?');
    $gStmt->execute([$groupId]);
    $group = $gStmt->fetch();
    $sStmt = $db->prepare('SELECT * FROM agent_skills WHERE id = ?');
    $sStmt->execute([$skillId]);
    $editing = $sStmt->fetch();
    if (!$group || !$editing || (int) $editing['group_id'] !== $groupId) {
        flash('error', 'Skill not found in this group.');
        redirect("/manager/groups/{$groupId}/skills");
    }
    render('manager/skills/form', ['group' => $group, 'editing' => $editing]);
});

$router->post('/manager/groups/{id}/skills/{skillId}/edit', function (array $p) {
    _managerGate();
    $groupId = (int) $p['id'];
    $skillId = (int) $p['skillId'];
    if (!canManageGroupSkills((int) Auth::id(), $groupId)) {
        flash('error', 'You do not manage that group.');
        redirect('/manager');
    }
    if (!canEditSkill((int) Auth::id(), $skillId)) {
        flash('error', 'You can only edit skills owned by your group.');
        redirect("/manager/groups/{$groupId}/skills");
    }
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/manager/groups/{$groupId}/skills/{$skillId}/edit");
    }
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Skill name is required.');
        redirect("/manager/groups/{$groupId}/skills/{$skillId}/edit");
    }
    $db = Database::connect();
    try {
        // group_id is NOT updated here — managers can't move ownership
        $db->prepare('UPDATE agent_skills SET name = ?, description = ?, sort_order = ? WHERE id = ?')
           ->execute([$name, $desc !== '' ? $desc : null, $order, $skillId]);
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate entry')
            ? 'A skill with that name already exists.'
            : 'Database error.');
        flashInput($_POST);
        redirect("/manager/groups/{$groupId}/skills/{$skillId}/edit");
    }
    logAudit('manager.skill_updated', $skillId, 'agent_skill', "Group #{$groupId} updated skill \"{$name}\"");
    flash('success', 'Skill updated.');
    redirect("/manager/groups/{$groupId}/skills");
});

$router->post('/manager/groups/{id}/skills/{skillId}/delete', function (array $p) {
    _managerGate();
    $groupId = (int) $p['id'];
    $skillId = (int) $p['skillId'];
    if (!canManageGroupSkills((int) Auth::id(), $groupId)) {
        flash('error', 'You do not manage that group.');
        redirect('/manager');
    }
    if (!canEditSkill((int) Auth::id(), $skillId)) {
        flash('error', 'You can only delete skills owned by your group.');
        redirect("/manager/groups/{$groupId}/skills");
    }
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/manager/groups/{$groupId}/skills");
    }
    Database::connect()->prepare('DELETE FROM agent_skills WHERE id = ? AND group_id = ?')->execute([$skillId, $groupId]);
    logAudit('manager.skill_deleted', $skillId, 'agent_skill', "Group #{$groupId} deleted skill #{$skillId}");
    flash('success', 'Skill deleted.');
    redirect("/manager/groups/{$groupId}/skills");
});
