<?php
$layout       = 'app';
$pageTitle    = 'Agent Skills – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Agent Skills'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Agent Skills</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary"
                data-bs-toggle="modal" data-bs-target="#suggestSkillsModal"
                title="Use AI to suggest skills based on your setup">
            <i class="bi bi-magic me-1"></i>Suggest with AI
        </button>
        <a href="/admin/skills/create" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-mortarboard me-1"></i>Add Skill
        </a>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Skills back the <strong>Skill-Based</strong> auto-assign strategy. Tag agents with the skills they hold, then mark which skills each Ticket Type requires. New tickets routed to a Skill-Based group will only be auto-assigned to members whose skill set covers every required skill.
    <br><br>
    <strong>Scope</strong> — leave a skill <em>Global</em> to keep it admin-only and visible everywhere. Pick a <em>group</em> to delegate ownership: managers of that group can edit the skill and assign it to their team without admin involvement. See <a href="/admin/docs/users#group-managers" class="alert-link">Group Managers</a>.
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Scope</th>
                    <th>Description</th>
                    <th>Agents</th>
                    <th>Required by Types</th>
                    <th>Sort</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($skills)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No skills defined yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($skills as $s): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-mortarboard text-muted me-1"></i><?= e($s['name']) ?>
                        </td>
                        <td>
                            <?php if (empty($s['group_id'])): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-globe me-1"></i>Global</span>
                            <?php else: ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-people me-1"></i><?= e($s['group_name'] ?? 'Group') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:300px;"><?= e($s['description'] ? mb_strimwidth($s['description'], 0, 80, '...') : '—') ?></td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary"><?= (int) $s['agent_count'] ?> agent<?= (int) $s['agent_count'] !== 1 ? 's' : '' ?></span>
                        </td>
                        <td>
                            <span class="badge bg-info bg-opacity-10 text-info"><?= (int) $s['type_count'] ?> type<?= (int) $s['type_count'] !== 1 ? 's' : '' ?></span>
                        </td>
                        <td class="text-muted"><?= (int) $s['sort_order'] ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/skills/<?= $s['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteSkillModal"
                                        data-id="<?= $s['id'] ?>"
                                        data-name="<?= e($s['name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="deleteSkillModal" tabindex="-1" aria-labelledby="deleteSkillModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteSkillModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Skill
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete skill <strong id="deleteSkillName"></strong>? Agents will lose this skill and ticket types will lose this requirement.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteSkillForm" action="">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('deleteSkillModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteSkillName').textContent = btn.dataset.name;
    document.getElementById('deleteSkillForm').action = '/admin/skills/' + btn.dataset.id + '/delete';
});
</script>

<div class="modal fade" id="suggestSkillsModal" tabindex="-1" aria-labelledby="suggestSkillsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="GET" action="/admin/skills/suggest" id="suggestSkillsForm">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="suggestSkillsModalLabel">
                        <i class="bi bi-magic me-2 text-primary"></i>Suggest Skills with AI
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Pick how the AI should generate suggestions. Either way, your
                        <strong>organization type</strong>, <strong>ticket types</strong>, <strong>groups</strong>, and
                        <strong>existing skills</strong> are always included so duplicates are avoided.
                    </p>

                    <div class="form-check border rounded p-3 mb-2">
                        <input class="form-check-input" type="radio" name="mode" id="modeBasic" value="basic" checked>
                        <label class="form-check-label w-100" for="modeBasic">
                            <strong>Basic</strong> — use only my organization profile
                            <div class="text-muted small mt-1">Fast. Good first pass when starting from scratch. Doesn't need any tickets to exist yet.</div>
                        </label>
                    </div>

                    <div class="form-check border rounded p-3">
                        <input class="form-check-input" type="radio" name="mode" id="modeMine" value="mine">
                        <label class="form-check-label w-100" for="modeMine">
                            <strong>Data-mine past tickets</strong> — also analyze recent ticket subjects
                            <div class="text-muted small mt-1">
                                Better for established installs. The AI sees a sample of your most recent
                                non-confidential ticket subjects and can spot real-world themes (specific
                                vendors, products, error patterns) you might want skills for.
                            </div>

                            <div class="mt-3 ps-2 border-start" id="sampleSizeWrap" style="opacity:.5;">
                                <label for="sampleSize" class="form-label fw-semibold mb-1">Sample size</label>
                                <select class="form-select form-select-sm" name="n" id="sampleSize" disabled style="max-width:240px;">
                                    <option value="100">100 most recent tickets</option>
                                    <option value="250">250 most recent tickets</option>
                                    <option value="500" selected>500 most recent tickets</option>
                                    <option value="1000">1,000 most recent tickets</option>
                                    <option value="2500">2,500 most recent tickets</option>
                                    <option value="5000">5,000 most recent tickets</option>
                                </select>
                                <div class="form-text">
                                    Larger samples give the AI more signal but take longer and may be sampled
                                    down to fit the prompt. Confidential ticket types are always excluded.
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="alert alert-warning mt-3 mb-0 small d-none" id="suggestLoadingNote">
                        <i class="bi bi-hourglass-split me-1"></i>
                        Generating suggestions can take 5–30 seconds. Please wait — don't close this tab.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);" id="suggestSubmitBtn">
                        <i class="bi bi-magic me-1"></i>Generate Suggestions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var modeBasic   = document.getElementById('modeBasic');
    var modeMine    = document.getElementById('modeMine');
    var sampleSize  = document.getElementById('sampleSize');
    var wrap        = document.getElementById('sampleSizeWrap');
    function syncSampleSize() {
        var on = modeMine.checked;
        sampleSize.disabled = !on;
        wrap.style.opacity  = on ? '1' : '.5';
    }
    modeBasic.addEventListener('change', syncSampleSize);
    modeMine.addEventListener('change',  syncSampleSize);
    syncSampleSize();

    // When the form is submitted, swap the button to a loading state and
    // surface the "this can take a while" hint.
    document.getElementById('suggestSkillsForm').addEventListener('submit', function () {
        var btn = document.getElementById('suggestSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating…';
        document.getElementById('suggestLoadingNote').classList.remove('d-none');
    });
})();
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
