<?php
$layout       = 'app';
$pageTitle    = 'AI Classification';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'AI Classification'],
];

$sentimentColors = [
    'neutral'    => 'secondary',
    'positive'   => 'success',
    'frustrated' => 'warning',
    'angry'      => 'danger',
    'urgent'     => 'danger',
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-cpu me-2"></i>AI Classification</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        Use a large language model to read each new ticket and decide which agent skills it needs, then route it via the existing <a href="/admin/docs/automations#group-auto-assign">Skill-Based</a> auto-assign machinery. Confidential ticket types are <strong>never</strong> sent to the provider. See <a href="/admin/docs/ai">AI Classification docs</a>.
    </p>
</div>

<form method="POST" action="/admin/settings/ai">
    <?= csrfField() ?>

    <!-- Master toggle -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="form-check form-switch fs-5">
                <input class="form-check-input" type="checkbox" role="switch" name="ai_enabled" id="ai_enabled" value="1"
                       <?= $aiEnabled === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="ai_enabled">
                    Enable AI ticket classification
                </label>
            </div>
            <p class="text-muted small mb-0 mt-1">When off, the <code>ai_skill_based</code> group strategy still runs but skips the AI call and uses the ticket type's required-skills (same as plain Skill-Based).</p>
        </div>
    </div>

    <!-- Provider picker -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-broadcast me-2"></i>Provider</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="radio" name="ai_provider" value="anthropic" id="prov_anthropic"
                               <?= $aiProvider === 'anthropic' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="prov_anthropic">
                            <i class="bi bi-stars text-primary me-1"></i>Anthropic Claude
                        </label>
                        <div class="form-text">Recommended. Default model is fast (Haiku) and inexpensive.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check border rounded p-3 h-100">
                        <input class="form-check-input" type="radio" name="ai_provider" value="openai" id="prov_openai"
                               <?= $aiProvider === 'openai' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="prov_openai">
                            <i class="bi bi-circle-half text-success me-1"></i>OpenAI
                        </label>
                        <div class="form-text">Use if you already have an OpenAI account or prefer GPT models.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Anthropic credentials -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-2"></i>Anthropic Credentials</h6>
            <span class="badge bg-secondary bg-opacity-10 text-secondary">api.anthropic.com</span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-7">
                    <label for="ai_anthropic_api_key" class="form-label fw-semibold">API key</label>
                    <input type="password" class="form-control" id="ai_anthropic_api_key" name="ai_anthropic_api_key"
                           placeholder="<?= $aiAnthropicKey !== '' ? '•••••••••••••• (saved)' : 'sk-ant-...' ?>"
                           autocomplete="off">
                    <div class="form-text">Leave blank to keep the existing key. Generate one at <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>.</div>
                </div>
                <div class="col-md-5">
                    <label for="ai_anthropic_model" class="form-label fw-semibold">Model</label>
                    <select class="form-select" id="ai_anthropic_model" name="ai_anthropic_model">
                        <?php
                        // Always include the saved value even if not in cache
                        $seen = [];
                        $renderOption = function (string $id, string $name) use (&$seen, $aiAnthropicModel) {
                            if (isset($seen[$id])) { return ''; }
                            $seen[$id] = true;
                            $sel = $id === $aiAnthropicModel ? 'selected' : '';
                            return '<option value="' . e($id) . '" ' . $sel . '>' . e($name) . '</option>';
                        };
                        if ($aiAnthropicModel !== '') { echo $renderOption($aiAnthropicModel, $aiAnthropicModel); }
                        foreach ($aiAnthropicModels as $m) {
                            echo $renderOption((string) $m[0], (string) ($m[1] ?? $m[0]));
                        }
                        // Sensible built-ins so a fresh install isn't empty
                        foreach ([
                            ['claude-haiku-4-5',    'Claude Haiku 4.5 (fast, cheap — recommended)'],
                            ['claude-sonnet-4-6',   'Claude Sonnet 4.6 (balanced)'],
                            ['claude-opus-4-7',     'Claude Opus 4.7 (most capable)'],
                        ] as $m) {
                            echo $renderOption($m[0], $m[1]);
                        }
                        ?>
                    </select>
                    <div class="form-text d-flex justify-content-between">
                        <span>
                            <?php if ($aiAnthropicCachedAt !== ''): ?>
                                Refreshed <?= e(substr($aiAnthropicCachedAt, 0, 16)) ?>
                            <?php else: ?>
                                Click Refresh to populate from the API
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
            <button type="submit" formaction="/admin/settings/ai/refresh-models" formmethod="POST" name="provider" value="anthropic"
                    class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh model list
            </button>
            <button type="submit" formaction="/admin/settings/ai/test" formmethod="POST" name="provider" value="anthropic"
                    class="btn btn-outline-primary btn-sm">
                <i class="bi bi-broadcast me-1"></i>Test connection
            </button>
        </div>
    </div>

    <!-- OpenAI credentials -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-2"></i>OpenAI Credentials</h6>
            <span class="badge bg-secondary bg-opacity-10 text-secondary">api.openai.com</span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-7">
                    <label for="ai_openai_api_key" class="form-label fw-semibold">API key</label>
                    <input type="password" class="form-control" id="ai_openai_api_key" name="ai_openai_api_key"
                           placeholder="<?= $aiOpenaiKey !== '' ? '•••••••••••••• (saved)' : 'sk-...' ?>"
                           autocomplete="off">
                    <div class="form-text">Leave blank to keep the existing key. Generate one at <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a>.</div>
                </div>
                <div class="col-md-5">
                    <label for="ai_openai_model" class="form-label fw-semibold">Model</label>
                    <select class="form-select" id="ai_openai_model" name="ai_openai_model">
                        <?php
                        $seen = [];
                        $renderOption = function (string $id, string $name) use (&$seen, $aiOpenaiModel) {
                            if (isset($seen[$id])) { return ''; }
                            $seen[$id] = true;
                            $sel = $id === $aiOpenaiModel ? 'selected' : '';
                            return '<option value="' . e($id) . '" ' . $sel . '>' . e($name) . '</option>';
                        };
                        if ($aiOpenaiModel !== '') { echo $renderOption($aiOpenaiModel, $aiOpenaiModel); }
                        foreach ($aiOpenaiModels as $m) {
                            echo $renderOption((string) $m[0], (string) ($m[1] ?? $m[0]));
                        }
                        foreach ([
                            ['gpt-4o-mini', 'gpt-4o-mini (fast, cheap — recommended)'],
                            ['gpt-4o',      'gpt-4o (balanced)'],
                            ['gpt-4-turbo', 'gpt-4-turbo'],
                        ] as $m) {
                            echo $renderOption($m[0], $m[1]);
                        }
                        ?>
                    </select>
                    <div class="form-text">
                        <?php if ($aiOpenaiCachedAt !== ''): ?>
                            Refreshed <?= e(substr($aiOpenaiCachedAt, 0, 16)) ?>
                        <?php else: ?>
                            Click Refresh to populate from the API
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
            <button type="submit" formaction="/admin/settings/ai/refresh-models" formmethod="POST" name="provider" value="openai"
                    class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh model list
            </button>
            <button type="submit" formaction="/admin/settings/ai/test" formmethod="POST" name="provider" value="openai"
                    class="btn btn-outline-primary btn-sm">
                <i class="bi bi-broadcast me-1"></i>Test connection
            </button>
        </div>
    </div>

    <!-- Tuning -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-sliders me-2"></i>Routing &amp; Tuning</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="ai_confidence_threshold" class="form-label fw-semibold">Confidence threshold</label>
                    <input type="number" step="0.01" min="0" max="1" class="form-control"
                           id="ai_confidence_threshold" name="ai_confidence_threshold"
                           value="<?= e($aiConfidenceThreshold) ?>">
                    <div class="form-text">Below this, the AI's skill suggestion is discarded and the ticket is left for an agent to claim. Default 0.7.</div>
                </div>
                <div class="col-md-4">
                    <label for="ai_max_tokens" class="form-label fw-semibold">Max output tokens</label>
                    <input type="number" min="50" max="4000" class="form-control"
                           id="ai_max_tokens" name="ai_max_tokens"
                           value="<?= e($aiMaxTokens) ?>">
                    <div class="form-text">Hard cap on the model's response. Default 500.</div>
                </div>
                <div class="col-md-4">
                    <label for="ai_timeout_seconds" class="form-label fw-semibold">Wall-clock timeout (s)</label>
                    <input type="number" min="2" max="30" class="form-control"
                           id="ai_timeout_seconds" name="ai_timeout_seconds"
                           value="<?= e($aiTimeoutSeconds) ?>">
                    <div class="form-text">Tickets are NEVER blocked by AI failures — if the call exceeds this, classification is skipped. Default 5.</div>
                </div>
            </div>

            <hr class="my-4">

            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="ai_sentiment_priority_bump" id="ai_sentiment_priority_bump" value="1"
                       <?= $aiSentimentPriorityBump === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ai_sentiment_priority_bump">
                    Bump priority by one level when AI sentiment is "angry" or "urgent"
                </label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="ai_classify_inbound_email" id="ai_classify_inbound_email" value="1"
                       <?= $aiClassifyInboundEmail === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ai_classify_inbound_email">
                    Classify tickets created from inbound email (in addition to portal / admin / API)
                </label>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-check-lg me-1"></i>Save settings
        </button>
    </div>
</form>

<!-- Recent classifications -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Classifications</h6>
        <span class="text-muted small">last 10</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recent)): ?>
            <div class="p-4 text-muted small">Nothing classified yet. Enable the feature, set a group's strategy to <code>ai_skill_based</code>, and submit a ticket.</div>
        <?php else: ?>
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ticket</th>
                    <th>Provider · Model</th>
                    <th>Confidence</th>
                    <th>Sentiment</th>
                    <th>Latency</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><a href="/admin/tickets/<?= (int) $r['ticket_id'] ?>">#<?= (int) $r['ticket_id'] ?></a> <span class="text-muted small"><?= e(mb_strimwidth((string) ($r['subject'] ?? ''), 0, 40, '…')) ?></span></td>
                    <td class="small text-muted"><?= e($r['provider']) ?> · <?= e($r['model']) ?></td>
                    <td><span class="badge bg-info bg-opacity-10 text-info"><?= number_format((float) $r['confidence'] * 100, 0) ?>%</span></td>
                    <td>
                        <?php $sc = $sentimentColors[$r['sentiment'] ?? 'neutral'] ?? 'secondary'; ?>
                        <span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?>"><?= e($r['sentiment'] ?? 'neutral') ?></span>
                    </td>
                    <td class="text-muted small"><?= (int) $r['latency_ms'] ?> ms</td>
                    <td class="text-muted small"><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Backfill -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-2"></i>Classify existing tickets</h6>
    </div>
    <div class="card-body p-4">
        <p class="text-muted small mb-3">
            Run the classifier across open / in-progress / pending tickets that haven't been classified yet (typically tickets created before you turned the feature on). Confidential ticket types are skipped automatically. Each call costs whatever your provider charges per call — start small.
        </p>
        <form method="POST" action="/admin/settings/ai/backfill" class="d-flex align-items-end gap-2">
            <?= csrfField() ?>
            <div>
                <label for="limit" class="form-label small fw-semibold mb-1">How many tickets?</label>
                <input type="number" min="1" max="200" name="limit" id="limit" class="form-control" value="25" style="width:120px;">
            </div>
            <button type="submit" class="btn btn-outline-primary" <?= $aiEnabled === '1' ? '' : 'disabled' ?>>
                <i class="bi bi-play-circle me-1"></i>Run backfill
            </button>
            <span class="text-muted small ms-2">For larger runs, schedule <code>php scripts/ai-classify-backfill.php --limit=N</code> via cron.</span>
        </form>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
