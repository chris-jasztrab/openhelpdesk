<?php
/**
 * Settings › Inventory. With no $kind: tag numbering + overview. With $kind
 * (categories|manufacturers|suppliers|statuses|models): one catalog table
 * with an add/edit form, driven by $spec from the route.
 */
$layout       = 'app';
$pageTitle    = 'Inventory – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Inventory', 'url' => '/admin/settings/inventory'],
];
if ($kind !== null) {
    $breadcrumbs[] = ['label' => $spec['title']];
}
$kinds = ['models' => 'Models', 'categories' => 'Categories', 'manufacturers' => 'Manufacturers', 'suppliers' => 'Suppliers', 'statuses' => 'Statuses'];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-bold mb-0"><i class="bi bi-pc-display me-1"></i>Inventory<?= $kind !== null ? ' · ' . e($spec['title']) : '' ?></h5>
    <a href="/inventory" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i>Open inventory</a>
</div>

<ul class="nav nav-pills mb-4 flex-wrap gap-1">
    <li class="nav-item"><a class="nav-link <?= $kind === null ? 'active' : '' ?>" href="/admin/settings/inventory">General</a></li>
    <?php foreach ($kinds as $k => $t): ?>
    <li class="nav-item"><a class="nav-link <?= $kind === $k ? 'active' : '' ?>" href="/admin/settings/inventory/<?= $k ?>"><?= $t ?> <span class="badge text-bg-light ms-1"><?= (int) ($counts[$k] ?? 0) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($kind === null): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-1">Asset tag numbering</h6>
        <p class="text-muted small mb-3">New assets get the next number unless a tag is typed by hand. Next tag: <span class="font-monospace fw-semibold"><?= e($nextTagPreview) ?></span></p>
        <form method="POST" action="/admin/settings/inventory" class="row g-3" style="max-width:640px;">
            <?= csrfField() ?>
            <div class="col-sm-4">
                <label for="asset_tag_prefix" class="form-label fw-semibold">Prefix</label>
                <input type="text" class="form-control font-monospace" id="asset_tag_prefix" name="asset_tag_prefix" value="<?= e($tagPrefix) ?>" placeholder="e.g. WPL-">
            </div>
            <div class="col-sm-4">
                <label for="asset_tag_zerofill" class="form-label fw-semibold">Digits</label>
                <input type="number" min="0" max="12" class="form-control" id="asset_tag_zerofill" name="asset_tag_zerofill" value="<?= (int) $tagZerofill ?>">
            </div>
            <div class="col-sm-4">
                <label for="asset_tag_next" class="form-label fw-semibold">Next number</label>
                <input type="number" min="1" class="form-control" id="asset_tag_next" name="asset_tag_next" value="<?= (int) $tagNext ?>">
            </div>
            <div class="col-sm-4">
                <label for="asset_audit_interval_months" class="form-label fw-semibold">Audit interval (months)</label>
                <input type="number" min="0" max="120" class="form-control" id="asset_audit_interval_months" name="asset_audit_interval_months" value="<?= (int) $auditInterval ?>">
                <div class="form-text">Audit mode sets the next audit date this far ahead unless a date is typed. 0 leaves it unchanged.</div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);"><i class="bi bi-check-lg me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm mb-4" id="warranty">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-1">Warranty lookup</h6>
        <p class="text-muted small mb-3">
            Dell and Lenovo assets get their warranty start, end and coverage from the manufacturer, by serial number / service tag:
            once a day by the <a href="/admin/settings/cron-jobs">Warranty Lookups</a> job (each asset again every 30 days), all at once with
            <a href="/inventory/reports?tab=warranty#warrantyScan">Look up all</a> on the Warranty report, and one at a time with
            <em>Look up warranty</em> on an asset's Details tab.
            <?php $wc = $warrantyCounts ?? []; ?>
            So far <?= (int) ($wc['looked_up'] ?? 0) ?> <?= (int) ($wc['looked_up'] ?? 0) === 1 ? 'asset has' : 'assets have' ?> a warranty on record: <?= (int) ($wc['active'] ?? 0) ?> in warranty, <?= (int) ($wc['expired'] ?? 0) ?> expired.
        </p>
        <form method="POST" action="/admin/settings/inventory" class="row g-3" style="max-width:640px;" autocomplete="off">
            <?= csrfField() ?>
            <input type="hidden" name="section" value="warranty">
            <div class="col-12 small fw-semibold">Dell — TechDirect Warranty API <span class="fw-normal text-muted">(<?= $dellClientId !== '' && $dellSecretSet ? 'set up' : 'not set up: Dell assets are skipped' ?>)</span></div>
            <div class="col-sm-6">
                <label for="dell_client_id" class="form-label small">Client ID</label>
                <input type="text" class="form-control form-control-sm font-monospace" id="dell_client_id" name="dell_client_id" value="<?= e($dellClientId) ?>">
            </div>
            <div class="col-sm-6">
                <label for="dell_client_secret" class="form-label small">Client secret</label>
                <input type="password" class="form-control form-control-sm" id="dell_client_secret" name="dell_client_secret" placeholder="<?= $dellSecretSet ? 'Saved; type to replace' : '' ?>">
                <?php if ($dellSecretSet): ?><div class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="dell_client_secret_clear" id="dell_client_secret_clear"><label class="form-check-label" for="dell_client_secret_clear">Clear</label></div><?php endif; ?>
            </div>
            <div class="col-12 form-text mt-1">Free from Dell TechDirect (tdm.dell.com → APIs → Warranty API); approval takes a few business days.</div>
            <div class="col-sm-6">
                <label for="lenovo_client_id" class="form-label small fw-semibold">Lenovo ClientID <span class="fw-normal text-muted">(optional)</span></label>
                <input type="password" class="form-control form-control-sm" id="lenovo_client_id" name="lenovo_client_id" placeholder="<?= $lenovoIdSet ? 'Saved; type to replace' : '' ?>">
                <?php if ($lenovoIdSet): ?><div class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="lenovo_client_id_clear" id="lenovo_client_id_clear"><label class="form-check-label" for="lenovo_client_id_clear">Clear</label></div><?php endif; ?>
            </div>
            <div class="col-12 form-text mt-1">
                Without a ClientID, Lenovo is asked through the public lookup its support website uses. It needs no key, but it is unofficial and can stop working without notice.
                A ClientID from your Lenovo account manager switches to Lenovo's official Warranty API.
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);"><i class="bi bi-check-lg me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 small text-muted">
        <p class="mb-2"><strong class="text-dark">How it fits together.</strong> Every asset is one physical thing described by a <strong>model</strong> (which carries the category and manufacturer), a <strong>status</strong>, and optionally a supplier. A chair or a bookshelf is just another model in a non-IT category.</p>
        <p class="mb-2">Who can see and change the inventory is set under <a href="/admin/roles">Permission Levels</a> (<em>View inventory</em> / <em>Manage inventory</em>).</p>
        <p class="mb-0">Importing from Snipe-IT: <code>php scripts/import-snipeit.php --source=&lt;backup&gt; --user-domain=&lt;domain&gt;</code> (dry run until <code>--apply</code>).</p>
    </div>
</div>

<?php else: ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><?= $editing ? 'Edit' : 'Add' ?> <?= e(strtolower($spec['singular'])) ?></h6>
                <form method="POST" action="/admin/settings/inventory/<?= $kind ?>">
                    <?= csrfField() ?>
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                    <?php foreach ($spec['fields'] as $f): [$fname, $flabel, $ftype] = $f; $req = !empty($f[3]); $val = old($fname, (string) ($editing[$fname] ?? ($f[4] ?? ''))); ?>
                    <div class="mb-3">
                        <label for="f_<?= $fname ?>" class="form-label fw-semibold small"><?= e($flabel) ?><?= $req ? ' <span class="text-danger">*</span>' : '' ?></label>
                        <?php if ($ftype === 'textarea'): ?>
                        <textarea class="form-control form-control-sm" id="f_<?= $fname ?>" name="<?= $fname ?>" rows="2"><?= e($val) ?></textarea>
                        <?php elseif ($ftype === 'select'): ?>
                        <select class="form-select form-select-sm" id="f_<?= $fname ?>" name="<?= $fname ?>" <?= $req ? 'required' : '' ?>>
                            <?php if (!$req): ?><option value="">—</option><?php endif; ?>
                            <?php foreach ($options[$fname] ?? [] as $ov => $ol): ?>
                            <option value="<?= e((string) $ov) ?>" <?= (string) $ov === $val ? 'selected' : '' ?>><?= e($ol) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php elseif ($ftype === 'color'): ?>
                        <input type="color" class="form-control form-control-sm form-control-color" id="f_<?= $fname ?>" name="<?= $fname ?>" value="<?= e($val ?: '#6c757d') ?>">
                        <?php else: ?>
                        <input type="<?= $ftype ?>" class="form-control form-control-sm" id="f_<?= $fname ?>" name="<?= $fname ?>" value="<?= e($val) ?>" <?= $req ? 'required' : '' ?> <?= $ftype === 'number' ? 'min="0"' : '' ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save' : 'Add' ?></button>
                        <?php if ($editing): ?><a href="/admin/settings/inventory/<?= $kind ?>" class="btn btn-sm btn-outline-secondary">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($spec['columns'] as $c): ?><th><?= e($c[1]) ?></th><?php endforeach; ?>
                            <th class="text-end">In use</th>
                            <th style="width:100px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?><tr><td colspan="<?= count($spec['columns']) + 2 ?>" class="text-center py-4 text-muted">Nothing here yet.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php foreach ($spec['columns'] as $c): [$cname] = $c; ?>
                            <td class="<?= $cname === 'name' ? 'fw-semibold' : 'text-muted small' ?>">
                                <?php if ($cname === 'color'): ?>
                                <span class="badge" style="background:<?= e($r['color']) ?>;color:<?= ticketStatusTextColor($r['color']) ?>;"><?= e($r['name']) ?></span>
                                <?php else: ?>
                                <?= e((string) ($r[$cname] ?? '') ?: '—') ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <td class="text-end text-muted small"><?= (int) $r['in_use'] ?></td>
                            <td>
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="/admin/settings/inventory/<?= $kind ?>?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="/admin/settings/inventory/<?= $kind ?>/<?= (int) $r['id'] ?>/delete" onsubmit="return confirm('Delete <?= e(addslashes((string) $r['name'])) ?>?');">
                                        <?= csrfField() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= (int) $r['in_use'] > 0 ? 'In use — cannot delete' : 'Delete' ?>" <?= (int) $r['in_use'] > 0 ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
