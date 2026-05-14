<?php
$layout       = 'app';
$pageTitle    = 'AI Debug';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'AI Classification', 'url' => '/admin/settings/ai'],
    ['label' => 'Debug'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-semibold mb-1"><i class="bi bi-bug me-2"></i>AI API Debug</h5>
        <p class="text-muted mb-0" style="font-size:.875rem;">
            Bypasses the classifier abstraction and makes raw HTTP calls so you can see exactly what each provider returns — status code, response headers, full body. Use when the regular Test Connection button comes back with a generic error.
        </p>
    </div>
    <a href="/admin/settings/ai" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to AI settings
    </a>
</div>

<form method="POST" action="/admin/settings/ai/debug" class="card border-0 shadow-sm mb-4">
    <?= csrfField() ?>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Provider</label>
                <div class="d-flex flex-column gap-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="provider" id="d_prov_anthropic" value="anthropic" checked>
                        <label class="form-check-label" for="d_prov_anthropic">Anthropic</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="provider" id="d_prov_openai" value="openai">
                        <label class="form-check-label" for="d_prov_openai">OpenAI</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Test type</label>
                <select name="test_type" class="form-select">
                    <option value="both" selected>Both — list models + send a probe message</option>
                    <option value="models">Models list only (cheapest, no token cost)</option>
                    <option value="message">Message only (verifies billing)</option>
                </select>
                <div class="form-text small">If <em>models list</em> works but <em>message</em> fails, billing/quota is the issue, not the key itself.</div>
            </div>
            <div class="col-md-3">
                <label for="api_key" class="form-label fw-semibold">API key override</label>
                <input type="password" class="form-control" id="api_key" name="api_key" autocomplete="off"
                       placeholder="<?= ($savedAnthropicKey || $savedOpenaiKey) ? 'leave blank = use saved key' : 'sk-ant-... or sk-...' ?>">
                <div class="form-text small">
                    Saved keys: Anthropic <?= $savedAnthropicKey ? '<span class="text-success">✓</span>' : '<span class="text-muted">(none)</span>' ?>,
                    OpenAI <?= $savedOpenaiKey ? '<span class="text-success">✓</span>' : '<span class="text-muted">(none)</span>' ?>
                </div>
            </div>
            <div class="col-md-3">
                <label for="model" class="form-label fw-semibold">Model override</label>
                <input type="text" class="form-control" id="model" name="model" autocomplete="off"
                       placeholder="leave blank = saved (<?= e($savedAnthropicModel) ?> / <?= e($savedOpenaiModel) ?>)">
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-send me-1"></i>Run debug test
            </button>
        </div>
    </div>
</form>

<?php if (!empty($result)): ?>
<?php
$styleByCode = static function (int $code): string {
    if ($code >= 200 && $code < 300) { return 'success'; }
    if ($code >= 400 && $code < 500) { return 'danger'; }
    if ($code >= 500) { return 'warning'; }
    return 'secondary';
};
$pretty = static function (string $body): string {
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return $body;
};
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clipboard-data me-2"></i>Run summary</h6>
    </div>
    <div class="card-body p-4 small">
        <dl class="row mb-0">
            <dt class="col-sm-3 text-muted">Provider</dt>
            <dd class="col-sm-9"><?= e($result['provider']) ?></dd>
            <dt class="col-sm-3 text-muted">Model</dt>
            <dd class="col-sm-9"><code><?= e($result['model']) ?></code></dd>
            <dt class="col-sm-3 text-muted">Key used</dt>
            <dd class="col-sm-9">
                <code><?= e($result['masked_key']) ?></code>
                <?= $result['used_pasted']
                    ? '<span class="badge bg-info bg-opacity-10 text-info ms-2">pasted (not saved)</span>'
                    : '<span class="badge bg-secondary bg-opacity-10 text-secondary ms-2">from settings</span>' ?>
            </dd>
            <dt class="col-sm-3 text-muted">cURL version</dt>
            <dd class="col-sm-9 text-muted"><?= e($result['php_curl_ver']) ?></dd>
        </dl>
    </div>
</div>

<?php foreach ($result['calls'] as $idx => $c): $st = $styleByCode((int) $c['http_code']); ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-arrow-left-right me-2"></i><?= e($c['label']) ?>
        </h6>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($c['http_code'] === 0): ?>
                <span class="badge bg-warning">network failed</span>
            <?php else: ?>
                <span class="badge bg-<?= $st ?>"><?= (int) $c['http_code'] ?></span>
            <?php endif; ?>
            <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= (int) $c['latency_ms'] ?> ms</span>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 small">
            <tbody>
                <tr><th class="text-muted ps-3" style="width:160px;">Method · URL</th>
                    <td><code><?= e($c['method']) ?></code> <code><?= e($c['url']) ?></code></td></tr>
                <?php if (!empty($c['curl_error'])): ?>
                <tr><th class="text-muted ps-3">cURL error</th>
                    <td class="text-danger"><?= e($c['curl_error']) ?></td></tr>
                <?php endif; ?>
                <tr><th class="text-muted ps-3 align-top">Response headers</th>
                    <td>
                        <?php if (empty($c['headers'])): ?>
                            <span class="text-muted">(none)</span>
                        <?php else: ?>
                        <pre class="bg-light rounded p-2 mb-0 small" style="max-height:180px;overflow:auto;white-space:pre-wrap;"><?= e(implode("\n", $c['headers'])) ?></pre>
                        <?php endif; ?>
                    </td></tr>
                <tr><th class="text-muted ps-3 align-top">Response body</th>
                    <td>
                        <?php if ($c['body'] === ''): ?>
                            <span class="text-muted">(empty)</span>
                        <?php else: ?>
                        <pre class="bg-dark text-light rounded p-3 mb-0 small" style="max-height:400px;overflow:auto;white-space:pre-wrap;word-break:break-word;"><?= e($pretty((string) $c['body'])) ?></pre>
                        <?php endif; ?>
                    </td></tr>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4 small">
        <h6 class="fw-semibold mb-3"><i class="bi bi-lightbulb me-2"></i>Reading the result</h6>
        <ul class="text-muted mb-0">
            <li><strong>200</strong> on both calls — provider is reachable, key is valid, billing is fine. If OpenHelpDesk's classifier still fails, the issue is in the classification flow specifically (open <a href="/admin/settings/ai">AI settings</a> and click Test Connection there).</li>
            <li><strong>401 Unauthorized</strong> — wrong / revoked key, or you copied with whitespace. Generate a new one.</li>
            <li><strong>403 Forbidden</strong> — key is valid but lacks permission for that endpoint (workspace restrictions, region blocks).</li>
            <li><strong>400 with "credit balance is too low"</strong> — workspace spend limit is $0 or org credits aren't visible to this workspace. Check <strong>Workspaces → [your workspace] → Limits</strong> in Anthropic Console.</li>
            <li><strong>404 model_not_found</strong> — model ID is wrong. Run a Models test alone to see the valid list.</li>
            <li><strong>429</strong> — rate limit. Slow down or check the workspace's RPM cap.</li>
            <li><strong>5xx</strong> — provider is having problems; not your fault. Try again in a few minutes.</li>
            <li><strong>cURL error / no HTTP code</strong> — DNS, firewall, or outbound HTTPS is blocked from the server. Test <code>curl https://api.anthropic.com</code> from the host shell.</li>
        </ul>
    </div>
</div>

<?php endif; ?>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
