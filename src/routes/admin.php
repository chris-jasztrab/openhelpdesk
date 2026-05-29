<?php

declare(strict_types=1);

/* ==================================================================
 * ADMIN – SSO Settings
 * ================================================================== */

$router->get('/admin/settings/sso', function () {
    Auth::requirePermission('settings.manage');
    $logFile    = ROOT_DIR . '/storage/logs/sso-debug.log';
    $ssoLog     = is_readable($logFile) ? file_get_contents($logFile) : null;
    render('admin/settings/sso', [
        'ssoEnabled'        => getSetting('sso_enabled',        '0'),
        'ssoTenantId'       => getSetting('sso_tenant_id',      ''),
        'ssoClientId'       => getSetting('sso_client_id',      ''),
        'ssoClientSecret'   => getSetting('sso_client_secret',  ''),
        'ssoLocationPrompt' => getSetting('sso_location_prompt','sso_only'),
        'ssoDebug'          => getSetting('sso_debug',          '0'),
        'ssoLog'            => $ssoLog,
    ]);
});

$router->post('/admin/settings/sso', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect('/admin/settings/sso');
    }

    $enabled        = isset($_POST['sso_enabled'])       ? '1' : '0';
    $tenantId       = trim($_POST['sso_tenant_id']       ?? '');
    $clientId       = trim($_POST['sso_client_id']       ?? '');
    $clientSecret   = $_POST['sso_client_secret']        ?? '';
    $locationPrompt = in_array($_POST['sso_location_prompt'] ?? '', ['sso_only', 'all'], true)
                        ? $_POST['sso_location_prompt']
                        : 'sso_only';

    if ($enabled === '1' && ($tenantId === '' || $clientId === '')) {
        flash('error', 'Tenant ID and Client ID are required to enable SSO.');
        redirect('/admin/settings/sso');
    }

    $debug = isset($_POST['sso_debug']) ? '1' : '0';

    $before = [
        'sso_enabled'         => getSetting('sso_enabled',         '0'),
        'sso_tenant_id'       => getSetting('sso_tenant_id',       ''),
        'sso_client_id'       => getSetting('sso_client_id',       ''),
        'sso_location_prompt' => getSetting('sso_location_prompt', 'sso_only'),
        'sso_debug'           => getSetting('sso_debug',           '0'),
        // Use a stable placeholder so the diff fires only when the secret
        // is actually rotated (i.e. when $clientSecret is non-empty below).
        'sso_client_secret'   => '(unchanged)',
    ];

    setSetting('sso_enabled',        $enabled);
    setSetting('sso_tenant_id',      $tenantId);
    setSetting('sso_client_id',      $clientId);
    setSetting('sso_location_prompt', $locationPrompt);
    setSetting('sso_debug',          $debug);

    $after = [
        'sso_enabled'         => $enabled,
        'sso_tenant_id'       => $tenantId,
        'sso_client_id'       => $clientId,
        'sso_location_prompt' => $locationPrompt,
        'sso_debug'           => $debug,
        'sso_client_secret'   => '(unchanged)',
    ];

    // Only overwrite the secret if a new value was provided
    if ($clientSecret !== '') {
        setSetting('sso_client_secret', $clientSecret);
        $after['sso_client_secret'] = '(rotated)';
    }

    logAuditChange(
        'sso.settings_changed',
        null,
        null,
        $before,
        $after,
        ['sso_client_secret']
    );

    flash('success', 'SSO settings saved successfully.');
    redirect('/admin/settings/sso');
});

$router->get('/admin/settings/sso/help', function () {
    Auth::requirePermission('settings.manage');
    render('admin/settings/sso-help');
});

$router->post('/admin/settings/sso/clear-log', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect('/admin/settings/sso');
    }
    $logFile = ROOT_DIR . '/storage/logs/sso-debug.log';
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    flash('success', 'SSO debug log cleared.');
    redirect('/admin/settings/sso');
});

/* ==================================================================
 * ADMIN – AI Classification settings
 *
 * Controls the AI provider, key, model, confidence threshold, and the
 * sentiment-driven priority bump. The model dropdown is auto-populated
 * from the provider's API so a new Anthropic / OpenAI model release
 * does NOT require a code change.
 * ================================================================== */

$router->get('/admin/settings/ai', function () {
    Auth::requirePermission('ai.manage');

    $anthropicCache = json_decode((string) getSetting('ai_anthropic_models_cache', '[]'), true) ?: [];
    $openaiCache    = json_decode((string) getSetting('ai_openai_models_cache',    '[]'), true) ?: [];

    // Recent classifications for the activity strip on the page
    $db = Database::connect();
    $recent = $db->query(
        "SELECT c.id, c.ticket_id, c.provider, c.model, c.confidence, c.sentiment,
                c.latency_ms, c.created_at, t.subject
         FROM ai_classifications c
         LEFT JOIN tickets t ON t.id = c.ticket_id
         ORDER BY c.id DESC
         LIMIT 10"
    )->fetchAll();

    render('admin/settings/ai', [
        'aiEnabled'                => getSetting('ai_enabled', '0'),
        'aiProvider'               => getSetting('ai_provider', 'anthropic'),
        'aiAnthropicKey'           => getSetting('ai_anthropic_api_key', ''),
        'aiAnthropicModel'         => getSetting('ai_anthropic_model', 'claude-haiku-4-5'),
        'aiAnthropicModels'        => $anthropicCache,
        'aiAnthropicCachedAt'      => getSetting('ai_anthropic_models_cached_at', ''),
        'aiOpenaiKey'              => getSetting('ai_openai_api_key', ''),
        'aiOpenaiModel'            => getSetting('ai_openai_model', 'gpt-4o-mini'),
        'aiOpenaiModels'           => $openaiCache,
        'aiOpenaiCachedAt'         => getSetting('ai_openai_models_cached_at', ''),
        'aiConfidenceThreshold'    => getSetting('ai_confidence_threshold', '0.7'),
        'aiMaxTokens'              => getSetting('ai_max_tokens', '500'),
        'aiTimeoutSeconds'         => getSetting('ai_timeout_seconds', '5'),
        'aiSentimentPriorityBump'  => getSetting('ai_sentiment_priority_bump', '1'),
        'aiClassifyInboundEmail'   => getSetting('ai_classify_inbound_email', '1'),
        'recent'                   => $recent,
    ]);
});

$router->post('/admin/settings/ai', function () {
    Auth::requirePermission('ai.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ai');
    }

    $enabled         = isset($_POST['ai_enabled']) ? '1' : '0';
    $provider        = in_array($_POST['ai_provider'] ?? '', ['anthropic', 'openai'], true) ? $_POST['ai_provider'] : 'anthropic';
    $anthropicKey    = $_POST['ai_anthropic_api_key'] ?? '';
    $anthropicModel  = trim($_POST['ai_anthropic_model'] ?? '');
    $openaiKey       = $_POST['ai_openai_api_key'] ?? '';
    $openaiModel     = trim($_POST['ai_openai_model']    ?? '');
    $threshold       = (float) ($_POST['ai_confidence_threshold'] ?? '0.7');
    $maxTokens       = (int)   ($_POST['ai_max_tokens']     ?? '500');
    $timeout         = (int)   ($_POST['ai_timeout_seconds'] ?? '5');
    $sentimentBump   = isset($_POST['ai_sentiment_priority_bump']) ? '1' : '0';
    $classifyInbound = isset($_POST['ai_classify_inbound_email']) ? '1' : '0';

    if ($threshold < 0.0) { $threshold = 0.0; }
    if ($threshold > 1.0) { $threshold = 1.0; }
    if ($maxTokens < 50)  { $maxTokens = 50; }
    if ($maxTokens > 4000){ $maxTokens = 4000; }
    if ($timeout < 2)     { $timeout = 2; }
    if ($timeout > 30)    { $timeout = 30; }

    if ($enabled === '1') {
        $key = $provider === 'anthropic' ? $anthropicKey : $openaiKey;
        if (trim($key) === '' && (
            ($provider === 'anthropic' && getSetting('ai_anthropic_api_key', '') === '') ||
            ($provider === 'openai'    && getSetting('ai_openai_api_key',    '') === '')
        )) {
            flash('error', 'Cannot enable AI classification — set an API key for the chosen provider first.');
            redirect('/admin/settings/ai');
        }
    }

    setSetting('ai_enabled',                 $enabled);
    setSetting('ai_provider',                $provider);
    if ($anthropicModel !== '') { setSetting('ai_anthropic_model', $anthropicModel); }
    if ($openaiModel    !== '') { setSetting('ai_openai_model',    $openaiModel); }
    setSetting('ai_confidence_threshold',    (string) $threshold);
    setSetting('ai_max_tokens',              (string) $maxTokens);
    setSetting('ai_timeout_seconds',         (string) $timeout);
    setSetting('ai_sentiment_priority_bump', $sentimentBump);
    setSetting('ai_classify_inbound_email',  $classifyInbound);

    // Only overwrite a key if the admin provided a new value
    if ($anthropicKey !== '') { setSetting('ai_anthropic_api_key', $anthropicKey); }
    if ($openaiKey    !== '') { setSetting('ai_openai_api_key',    $openaiKey); }

    logAudit('ai.settings_changed', null, 'settings', 'AI provider=' . $provider . ' enabled=' . $enabled);
    flash('success', 'AI classification settings saved.');
    redirect('/admin/settings/ai');
});

$router->post('/admin/settings/ai/refresh-models', function () {
    Auth::requirePermission('ai.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ai');
    }
    $provider = $_POST['provider'] ?? '';
    if ($provider === 'anthropic') {
        $key = (string) getSetting('ai_anthropic_api_key', '');
        if ($key === '') {
            flash('error', 'Set the Anthropic API key first.');
            redirect('/admin/settings/ai');
        }
        $cli = AIClassifierFactory::forProvider('anthropic', $key, (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5'));
        try {
            $models = $cli->listModels(15);
            setSetting('ai_anthropic_models_cache',     json_encode($models, JSON_UNESCAPED_SLASHES));
            setSetting('ai_anthropic_models_cached_at', date('c'));
            flash('success', 'Anthropic model list refreshed (' . count($models) . ' models).');
        } catch (\Throwable $e) {
            flash('error', 'Anthropic refresh failed: ' . $e->getMessage());
        }
    } elseif ($provider === 'openai') {
        $key = (string) getSetting('ai_openai_api_key', '');
        if ($key === '') {
            flash('error', 'Set the OpenAI API key first.');
            redirect('/admin/settings/ai');
        }
        $cli = AIClassifierFactory::forProvider('openai', $key, (string) getSetting('ai_openai_model', 'gpt-4o-mini'));
        try {
            $models = $cli->listModels(15);
            setSetting('ai_openai_models_cache',     json_encode($models, JSON_UNESCAPED_SLASHES));
            setSetting('ai_openai_models_cached_at', date('c'));
            flash('success', 'OpenAI model list refreshed (' . count($models) . ' models).');
        } catch (\Throwable $e) {
            flash('error', 'OpenAI refresh failed: ' . $e->getMessage());
        }
    } else {
        flash('error', 'Unknown provider.');
    }
    redirect('/admin/settings/ai');
});

/**
 * Manually re-classify a single ticket. Useful when an admin edited
 * the subject / body and wants the AI to take another look. Creates
 * a fresh ai_classifications row (history is preserved).
 */
$router->post('/admin/tickets/{id}/classify', function (array $p) {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets/' . (int) $p['id']);
    }
    $ticketId = (int) $p['id'];
    if (getSetting('ai_enabled', '0') !== '1') {
        flash('error', 'AI classification is disabled in settings.');
        redirect('/admin/tickets/' . $ticketId);
    }
    set_time_limit(0);
    $verdict = classifyTicketWithAI($ticketId);
    if ($verdict === null) {
        flash('error', 'Re-classification failed (provider error or confidential type).');
    } else {
        $confPct = (int) round(((float) ($verdict['confidence'] ?? 0)) * 100);
        flash('success', "Re-classified — {$confPct}% confidence, sentiment " . ($verdict['sentiment'] ?? 'neutral') . '.');
    }
    redirect('/admin/tickets/' . $ticketId);
});

/**
 * Override the AI's suggested skill set for a ticket. Stored on the
 * existing ai_classifications row so we keep both signals (what AI
 * said vs. what the human decided) for reporting.
 */
$router->post('/admin/tickets/{id}/classification/override', function (array $p) {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets/' . (int) $p['id']);
    }
    $ticketId = (int) $p['id'];
    $db = Database::connect();

    $tStmt = $db->prepare('SELECT ai_classification_id, group_id FROM tickets WHERE id = ?');
    $tStmt->execute([$ticketId]);
    $ticket = $tStmt->fetch();
    if (!$ticket || empty($ticket['ai_classification_id'])) {
        flash('error', 'No AI classification on this ticket.');
        redirect('/admin/tickets/' . $ticketId);
    }
    $classificationId = (int) $ticket['ai_classification_id'];

    $rawSkillIds = $_POST['skill_ids'] ?? [];
    if (!is_array($rawSkillIds)) { $rawSkillIds = []; }
    $skillIds = array_values(array_unique(array_filter(array_map('intval', $rawSkillIds))));

    // Re-derive the allowed skill set so a user can't override with
    // skills outside the ticket's group / global pool.
    $groupId = $ticket['group_id'] !== null ? (int) $ticket['group_id'] : null;
    if ($groupId !== null) {
        $skStmt = $db->prepare(
            "SELECT id FROM agent_skills WHERE group_id IS NULL OR group_id = ?"
        );
        $skStmt->execute([$groupId]);
    } else {
        $skStmt = $db->query("SELECT id FROM agent_skills WHERE group_id IS NULL");
    }
    $allowed = array_flip(array_map('intval', $skStmt->fetchAll(PDO::FETCH_COLUMN)));
    $skillIds = array_values(array_filter($skillIds, static fn($sid) => isset($allowed[$sid])));

    $reason = trim((string) ($_POST['reason'] ?? ''));
    if (mb_strlen($reason) > 500) { $reason = mb_substr($reason, 0, 500); }

    $db->prepare(
        'UPDATE ai_classifications
         SET overridden_skill_ids = ?, overridden_by = ?, overridden_at = NOW(), override_reason = ?
         WHERE id = ?'
    )->execute([
        json_encode($skillIds, JSON_UNESCAPED_SLASHES),
        Auth::id(),
        $reason !== '' ? $reason : null,
        $classificationId,
    ]);

    // Timeline entry — visible to agents, not portal users
    $skillNames = [];
    if (!empty($skillIds)) {
        $ph = implode(',', array_fill(0, count($skillIds), '?'));
        $nStmt = $db->prepare("SELECT name FROM agent_skills WHERE id IN ($ph)");
        $nStmt->execute($skillIds);
        $skillNames = $nStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    $detail = Auth::fullName() . ' overrode AI skill suggestion to: '
            . (empty($skillNames) ? '(none)' : implode(', ', $skillNames))
            . ($reason !== '' ? ' — ' . $reason : '');
    $db->prepare(
        "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, ?, 'ai_override', ?, 1)"
    )->execute([$ticketId, Auth::id(), $detail]);

    logAudit('ai.classification_override', $classificationId, 'ai_classification', "Ticket #{$ticketId}: " . $detail);
    flash('success', 'AI suggestion overridden.');
    redirect('/admin/tickets/' . $ticketId);
});

$router->post('/admin/settings/ai/backfill', function () {
    Auth::requirePermission('ai.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ai');
    }
    $limit = max(1, min(200, (int) ($_POST['limit'] ?? 25)));
    if (getSetting('ai_enabled', '0') !== '1') {
        flash('error', 'Enable AI classification first.');
        redirect('/admin/settings/ai');
    }

    // Run inline. With a 25-ticket default and 250ms inter-call sleep
    // plus 5-sec per-call timeout, worst case is ~140 seconds — within
    // PHP's default 300s limit. For larger backfills, schedule the CLI
    // script via cron.
    set_time_limit(0);

    $db = Database::connect();
    $openInT = ticketStatusSqlIn(ticketOpenBucketSlugs(), 't.status');
    $stmt = $db->prepare(
        "SELECT t.id FROM tickets t
         LEFT JOIN ticket_types tt ON tt.id = t.type_id
         WHERE t.ai_classification_id IS NULL
           AND $openInT
           AND COALESCE(tt.is_confidential, 0) = 0
         ORDER BY t.id DESC
         LIMIT {$limit}"
    );
    $stmt->execute();
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $ok = 0; $fail = 0;
    foreach ($ids as $tid) {
        try {
            if (classifyTicketWithAI($tid) !== null) { $ok++; } else { $fail++; }
        } catch (\Throwable $e) {
            $fail++;
        }
        usleep(250000);
    }
    logAudit('ai.backfill_run', null, 'settings', "Backfill processed {$ok} ok / {$fail} failed (limit {$limit})");
    flash('success', "Backfill complete — classified {$ok} ticket(s), {$fail} skipped/failed.");
    redirect('/admin/settings/ai');
});

/**
 * Debug page — bypasses the classifier abstraction and makes raw HTTP
 * calls so you can see exactly what each provider returns. Useful when
 * the saved-key Test Connection button comes back with a generic error.
 */
$router->get('/admin/settings/ai/debug', function () {
    Auth::requirePermission('ai.manage');
    render('admin/settings/ai-debug', [
        'savedAnthropicKey'   => getSetting('ai_anthropic_api_key', '') !== '',
        'savedAnthropicModel' => (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5'),
        'savedOpenaiKey'      => getSetting('ai_openai_api_key', '') !== '',
        'savedOpenaiModel'    => (string) getSetting('ai_openai_model', 'gpt-4o-mini'),
        'result'              => null,
    ]);
});

$router->post('/admin/settings/ai/debug', function () {
    Auth::requirePermission('ai.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ai/debug');
    }

    $provider = in_array($_POST['provider'] ?? '', ['anthropic', 'openai'], true) ? $_POST['provider'] : 'anthropic';
    $testType = in_array($_POST['test_type'] ?? '', ['models', 'message', 'both'], true) ? $_POST['test_type'] : 'both';

    // Use the pasted key when provided, otherwise fall back to the saved one
    $pastedKey   = trim((string) ($_POST['api_key'] ?? ''));
    $pastedModel = trim((string) ($_POST['model']   ?? ''));
    if ($provider === 'anthropic') {
        $apiKey = $pastedKey !== '' ? $pastedKey : (string) getSetting('ai_anthropic_api_key', '');
        $model  = $pastedModel !== '' ? $pastedModel : (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5');
    } else {
        $apiKey = $pastedKey !== '' ? $pastedKey : (string) getSetting('ai_openai_api_key', '');
        $model  = $pastedModel !== '' ? $pastedModel : (string) getSetting('ai_openai_model', 'gpt-4o-mini');
    }

    if ($apiKey === '') {
        flash('error', 'No API key — paste one or save one first.');
        redirect('/admin/settings/ai/debug');
    }

    // ── Raw cURL helper that captures EVERYTHING — status, headers, body, timing
    $rawCall = static function (string $method, string $url, array $headers, ?string $body) {
        $ch = curl_init($url);
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HEADERFUNCTION => function ($_ch, $line) use (&$respHeaders) {
                // cURL aborts with "Failed writing header" if we don't return
                // the EXACT byte count we received. Capture length BEFORE any
                // trimming and return the original length unconditionally.
                $len   = strlen($line);
                $clean = rtrim($line);
                if ($clean !== '' && str_contains($clean, ':')) {
                    $respHeaders[] = $clean;
                }
                return $len;
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $started   = microtime(true);
        $response  = curl_exec($ch);
        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err       = curl_error($ch);
        curl_close($ch);
        return [
            'method'     => $method,
            'url'        => $url,
            'http_code'  => $httpCode,
            'latency_ms' => $latencyMs,
            'curl_error' => $err,
            'headers'    => $respHeaders,
            'body'       => is_string($response) ? $response : '',
        ];
    };

    $maskKey = static function (string $k): string {
        $len = strlen($k);
        if ($len <= 12) { return str_repeat('•', $len); }
        return substr($k, 0, 8) . str_repeat('•', max(4, $len - 12)) . substr($k, -4);
    };

    $calls = [];

    if ($provider === 'anthropic') {
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];
        if ($testType === 'models' || $testType === 'both') {
            $calls[] = ['label' => 'GET /v1/models'] + $rawCall('GET', 'https://api.anthropic.com/v1/models', $headers, null);
        }
        if ($testType === 'message' || $testType === 'both') {
            $payload = json_encode([
                'model'      => $model,
                'max_tokens' => 50,
                'messages'   => [['role' => 'user', 'content' => 'Say "ok" and nothing else.']],
            ], JSON_UNESCAPED_SLASHES);
            $calls[] = ['label' => 'POST /v1/messages — minimal probe'] + $rawCall('POST', 'https://api.anthropic.com/v1/messages', $headers, $payload);
        }
    } else {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        if ($testType === 'models' || $testType === 'both') {
            $calls[] = ['label' => 'GET /v1/models'] + $rawCall('GET', 'https://api.openai.com/v1/models', $headers, null);
        }
        if ($testType === 'message' || $testType === 'both') {
            $payload = json_encode([
                'model'    => $model,
                'messages' => [['role' => 'user', 'content' => 'Say "ok" and nothing else.']],
                'max_tokens' => 50,
            ], JSON_UNESCAPED_SLASHES);
            $calls[] = ['label' => 'POST /v1/chat/completions — minimal probe'] + $rawCall('POST', 'https://api.openai.com/v1/chat/completions', $headers, $payload);
        }
    }

    render('admin/settings/ai-debug', [
        'savedAnthropicKey'   => getSetting('ai_anthropic_api_key', '') !== '',
        'savedAnthropicModel' => (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5'),
        'savedOpenaiKey'      => getSetting('ai_openai_api_key', '') !== '',
        'savedOpenaiModel'    => (string) getSetting('ai_openai_model', 'gpt-4o-mini'),
        'result' => [
            'provider'     => $provider,
            'model'        => $model,
            'used_pasted'  => $pastedKey !== '',
            'masked_key'   => $maskKey($apiKey),
            'calls'        => $calls,
            'php_curl_ver' => curl_version()['version'] ?? 'unknown',
        ],
    ]);
});

$router->post('/admin/settings/ai/test', function () {
    Auth::requirePermission('ai.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ai');
    }
    $provider = $_POST['provider'] ?? getSetting('ai_provider', 'anthropic');
    if ($provider === 'anthropic') {
        $key = (string) getSetting('ai_anthropic_api_key', '');
        $model = (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5');
    } else {
        $key = (string) getSetting('ai_openai_api_key', '');
        $model = (string) getSetting('ai_openai_model', 'gpt-4o-mini');
    }
    if ($key === '') {
        flash('error', 'Set the API key first.');
        redirect('/admin/settings/ai');
    }
    $cli = AIClassifierFactory::forProvider($provider, $key, $model);
    $result = $cli->testConnection(15);
    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('/admin/settings/ai');
});

/* ==================================================================
 * ADMIN – Onboarding
 * ================================================================== */

$router->post('/admin/onboarding/dismiss', function () {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect('/admin');
    }
    setSetting('show_onboarding', '0');
    redirect('/admin');
});

/* ==================================================================
 * ADMIN – Documentation
 * ================================================================== */

$validDocPages = [
    'getting-started', 'tickets', 'users', 'email',
    'sla', 'automations', 'flows', 'ai', 'branding', 'portal', 'csat', 'import', 'kb', 'sso',
];

$router->get('/admin/docs', function () {
    Auth::requireAdmin();
    render('admin/docs/index', [
        'sidebarItems' => adminSidebar('docs'),
        'layout'       => 'app',
        'pageTitle'    => 'Documentation',
        'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Docs']],
    ]);
});

$router->get('/admin/docs/{page}', function (array $p) use ($validDocPages) {
    Auth::requireAdmin();
    $page = $p['page'] ?? '';
    if (!in_array($page, $validDocPages, true)) {
        redirect('/admin/docs');
    }
    $titles = [
        'getting-started' => 'Getting Started',
        'tickets'         => 'Tickets',
        'users'           => 'Users & Roles',
        'email'           => 'Email & Notifications',
        'sla'             => 'SLA Policies',
        'automations'     => 'Automations',
        'flows'           => 'Assignment Flow Diagrams',
        'ai'              => 'AI Classification',
        'branding'        => 'Branding',
        'portal'          => 'Portal',
        'csat'            => 'Satisfaction Surveys',
        'import'          => 'Importing Tickets',
        'kb'              => 'Knowledge Base',
        'sso'             => 'Single Sign-On',
    ];
    render('admin/docs/' . $page, [
        'sidebarItems' => adminSidebar('docs'),
        'layout'       => 'app',
        'pageTitle'    => 'Docs: ' . ($titles[$page] ?? $page),
        'breadcrumbs'  => [
            ['label' => 'Admin', 'url' => '/admin'],
            ['label' => 'Docs',  'url' => '/admin/docs'],
            ['label' => $titles[$page] ?? $page],
        ],
    ]);
});

/* ==================================================================
 * ADMIN – User Management
 * ================================================================== */

$router->get('/admin/users/online', function () {
    Auth::requirePermission('users.manage');
    $db = Database::connect();

    // Single source of truth for the online window. Anyone whose last_seen is
    // within the last 120 seconds is considered "currently online". The
    // heartbeat fires every 30s while a tab is in the foreground, but
    // browsers throttle setInterval in background tabs to roughly once per
    // minute — 120s absorbs that with a missed-ping margin so a user who
    // minimizes their window or tabs away doesn't drop off the list.
    $onlineWindow = 120;

    // Drop rows older than 24h to keep the table tidy. The 60s online window
    // is enforced separately below; we just don't want stale rows piling up.
    $db->exec('DELETE FROM user_presence WHERE last_seen < DATE_SUB(NOW(), INTERVAL 1 DAY)');

    $stmt = $db->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.role,
                p.last_seen, p.ip_address, p.user_agent,
                TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) AS seconds_ago
           FROM user_presence p
           JOIN users u ON u.id = p.user_id
          WHERE p.last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)
          ORDER BY p.last_seen DESC"
    );
    $stmt->execute([$onlineWindow]);
    $online = $stmt->fetchAll();

    render('admin/users/online', [
        'online'       => $online,
        'onlineWindow' => $onlineWindow,
    ]);
});

$router->get('/admin/users', function () {
    Auth::requirePermission('users.manage');
    $db   = Database::connect();

    if (isset($_GET['reset'])) {
        redirect('/admin/users');
    }

    $roleFilter = array_values(array_filter(array_map('trim', (array) ($_GET['role']     ?? []))));
    $locFilter  = array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? []))));
    $q          = trim($_GET['q'] ?? '');

    $sql    = 'SELECT u.*, l.name AS location_name FROM users u LEFT JOIN locations l ON u.location_id = l.id';
    $where  = [];
    $params = [];

    $roles = array_values(array_filter($roleFilter, fn($r) => roleExists($r)));
    if (!empty($roles)) {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $where[]  = "u.role IN ($placeholders)";
        $params   = array_merge($params, $roles);
    }

    $hasNone = in_array('none', $locFilter, true);
    $locIds  = array_values(array_filter($locFilter, fn($l) => $l !== 'none' && ctype_digit((string) $l)));
    if ($hasNone && !empty($locIds)) {
        $placeholders = implode(',', array_fill(0, count($locIds), '?'));
        $where[]  = "(u.location_id IS NULL OR u.location_id IN ($placeholders))";
        $params   = array_merge($params, array_map('intval', $locIds));
    } elseif ($hasNone) {
        $where[] = 'u.location_id IS NULL';
    } elseif (!empty($locIds)) {
        $placeholders = implode(',', array_fill(0, count($locIds), '?'));
        $where[]  = "u.location_id IN ($placeholders)";
        $params   = array_merge($params, array_map('intval', $locIds));
    }

    if ($q !== '') {
        $where[]  = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // Sorting
    $sortableColumns = [
        'name'       => 'u.first_name',
        'email'      => 'u.email',
        'role'       => 'u.role',
        'location'   => 'l.name',
        'created_at' => 'u.created_at',
    ];
    $sort = $_GET['sort'] ?? 'created_at';
    $dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $orderCol = $sortableColumns[$sort] ?? 'u.created_at';

    $sql .= " ORDER BY {$orderCol} {$dir}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $locations = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();

    render('admin/users/index', [
        'users'      => $users,
        'roleFilter' => $roleFilter,
        'locFilter'  => $locFilter,
        'qFilter'    => $q,
        'locations'  => $locations,
        'sort'       => $sort,
        'dir'        => strtolower($dir),
    ]);
});

$router->get('/admin/users/create', function () {
    Auth::requirePermission('users.manage');
    $locations = Database::connect()->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    render('admin/users/form', ['locations' => $locations, 'editing' => null]);
});

$router->post('/admin/users/create', function () {
    Auth::requirePermission('users.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/users/create');
    }

    $fn              = trim($_POST['first_name'] ?? '');
    $ln              = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $roleRaw         = $_POST['role'] ?? 'user';
    $role            = roleExists($roleRaw) ? $roleRaw : 'user';
    $phone           = trim($_POST['work_phone'] ?? '');
    $locId           = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
    $canViewLocTix   = !empty($_POST['can_view_location_tickets']) ? 1 : 0;

    if ($fn === '' || $ln === '' || $email === '' || $password === '') {
        flashInput($_POST);
        flash('error', 'First name, last name, email and password are required.');
        redirect('/admin/users/create');
    }

    // Avatar upload
    $avatar = handleAvatarUpload();

    $db = Database::connect();
    try {
        $stmt = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role, avatar, work_phone, location_id, can_view_location_tickets)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fn, $ln, $email, password_hash($password, PASSWORD_DEFAULT), $role, $avatar, $phone, $locId, $canViewLocTix]);
        $newId = (int) $db->lastInsertId();
        logAudit('user.create', $newId, 'user', "{$fn} {$ln} ({$email}), role={$role}");
        flash('success', 'User created successfully.');
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate entry')
            ? 'A user with this email already exists.'
            : 'Database error: ' . $e->getMessage());
        flashInput($_POST);
        redirect('/admin/users/create');
    }
    redirect('/admin/users');
});

$router->get('/admin/users/{id}', function (array $p) {
    Auth::requirePermission('users.manage');
    $db  = Database::connect();
    $uid = (int) $p['id'];

    $stmt = $db->prepare(
        'SELECT u.*, l.name AS location_name
         FROM users u LEFT JOIN locations l ON u.location_id = l.id
         WHERE u.id = ?'
    );
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (!$user) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }

    // Filter params for ticket list
    $userTicketFilters = [
        'q'       => trim($_GET['q'] ?? ''),
        'status'  => array_map('strval', (array) ($_GET['status'] ?? [])),
        'priority'=> array_map('strval', (array) ($_GET['priority'] ?? [])),
        'type'    => array_map('strval', (array) ($_GET['type'] ?? [])),
        'agent'   => array_map('strval', (array) ($_GET['agent'] ?? [])),
    ];

    // Options for filter panel
    $ticketPriorities = $db->query('SELECT id, name, color FROM ticket_priorities ORDER BY sort_order, name')->fetchAll();
    $ticketTypes      = $db->query('SELECT id, name FROM ticket_types ORDER BY name')->fetchAll();
    $ticketAgents     = $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name")->fetchAll();

    // Build dynamic ticket query for this user
    $where  = ['t.created_by = ?'];
    $params = [$uid];

    if ($userTicketFilters['q'] !== '') {
        $where[] = 't.subject LIKE ?';
        $params[] = '%' . $userTicketFilters['q'] . '%';
    }

    if (!empty($userTicketFilters['status'])) {
        $placeholders = implode(',', array_fill(0, count($userTicketFilters['status']), '?'));
        $where[] = "t.status IN ($placeholders)";
        $params  = array_merge($params, $userTicketFilters['status']);
    } else {
        $where[] = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status', true);
    }

    if (!empty($userTicketFilters['priority'])) {
        $placeholders = implode(',', array_fill(0, count($userTicketFilters['priority']), '?'));
        $where[] = "t.priority_id IN ($placeholders)";
        $params  = array_merge($params, $userTicketFilters['priority']);
    }

    if (!empty($userTicketFilters['type'])) {
        $placeholders = implode(',', array_fill(0, count($userTicketFilters['type']), '?'));
        $where[] = "t.type_id IN ($placeholders)";
        $params  = array_merge($params, $userTicketFilters['type']);
    }

    if (!empty($userTicketFilters['agent'])) {
        $agentClauses = [];
        foreach ($userTicketFilters['agent'] as $av) {
            if ($av === 'unassigned') {
                $agentClauses[] = 't.assigned_to IS NULL';
            } else {
                $agentClauses[] = 't.assigned_to = ?';
                $params[] = $av;
            }
        }
        $where[] = '(' . implode(' OR ', $agentClauses) . ')';
    }

    $tSql = "SELECT t.id, t.subject, t.status, t.created_at,
                    tp.name AS priority_name, tp.color AS priority_color,
                    tt.name AS type_name, tt.color AS type_color,
                    CONCAT(a.first_name, ' ', a.last_name) AS assigned_name
             FROM tickets t
             LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
             LEFT JOIN ticket_types     tt ON t.type_id     = tt.id
             LEFT JOIN users             a  ON t.assigned_to = a.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY t.created_at DESC";
    $tStmt = $db->prepare($tSql);
    $tStmt->execute($params);
    $openTickets = $tStmt->fetchAll();

    // Counts needed for the delete/transfer modal
    $cStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_by = ?');
    $cStmt->execute([$uid]);
    $createdCount = (int) $cStmt->fetchColumn();

    $aStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE assigned_to = ?');
    $aStmt->execute([$uid]);
    $assignedCount = (int) $aStmt->fetchColumn();

    $kbStmt = $db->prepare('SELECT COUNT(*) FROM kb_articles WHERE created_by = ?');
    $kbStmt->execute([$uid]);
    $kbCount = (int) $kbStmt->fetchColumn();

    render('admin/users/view', [
        'profileUser'        => $user,
        'openTickets'        => $openTickets,
        'userTicketFilters'  => $userTicketFilters,
        'ticketPriorities'   => $ticketPriorities,
        'ticketTypes'        => $ticketTypes,
        'ticketAgents'       => $ticketAgents,
        'createdCount'       => $createdCount,
        'assignedCount'      => $assignedCount,
        'kbCount'            => $kbCount,
    ]);
});

$router->get('/admin/users/{id}/edit', function (array $p) {
    Auth::requirePermission('users.manage');
    $db   = Database::connect();
    $user = $db->prepare('SELECT * FROM users WHERE id = ?');
    $user->execute([(int) $p['id']]);
    $editing = $user->fetch();
    if (!$editing) {
        flash('error', 'User not found.');
        redirect('/admin/users');
    }
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $userGroups = [];
    if (roleIsStaff($editing['role'])) {
        $gStmt = $db->prepare(
            'SELECT g.id, g.name FROM `groups` g
             JOIN group_user_map gum ON gum.group_id = g.id
             WHERE gum.user_id = ? ORDER BY g.name'
        );
        $gStmt->execute([(int) $p['id']]);
        $userGroups = $gStmt->fetchAll();
    }
    render('admin/users/form', ['locations' => $locations, 'editing' => $editing, 'userGroups' => $userGroups]);
});

$router->post('/admin/users/{id}/edit', function (array $p) {
    Auth::requirePermission('users.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/users/{$id}/edit");
    }

    $fn            = trim($_POST['first_name'] ?? '');
    $ln            = trim($_POST['last_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $roleRaw       = $_POST['role'] ?? 'user';
    $role          = in_array($roleRaw, ['admin', 'agent', 'power_user', 'user'], true) ? $roleRaw : 'user';
    $phone         = trim($_POST['work_phone'] ?? '');
    $locId         = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
    $canViewLocTix = !empty($_POST['can_view_location_tickets']) ? 1 : 0;

    if ($fn === '' || $ln === '' || $email === '') {
        flashInput($_POST);
        flash('error', 'First name, last name, and email are required.');
        redirect("/admin/users/{$id}/edit");
    }

    $db = Database::connect();

    // Handle avatar
    $avatar = handleAvatarUpload();
    if ($avatar === null && empty($_POST['remove_avatar'])) {
        // Keep existing avatar
        $existing = $db->prepare('SELECT avatar FROM users WHERE id = ?');
        $existing->execute([$id]);
        $avatar = $existing->fetchColumn() ?: null;
    }

    $password = $_POST['password'] ?? '';

    try {
        if ($password !== '') {
            $stmt = $db->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, password=?, role=?, avatar=?, work_phone=?, location_id=?, can_view_location_tickets=? WHERE id=?'
            );
            $stmt->execute([$fn, $ln, $email, password_hash($password, PASSWORD_DEFAULT), $role, $avatar, $phone, $locId, $canViewLocTix, $id]);
        } else {
            $stmt = $db->prepare(
                'UPDATE users SET first_name=?, last_name=?, email=?, role=?, avatar=?, work_phone=?, location_id=?, can_view_location_tickets=? WHERE id=?'
            );
            $stmt->execute([$fn, $ln, $email, $role, $avatar, $phone, $locId, $canViewLocTix, $id]);
        }
        logAudit('user.update', $id, 'user', "{$fn} {$ln} ({$email}), role={$role}");
        flash('success', 'User updated successfully.');
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate entry')
            ? 'A user with this email already exists.'
            : 'Database error: ' . $e->getMessage());
        flashInput($_POST);
        redirect("/admin/users/{$id}/edit");
    }
    redirect('/admin/users');
});

$router->post('/admin/users/{id}/delete', function (array $p) {
    Auth::requirePermission('users.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/users');
    }
    if ($id === Auth::id()) {
        flash('error', 'You cannot delete your own account.');
        redirect('/admin/users');
    }
    $db = Database::connect();

    $transferTo = !empty($_POST['transfer_to']) ? (int) $_POST['transfer_to'] : null;
    $deleteData = ($_POST['delete_data'] ?? '0') === '1';

    // Count records associated with this user
    $cStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_by = ?');
    $cStmt->execute([$id]);
    $createdCount = (int) $cStmt->fetchColumn();

    $aStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE assigned_to = ?');
    $aStmt->execute([$id]);
    $assignedCount = (int) $aStmt->fetchColumn();

    $kbStmt = $db->prepare('SELECT COUNT(*) FROM kb_articles WHERE created_by = ?');
    $kbStmt->execute([$id]);
    $kbCount = (int) $kbStmt->fetchColumn();

    $hasAssociated = $createdCount > 0 || $assignedCount > 0 || $kbCount > 0;

    // If there are associated records, either a transfer target or delete_data is required
    if ($hasAssociated && $transferTo === null && !$deleteData) {
        flash('error', 'This user has associated tickets or KB articles. Select a user to transfer them to, or choose to delete their records.');
        redirect("/admin/users/{$id}?delete=1");
    }

    if ($deleteData) {
        // Collect attachment files before deleting tickets
        if ($createdCount > 0) {
            $files = $db->prepare('SELECT stored_name FROM ticket_attachments WHERE ticket_id IN (SELECT id FROM tickets WHERE created_by = ?)');
            $files->execute([$id]);
            $filesToDelete = $files->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            $filesToDelete = [];
        }

        // Delete ticket templates authored by this user
        $db->prepare('DELETE FROM ticket_templates WHERE created_by = ?')->execute([$id]);

        // Delete KB article revisions edited by this user on articles they did NOT author
        // (revisions on their own articles cascade when those articles are deleted below)
        $db->prepare('DELETE FROM kb_article_revisions WHERE edited_by = ? AND article_id NOT IN (SELECT id FROM kb_articles WHERE created_by = ?)')->execute([$id, $id]);

        // Delete tickets created by this user (cascades child tables)
        $db->prepare('DELETE FROM tickets WHERE created_by = ?')->execute([$id]);

        // Unassign remaining tickets assigned to this user
        $db->prepare('UPDATE tickets SET assigned_to = NULL WHERE assigned_to = ?')->execute([$id]);

        // Delete KB articles authored by this user (cascades ratings and revisions)
        $db->prepare('DELETE FROM kb_articles WHERE created_by = ?')->execute([$id]);

        // Remove physical attachment files
        foreach ($filesToDelete as $storedName) {
            $path = ATTACHMENT_STORAGE_PATH . $storedName;
            if (file_exists($path)) unlink($path);
        }
    } elseif ($transferTo !== null) {
        // Validate and perform transfer
        $targetStmt = $db->prepare('SELECT id FROM users WHERE id = ? AND id != ?');
        $targetStmt->execute([$transferTo, $id]);
        if (!$targetStmt->fetch()) {
            flash('error', 'Transfer target user not found.');
            redirect("/admin/users/{$id}?delete=1");
        }
        if ($createdCount > 0) {
            $db->prepare('UPDATE tickets SET created_by = ? WHERE created_by = ?')->execute([$transferTo, $id]);
        }
        if ($assignedCount > 0) {
            $db->prepare('UPDATE tickets SET assigned_to = ? WHERE assigned_to = ?')->execute([$transferTo, $id]);
        }
        if ($kbCount > 0) {
            $db->prepare('UPDATE kb_articles SET created_by = ? WHERE created_by = ?')->execute([$transferTo, $id]);
        }
    }

    // Remove avatar file
    $avatar = $db->prepare('SELECT avatar FROM users WHERE id = ?');
    $avatar->execute([$id]);
    $file = $avatar->fetchColumn();
    if ($file) {
        $file = basename($file);
        if (file_exists(ROOT_DIR . '/public/uploads/avatars/' . $file)) {
            unlink(ROOT_DIR . '/public/uploads/avatars/' . $file);
        }
    }

    $nameStmt = $db->prepare('SELECT CONCAT(first_name, " ", last_name, " (", email, ")") FROM users WHERE id = ?');
    $nameStmt->execute([$id]);
    $deletedName = $nameStmt->fetchColumn() ?: "id={$id}";

    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    logAudit('user.delete', $id, 'user', $deletedName);

    if ($deleteData) {
        flash('success', "User \"{$deletedName}\" was deleted along with all their associated records.");
    } elseif ($transferTo !== null) {
        flash('success', "User \"{$deletedName}\" was deleted and their records were transferred successfully.");
    } else {
        flash('success', "User \"{$deletedName}\" was deleted.");
    }
    redirect('/admin/users');
});

$router->post('/admin/users/{id}/reset-2fa', function (array $p) {
    Auth::requirePermission('users.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/users/{$id}");
    }
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT CONCAT(first_name, " ", last_name, " (", email, ")") FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn() ?: "id={$id}";
    $db->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?')->execute([$id]);
    logAudit('user.2fa_reset_by_admin', $id, 'user', $name);
    flash('success', '2FA has been reset for this user.');
    redirect("/admin/users/{$id}");
});

/* ==================================================================
 * ADMIN – Merge Users
 * ================================================================== */

$router->get('/admin/users/merge', function () {
    Auth::requirePermission('users.manage');
    $suggestDeleteId = (int) ($_GET['suggest_delete'] ?? 0);
    $suggestUser     = null;
    if ($suggestDeleteId > 0) {
        $s = Database::connect()->prepare('SELECT id, first_name, last_name, email FROM users WHERE id = ?');
        $s->execute([$suggestDeleteId]);
        $suggestUser = $s->fetch() ?: null;
    }
    render('admin/users/merge', ['step' => 'search', 'keepUser' => null, 'deleteUser' => null, 'stats' => null, 'suggestUser' => $suggestUser]);
});

$router->post('/admin/users/merge', function () {
    Auth::requirePermission('users.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/users/merge');
    }

    $db     = Database::connect();
    $action = $_POST['action'] ?? 'preview';

    // ── Preview step ────────────────────────────────────────────────
    if ($action === 'preview') {
        $keepId   = (int) ($_POST['keep_id']   ?? 0);
        $deleteId = (int) ($_POST['delete_id'] ?? 0);

        if ($keepId === 0 || $deleteId === 0 || $keepId === $deleteId) {
            flash('error', 'Please select two different users.');
            redirect('/admin/users/merge');
        }

        $stmt = $db->prepare('SELECT id, first_name, last_name, email, role, created_at, location_id FROM users WHERE id = ?');

        $stmt->execute([$keepId]);
        $keepUser = $stmt->fetch();
        $stmt->execute([$deleteId]);
        $deleteUser = $stmt->fetch();

        if (!$keepUser || !$deleteUser) {
            flash('error', 'One or both users not found.');
            redirect('/admin/users/merge');
        }

        // Build stats for each user
        $stats = [];
        foreach ([['keep', $keepId], ['delete', $deleteId]] as [$role, $uid]) {
            $tc = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_by = ?');
            $tc->execute([$uid]);

            $ta = $db->prepare('SELECT COUNT(*) FROM tickets WHERE assigned_to = ?');
            $ta->execute([$uid]);

            $tl = $db->prepare('SELECT COUNT(*) FROM ticket_timeline WHERE user_id = ?');
            $tl->execute([$uid]);

            $stats[$role] = [
                'tickets_created'  => (int) $tc->fetchColumn(),
                'tickets_assigned' => (int) $ta->fetchColumn(),
                'comments'         => (int) $tl->fetchColumn(),
            ];
        }

        render('admin/users/merge', [
            'step'       => 'preview',
            'keepUser'   => $keepUser,
            'deleteUser' => $deleteUser,
            'stats'      => $stats,
        ]);
        return;
    }

    // ── Execute step ────────────────────────────────────────────────
    if ($action === 'execute') {
        $keepId   = (int) ($_POST['keep_id']   ?? 0);
        $deleteId = (int) ($_POST['delete_id'] ?? 0);

        if ($keepId === 0 || $deleteId === 0 || $keepId === $deleteId) {
            flash('error', 'Invalid merge request.');
            redirect('/admin/users/merge');
        }

        $stmt = $db->prepare('SELECT id, email FROM users WHERE id = ?');
        $stmt->execute([$keepId]);
        $keepUser = $stmt->fetch();
        $stmt->execute([$deleteId]);
        $deleteUser = $stmt->fetch();

        if (!$keepUser || !$deleteUser) {
            flash('error', 'One or both users not found.');
            redirect('/admin/users/merge');
        }

        // Prevent merging the currently logged-in admin into another account
        if ($deleteId === Auth::id()) {
            flash('error', 'You cannot merge your own account.');
            redirect('/admin/users/merge');
        }

        $db->beginTransaction();
        try {
            // ── Simple column reassignments ──────────────────────────
            $simpleUpdates = [
                ['tickets',              'created_by'],
                ['tickets',              'assigned_to'],
                ['ticket_timeline',      'user_id'],
                ['ticket_attachments',   'uploaded_by'],
                ['ticket_cc',            'added_by'],
                ['notifications',        'user_id'],
                ['notifications',        'mentioned_by'],
                ['audit_log',            'user_id'],
                ['csat_surveys',         'user_id'],
                ['canned_responses',     'user_id'],
                ['saved_filters',        'user_id'],
                ['api_tokens',           'user_id'],
                ['kb_articles',          'created_by'],
                ['kb_article_revisions', 'edited_by'],
                ['ticket_templates',     'created_by'],
            ];

            foreach ($simpleUpdates as [$table, $col]) {
                $db->prepare("UPDATE `{$table}` SET `{$col}` = ? WHERE `{$col}` = ?")->execute([$keepId, $deleteId]);
            }

            // ── Pivot tables — reassign non-duplicates then drop duplicates ──
            // ticket_cc — unique on (ticket_id, user_id)
            $db->prepare(
                'UPDATE ticket_cc SET user_id = ? WHERE user_id = ?
                 AND ticket_id NOT IN (SELECT ticket_id FROM (SELECT ticket_id FROM ticket_cc WHERE user_id = ?) AS t)'
            )->execute([$keepId, $deleteId, $keepId]);
            $db->prepare('DELETE FROM ticket_cc WHERE user_id = ?')->execute([$deleteId]);

            // ticket_watchers — unique on (ticket_id, user_id)
            $db->prepare(
                'UPDATE ticket_watchers SET user_id = ? WHERE user_id = ?
                 AND ticket_id NOT IN (SELECT ticket_id FROM (SELECT ticket_id FROM ticket_watchers WHERE user_id = ?) AS t)'
            )->execute([$keepId, $deleteId, $keepId]);
            $db->prepare('DELETE FROM ticket_watchers WHERE user_id = ?')->execute([$deleteId]);

            // group_user_map — unique on (group_id, user_id)
            $db->prepare(
                'UPDATE group_user_map SET user_id = ? WHERE user_id = ?
                 AND group_id NOT IN (SELECT group_id FROM (SELECT group_id FROM group_user_map WHERE user_id = ?) AS t)'
            )->execute([$keepId, $deleteId, $keepId]);
            $db->prepare('DELETE FROM group_user_map WHERE user_id = ?')->execute([$deleteId]);

            // ticket_presence — transient, just remove
            $db->prepare('DELETE FROM ticket_presence WHERE user_id = ?')->execute([$deleteId]);

            // ── Delete the source user (cascades any remaining FK refs) ──
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$deleteId]);

            $db->commit();

            logAudit('user.merged', $deleteId, 'user',
                "Merged {$deleteUser['email']} into {$keepUser['email']} (kept id={$keepId})");

        } catch (\Throwable $e) {
            $db->rollBack();
            flash('error', 'Merge failed: ' . $e->getMessage());
            redirect('/admin/users/merge');
        }

        flash('success', "Accounts merged. \"{$deleteUser['email']}\" has been removed and all data transferred to \"{$keepUser['email']}\".");
        redirect("/admin/users/{$keepId}");
    }

    flash('error', 'Unknown action.');
    redirect('/admin/users/merge');
});

/* ==================================================================
 * ADMIN – Location Management
 * ================================================================== */

$router->get('/admin/locations', function () {
    Auth::requirePermission('locations.manage');
    $locations = Database::connect()->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    render('admin/locations/index', [
        'locations'  => $locations,
        'tzMode'     => getSetting('location_timezone_mode', 'shared'),
        'sharedTz'   => getSetting('location_timezone_shared', 'UTC'),
        'timezones'  => commonTimezones(),
    ]);
});

$router->post('/admin/locations/timezone-settings', function () {
    Auth::requirePermission('locations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/locations');
    }
    $beforeMode = getSetting('location_timezone_mode', 'shared');
    $beforeTz   = getSetting('location_timezone_shared', 'UTC');
    $mode = ($_POST['location_timezone_mode'] ?? '') === 'per_location' ? 'per_location' : 'shared';
    $sharedTz = trim($_POST['location_timezone_shared'] ?? '');
    setSetting('location_timezone_mode', $mode);
    if ($sharedTz !== '') {
        setSetting('location_timezone_shared', $sharedTz);
    }
    logAuditChange(
        'location.timezone_settings_changed',
        null,
        null,
        ['mode' => $beforeMode, 'shared_timezone' => $beforeTz],
        ['mode' => $mode,       'shared_timezone' => $sharedTz !== '' ? $sharedTz : $beforeTz]
    );
    flash('success', 'Timezone settings saved.');
    redirect('/admin/locations');
});

$router->get('/admin/locations/create', function () {
    Auth::requirePermission('locations.manage');
    render('admin/locations/form', [
        'editing'   => null,
        'tzMode'    => getSetting('location_timezone_mode', 'shared'),
        'sharedTz'  => getSetting('location_timezone_shared', 'UTC'),
        'timezones' => commonTimezones(),
    ]);
});

$router->post('/admin/locations/create', function () {
    Auth::requirePermission('locations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/locations/create');
    }
    $name = trim($_POST['name'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $tz   = trim($_POST['timezone'] ?? '') ?: null;
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Location name is required.');
        redirect('/admin/locations/create');
    }
    $db = Database::connect();
    $db->prepare('INSERT INTO locations (name, address, description, timezone) VALUES (?, ?, ?, ?)')
        ->execute([$name, $addr, $desc, $tz]);
    $newId = (int) $db->lastInsertId();
    logAudit('location.created', $newId, 'location', 'name=' . $name);
    flash('success', 'Location created.');
    redirect('/admin/locations');
});

$router->get('/admin/locations/{id}/edit', function (array $p) {
    Auth::requirePermission('locations.manage');
    $stmt = Database::connect()->prepare('SELECT * FROM locations WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Location not found.');
        redirect('/admin/locations');
    }
    render('admin/locations/form', [
        'editing'   => $editing,
        'tzMode'    => getSetting('location_timezone_mode', 'shared'),
        'sharedTz'  => getSetting('location_timezone_shared', 'UTC'),
        'timezones' => commonTimezones(),
    ]);
});

$router->post('/admin/locations/{id}/edit', function (array $p) {
    Auth::requirePermission('locations.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/locations/{$id}/edit");
    }
    $name = trim($_POST['name'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $tz   = trim($_POST['timezone'] ?? '') ?: null;
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Location name is required.');
        redirect("/admin/locations/{$id}/edit");
    }
    $db = Database::connect();
    $beforeStmt = $db->prepare('SELECT name, address, description, timezone FROM locations WHERE id = ?');
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    $db->prepare('UPDATE locations SET name=?, address=?, description=?, timezone=? WHERE id=?')
        ->execute([$name, $addr, $desc, $tz, $id]);
    logAuditChange(
        'location.updated',
        $id,
        'location',
        $before,
        ['name' => $name, 'address' => $addr, 'description' => $desc, 'timezone' => $tz]
    );
    flash('success', 'Location updated.');
    redirect('/admin/locations');
});

$router->get('/admin/locations/{id}/delete-confirm', function (array $p) {
    Auth::requirePermission('locations.manage');
    $db  = Database::connect();
    $id  = (int) $p['id'];

    $locStmt = $db->prepare('SELECT * FROM locations WHERE id = ?');
    $locStmt->execute([$id]);
    $location = $locStmt->fetch();
    if (!$location) {
        flash('error', 'Location not found.');
        redirect('/admin/locations');
    }

    $countStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE location_id = ?');
    $countStmt->execute([$id]);
    $ticketCount = (int) $countStmt->fetchColumn();

    $tickets = [];
    if ($ticketCount > 0) {
        $tStmt = $db->prepare('SELECT t.id, t.subject, t.status, u.first_name, u.last_name FROM tickets t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.location_id = ? ORDER BY t.id DESC');
        $tStmt->execute([$id]);
        $tickets = $tStmt->fetchAll();
    }

    $otherLocStmt = $db->prepare('SELECT id, name FROM locations WHERE id != ? ORDER BY name');
    $otherLocStmt->execute([$id]);
    $otherLocations = $otherLocStmt->fetchAll();

    render('admin/locations/delete-confirm', [
        'location'       => $location,
        'ticketCount'    => $ticketCount,
        'tickets'        => $tickets,
        'otherLocations' => $otherLocations,
    ]);
});

$router->post('/admin/locations/{id}/delete', function (array $p) {
    Auth::requirePermission('locations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/locations');
    }
    $db     = Database::connect();
    $id     = (int) $p['id'];
    $action = $_POST['action'] ?? '';

    $locStmt = $db->prepare('SELECT name FROM locations WHERE id = ?');
    $locStmt->execute([$id]);
    $locName = $locStmt->fetchColumn();
    if (!$locName) {
        flash('error', 'Location not found.');
        redirect('/admin/locations');
    }

    // Check if any tickets use this location
    $countStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE location_id = ?');
    $countStmt->execute([$id]);
    $ticketCount = (int) $countStmt->fetchColumn();

    if ($ticketCount > 0 && $action === '') {
        redirect("/admin/locations/{$id}/delete-confirm");
    }

    if ($action === 'reassign') {
        $newLocId = (int) ($_POST['new_location_id'] ?? 0);
        $checkStmt = $db->prepare('SELECT id FROM locations WHERE id = ? AND id != ?');
        $checkStmt->execute([$newLocId, $id]);
        if (!$checkStmt->fetch()) {
            flash('error', 'Please select a valid replacement location.');
            redirect("/admin/locations/{$id}/delete-confirm");
        }
        $db->prepare('UPDATE tickets SET location_id = ? WHERE location_id = ?')->execute([$newLocId, $id]);
    } elseif ($action === 'clear') {
        $db->prepare('UPDATE tickets SET location_id = NULL WHERE location_id = ?')->execute([$id]);
    }

    $db->prepare('DELETE FROM locations WHERE id = ?')->execute([$id]);
    logAudit(
        'location.deleted',
        $id,
        'location',
        'name=' . $locName . '; tickets_affected=' . $ticketCount
            . ($action !== '' ? '; reassign_action=' . $action : '')
    );
    flash('success', "Location \"{$locName}\" deleted.");
    redirect('/admin/locations');
});

/* ==================================================================
 * ADMIN – Priority Management
 * ================================================================== */

$router->get('/admin/priorities', function () {
    Auth::requirePermission('priorities.manage');
    $priorities = Database::connect()->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    render('admin/priorities/index', ['priorities' => $priorities]);
});

$router->post('/admin/priorities/reorder', function () {
    Auth::requirePermission('priorities.manage');
    handleSortableReorder('ticket_priorities');
});

$router->get('/admin/priorities/create', function () {
    Auth::requirePermission('priorities.manage');
    render('admin/priorities/form', ['editing' => null]);
});

$router->post('/admin/priorities/create', function () {
    Auth::requirePermission('priorities.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/priorities/create');
    }
    $name  = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#6c757d');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Priority name is required.');
        redirect('/admin/priorities/create');
    }
    $db = Database::connect();
    $db->prepare('INSERT INTO ticket_priorities (name, color, sort_order) VALUES (?, ?, ?)')
        ->execute([$name, $color, $order]);
    $newId = (int) $db->lastInsertId();
    logAudit('priority.created', $newId, 'priority', 'name=' . $name . '; color=' . $color);
    flash('success', 'Priority created.');
    redirect('/admin/priorities');
});

$router->get('/admin/priorities/{id}/edit', function (array $p) {
    Auth::requirePermission('priorities.manage');
    $stmt = Database::connect()->prepare('SELECT * FROM ticket_priorities WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Priority not found.');
        redirect('/admin/priorities');
    }
    render('admin/priorities/form', ['editing' => $editing]);
});

$router->post('/admin/priorities/{id}/edit', function (array $p) {
    Auth::requirePermission('priorities.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/priorities/{$id}/edit");
    }
    $name  = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '#6c757d');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Priority name is required.');
        redirect("/admin/priorities/{$id}/edit");
    }
    $db = Database::connect();
    $beforeStmt = $db->prepare('SELECT name, color, sort_order FROM ticket_priorities WHERE id = ?');
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    $db->prepare('UPDATE ticket_priorities SET name=?, color=?, sort_order=? WHERE id=?')
        ->execute([$name, $color, $order, $id]);
    logAuditChange(
        'priority.updated',
        $id,
        'priority',
        $before,
        ['name' => $name, 'color' => $color, 'sort_order' => $order]
    );
    flash('success', 'Priority updated.');
    redirect('/admin/priorities');
});

$router->post('/admin/priorities/{id}/delete', function (array $p) {
    Auth::requirePermission('priorities.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/priorities');
    }
    $id   = (int) $p['id'];
    $db   = Database::connect();
    $name = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
    $name->execute([$id]);
    $pname = (string) ($name->fetchColumn() ?: '');
    $db->prepare('DELETE FROM ticket_priorities WHERE id = ?')->execute([$id]);
    logAudit('priority.deleted', $id, 'priority', 'name=' . $pname);
    flash('success', 'Priority deleted.');
    redirect('/admin/priorities');
});

/* ==================================================================
 * ADMIN – Ticket Type Management
 * ================================================================== */

$router->get('/admin/types', function () {
    Auth::requirePermission('workflows.manage');
    $types = Database::connect()->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    render('admin/types/index', ['types' => $types]);
});

$router->post('/admin/types/reorder', function () {
    Auth::requirePermission('workflows.manage');
    handleSortableReorder('ticket_types');
});

$router->get('/admin/types/matrix', function () {
    Auth::requirePermission('workflows.manage');
    $types = Database::connect()->query(
        'SELECT tt.*, g.name AS group_name
         FROM ticket_types tt
         LEFT JOIN `groups` g ON g.id = tt.group_id
         ORDER BY tt.sort_order, tt.name'
    )->fetchAll();
    render('admin/types/matrix', ['types' => $types]);
});

$router->get('/admin/types/create', function () {
    Auth::requirePermission('workflows.manage');
    $db     = Database::connect();
    $groups = $db->query('SELECT id, name FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $skills = $db->query('SELECT id, name FROM agent_skills ORDER BY sort_order, name')->fetchAll();
    render('admin/types/form', ['editing' => null, 'groups' => $groups, 'skills' => $skills, 'requiredSkillIds' => []]);
});

$router->post('/admin/types/create', function () {
    Auth::requirePermission('workflows.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/types/create');
    }
    $name    = trim($_POST['name'] ?? '');
    $color   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#6c757d';
    $order   = (int) ($_POST['sort_order'] ?? 0);
    $groupId        = !empty($_POST['group_id']) ? (int) $_POST['group_id'] : null;
    $isConfidential = !empty($_POST['is_confidential']) && $groupId ? 1 : 0;
    $aiRouteGroup   = !empty($_POST['ai_route_group']) && !$isConfidential ? 1 : 0;
    $aiDupCheck     = !empty($_POST['ai_dup_check_enabled']) && !$isConfidential ? 1 : 0;
    $aiDupThreshold = isset($_POST['ai_dup_threshold']) ? (float) $_POST['ai_dup_threshold'] : 0.75;
    if ($aiDupThreshold < 0.50) { $aiDupThreshold = 0.50; }
    if ($aiDupThreshold > 0.99) { $aiDupThreshold = 0.99; }
    $showToLocVis   = !empty($_POST['show_to_location_visibility']) ? 1 : 0;
    $staleRaw       = trim((string) ($_POST['stale_threshold_hours'] ?? ''));
    $staleHours     = $staleRaw === '' ? null : max(0, (int) $staleRaw);
    $skillIds       = array_filter(array_map('intval', (array) ($_POST['required_skills'] ?? [])));
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Type name is required.');
        redirect('/admin/types/create');
    }
    $db = Database::connect();
    $db->prepare('INSERT INTO ticket_types (name, color, group_id, is_confidential, ai_route_group, ai_dup_check_enabled, ai_dup_threshold, show_to_location_visibility, sort_order, stale_threshold_hours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $color, $groupId, $isConfidential, $aiRouteGroup, $aiDupCheck, $aiDupThreshold, $showToLocVis, $order, $staleHours]);
    $typeId = (int) $db->lastInsertId();
    if ($skillIds) {
        $stmt = $db->prepare('INSERT IGNORE INTO ticket_type_skill_map (ticket_type_id, skill_id) VALUES (?, ?)');
        foreach ($skillIds as $sid) {
            $stmt->execute([$typeId, $sid]);
        }
    }
    logAudit(
        'ticket_type.created',
        $typeId,
        'ticket_type',
        'name=' . $name
            . '; group_id=' . ($groupId ?? 'null')
            . '; is_confidential=' . $isConfidential
            . '; required_skills=' . count($skillIds)
    );
    // Seed the form-builder layout so this new type has the standard set of
    // fields out of the box — admin can edit them in the form builder.
    seedDefaultLayoutForType($db, $typeId);
    flash('success', 'Ticket type created.');
    redirect('/admin/types/' . $typeId . '/edit');
});

$router->get('/admin/types/{id}/edit', function (array $p) {
    Auth::requirePermission('workflows.manage');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM ticket_types WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Ticket type not found.');
        redirect('/admin/types');
    }
    $groups = $db->query('SELECT id, name FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $skills = $db->query('SELECT id, name FROM agent_skills ORDER BY sort_order, name')->fetchAll();
    $rsStmt = $db->prepare('SELECT skill_id FROM ticket_type_skill_map WHERE ticket_type_id = ?');
    $rsStmt->execute([(int) $p['id']]);
    $requiredSkillIds = array_map('intval', $rsStmt->fetchAll(PDO::FETCH_COLUMN));
    render('admin/types/form', ['editing' => $editing, 'groups' => $groups, 'skills' => $skills, 'requiredSkillIds' => $requiredSkillIds]);
});

$router->post('/admin/types/{id}/edit', function (array $p) {
    Auth::requirePermission('workflows.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/types/{$id}/edit");
    }
    $name    = trim($_POST['name'] ?? '');
    $color   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#6c757d';
    $order   = (int) ($_POST['sort_order'] ?? 0);
    $groupId        = !empty($_POST['group_id']) ? (int) $_POST['group_id'] : null;
    $isConfidential = !empty($_POST['is_confidential']) && $groupId ? 1 : 0;
    $aiRouteGroup   = !empty($_POST['ai_route_group']) && !$isConfidential ? 1 : 0;
    $aiDupCheck     = !empty($_POST['ai_dup_check_enabled']) && !$isConfidential ? 1 : 0;
    $aiDupThreshold = isset($_POST['ai_dup_threshold']) ? (float) $_POST['ai_dup_threshold'] : 0.75;
    if ($aiDupThreshold < 0.50) { $aiDupThreshold = 0.50; }
    if ($aiDupThreshold > 0.99) { $aiDupThreshold = 0.99; }
    $showToLocVis   = !empty($_POST['show_to_location_visibility']) ? 1 : 0;
    $staleRaw       = trim((string) ($_POST['stale_threshold_hours'] ?? ''));
    $staleHours     = $staleRaw === '' ? null : max(0, (int) $staleRaw);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Type name is required.');
        redirect("/admin/types/{$id}/edit");
    }

    $db = Database::connect();

    // Snapshot prior confidential state
    $priorStmt = $db->prepare('SELECT name, is_confidential, group_id FROM ticket_types WHERE id = ?');
    $priorStmt->execute([$id]);
    $priorType = $priorStmt->fetch();
    $wasConfidential = $priorType ? (int) $priorType['is_confidential'] : 0;
    $priorGroupId    = $priorType ? (int) $priorType['group_id'] : 0;

    // Re-auth gate: removing confidential flag requires password verification
    if ($wasConfidential && !$isConfidential) {
        if (empty($_POST['_confidential_reauth'])) {
            $hiddenFields = [
                '_token'    => $_POST['_token'] ?? '',
                'name'      => $name,
                'color'     => $color,
                'sort_order' => (string) $order,
                'group_id'  => $groupId ? (string) $groupId : '',
                'stale_threshold_hours' => $staleHours === null ? '' : (string) $staleHours,
                'show_to_location_visibility' => $showToLocVis ? '1' : '',
                'ai_route_group' => $aiRouteGroup ? '1' : '',
                'ai_dup_check_enabled' => $aiDupCheck ? '1' : '',
                'ai_dup_threshold' => (string) $aiDupThreshold,
                // is_confidential intentionally omitted (unchecked = removal)
            ];
            logAudit(
                'confidential_flag_removal_attempted',
                $id,
                'ticket_type',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') attempted to remove the confidential flag from ticket type "' . $priorType['name'] . '" (ID: ' . $id . ') — re-authentication required'
            );
            render('admin/confidential-reauth', [
                'action'            => 'remove_flag',
                'targetType'        => 'ticket type',
                'targetName'        => $priorType['name'],
                'formAction'        => "/admin/types/{$id}/edit",
                'cancelUrl'         => "/admin/types/{$id}/edit",
                'hiddenFields'      => $hiddenFields,
                'hiddenArrayFields' => [],
            ]);
            return;
        }

        // Verify password
        $pwStmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $pwStmt->execute([Auth::id()]);
        $pwUser = $pwStmt->fetch();
        if (!$pwUser || !password_verify($_POST['reauth_password'] ?? '', $pwUser['password'])) {
            logAudit(
                'confidential_flag_removal_auth_failed',
                $id,
                'ticket_type',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') failed re-authentication when attempting to remove confidential flag from ticket type "' . $priorType['name'] . '"'
            );
            flash('error', 'Incorrect password. The confidential flag was not removed.');
            redirect("/admin/types/{$id}/edit");
        }
    }

    // Capture the full prior row so the diff covers all editable fields,
    // not just the confidential flag we already snapshotted above.
    $fullPriorStmt = $db->prepare(
        'SELECT name, color, group_id, is_confidential, ai_route_group, ai_dup_check_enabled,
                ai_dup_threshold, show_to_location_visibility, sort_order, stale_threshold_hours
         FROM ticket_types WHERE id = ?'
    );
    $fullPriorStmt->execute([$id]);
    $fullPrior = $fullPriorStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

    $db->prepare('UPDATE ticket_types SET name=?, color=?, group_id=?, is_confidential=?, ai_route_group=?, ai_dup_check_enabled=?, ai_dup_threshold=?, show_to_location_visibility=?, sort_order=?, stale_threshold_hours=? WHERE id=?')
        ->execute([$name, $color, $groupId, $isConfidential, $aiRouteGroup, $aiDupCheck, $aiDupThreshold, $showToLocVis, $order, $staleHours, $id]);

    // Required skills (used by Skill-Based group auto-assignment)
    $skillIds = array_filter(array_map('intval', (array) ($_POST['required_skills'] ?? [])));
    $db->prepare('DELETE FROM ticket_type_skill_map WHERE ticket_type_id = ?')->execute([$id]);
    if ($skillIds) {
        $sStmt = $db->prepare('INSERT IGNORE INTO ticket_type_skill_map (ticket_type_id, skill_id) VALUES (?, ?)');
        foreach ($skillIds as $sid) {
            $sStmt->execute([$id, $sid]);
        }
    }

    logAuditChange(
        'ticket_type.updated',
        $id,
        'ticket_type',
        $fullPrior,
        [
            'name'                        => $name,
            'color'                       => $color,
            'group_id'                    => $groupId,
            'is_confidential'             => $isConfidential,
            'ai_route_group'              => $aiRouteGroup,
            'ai_dup_check_enabled'        => $aiDupCheck,
            'ai_dup_threshold'            => $aiDupThreshold,
            'show_to_location_visibility' => $showToLocVis,
            'sort_order'                  => $order,
            'stale_threshold_hours'       => $staleHours,
        ]
    );

    // Confidential flag removal: audit log + notify all group members
    if ($wasConfidential && !$isConfidential && $priorGroupId) {
        logAudit(
            'confidential_flag_removed',
            $id,
            'ticket_type',
            Auth::fullName() . ' (ID: ' . Auth::id() . ') removed the confidential flag from ticket type "' . $name . '" (ID: ' . $id . ') after re-authentication'
        );
        $appUrl = env('APP_URL', 'http://localhost:8000');
        notifyConfidentialFlagRemoved($db, 'ticket type', $name, $priorGroupId, $appUrl . '/admin/types/' . $id . '/edit');
    }

    flash('success', 'Ticket type updated.');
    redirect('/admin/types');
});

$router->get('/admin/types/{id}/delete-confirm', function (array $p) {
    Auth::requirePermission('workflows.manage');
    $db  = Database::connect();
    $id  = (int) $p['id'];

    $typeStmt = $db->prepare('SELECT * FROM ticket_types WHERE id = ?');
    $typeStmt->execute([$id]);
    $type = $typeStmt->fetch();
    if (!$type) {
        flash('error', 'Ticket type not found.');
        redirect('/admin/types');
    }

    $countStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE type_id = ?');
    $countStmt->execute([$id]);
    $ticketCount = (int) $countStmt->fetchColumn();

    $tickets = [];
    if ($ticketCount > 0) {
        $tStmt = $db->prepare('SELECT t.id, t.subject, t.status, u.first_name, u.last_name FROM tickets t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.type_id = ? ORDER BY t.id DESC');
        $tStmt->execute([$id]);
        $tickets = $tStmt->fetchAll();
    }

    $otherTypesStmt = $db->prepare('SELECT id, name FROM ticket_types WHERE id != ? ORDER BY sort_order, name');
    $otherTypesStmt->execute([$id]);
    $otherTypes = $otherTypesStmt->fetchAll();

    render('admin/types/delete-confirm', [
        'type'        => $type,
        'ticketCount' => $ticketCount,
        'tickets'     => $tickets,
        'otherTypes'  => $otherTypes,
    ]);
});

$router->post('/admin/types/{id}/delete', function (array $p) {
    Auth::requirePermission('workflows.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/types');
    }
    $db     = Database::connect();
    $id     = (int) $p['id'];
    $action = $_POST['action'] ?? '';

    $typeStmt = $db->prepare('SELECT name, is_confidential, group_id FROM ticket_types WHERE id = ?');
    $typeStmt->execute([$id]);
    $typeRow = $typeStmt->fetch();
    if (!$typeRow) {
        flash('error', 'Ticket type not found.');
        redirect('/admin/types');
    }
    $typeName        = $typeRow['name'];
    $wasConfidential = (int) $typeRow['is_confidential'];
    $typeGroupId     = (int) $typeRow['group_id'];

    // Re-auth gate: deleting a confidential ticket type requires password verification
    if ($wasConfidential && $typeGroupId) {
        if (empty($_POST['_confidential_reauth'])) {
            $hiddenFields = ['_token' => $_POST['_token'] ?? '', 'action' => $action];
            if ($action === 'reassign') {
                $hiddenFields['new_type_id'] = $_POST['new_type_id'] ?? '';
            }
            logAudit(
                'confidential_delete_attempted',
                $id,
                'ticket_type',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') attempted to delete confidential ticket type "' . $typeName . '" (ID: ' . $id . ') — re-authentication required'
            );
            render('admin/confidential-reauth', [
                'action'            => 'delete',
                'targetType'        => 'ticket type',
                'targetName'        => $typeName,
                'formAction'        => "/admin/types/{$id}/delete",
                'cancelUrl'         => "/admin/types/{$id}/delete-confirm",
                'hiddenFields'      => $hiddenFields,
                'hiddenArrayFields' => [],
            ]);
            return;
        }

        // Verify password
        $pwStmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $pwStmt->execute([Auth::id()]);
        $pwUser = $pwStmt->fetch();
        if (!$pwUser || !password_verify($_POST['reauth_password'] ?? '', $pwUser['password'])) {
            logAudit(
                'confidential_delete_auth_failed',
                $id,
                'ticket_type',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') failed re-authentication when attempting to delete confidential ticket type "' . $typeName . '"'
            );
            flash('error', 'Incorrect password. The confidential ticket type was not deleted.');
            redirect("/admin/types/{$id}/delete-confirm");
        }

        // Snapshot group members BEFORE deletion for email alerts
        $mStmt = $db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.role
             FROM group_user_map gum
             JOIN users u ON u.id = gum.user_id
             WHERE gum.group_id = ?'
        );
        $mStmt->execute([$typeGroupId]);
        $members = $mStmt->fetchAll();
    }

    // Check if any tickets use this type
    $countStmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE type_id = ?');
    $countStmt->execute([$id]);
    $ticketCount = (int) $countStmt->fetchColumn();

    if ($ticketCount > 0 && $action === '') {
        // No action chosen yet — redirect to confirmation page
        redirect("/admin/types/{$id}/delete-confirm");
    }

    if ($action === 'delete_tickets') {
        $db->prepare('DELETE FROM tickets WHERE type_id = ?')->execute([$id]);
    } elseif ($action === 'reassign') {
        $newTypeId = (int) ($_POST['new_type_id'] ?? 0);
        $checkStmt = $db->prepare('SELECT id FROM ticket_types WHERE id = ? AND id != ?');
        $checkStmt->execute([$newTypeId, $id]);
        if (!$checkStmt->fetch()) {
            flash('error', 'Please select a valid replacement ticket type.');
            redirect("/admin/types/{$id}/delete-confirm");
        }
        $db->prepare('UPDATE tickets SET type_id = ? WHERE type_id = ?')->execute([$newTypeId, $id]);
    }

    // sla_policies.type_id uses an ON DELETE RESTRICT foreign key (it cannot
    // cascade — MySQL forbids a cascading FK on the base column of the
    // `type_id_norm` generated column), so clear per-type SLA policies by hand
    // before deleting the type, or the DELETE below would be rejected.
    $db->prepare('DELETE FROM sla_policies WHERE type_id = ?')->execute([$id]);

    $db->prepare('DELETE FROM ticket_types WHERE id = ?')->execute([$id]);

    // Audit log + email alert for confidential type deletion
    if ($wasConfidential && $typeGroupId && !empty($members)) {
        logAudit(
            'confidential_entity_deleted',
            $id,
            'ticket_type',
            Auth::fullName() . ' (ID: ' . Auth::id() . ') deleted confidential ticket type "' . $typeName . '" (ID: ' . $id . ') after re-authentication'
        );
        notifyConfidentialEntityDeleted($db, 'ticket type', $typeName, $members);
    } else {
        // Non-confidential deletes don't get the dedicated confidential alert,
        // so add the standard CRUD entry here.
        logAudit(
            'ticket_type.deleted',
            $id,
            'ticket_type',
            'name=' . $typeName . '; tickets_affected=' . $ticketCount
                . ($action !== '' ? '; ticket_action=' . $action : '')
        );
    }

    flash('success', "Ticket type \"{$typeName}\" deleted.");
    redirect('/admin/types');
});

/* ==================================================================
 * ADMIN – Canned Responses (global, admin-managed)
 * ================================================================== */

$router->get('/admin/settings/canned-responses', function () {
    Auth::requirePermission('settings.manage');
    $db = Database::connect();
    $responses = $db->query(
        'SELECT * FROM canned_responses WHERE user_id IS NULL ORDER BY sort_order, title'
    )->fetchAll();
    render('admin/settings/canned-responses/index', ['responses' => $responses]);
});

$router->post('/admin/settings/canned-responses/reorder', function () {
    Auth::requirePermission('settings.manage');
    handleSortableReorder('canned_responses');
});

$router->get('/admin/settings/canned-responses/create', function () {
    Auth::requirePermission('settings.manage');
    render('admin/settings/canned-responses/form', ['editing' => null]);
});

$router->post('/admin/settings/canned-responses/create', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/canned-responses/create');
    }
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($title === '' || $body === '') {
        flashInput($_POST);
        flash('error', 'Title and body are required.');
        redirect('/admin/settings/canned-responses/create');
    }
    $db = Database::connect();
    $db->prepare(
        'INSERT INTO canned_responses (user_id, title, body, sort_order) VALUES (NULL, ?, ?, ?)'
    )->execute([$title, $body, $order]);
    $newId = (int) $db->lastInsertId();
    logAudit('canned_response.created', $newId, 'canned_response', 'title=' . $title);
    flash('success', 'Canned response created.');
    redirect('/admin/settings/canned-responses');
});

$router->get('/admin/settings/canned-responses/{id}/edit', function (array $p) {
    Auth::requirePermission('settings.manage');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM canned_responses WHERE id = ? AND user_id IS NULL');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Canned response not found.');
        redirect('/admin/settings/canned-responses');
    }
    render('admin/settings/canned-responses/form', ['editing' => $editing]);
});

$router->post('/admin/settings/canned-responses/{id}/edit', function (array $p) {
    Auth::requirePermission('settings.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/settings/canned-responses/{$id}/edit");
    }
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($title === '' || $body === '') {
        flashInput($_POST);
        flash('error', 'Title and body are required.');
        redirect("/admin/settings/canned-responses/{$id}/edit");
    }
    $db = Database::connect();
    $beforeStmt = $db->prepare('SELECT title, sort_order FROM canned_responses WHERE id = ? AND user_id IS NULL');
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    $db->prepare(
        'UPDATE canned_responses SET title = ?, body = ?, sort_order = ? WHERE id = ? AND user_id IS NULL'
    )->execute([$title, $body, $order, $id]);
    logAuditChange(
        'canned_response.updated',
        $id,
        'canned_response',
        $before,
        ['title' => $title, 'sort_order' => $order]
    );
    flash('success', 'Canned response updated.');
    redirect('/admin/settings/canned-responses');
});

$router->post('/admin/settings/canned-responses/{id}/delete', function (array $p) {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/canned-responses');
    }
    $id    = (int) $p['id'];
    $db    = Database::connect();
    $titleStmt = $db->prepare('SELECT title FROM canned_responses WHERE id = ? AND user_id IS NULL');
    $titleStmt->execute([$id]);
    $title = (string) ($titleStmt->fetchColumn() ?: '');
    $db->prepare(
        'DELETE FROM canned_responses WHERE id = ? AND user_id IS NULL'
    )->execute([$id]);
    logAudit('canned_response.deleted', $id, 'canned_response', 'title=' . $title);
    flash('success', 'Canned response deleted.');
    redirect('/admin/settings/canned-responses');
});

/* ==================================================================
 * ADMIN – Group Management
 * ================================================================== */

$router->get('/admin/groups', function () {
    Auth::requirePermission('groups.manage');
    $groups = Database::connect()->query(
        'SELECT g.*, COUNT(gum.user_id) AS member_count
         FROM `groups` g
         LEFT JOIN group_user_map gum ON g.id = gum.group_id
         GROUP BY g.id
         ORDER BY g.sort_order, g.name'
    )->fetchAll();
    render('admin/groups/index', ['groups' => $groups]);
});

$router->post('/admin/groups/reorder', function () {
    Auth::requirePermission('groups.manage');
    handleSortableReorder('groups');
});

$router->get('/admin/groups/create', function () {
    Auth::requirePermission('groups.manage');
    $users = Database::connect()->query(
        "SELECT id, first_name, last_name, role FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();
    render('admin/groups/form', ['editing' => null, 'users' => $users, 'memberIds' => [], 'managerIds' => []]);
});

$router->post('/admin/groups/create', function () {
    Auth::requirePermission('groups.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/groups/create');
    }

    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);

    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Group name is required.');
        redirect('/admin/groups/create');
    }

    // Treat a checkbox sent with an explicit value of "0"/"" as off, not on.
    // Browsers omit unchecked boxes (so `isset` works for them), but API
    // clients and CSV-driven scripts pass `is_confidential=0` literally —
    // `!empty` is the only form that handles both.
    $notifyNew      = !empty($_POST['notify_new_ticket']) ? 1 : 0;
    $isConfidential = !empty($_POST['is_confidential']) ? 1 : 0;

    $allowedStrategies = ['manual','round_robin','load_based','skill_based','first_available'];
    $assignStrategy = in_array($_POST['assign_strategy'] ?? '', $allowedStrategies, true) ? $_POST['assign_strategy'] : 'manual';
    $allowedFallbacks = ['round_robin','load_based','none'];
    $assignFallback = in_array($_POST['assign_fallback'] ?? '', $allowedFallbacks, true) ? $_POST['assign_fallback'] : 'load_based';

    $db = Database::connect();
    $db->prepare('INSERT INTO `groups` (name, description, sort_order, notify_new_ticket, is_confidential, assign_strategy, assign_fallback) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $desc, $order, $notifyNew, $isConfidential, $assignStrategy, $assignFallback]);
    $groupId = (int) $db->lastInsertId();

    // Assign members
    $userIds    = isset($_POST['members'])  && is_array($_POST['members'])  ? array_map('intval', $_POST['members'])  : [];
    $managerSet = isset($_POST['managers']) && is_array($_POST['managers']) ? array_flip(array_map('intval', $_POST['managers'])) : [];
    if (!empty($userIds)) {
        $stmt = $db->prepare('INSERT INTO group_user_map (group_id, user_id, is_manager) VALUES (?, ?, ?)');
        foreach ($userIds as $uid) {
            $stmt->execute([$groupId, $uid, isset($managerSet[$uid]) ? 1 : 0]);
        }
    }

    // No notification on create — there are no prior members to alert.
    // Per spec, alerts only fire after the first person is in the group.

    logAudit(
        'group.created',
        $groupId,
        'group',
        'name=' . $name
            . '; is_confidential=' . $isConfidential
            . '; assign_strategy=' . $assignStrategy
            . '; members=' . count($userIds)
            . '; managers=' . count($managerSet)
    );

    flash('success', 'Group created.');
    redirect('/admin/groups');
});

$router->get('/admin/groups/{id}/edit', function (array $p) {
    Auth::requirePermission('groups.manage');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM `groups` WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Group not found.');
        redirect('/admin/groups');
    }

    $users = $db->query(
        "SELECT id, first_name, last_name, role FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();

    $memberStmt = $db->prepare('SELECT user_id, is_manager FROM group_user_map WHERE group_id = ?');
    $memberStmt->execute([$editing['id']]);
    $memberIds  = [];
    $managerIds = [];
    foreach ($memberStmt->fetchAll() as $row) {
        $uid = (int) $row['user_id'];
        $memberIds[] = $uid;
        if ((int) $row['is_manager'] === 1) {
            $managerIds[] = $uid;
        }
    }

    render('admin/groups/form', ['editing' => $editing, 'users' => $users, 'memberIds' => $memberIds, 'managerIds' => $managerIds]);
});

$router->post('/admin/groups/{id}/edit', function (array $p) {
    Auth::requirePermission('groups.manage');
    $id = (int) $p['id'];

    // Log CSRF failures on confidential groups too — this could indicate an attack
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        $cdb = Database::connect();
        $cs  = $cdb->prepare('SELECT name, is_confidential FROM `groups` WHERE id = ?');
        $cs->execute([$id]);
        $cg = $cs->fetch();
        if ($cg && !empty($cg['is_confidential'])) {
            logAudit(
                'confidential_group_csrf_failure',
                $id,
                'group',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') submitted an edit to confidential group "' . $cg['name'] . '" with an invalid CSRF token'
            );
        }
        flash('error', 'Invalid request.');
        redirect("/admin/groups/{$id}/edit");
    }

    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);

    $notifyNew      = !empty($_POST['notify_new_ticket']) ? 1 : 0;
    $isConfidential = !empty($_POST['is_confidential']) ? 1 : 0;
    $userIds        = isset($_POST['members']) && is_array($_POST['members']) ? array_map('intval', $_POST['members']) : [];
    $managerSet     = isset($_POST['managers']) && is_array($_POST['managers']) ? array_flip(array_map('intval', $_POST['managers'])) : [];

    $db = Database::connect();

    // Snapshot prior state BEFORE any writes:
    //   1. existing member IDs (needed to compute the diff for alerts)
    //   2. prior is_confidential (so we can audit-log even if the user is un-confidentialing
    //      the group in the same edit)
    $priorStmt = $db->prepare('SELECT name, is_confidential FROM `groups` WHERE id = ?');
    $priorStmt->execute([$id]);
    $priorGroup = $priorStmt->fetch();
    $wasConfidential = $priorGroup ? (int) $priorGroup['is_confidential'] : 0;

    // Re-auth gate: removing confidential flag requires password verification
    if ($wasConfidential && !$isConfidential) {
        if (empty($_POST['_confidential_reauth'])) {
            // Show re-auth form — preserve all form data as hidden fields
            $hiddenFields = [
                '_token'            => $_POST['_token'] ?? '',
                'name'              => $name,
                'description'       => $desc,
                'sort_order'        => (string) $order,
                'notify_new_ticket' => $notifyNew ? '1' : '',
                // is_confidential intentionally omitted (unchecked = removal)
            ];
            $hiddenArrayFields = ['members' => array_map('strval', $userIds)];
            logAudit(
                'confidential_flag_removal_attempted',
                $id,
                'group',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') attempted to remove the confidential flag from group "' . $priorGroup['name'] . '" (ID: ' . $id . ') — re-authentication required'
            );
            render('admin/confidential-reauth', [
                'action'            => 'remove_flag',
                'targetType'        => 'group',
                'targetName'        => $priorGroup['name'],
                'formAction'        => "/admin/groups/{$id}/edit",
                'cancelUrl'         => "/admin/groups/{$id}/edit",
                'hiddenFields'      => $hiddenFields,
                'hiddenArrayFields' => $hiddenArrayFields,
            ]);
            return;
        }

        // Verify password
        $pwStmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $pwStmt->execute([Auth::id()]);
        $pwUser = $pwStmt->fetch();
        if (!$pwUser || !password_verify($_POST['reauth_password'] ?? '', $pwUser['password'])) {
            logAudit(
                'confidential_flag_removal_auth_failed',
                $id,
                'group',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') failed re-authentication when attempting to remove confidential flag from group "' . $priorGroup['name'] . '"'
            );
            flash('error', 'Incorrect password. The confidential flag was not removed.');
            redirect("/admin/groups/{$id}/edit");
        }
    }

    $existingStmt = $db->prepare('SELECT user_id FROM group_user_map WHERE group_id = ?');
    $existingStmt->execute([$id]);
    $existingMemberIds = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));

    $addedIds   = array_values(array_diff($userIds, $existingMemberIds));
    $removedIds = array_values(array_diff($existingMemberIds, $userIds));

    // Audit: log the ATTEMPT to add members to a confidential group BEFORE any DB writes,
    // so the attempt is recorded even if validation or the update later fails.
    // Triggers when EITHER prior or new state is confidential.
    if (($wasConfidential || $isConfidential) && !empty($addedIds)) {
        $aPlaceholders   = implode(',', array_fill(0, count($addedIds), '?'));
        $aStmt           = $db->prepare("SELECT id, first_name, last_name, email FROM users WHERE id IN ($aPlaceholders)");
        $aStmt->execute($addedIds);
        $addedUsersForLog = $aStmt->fetchAll();
        $addedNames       = implode(', ', array_map(
            static fn($u) => trim($u['first_name'] . ' ' . $u['last_name']) . ' <' . $u['email'] . '>',
            $addedUsersForLog
        ));
        logAudit(
            'confidential_group_member_add_attempted',
            $id,
            'group',
            Auth::fullName() . ' (ID: ' . Auth::id() . ') is attempting to add ' . count($addedIds)
                . ' member(s) to confidential group "' . $name . '" (ID: ' . $id . '): ' . $addedNames
        );
    }

    // Same for removals from a confidential group, since the user asked for full audit coverage
    if (($wasConfidential || $isConfidential) && !empty($removedIds)) {
        $rPlaceholders     = implode(',', array_fill(0, count($removedIds), '?'));
        $rStmt             = $db->prepare("SELECT id, first_name, last_name, email FROM users WHERE id IN ($rPlaceholders)");
        $rStmt->execute($removedIds);
        $removedUsersForLog = $rStmt->fetchAll();
        $removedNames       = implode(', ', array_map(
            static fn($u) => trim($u['first_name'] . ' ' . $u['last_name']) . ' <' . $u['email'] . '>',
            $removedUsersForLog
        ));
        logAudit(
            'confidential_group_member_remove_attempted',
            $id,
            'group',
            Auth::fullName() . ' (ID: ' . Auth::id() . ') is attempting to remove ' . count($removedIds)
                . ' member(s) from confidential group "' . $name . '" (ID: ' . $id . '): ' . $removedNames
        );
    }

    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Group name is required.');
        redirect("/admin/groups/{$id}/edit");
    }

    $allowedStrategies = ['manual','round_robin','load_based','skill_based','first_available'];
    $assignStrategy = in_array($_POST['assign_strategy'] ?? '', $allowedStrategies, true) ? $_POST['assign_strategy'] : 'manual';
    $allowedFallbacks = ['round_robin','load_based','none'];
    $assignFallback = in_array($_POST['assign_fallback'] ?? '', $allowedFallbacks, true) ? $_POST['assign_fallback'] : 'load_based';

    // Capture the full editable set on the group row before update so the
    // diff covers everything (name, strategy, fallback, etc.) on top of the
    // dedicated confidential-flag log entry.
    $fullGroupPriorStmt = $db->prepare(
        'SELECT name, description, sort_order, notify_new_ticket, is_confidential,
                assign_strategy, assign_fallback
         FROM `groups` WHERE id = ?'
    );
    $fullGroupPriorStmt->execute([$id]);
    $fullGroupPrior = $fullGroupPriorStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

    $db->prepare('UPDATE `groups` SET name=?, description=?, sort_order=?, notify_new_ticket=?, is_confidential=?, assign_strategy=?, assign_fallback=? WHERE id=?')
        ->execute([$name, $desc, $order, $notifyNew, $isConfidential, $assignStrategy, $assignFallback, $id]);

    logAuditChange(
        'group.updated',
        $id,
        'group',
        $fullGroupPrior,
        [
            'name'              => $name,
            'description'       => $desc,
            'sort_order'        => $order,
            'notify_new_ticket' => $notifyNew,
            'is_confidential'   => $isConfidential,
            'assign_strategy'   => $assignStrategy,
            'assign_fallback'   => $assignFallback,
        ]
    );

    // Snapshot the prior manager set before we overwrite the membership rows,
    // so we can audit-log what changed.
    $priorManagerStmt = $db->prepare('SELECT user_id FROM group_user_map WHERE group_id = ? AND is_manager = 1');
    $priorManagerStmt->execute([$id]);
    $priorManagerIds = array_map('intval', $priorManagerStmt->fetchAll(PDO::FETCH_COLUMN));

    // Sync members: delete existing, insert new (with is_manager flag)
    $db->prepare('DELETE FROM group_user_map WHERE group_id = ?')->execute([$id]);
    if (!empty($userIds)) {
        $stmt = $db->prepare('INSERT INTO group_user_map (group_id, user_id, is_manager) VALUES (?, ?, ?)');
        foreach ($userIds as $uid) {
            $stmt->execute([$id, $uid, isset($managerSet[$uid]) ? 1 : 0]);
        }
    }

    // Audit any change to the manager set so admins can see who delegated rights
    $newManagerIds   = array_keys($managerSet);
    $managersAdded   = array_diff($newManagerIds, $priorManagerIds);
    $managersRemoved = array_diff($priorManagerIds, $newManagerIds);
    if (!empty($managersAdded) || !empty($managersRemoved)) {
        $detail = 'Group "' . $name . '" (ID: ' . $id . ') manager set changed.';
        if (!empty($managersAdded))   { $detail .= ' Added: ' . implode(',', $managersAdded) . '.'; }
        if (!empty($managersRemoved)) { $detail .= ' Removed: ' . implode(',', $managersRemoved) . '.'; }
        logAudit('group.managers_changed', $id, 'group', $detail);
    }

    // Confidential alert: if the group is now confidential, had at least one
    // prior member, and new members were added, notify all current members.
    if ($isConfidential && !empty($existingMemberIds) && !empty($addedIds)) {
        notifyConfidentialGroupMembership($db, $id, $addedIds);
    }

    // Confidential flag removal: audit log + notify all members
    if ($wasConfidential && !$isConfidential) {
        logAudit(
            'confidential_flag_removed',
            $id,
            'group',
            Auth::fullName() . ' (ID: ' . Auth::id() . ') removed the confidential flag from group "' . $name . '" (ID: ' . $id . ') after re-authentication'
        );
        $appUrl = env('APP_URL', 'http://localhost:8000');
        notifyConfidentialFlagRemoved($db, 'group', $name, $id, $appUrl . '/admin/groups/' . $id . '/edit');
    }

    flash('success', 'Group updated.');
    redirect('/admin/groups');
});

$router->post('/admin/groups/{id}/delete', function (array $p) {
    Auth::requirePermission('groups.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/groups');
    }
    $id = (int) $p['id'];
    $db = Database::connect();

    // Check if the group is confidential — require re-auth before deletion
    $gStmt = $db->prepare('SELECT name, is_confidential FROM `groups` WHERE id = ?');
    $gStmt->execute([$id]);
    $group = $gStmt->fetch();
    if (!$group) {
        flash('error', 'Group not found.');
        redirect('/admin/groups');
    }

    if (!empty($group['is_confidential'])) {
        if (empty($_POST['_confidential_reauth'])) {
            logAudit(
                'confidential_delete_attempted',
                $id,
                'group',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') attempted to delete confidential group "' . $group['name'] . '" (ID: ' . $id . ') — re-authentication required'
            );
            render('admin/confidential-reauth', [
                'action'            => 'delete',
                'targetType'        => 'group',
                'targetName'        => $group['name'],
                'formAction'        => "/admin/groups/{$id}/delete",
                'cancelUrl'         => '/admin/groups',
                'hiddenFields'      => ['_token' => $_POST['_token'] ?? ''],
                'hiddenArrayFields' => [],
            ]);
            return;
        }

        // Verify password
        $pwStmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $pwStmt->execute([Auth::id()]);
        $pwUser = $pwStmt->fetch();
        if (!$pwUser || !password_verify($_POST['reauth_password'] ?? '', $pwUser['password'])) {
            logAudit(
                'confidential_delete_auth_failed',
                $id,
                'group',
                Auth::fullName() . ' (ID: ' . Auth::id() . ') failed re-authentication when attempting to delete confidential group "' . $group['name'] . '"'
            );
            flash('error', 'Incorrect password. The confidential group was not deleted.');
            redirect('/admin/groups');
        }

        // Snapshot members BEFORE deletion for email alerts
        $mStmt = $db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.role
             FROM group_user_map gum
             JOIN users u ON u.id = gum.user_id
             WHERE gum.group_id = ?'
        );
        $mStmt->execute([$id]);
        $members = $mStmt->fetchAll();

        logAudit(
            'confidential_entity_deleted',
            $id,
            'group',
            Auth::fullName() . ' (ID: ' . Auth::id() . ') deleted confidential group "' . $group['name'] . '" (ID: ' . $id . ') after re-authentication'
        );

        $db->prepare('DELETE FROM `groups` WHERE id = ?')->execute([$id]);

        notifyConfidentialEntityDeleted($db, 'group', $group['name'], $members);

        flash('success', 'Confidential group deleted.');
        redirect('/admin/groups');
    }

    $db->prepare('DELETE FROM `groups` WHERE id = ?')->execute([$id]);
    logAudit('group.deleted', $id, 'group', 'name=' . $group['name']);
    flash('success', 'Group deleted.');
    redirect('/admin/groups');
});

/* ==================================================================
 * ADMIN – Agent Skills
 *
 * Skills back the "Skill-Based" group auto-assignment strategy. Each
 * skill is a tag (e.g. "Billing", "Network", "French"). Agents declare
 * which skills they hold; ticket types declare which skills they
 * require. Routing picks group members whose skill set covers every
 * required skill.
 * ================================================================== */

/**
 * Build a `[user_id => [group_id, ...]]` map for the skill edit/create form.
 * Used by the JS scope filter to hide users who aren't in the chosen
 * owning group, so the "Agents with this skill" list only shows people
 * who actually belong to the group the skill is being scoped to.
 */
function _skillFormUserGroups(PDO $db): array
{
    $rows = $db->query(
        "SELECT gum.user_id, gum.group_id
           FROM group_user_map gum
           JOIN users u ON u.id = gum.user_id
          WHERE " . staffRoleSqlIn('u.role') . ""
    )->fetchAll();
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r['user_id']][] = (int) $r['group_id'];
    }
    return $map;
}

$router->get('/admin/skills', function () {
    Auth::requirePermission('skills.manage');
    $db = Database::connect();
    $skills = $db->query(
        "SELECT s.*,
                g.name AS group_name,
                COALESCE(uc.cnt, 0) AS agent_count,
                COALESCE(tc.cnt, 0) AS type_count
         FROM agent_skills s
         LEFT JOIN `groups` g ON g.id = s.group_id
         LEFT JOIN (SELECT skill_id, COUNT(*) AS cnt FROM user_skill_map GROUP BY skill_id) uc ON uc.skill_id = s.id
         LEFT JOIN (SELECT skill_id, COUNT(*) AS cnt FROM ticket_type_skill_map GROUP BY skill_id) tc ON tc.skill_id = s.id
         ORDER BY s.sort_order, s.name"
    )->fetchAll();
    render('admin/skills/index', ['skills' => $skills]);
});

$router->post('/admin/skills/reorder', function () {
    Auth::requirePermission('skills.manage');
    handleSortableReorder('agent_skills');
});

$router->get('/admin/skills/create', function () {
    Auth::requirePermission('skills.manage');
    $db = Database::connect();
    $users = $db->query(
        "SELECT id, first_name, last_name, role FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();
    $groups = $db->query("SELECT id, name FROM `groups` ORDER BY sort_order, name")->fetchAll();
    $userGroups = _skillFormUserGroups($db);
    render('admin/skills/form', ['editing' => null, 'users' => $users, 'memberIds' => [], 'groups' => $groups, 'userGroups' => $userGroups]);
});

$router->post('/admin/skills/create', function () {
    Auth::requirePermission('skills.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/skills/create');
    }
    $name    = trim($_POST['name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $order   = (int) ($_POST['sort_order'] ?? 0);
    $groupId = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null;
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Skill name is required.');
        redirect('/admin/skills/create');
    }
    $db = Database::connect();
    try {
        $db->prepare('INSERT INTO agent_skills (name, description, sort_order, group_id) VALUES (?, ?, ?, ?)')
           ->execute([$name, $desc !== '' ? $desc : null, $order, $groupId]);
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate entry') ? 'A skill with that name already exists.' : 'Database error.');
        flashInput($_POST);
        redirect('/admin/skills/create');
    }
    $skillId = (int) $db->lastInsertId();
    $userIds = array_filter(array_map('intval', (array) ($_POST['members'] ?? [])));
    if ($userIds) {
        $stmt = $db->prepare('INSERT INTO user_skill_map (user_id, skill_id) VALUES (?, ?)');
        foreach ($userIds as $uid) {
            $stmt->execute([$uid, $skillId]);
        }
    }
    logAudit(
        'skill.created',
        $skillId,
        'skill',
        'name=' . $name . '; group_id=' . ($groupId ?? 'null') . '; members=' . count($userIds)
    );
    flash('success', 'Skill created.');
    redirect('/admin/skills');
});

$router->get('/admin/skills/{id}/edit', function (array $p) {
    Auth::requirePermission('skills.manage');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM agent_skills WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Skill not found.');
        redirect('/admin/skills');
    }
    $users = $db->query(
        "SELECT id, first_name, last_name, role FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();
    $mStmt = $db->prepare('SELECT user_id FROM user_skill_map WHERE skill_id = ?');
    $mStmt->execute([(int) $p['id']]);
    $memberIds = array_map('intval', $mStmt->fetchAll(PDO::FETCH_COLUMN));
    $groups = $db->query("SELECT id, name FROM `groups` ORDER BY sort_order, name")->fetchAll();
    $userGroups = _skillFormUserGroups($db);
    render('admin/skills/form', ['editing' => $editing, 'users' => $users, 'memberIds' => $memberIds, 'groups' => $groups, 'userGroups' => $userGroups]);
});

$router->post('/admin/skills/{id}/edit', function (array $p) {
    Auth::requirePermission('skills.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/skills/{$id}/edit");
    }
    $name    = trim($_POST['name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $order   = (int) ($_POST['sort_order'] ?? 0);
    $groupId = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? (int) $_POST['group_id'] : null;
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Skill name is required.');
        redirect("/admin/skills/{$id}/edit");
    }
    $db = Database::connect();
    $beforeStmt = $db->prepare('SELECT name, description, sort_order, group_id FROM agent_skills WHERE id = ?');
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    try {
        $db->prepare('UPDATE agent_skills SET name = ?, description = ?, sort_order = ?, group_id = ? WHERE id = ?')
           ->execute([$name, $desc !== '' ? $desc : null, $order, $groupId, $id]);
    } catch (PDOException $e) {
        flash('error', str_contains($e->getMessage(), 'Duplicate entry') ? 'A skill with that name already exists.' : 'Database error.');
        flashInput($_POST);
        redirect("/admin/skills/{$id}/edit");
    }
    $userIds = array_filter(array_map('intval', (array) ($_POST['members'] ?? [])));
    $db->prepare('DELETE FROM user_skill_map WHERE skill_id = ?')->execute([$id]);
    if ($userIds) {
        $stmt = $db->prepare('INSERT INTO user_skill_map (user_id, skill_id) VALUES (?, ?)');
        foreach ($userIds as $uid) {
            $stmt->execute([$uid, $id]);
        }
    }
    logAuditChange(
        'skill.updated',
        $id,
        'skill',
        $before,
        ['name' => $name, 'description' => $desc !== '' ? $desc : null, 'sort_order' => $order, 'group_id' => $groupId]
    );
    flash('success', 'Skill updated.');
    redirect('/admin/skills');
});

$router->post('/admin/skills/{id}/delete', function (array $p) {
    Auth::requirePermission('skills.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/skills');
    }
    $id   = (int) $p['id'];
    $db   = Database::connect();
    $name = $db->prepare('SELECT name FROM agent_skills WHERE id = ?');
    $name->execute([$id]);
    $sname = (string) ($name->fetchColumn() ?: '');
    $db->prepare('DELETE FROM agent_skills WHERE id = ?')->execute([$id]);
    logAudit('skill.deleted', $id, 'skill', 'name=' . $sname);
    flash('success', 'Skill deleted.');
    redirect('/admin/skills');
});

/* ------------------------------------------------------------------
 * Skill Suggestion (AI-assisted)
 *
 * GET /admin/skills/suggest — gather org type + ticket types + groups
 *   + existing skills, ask the configured AI provider to suggest a set
 *   of skills, render them with checkboxes for the admin to cherry-pick.
 *
 * POST /admin/skills/suggest — bulk-insert the rows the admin checked.
 *   Skips any name that already exists; group_id is optional.
 * ------------------------------------------------------------------ */

$router->get('/admin/skills/suggest', function () {
    Auth::requirePermission('skills.manage');

    if (getSetting('ai_enabled', '0') !== '1') {
        flash('error', 'AI is not enabled. Configure it in Settings → AI Classification before using skill suggestions.');
        redirect('/admin/skills');
    }

    $db = Database::connect();
    $ticketTypes   = $db->query('SELECT id, name FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $groups        = $db->query("SELECT id, name FROM `groups` ORDER BY sort_order, name")->fetchAll();
    $existingSkills = $db->query('SELECT id, name, description FROM agent_skills ORDER BY name')->fetchAll();

    $orgSlug  = getSetting('organization_type', 'other');
    $orgLabel = organizationTypeLabel($orgSlug);

    // Mode + sample-size knobs from the launcher modal. mode=basic uses only
    // org/types/groups; mode=mine also feeds the AI a sample of recent
    // ticket subjects so suggestions reflect what this install actually deals
    // with day-to-day. n is clamped to a safe range — the AI prompt has its
    // own 60K-char cap on the subjects block, so very large n won't blow up
    // the request, just take longer to query.
    $mode = ($_GET['mode'] ?? 'basic') === 'mine' ? 'mine' : 'basic';
    $n    = max(50, min(5000, (int) ($_GET['n'] ?? 500)));

    $recentSubjects = [];
    $minedCount     = 0;
    if ($mode === 'mine') {
        // Skip confidential ticket types so subjects aren't leaked to a
        // third-party API — same rule classifyTicketWithAI() enforces.
        $sub = $db->prepare(
            "SELECT t.subject
             FROM tickets t
             LEFT JOIN ticket_types tt ON tt.id = t.type_id
             WHERE COALESCE(tt.is_confidential, 0) = 0
               AND t.subject IS NOT NULL AND t.subject <> ''
             ORDER BY t.id DESC
             LIMIT {$n}"
        );
        $sub->execute();
        $recentSubjects = array_map('strval', $sub->fetchAll(PDO::FETCH_COLUMN));
        $minedCount     = count($recentSubjects);
    }

    try {
        $suggestions = AIClassifierFactory::suggestSkillsFromSettings(
            $orgLabel,
            $ticketTypes,
            $groups,
            $existingSkills,
            $recentSubjects
        );
    } catch (SoftAIException $e) {
        flash('error', 'AI suggestion failed: ' . $e->getMessage());
        redirect('/admin/skills');
    } catch (\Throwable $e) {
        error_log('[AI suggest skills] failed: ' . $e->getMessage());
        flash('error', 'AI suggestion failed: ' . $e->getMessage());
        redirect('/admin/skills');
    }

    render('admin/skills/suggest', [
        'suggestions'    => $suggestions,
        'orgLabel'       => $orgLabel,
        'ticketTypes'    => $ticketTypes,
        'groups'         => $groups,
        'existingSkills' => $existingSkills,
        'mode'           => $mode,
        'sampleSize'     => $n,
        'minedCount'     => $minedCount,
    ]);
});

$router->post('/admin/skills/suggest', function () {
    Auth::requirePermission('skills.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/skills');
    }

    $rows = isset($_POST['skills']) && is_array($_POST['skills']) ? $_POST['skills'] : [];
    if (!$rows) {
        flash('error', 'Select at least one suggested skill to add.');
        redirect('/admin/skills/suggest');
    }

    $db = Database::connect();
    // Pull existing names + valid group ids once so we can de-dupe and
    // reject hand-tampered group ids without round-tripping per row.
    $existingLower = array_map(
        static fn($n) => mb_strtolower(trim((string) $n)),
        $db->query('SELECT name FROM agent_skills')->fetchAll(PDO::FETCH_COLUMN)
    );
    $validGroupIds = array_map('intval', $db->query('SELECT id FROM `groups`')->fetchAll(PDO::FETCH_COLUMN));

    $insert = $db->prepare('INSERT INTO agent_skills (name, description, sort_order, group_id) VALUES (?, ?, 0, ?)');

    $added   = 0;
    $skipped = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) { continue; }
        // `_keep` is the row's "include this suggestion" checkbox. Only act on
        // rows the admin explicitly opted in to (defends against JS-disabled
        // browsers where unchecked rows still submit their other inputs).
        if (empty($row['_keep'])) { continue; }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') { $skipped++; continue; }
        if (in_array(mb_strtolower($name), $existingLower, true)) { $skipped++; continue; }

        $desc    = trim((string) ($row['description'] ?? ''));
        $groupId = isset($row['group_id']) && $row['group_id'] !== '' ? (int) $row['group_id'] : null;
        if ($groupId !== null && !in_array($groupId, $validGroupIds, true)) {
            $groupId = null;
        }

        try {
            $insert->execute([
                mb_substr($name, 0, 100),
                $desc !== '' ? mb_substr($desc, 0, 500) : null,
                $groupId,
            ]);
            $added++;
            $existingLower[] = mb_strtolower($name);
        } catch (PDOException $e) {
            // Likely a duplicate-name race; just skip and continue.
            $skipped++;
        }
    }

    if ($added > 0) {
        flash('success', "Added {$added} skill" . ($added === 1 ? '' : 's') . '.' . ($skipped > 0 ? " Skipped {$skipped} duplicate or invalid row" . ($skipped === 1 ? '' : 's') . '.' : ''));
    } else {
        flash('error', 'No skills were added.' . ($skipped > 0 ? " Skipped {$skipped} duplicate or invalid row" . ($skipped === 1 ? '' : 's') . '.' : ''));
    }
    redirect('/admin/skills');
});

/* ==================================================================
 * ADMIN – Ticket Templates
 * ================================================================== */

$router->get('/admin/ticket-templates', function () {
    Auth::requirePermission('ticket_templates.manage');
    $db = Database::connect();
    $templates = $db->query(
        "SELECT t.*,
                CONCAT(u.first_name, ' ', u.last_name) AS creator_name,
                tp.name  AS type_name,
                pri.name AS priority_name
         FROM ticket_templates t
         LEFT JOIN users             u   ON t.created_by  = u.id
         LEFT JOIN ticket_types      tp  ON t.type_id     = tp.id
         LEFT JOIN ticket_priorities pri ON t.priority_id = pri.id
         ORDER BY t.name"
    )->fetchAll();
    render('admin/ticket-templates/index', ['templates' => $templates]);
});

$router->get('/admin/ticket-templates/create', function () {
    Auth::requirePermission('ticket_templates.manage');
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    render('admin/ticket-templates/form', ['types' => $types, 'priorities' => $priorities]);
});

$router->post('/admin/ticket-templates/create', function () {
    Auth::requirePermission('ticket_templates.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/ticket-templates/create');
    }
    $name     = trim($_POST['name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $typeId   = !empty($_POST['type_id'])     ? (int) $_POST['type_id']     : null;
    $priId    = !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null;
    $isShared = !empty($_POST['is_shared'])   ? 1 : 0;

    if ($name === '') {
        flash('error', 'Template name is required.');
        flashInput($_POST);
        redirect('/admin/ticket-templates/create');
    }

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO ticket_templates (name, description, subject, body, type_id, priority_id, is_shared, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$name, $desc ?: null, $subject, $body, $typeId, $priId, $isShared, Auth::id()]);
    $newId = (int) $db->lastInsertId();
    logAudit(
        'ticket_template.created',
        $newId,
        'ticket_template',
        'name=' . $name . '; shared=' . $isShared
    );

    flash('success', 'Template created.');
    redirect('/admin/ticket-templates');
});

$router->get('/admin/ticket-templates/{id}/edit', function (array $p) {
    Auth::requirePermission('ticket_templates.manage');
    $db   = Database::connect();
    $tpl  = $db->prepare('SELECT * FROM ticket_templates WHERE id = ?');
    $tpl->execute([(int) $p['id']]);
    $editing = $tpl->fetch();
    if (!$editing) {
        flash('error', 'Template not found.');
        redirect('/admin/ticket-templates');
    }
    // Only creator or admin may edit
    if (!Auth::isAdmin() && $editing['created_by'] !== Auth::id()) {
        flash('error', 'You can only edit your own templates.');
        redirect('/admin/ticket-templates');
    }
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    render('admin/ticket-templates/form', ['editing' => $editing, 'types' => $types, 'priorities' => $priorities]);
});

$router->post('/admin/ticket-templates/{id}/edit', function (array $p) {
    Auth::requirePermission('ticket_templates.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/ticket-templates/{$id}/edit");
    }
    $db  = Database::connect();
    $tpl = $db->prepare('SELECT * FROM ticket_templates WHERE id = ?');
    $tpl->execute([$id]);
    $existing = $tpl->fetch();
    if (!$existing || (!Auth::isAdmin() && $existing['created_by'] !== Auth::id())) {
        flash('error', 'Not found or insufficient permissions.');
        redirect('/admin/ticket-templates');
    }
    $name     = trim($_POST['name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $typeId   = !empty($_POST['type_id'])     ? (int) $_POST['type_id']     : null;
    $priId    = !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null;
    $isShared = !empty($_POST['is_shared'])   ? 1 : 0;

    if ($name === '') {
        flash('error', 'Template name is required.');
        flashInput($_POST);
        redirect("/admin/ticket-templates/{$id}/edit");
    }
    $db->prepare(
        'UPDATE ticket_templates SET name=?, description=?, subject=?, body=?, type_id=?, priority_id=?, is_shared=? WHERE id=?'
    )->execute([$name, $desc ?: null, $subject, $body, $typeId, $priId, $isShared, $id]);

    logAuditChange(
        'ticket_template.updated',
        $id,
        'ticket_template',
        ['name' => $existing['name'], 'is_shared' => $existing['is_shared'], 'type_id' => $existing['type_id'], 'priority_id' => $existing['priority_id']],
        ['name' => $name,            'is_shared' => $isShared,             'type_id' => $typeId,             'priority_id' => $priId]
    );

    flash('success', 'Template updated.');
    redirect('/admin/ticket-templates');
});

$router->post('/admin/ticket-templates/{id}/delete', function (array $p) {
    Auth::requirePermission('ticket_templates.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/ticket-templates');
    }
    $db  = Database::connect();
    $tpl = $db->prepare('SELECT created_by, name FROM ticket_templates WHERE id = ?');
    $tpl->execute([$id]);
    $existing = $tpl->fetch();
    if (!$existing || (!Auth::isAdmin() && $existing['created_by'] !== Auth::id())) {
        flash('error', 'Not found or insufficient permissions.');
        redirect('/admin/ticket-templates');
    }
    $db->prepare('DELETE FROM ticket_templates WHERE id = ?')->execute([$id]);
    logAudit('ticket_template.deleted', $id, 'ticket_template', 'name=' . $existing['name']);
    flash('success', 'Template deleted.');
    redirect('/admin/ticket-templates');
});

/* ==================================================================
 * ADMIN – Recurring / Preventive-Maintenance Ticket Schedules
 * ================================================================== */

/**
 * Pull POST input into the schema's column shape, with the cadence
 * fields normalised to the right types and the day/month anchors only
 * carried through when the chosen frequency actually uses them.
 */
function _recurringPostToRow(): array
{
    $frequency = $_POST['frequency'] ?? 'monthly';
    $allowed   = ['daily', 'weekly', 'monthly', 'yearly', 'custom'];
    if (!in_array($frequency, $allowed, true)) {
        $frequency = 'monthly';
    }

    $row = [
        'name'                 => trim($_POST['name'] ?? ''),
        'description_internal' => trim($_POST['description_internal'] ?? '') ?: null,
        'subject'              => trim($_POST['subject'] ?? ''),
        'body'                 => trim($_POST['body'] ?? ''),
        'type_id'              => !empty($_POST['type_id'])     ? (int) $_POST['type_id']     : null,
        'priority_id'          => !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null,
        'location_id'          => !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null,
        'assigned_to'          => !empty($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null,
        'group_id'             => !empty($_POST['group_id'])    ? (int) $_POST['group_id']    : null,
        'requester_id'         => !empty($_POST['requester_id']) ? (int) $_POST['requester_id'] : null,
        'due_date_offset_days' => ($_POST['due_date_offset_days'] ?? '') !== '' ? max(0, (int) $_POST['due_date_offset_days']) : null,
        'frequency'            => $frequency,
        'interval_value'       => max(1, (int) ($_POST['interval_value'] ?? 1)),
        'day_of_week'          => null,
        'day_of_month'         => null,
        'month_of_year'        => null,
        'start_date'           => trim($_POST['start_date'] ?? '') ?: date('Y-m-d'),
        'is_active'            => !empty($_POST['is_active']) ? 1 : 0,
    ];

    // Anchor fields only carry through when the chosen frequency uses them.
    if ($frequency === 'weekly' && ($_POST['day_of_week'] ?? '') !== '') {
        $row['day_of_week'] = max(0, min(6, (int) $_POST['day_of_week']));
    }
    if (in_array($frequency, ['monthly', 'yearly'], true) && ($_POST['day_of_month'] ?? '') !== '') {
        $row['day_of_month'] = max(1, min(31, (int) $_POST['day_of_month']));
    }
    if ($frequency === 'yearly' && ($_POST['month_of_year'] ?? '') !== '') {
        $row['month_of_year'] = max(1, min(12, (int) $_POST['month_of_year']));
    }

    return $row;
}

$router->get('/admin/recurring-tickets', function () {
    Auth::requirePermission('recurring_tickets.manage');
    $db = Database::connect();
    $rows = $db->query(
        "SELECT r.*,
                tt.name  AS type_name,
                pri.name AS priority_name,
                loc.name AS location_name,
                g.name   AS group_name,
                CONCAT(req.first_name, ' ', req.last_name) AS requester_name,
                CONCAT(asg.first_name, ' ', asg.last_name) AS assignee_name
         FROM recurring_tickets r
         LEFT JOIN ticket_types       tt  ON r.type_id     = tt.id
         LEFT JOIN ticket_priorities  pri ON r.priority_id = pri.id
         LEFT JOIN locations          loc ON r.location_id = loc.id
         LEFT JOIN `groups`           g   ON r.group_id    = g.id
         LEFT JOIN users              req ON r.requester_id = req.id
         LEFT JOIN users              asg ON r.assigned_to  = asg.id
         ORDER BY r.is_active DESC, r.next_run_at ASC, r.name ASC"
    )->fetchAll();
    render('admin/recurring-tickets/index', ['schedules' => $rows]);
});

$router->get('/admin/recurring-tickets/create', function () {
    Auth::requirePermission('recurring_tickets.manage');
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $agents     = $db->query(
        "SELECT id, first_name, last_name FROM users
         WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();
    $allUsers   = $db->query(
        "SELECT id, first_name, last_name, email FROM users ORDER BY first_name, last_name"
    )->fetchAll();
    render('admin/recurring-tickets/form', [
        'types'      => $types,
        'priorities' => $priorities,
        'locations'  => $locations,
        'groups'     => $groups,
        'agents'     => $agents,
        'allUsers'   => $allUsers,
    ]);
});

$router->post('/admin/recurring-tickets/create', function () {
    Auth::requirePermission('recurring_tickets.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/recurring-tickets/create');
    }

    $row = _recurringPostToRow();
    if ($row['name'] === '' || $row['subject'] === '' || $row['body'] === '') {
        flash('error', 'Schedule name, ticket subject and ticket description are required.');
        flashInput($_POST);
        redirect('/admin/recurring-tickets/create');
    }
    if (!$row['requester_id']) {
        $row['requester_id'] = Auth::id();
    }

    $now = new DateTimeImmutable('now');
    $nextRun = RecurringTickets::computeNextRun($row, $now, false);

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO recurring_tickets
            (name, description_internal, subject, body, type_id, priority_id, location_id,
             assigned_to, group_id, requester_id, due_date_offset_days,
             frequency, interval_value, day_of_week, day_of_month, month_of_year,
             start_date, next_run_at, is_active, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $row['name'], $row['description_internal'], $row['subject'], $row['body'],
        $row['type_id'], $row['priority_id'], $row['location_id'],
        $row['assigned_to'], $row['group_id'], $row['requester_id'], $row['due_date_offset_days'],
        $row['frequency'], $row['interval_value'], $row['day_of_week'], $row['day_of_month'], $row['month_of_year'],
        $row['start_date'], $nextRun->format('Y-m-d H:i:s'), $row['is_active'],
        Auth::id(), Auth::id(),
    ]);
    $newId = (int) $db->lastInsertId();
    logAudit(
        'recurring_ticket.created',
        $newId,
        'recurring_ticket',
        'name=' . $row['name']
            . '; frequency=' . $row['frequency']
            . '; next_run=' . $nextRun->format('Y-m-d')
            . '; active=' . $row['is_active']
    );

    flash('success', 'Recurring schedule created. First ticket will fire on ' . $nextRun->format('M j, Y') . '.');
    redirect('/admin/recurring-tickets');
});

$router->get('/admin/recurring-tickets/{id}/edit', function (array $p) {
    Auth::requirePermission('recurring_tickets.manage');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM recurring_tickets WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Recurring schedule not found.');
        redirect('/admin/recurring-tickets');
    }
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $agents     = $db->query(
        "SELECT id, first_name, last_name FROM users
         WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();
    $allUsers   = $db->query(
        "SELECT id, first_name, last_name, email FROM users ORDER BY first_name, last_name"
    )->fetchAll();
    render('admin/recurring-tickets/form', [
        'editing'    => $editing,
        'types'      => $types,
        'priorities' => $priorities,
        'locations'  => $locations,
        'groups'     => $groups,
        'agents'     => $agents,
        'allUsers'   => $allUsers,
    ]);
});

$router->post('/admin/recurring-tickets/{id}/edit', function (array $p) {
    Auth::requirePermission('recurring_tickets.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/recurring-tickets/{$id}/edit");
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM recurring_tickets WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('error', 'Recurring schedule not found.');
        redirect('/admin/recurring-tickets');
    }

    $row = _recurringPostToRow();
    if ($row['name'] === '' || $row['subject'] === '' || $row['body'] === '') {
        flash('error', 'Schedule name, ticket subject and ticket description are required.');
        flashInput($_POST);
        redirect("/admin/recurring-tickets/{$id}/edit");
    }
    if (!$row['requester_id']) {
        $row['requester_id'] = (int) ($existing['requester_id'] ?? Auth::id());
    }

    // Recompute next_run_at when cadence inputs changed — otherwise a
    // user editing a schedule from monthly-15th to monthly-1st would
    // keep firing on the 15th until it next fired.
    $cadenceChanged =
        $row['frequency']      !== (string) $existing['frequency']
        || (int) $row['interval_value'] !== (int) $existing['interval_value']
        || (string) ($row['day_of_week']   ?? '') !== (string) ($existing['day_of_week']   ?? '')
        || (string) ($row['day_of_month']  ?? '') !== (string) ($existing['day_of_month']  ?? '')
        || (string) ($row['month_of_year'] ?? '') !== (string) ($existing['month_of_year'] ?? '')
        || $row['start_date']  !== (string) $existing['start_date'];

    $nextRun = $cadenceChanged
        ? RecurringTickets::computeNextRun($row, new DateTimeImmutable('now'), false)
        : new DateTimeImmutable((string) $existing['next_run_at']);

    $db->prepare(
        'UPDATE recurring_tickets SET
            name = ?, description_internal = ?, subject = ?, body = ?,
            type_id = ?, priority_id = ?, location_id = ?, assigned_to = ?, group_id = ?,
            requester_id = ?, due_date_offset_days = ?,
            frequency = ?, interval_value = ?, day_of_week = ?, day_of_month = ?, month_of_year = ?,
            start_date = ?, next_run_at = ?, is_active = ?, updated_by = ?
         WHERE id = ?'
    )->execute([
        $row['name'], $row['description_internal'], $row['subject'], $row['body'],
        $row['type_id'], $row['priority_id'], $row['location_id'], $row['assigned_to'], $row['group_id'],
        $row['requester_id'], $row['due_date_offset_days'],
        $row['frequency'], $row['interval_value'], $row['day_of_week'], $row['day_of_month'], $row['month_of_year'],
        $row['start_date'], $nextRun->format('Y-m-d H:i:s'), $row['is_active'],
        Auth::id(), $id,
    ]);

    logAuditChange(
        'recurring_ticket.updated',
        $id,
        'recurring_ticket',
        [
            'name'           => $existing['name'],
            'frequency'      => $existing['frequency'],
            'interval_value' => $existing['interval_value'],
            'is_active'      => $existing['is_active'],
            'next_run_at'    => $existing['next_run_at'],
        ],
        [
            'name'           => $row['name'],
            'frequency'      => $row['frequency'],
            'interval_value' => $row['interval_value'],
            'is_active'      => $row['is_active'],
            'next_run_at'    => $nextRun->format('Y-m-d H:i:s'),
        ]
    );

    flash('success', 'Recurring schedule updated.' . ($cadenceChanged ? ' Next run recalculated to ' . $nextRun->format('M j, Y') . '.' : ''));
    redirect('/admin/recurring-tickets');
});

$router->post('/admin/recurring-tickets/{id}/toggle', function (array $p) {
    Auth::requirePermission('recurring_tickets.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/recurring-tickets');
    }
    $db = Database::connect();
    $db->prepare('UPDATE recurring_tickets SET is_active = 1 - is_active, updated_by = ? WHERE id = ?')
        ->execute([Auth::id(), $id]);
    $stateStmt = $db->prepare('SELECT is_active, name FROM recurring_tickets WHERE id = ?');
    $stateStmt->execute([$id]);
    $state = $stateStmt->fetch(\PDO::FETCH_ASSOC) ?: ['is_active' => null, 'name' => ''];
    logAudit(
        'recurring_ticket.toggled',
        $id,
        'recurring_ticket',
        'name=' . $state['name'] . '; is_active=' . $state['is_active']
    );
    flash('success', 'Schedule status updated.');
    redirect('/admin/recurring-tickets');
});

$router->post('/admin/recurring-tickets/{id}/run-now', function (array $p) {
    Auth::requirePermission('recurring_tickets.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/recurring-tickets');
    }
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM recurring_tickets WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Schedule not found.');
        redirect('/admin/recurring-tickets');
    }

    $ticketId = RecurringTickets::mintTicket($db, $row);
    if (!$ticketId) {
        flash('error', 'Could not create ticket — schedule is missing a requester or required fields.');
        redirect('/admin/recurring-tickets');
    }

    // "Run now" doesn't reset the cadence — the next scheduled run still
    // fires on the configured cycle. We do bump last_run_at + run_count
    // so the index list reflects reality.
    $db->prepare(
        'UPDATE recurring_tickets SET last_run_at = NOW(), last_ticket_id = ?, run_count = run_count + 1 WHERE id = ?'
    )->execute([$ticketId, $id]);

    logAudit(
        'recurring_ticket.run_now',
        $id,
        'recurring_ticket',
        'name=' . $row['name'] . '; ticket_id=' . $ticketId
    );

    flash('success', 'Ticket #' . $ticketId . ' created from schedule.');
    redirect('/admin/recurring-tickets');
});

$router->post('/admin/recurring-tickets/{id}/delete', function (array $p) {
    Auth::requirePermission('recurring_tickets.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/recurring-tickets');
    }
    $db   = Database::connect();
    $name = $db->prepare('SELECT name FROM recurring_tickets WHERE id = ?');
    $name->execute([$id]);
    $rname = (string) ($name->fetchColumn() ?: '');
    $db->prepare('DELETE FROM recurring_tickets WHERE id = ?')->execute([$id]);
    logAudit('recurring_ticket.deleted', $id, 'recurring_ticket', 'name=' . $rname);
    flash('success', 'Recurring schedule deleted.');
    redirect('/admin/recurring-tickets');
});

/* ==================================================================
 * ADMIN – Ticket Viewing
 * ================================================================== */

$router->get('/admin/tickets', function () {
    Auth::requireAdmin();
    $db = Database::connect();

    // Compute default filter URL for client-side persistence logic
    $defaultFilterUrl = '';
    $defStmt = $db->prepare(
        'SELECT filters FROM saved_filters WHERE user_id = ? AND is_default = 1 LIMIT 1'
    );
    $defStmt->execute([Auth::id()]);
    $defaultFilter = $defStmt->fetchColumn();
    if ($defaultFilter) {
        $filterData = json_decode($defaultFilter, true) ?: [];
        if ($filterData) {
            $defaultFilterUrl = '/admin/tickets?' . http_build_query($filterData);
        }
    }

    // Read filter params (multi-select arrays for status/priority/type/location/agent/group)
    $filters = [
        'status'    => array_values(array_filter(array_map('trim', (array) ($_GET['status']   ?? [])))),
        'priority'  => array_values(array_filter(array_map('trim', (array) ($_GET['priority'] ?? [])))),
        'type'      => array_values(array_filter(array_map('trim', (array) ($_GET['type']     ?? [])))),
        'location'  => array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? [])))),
        'agent'     => array_values(array_filter(array_map('trim', (array) ($_GET['agent']    ?? [])))),
        'group'     => array_values(array_filter(array_map('trim', (array) ($_GET['group']    ?? [])))),
        'requester' => array_values(array_filter(array_map('trim', (array) ($_GET['requester'] ?? [])))),
        'q'         => trim($_GET['q'] ?? ''),
        'date_from' => trim($_GET['date_from'] ?? ''),
        'date_to'   => trim($_GET['date_to'] ?? ''),
        'watched'   => !empty($_GET['watched']) ? '1' : '',
    ];

    $filterResult = buildTicketFilterQuery($filters);
    $whereClause  = $filterResult['where'];
    $params       = $filterResult['params'];

    $sql = "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name, tt.color AS type_color, tt.group_id AS type_group_id,
                g.name  AS group_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         LEFT JOIN `groups` g          ON t.group_id     = g.id";

    // Count total matching tickets
    $countSql = "SELECT COUNT(*) FROM tickets t" . $whereClause;
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalTickets = (int) $countStmt->fetchColumn();
    $allTickets   = (int) $db->query('SELECT COUNT(*) FROM tickets')->fetchColumn();

    // Sorting
    $sortableColumns = [
        'id'         => 't.id',
        'subject'    => 't.subject',
        'status'     => 't.status',
        'priority'   => 'tp.sort_order',
        'type'       => 'tt.name',
        'agent'      => 'a.first_name',
        'creator'    => 'c.first_name',
        'group'      => 'g.name',
        'location'   => 'l.name',
        'created_at' => 't.created_at',
        'due_date'   => 't.due_date',
    ];
    $sort = $_GET['sort'] ?? 'created_at';
    $dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $orderCol = $sortableColumns[$sort] ?? 't.created_at';

    // Pagination
    $allowedPerPage = [25, 50, 100, 200];
    $perPage    = (isset($_GET['per_page']) && in_array((int) $_GET['per_page'], $allowedPerPage, true)) ? (int) $_GET['per_page'] : 25;
    $totalPages = max(1, (int) ceil($totalTickets / $perPage));
    $page       = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
    $offset     = ($page - 1) * $perPage;

    $sql .= $whereClause . " ORDER BY {$orderCol} {$dir} LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    // Load filter dropdown options
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name")->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

    // Build group → agents map for quick-assign dropdowns
    $gaRows = $db->query(
        "SELECT gum.group_id, u.id, CONCAT(u.first_name, ' ', u.last_name) AS name
         FROM group_user_map gum
         JOIN users u ON gum.user_id = u.id
         WHERE " . staffRoleSqlIn('u.role') . "
         ORDER BY u.first_name, u.last_name"
    )->fetchAll();
    $groupAgents = [];
    foreach ($gaRows as $row) {
        $groupAgents[(int) $row['group_id']][] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
    $allAgentsForAssign = $db->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();

    // Load saved filters (own + shared)
    $sfStmt = $db->prepare(
        "SELECT sf.*, CONCAT(u.first_name, ' ', u.last_name) AS owner_name
         FROM saved_filters sf
         JOIN users u ON sf.user_id = u.id
         WHERE sf.user_id = ? OR sf.is_shared = 1
         ORDER BY sf.user_id = ? DESC, sf.name ASC"
    );
    $sfStmt->execute([Auth::id(), Auth::id()]);
    $savedFilters = $sfStmt->fetchAll();

    // Preload confidential ticket type info for redaction in the template
    $confidentialTypeIds = [];
    $adminGroupIds       = [];
    $confStmt = $db->query('SELECT id, group_id FROM ticket_types WHERE is_confidential = 1 AND group_id IS NOT NULL');
    foreach ($confStmt->fetchAll() as $ct) {
        $confidentialTypeIds[] = (int) $ct['id'];
    }
    if (!empty($confidentialTypeIds)) {
        $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gs->execute([Auth::id()]);
        $adminGroupIds = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));
    }

    render('admin/tickets/index', [
        'tickets'              => $tickets,
        'priorities'           => $priorities,
        'types'                => $types,
        'locations'            => $locations,
        'agents'               => $agents,
        'groups'               => $groups,
        'groupAgents'          => $groupAgents,
        'allAgentsForAssign'   => $allAgentsForAssign,
        'filters'              => $filters,
        'savedFilters'         => $savedFilters,
        'page'                 => $page,
        'perPage'              => $perPage,
        'totalPages'           => $totalPages,
        'totalTickets'         => $totalTickets,
        'allTickets'           => $allTickets,
        'sort'                 => $sort,
        'dir'                  => strtolower($dir),
        'visibleColumns'       => getUserColumns(Auth::id()),
        'defaultFilterUrl'     => $defaultFilterUrl,
        'confidentialTypeIds'  => $confidentialTypeIds,
        'adminGroupIds'        => $adminGroupIds,
    ]);
});

/* ── Export Tickets (CSV) ─────────────────────────────────────────── */

$router->get('/admin/tickets/export', function () {
    Auth::requireAdmin();
    $db = Database::connect();

    // Build filters from query params
    $filters = [
        'status'    => array_values(array_filter(array_map('trim', (array) ($_GET['status']   ?? [])))),
        'priority'  => array_values(array_filter(array_map('trim', (array) ($_GET['priority'] ?? [])))),
        'type'      => array_values(array_filter(array_map('trim', (array) ($_GET['type']     ?? [])))),
        'location'  => array_values(array_filter(array_map('trim', (array) ($_GET['location'] ?? [])))),
        'agent'     => array_values(array_filter(array_map('trim', (array) ($_GET['agent']    ?? [])))),
        'group'     => array_values(array_filter(array_map('trim', (array) ($_GET['group']    ?? [])))),
        'requester' => array_values(array_filter(array_map('trim', (array) ($_GET['requester'] ?? [])))),
        'q'         => trim($_GET['q'] ?? ''),
        'date_from' => trim($_GET['date_from'] ?? ''),
        'date_to'   => trim($_GET['date_to'] ?? ''),
        'watched'   => !empty($_GET['watched']) ? '1' : '',
    ];

    $filterResult = buildTicketFilterQuery($filters);
    $whereClause  = $filterResult['where'];
    $params       = $filterResult['params'];

    // Sorting
    $sortableColumns = [
        'id'         => 't.id',
        'subject'    => 't.subject',
        'status'     => 't.status',
        'priority'   => 'tp.sort_order',
        'type'       => 'tt.name',
        'agent'      => 'a.first_name',
        'creator'    => 'c.first_name',
        'group'      => 'g.name',
        'location'   => 'l.name',
        'created_at' => 't.created_at',
        'due_date'   => 't.due_date',
    ];
    $sort     = $_GET['sort'] ?? 'created_at';
    $dir      = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $orderCol = $sortableColumns[$sort] ?? 't.created_at';

    $sql = "SELECT t.*,
                tp.name AS priority_name,
                l.name  AS location_name,
                tt.name AS type_name, tt.color AS type_color,
                tt.is_confidential AS type_confidential, tt.group_id AS type_group_id,
                g.name  AS group_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name,
                (SELECT GROUP_CONCAT(tg.name SEPARATOR ', ')
                 FROM ticket_tag_map ttm
                 JOIN ticket_tags tg ON ttm.tag_id = tg.id
                 WHERE ttm.ticket_id = t.id) AS tag_list
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         LEFT JOIN `groups` g          ON t.group_id     = g.id"
         . $whereClause
         . " ORDER BY {$orderCol} {$dir}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $statusLabels = [
        'open'                   => 'Open',
        'in_progress'            => 'In Progress',
        'pending'                => 'Pending',
        'waiting_on_customer'    => 'Waiting on Customer',
        'waiting_on_third_party' => 'Waiting on Third Party',
        'resolved'               => 'Resolved',
        'closed'                 => 'Closed',
    ];

    $filename = 'tickets-export-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // BOM for Excel UTF-8 compatibility
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($out, [
        'ID', 'Subject', 'Status', 'Priority', 'Type', 'Location',
        'Group', 'Assigned To', 'Created By', 'Tags',
        'Created', 'Due Date', 'SLA State',
    ]);

    // Preload admin's group memberships for confidential redaction
    $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
    $gs->execute([Auth::id()]);
    $exportAdminGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

    while ($row = $stmt->fetch()) {
        // Redact confidential tickets the admin is not in the group for
        $redact = false;
        if (!empty($row['type_confidential']) && !empty($row['type_group_id'])
            && !in_array((int) $row['type_group_id'], $exportAdminGroups, true)) {
            $redact = true;
        }

        fputcsv($out, [
            $row['id'],
            $redact ? '[Confidential]' : $row['subject'],
            $statusLabels[$row['status']] ?? $row['status'],
            $row['priority_name'] ?? '',
            $row['type_name'] ?? '',
            $row['location_name'] ?? '',
            $row['group_name'] ?? '',
            $redact ? '—' : ($row['agent_name'] ?? 'Unassigned'),
            $redact ? '—' : ($row['creator_name'] ?? ''),
            $redact ? '' : ($row['tag_list'] ?? ''),
            $row['created_at'] ? date('Y-m-d H:i', strtotime($row['created_at'])) : '',
            $row['due_date'] ? date('Y-m-d H:i', strtotime($row['due_date'])) : '',
            $row['sla_state'] ?? '',
        ]);
    }

    fclose($out);
    exit;
});

/* ── Column Preferences (Admin) ───────────────────────────────────── */

$router->post('/admin/tickets/columns', function () {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $columns = $_POST['columns'] ?? [];
    if (!is_array($columns)) {
        $columns = [];
    }
    setUserColumns(Auth::id(), $columns);
    redirect($_POST['_redirect'] ?? '/admin/tickets');
});

/* ── Saved Filters (Admin) ────────────────────────────────────────── */

$router->post('/admin/tickets/filters/save', function () {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', 'Filter name is required.');
        redirect('/admin/tickets');
    }

    $filterData = [];
    foreach (['status', 'priority', 'type', 'location', 'agent', 'group'] as $key) {
        $vals = array_values(array_filter(array_map('trim', (array) ($_POST[$key] ?? []))));
        if (!empty($vals)) {
            $filterData[$key] = $vals;
        }
    }
    if (trim($_POST['q'] ?? '') !== '') {
        $filterData['q'] = trim($_POST['q']);
    }

    $db = Database::connect();
    $stmt = $db->prepare('INSERT INTO saved_filters (user_id, name, filters) VALUES (?, ?, ?)');
    $stmt->execute([Auth::id(), $name, json_encode($filterData)]);

    flash('success', 'Filter "' . e($name) . '" saved.');
    $qs = http_build_query($filterData);
    redirect('/admin/tickets' . ($qs ? '?' . $qs : ''));
});

$router->post('/admin/tickets/filters/{id}/delete', function (array $p) {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    if (!$stmt->fetch()) {
        flash('error', 'Filter not found or access denied.');
        redirect('/admin/tickets');
    }

    $db->prepare('DELETE FROM saved_filters WHERE id = ?')->execute([$id]);
    flash('success', 'Filter deleted.');
    redirect('/admin/tickets');
});

$router->post('/admin/tickets/filters/{id}/toggle-share', function (array $p) {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    $filter = $stmt->fetch();
    if (!$filter) {
        flash('error', 'Filter not found or access denied.');
        redirect('/admin/tickets');
    }

    $newShared = $filter['is_shared'] ? 0 : 1;
    $db->prepare('UPDATE saved_filters SET is_shared = ? WHERE id = ?')->execute([$newShared, $id]);
    flash('success', $newShared ? 'Filter is now shared.' : 'Filter is now private.');
    redirect('/admin/tickets');
});

$router->post('/admin/tickets/filters/{id}/toggle-default', function (array $p) {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $id = (int) $p['id'];
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM saved_filters WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    $filter = $stmt->fetch();
    if (!$filter) {
        flash('error', 'Filter not found or access denied.');
        redirect('/admin/tickets');
    }

    if ($filter['is_default']) {
        // Unset default
        $db->prepare('UPDATE saved_filters SET is_default = 0 WHERE id = ?')->execute([$id]);
        flash('success', 'Default filter removed.');
    } else {
        // Clear any existing default for this user, then set this one
        $db->prepare('UPDATE saved_filters SET is_default = 0 WHERE user_id = ?')->execute([Auth::id()]);
        $db->prepare('UPDATE saved_filters SET is_default = 1 WHERE id = ?')->execute([$id]);
        flash('success', '"' . e($filter['name']) . '" is now your default filter.');
    }
    redirect('/admin/tickets');
});

/* ==================================================================
 * ADMIN – Ticket Search (JSON, for merge modal typeahead)
 * ================================================================== */

$router->get('/admin/tickets/search', function () {
    Auth::requireAdmin();
    $db      = Database::connect();
    $q       = trim($_GET['q'] ?? '');
    $exclude = (int) ($_GET['exclude'] ?? 0);

    if ($q === '') {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    $params = [];
    $idMatch = is_numeric($q) ? (int) $q : 0;

    if ($idMatch > 0) {
        $where = 't.id = ? AND t.merged_into_ticket_id IS NULL';
        $params[] = $idMatch;
    } else {
        $where = 't.subject LIKE ? AND t.merged_into_ticket_id IS NULL';
        $params[] = '%' . $q . '%';
    }

    if ($exclude > 0) {
        $where .= ' AND t.id != ?';
        $params[] = $exclude;
    }

    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.status,
                CONCAT(u.first_name, ' ', u.last_name) AS creator_name
         FROM tickets t
         JOIN users u ON t.created_by = u.id
         WHERE {$where}
         ORDER BY t.id DESC
         LIMIT 10"
    );
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Redact confidential ticket subjects for admins not in the type's group
    $confStmt = $db->query('SELECT id, group_id FROM ticket_types WHERE is_confidential = 1 AND group_id IS NOT NULL');
    $confTypes = [];
    foreach ($confStmt->fetchAll() as $ct) {
        $confTypes[(int) $ct['id']] = (int) $ct['group_id'];
    }
    if (!empty($confTypes)) {
        $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gs->execute([Auth::id()]);
        $myGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

        foreach ($results as &$r) {
            $tTypeId = null;
            if (!empty($r['id'])) {
                $ts = $db->prepare('SELECT type_id FROM tickets WHERE id = ?');
                $ts->execute([$r['id']]);
                $tTypeId = $ts->fetchColumn();
            }
            if ($tTypeId && isset($confTypes[(int) $tTypeId])
                && !in_array($confTypes[(int) $tTypeId], $myGroups, true)) {
                $r['subject'] = '[Confidential]';
                $r['creator_name'] = '—';
            }
        }
        unset($r);
    }

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
});

$router->get('/admin/tickets/create', function () {
    Auth::requireStaff();
    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $locations  = $db->query('SELECT * FROM locations ORDER BY name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();
    $agents     = $db->query(
        "SELECT id, first_name, last_name, email FROM users
         WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name, last_name"
    )->fetchAll();
    $templates  = $db->query(
        'SELECT * FROM ticket_templates ORDER BY name'
    )->fetchAll();

    // Per-type form layouts — same approach as the portal create page.
    $formLayouts  = [];
    $customFields = [];
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

    render('admin/tickets/create', [
        'types'         => $types,
        'priorities'    => $priorities,
        'locations'     => $locations,
        'groups'        => $groups,
        'agents'        => $agents,
        'templates'     => $templates,
        'isAgent'       => false,
        'customFields'  => $customFields,
        'fieldOptions'  => $fieldOptions,
        'formLayouts'   => $formLayouts,
    ]);
});

$router->post('/admin/tickets/create', function () {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets/create');
    }

    $subject    = trim($_POST['subject'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $typeId     = !empty($_POST['type_id'])      ? (int) $_POST['type_id']      : null;
    $priId      = !empty($_POST['priority_id'])  ? (int) $_POST['priority_id']  : null;
    $locationId = !empty($_POST['location_id'])  ? (int) $_POST['location_id']  : null;
    $assignedTo = !empty($_POST['assigned_to'])  ? (int) $_POST['assigned_to']  : null;
    $groupId    = !empty($_POST['group_id'])      ? (int) $_POST['group_id']     : null;
    $status     = $_POST['status'] ?? 'open';
    $dueDate    = trim($_POST['due_date'] ?? '') ?: null;
    $tagNames   = $_POST['tags'] ?? [];
    // Agents/admins/power-users can file a ticket on behalf of another user
    // (e.g. someone phones the helpdesk). When set, `created_by` becomes the
    // requester and `submitted_by` records who actually clicked submit so the
    // audit trail isn't lost. Self-submission keeps `submitted_by` NULL.
    $onBehalf    = !empty($_POST['on_behalf_of_id']) ? (int) $_POST['on_behalf_of_id'] : null;
    $canDelegate = Auth::isStaff();
    if ($onBehalf && $canDelegate && $onBehalf !== Auth::id()) {
        $createdBy   = $onBehalf;
        $submittedBy = Auth::id();
    } else {
        $createdBy   = Auth::id();
        $submittedBy = null;
        $onBehalf    = null; // discard if self or not permitted
    }

    if (!in_array($status, ticketActiveStatusSlugs(), true)) {
        $status = ticketDefaultNewStatusSlug();
    }

    if ($subject === '' || $desc === '') {
        flashInput($_POST);
        flash('error', 'Subject and description are required.');
        $redirectBase = (Auth::isStaff() && !Auth::isAdmin()) ? '/agent' : '/admin';
        redirect("{$redirectBase}/tickets/create");
    }

    $db = Database::connect();
    $groupId = resolveTicketGroup($db, $groupId, $typeId);
    // If the priority picker is hidden for this type, the staff form will not
    // have rendered it — fall back to the system default so the ticket has a
    // priority on creation.
    if ($typeId && resolveFieldVisibility($db, $typeId, 'system', 'priority') === 'hidden') {
        $priId = getDefaultPriorityId($db);
    }
    $db->prepare(
        'INSERT INTO tickets (subject, description, created_by, submitted_by, type_id, location_id, status, priority_id, assigned_to, group_id, due_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$subject, $desc, $createdBy, $submittedBy, $typeId, $locationId, $status, $priId, $assignedTo, $groupId, $dueDate]);
    $ticketId = (int) $db->lastInsertId();

    // Always run post-create hooks: AI classification (if enabled & non-confidential)
    // happens regardless of who's assigned; auto-assign no-ops when assigned_to is
    // already set or the group strategy is manual.
    runPostTicketCreateHooks($db, $ticketId);

    // Tags
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

    // CC
    $ccUserIds = array_values(array_unique(array_map('intval', (array) ($_POST['cc_user_ids'] ?? []))));
    if (!empty($ccUserIds)) {
        $ccInsert = $db->prepare('INSERT IGNORE INTO ticket_cc (ticket_id, user_id, added_by) VALUES (?, ?, ?)');
        foreach ($ccUserIds as $uid) {
            if ($uid > 0) {
                $ccInsert->execute([$ticketId, $uid, Auth::id()]);
            }
        }
    }
    // Save custom field values (filtered by this ticket type's layout —
    // hidden fields are skipped so a stale value can't leak through).
    $adminLayout = $typeId ? getFormLayoutForType($db, $typeId, true) : [];
    $adminCustomFields = array_values(array_map(
        fn($r) => $r['field'],
        array_filter($adminLayout, fn($r) => $r['kind'] === 'custom' && $r['field'] !== null)
    ));
    if (!empty($adminCustomFields)) {
        $cfSaveStmt = $db->prepare(
            'INSERT INTO ticket_field_values (ticket_id, field_id, value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        foreach ($adminCustomFields as $cf) {
            if (in_array($cf['field_type'], ['text_block', 'image', 'cc'], true)) continue;
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
    }

    // Timeline — record who actually clicked submit, and (when delegating)
    // who the ticket was filed for so the on-behalf-of relationship is
    // legible in the audit trail.
    if ($submittedBy) {
        $rs = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
        $rs->execute([$createdBy]);
        $requesterName = (string) ($rs->fetchColumn() ?: 'requester');
        $timelineDetails = 'Ticket filed by ' . Auth::fullName()
                         . ' on behalf of ' . $requesterName . '.';
    } else {
        $timelineDetails = 'Ticket created by ' . Auth::fullName() . '.';
    }
    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details) VALUES (?, ?, ?, ?)'
    )->execute([$ticketId, Auth::id(), 'created', $timelineDetails]);

    // If the AI dup-check warned the agent and they overrode it, audit that.
    $dupOverrideCsv = (string) ($_POST['_dup_matched_ids'] ?? '');
    if ($dupOverrideCsv !== '') {
        recordDupOverrideOnNewTicket($db, $ticketId, (int) Auth::id(), $dupOverrideCsv);
    }

    // Initialize SLA timers if priority is set
    if ($priId) {
        Sla::initializeForTicket($db, $ticketId, $priId, $typeId);
    }

    // Notify group members watching new tickets
    notifyGroupMembers($db, $ticketId);

    // Send confirmation email to the requester (gated by global + user prefs)
    notifyRequesterTicketCreated($db, $ticketId);

    // If an assignee was chosen at creation time, notify them and the requester
    if ($assignedTo) {
        notifyAssignedAgent($db, $ticketId, $assignedTo);
        notifyRequesterTicketAssigned($db, $ticketId, $assignedTo);
    }

    flash('success', 'Ticket #' . $ticketId . ' created.');
    $redirectBase = (Auth::isStaff() && !Auth::isAdmin()) ? '/agent' : '/admin';
    redirect("{$redirectBase}/tickets/{$ticketId}");
});

$router->post('/admin/tickets/bulk', function () {
    Auth::requireStaff();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $action    = $_POST['action'] ?? '';
    $rawIds    = $_POST['ticket_ids'] ?? [];
    $ticketIds = array_values(array_unique(array_map('intval', (array) $rawIds)));
    $ticketIds = array_filter($ticketIds, fn($id) => $id > 0);

    if (empty($ticketIds)) {
        flash('error', 'No tickets selected.');
        redirect('/admin/tickets');
    }

    $db          = Database::connect();

    // Filter out confidential tickets the user cannot access
    if (Auth::isAdmin()) {
        $gs = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
        $gs->execute([Auth::id()]);
        $myGroups = array_map('intval', $gs->fetchAll(PDO::FETCH_COLUMN));

        $allowed = [];
        foreach ($ticketIds as $tid) {
            $ts = $db->prepare(
                'SELECT t.type_id, tt.is_confidential, tt.group_id AS type_group_id
                 FROM tickets t LEFT JOIN ticket_types tt ON t.type_id = tt.id WHERE t.id = ?'
            );
            $ts->execute([$tid]);
            $tRow = $ts->fetch();
            if ($tRow && $tRow['is_confidential'] && $tRow['type_group_id']
                && !in_array((int) $tRow['type_group_id'], $myGroups, true)) {
                continue; // skip confidential tickets the admin cannot access
            }
            $allowed[] = $tid;
        }
        $ticketIds = $allowed;
        if (empty($ticketIds)) {
            flash('error', 'No accessible tickets selected (confidential tickets were excluded).');
            redirect('/admin/tickets');
        }
    }

    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));

    switch ($action) {
        case 'close':
            $db->prepare("UPDATE tickets SET status = 'closed' WHERE id IN ({$placeholders})")
               ->execute($ticketIds);
            logAudit(
                'ticket.bulk_closed',
                null,
                'ticket',
                'count=' . count($ticketIds) . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) closed.');
            break;

        case 'assign':
            $assignTo = !empty($_POST['assign_to']) ? (int) $_POST['assign_to'] : null;
            $db->prepare("UPDATE tickets SET assigned_to = ? WHERE id IN ({$placeholders})")
               ->execute(array_merge([$assignTo], $ticketIds));
            $label = $assignTo ? 'reassigned' : 'unassigned';
            logAudit(
                'ticket.bulk_assigned',
                $assignTo,
                'user',
                'count=' . count($ticketIds) . '; assign_to=' . ($assignTo ?? 'none') . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) ' . $label . '.');
            break;

        case 'merge':
            if (count($ticketIds) < 2) {
                flash('error', 'Select at least 2 tickets to merge.');
                redirect('/admin/tickets');
            }
            $primaryId = (int) ($_POST['primary_ticket_id'] ?? 0);
            if ($primaryId > 0 && in_array($primaryId, $ticketIds)) {
                $targetId  = $primaryId;
                $ticketIds = array_values(array_filter($ticketIds, fn($id) => $id !== $primaryId));
            } else {
                sort($ticketIds);
                $targetId = array_shift($ticketIds);
            }
            $tgt = $db->prepare('SELECT id, subject FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
            $tgt->execute([$targetId]);
            $targetTicket = $tgt->fetch();
            if (!$targetTicket) {
                flash('error', 'Primary ticket not found or already merged.');
                redirect('/admin/tickets');
            }
            $actor  = Auth::fullName();
            // Find the highest priority across all tickets being merged (target + sources)
            $allIds = array_merge([$targetId], $ticketIds);
            $allPlaceholders = implode(',', array_fill(0, count($allIds), '?'));
            $hpStmt = $db->prepare(
                "SELECT tp.id FROM ticket_priorities tp
                 JOIN tickets t ON t.priority_id = tp.id
                 WHERE t.id IN ({$allPlaceholders})
                 ORDER BY tp.sort_order DESC LIMIT 1"
            );
            $hpStmt->execute($allIds);
            $bestPriorityId = $hpStmt->fetchColumn() ?: null;
            $merged = 0;
            foreach ($ticketIds as $sourceId) {
                $src = $db->prepare('SELECT id, subject FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
                $src->execute([$sourceId]);
                $sourceTicket = $src->fetch();
                if (!$sourceTicket) continue;
                $db->beginTransaction();
                try {
                    $db->prepare('INSERT IGNORE INTO ticket_cc (ticket_id, user_id, added_by) SELECT ?, user_id, ? FROM ticket_cc WHERE ticket_id = ?')
                       ->execute([$targetId, Auth::id(), $sourceId]);
                    $db->prepare('INSERT IGNORE INTO ticket_tag_map (ticket_id, tag_id) SELECT ?, tag_id FROM ticket_tag_map WHERE ticket_id = ?')
                       ->execute([$targetId, $sourceId]);
                    $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
                       ->execute([$targetId, Auth::id(), 'merged', "Ticket #{$sourceId} ({$sourceTicket['subject']}) was merged into this ticket by {$actor}"]);
                    $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
                       ->execute([$sourceId, Auth::id(), 'merged', "This ticket was merged into #{$targetId} ({$targetTicket['subject']}) by {$actor}"]);
                    $db->prepare('UPDATE tickets SET status = ?, merged_into_ticket_id = ? WHERE id = ?')
                       ->execute(['closed', $targetId, $sourceId]);
                    $db->commit();
                    notifyTicketMerged($db, $sourceId, $targetId);
                    $merged++;
                } catch (\Throwable $e) {
                    $db->rollBack();
                }
            }
            // Escalate primary ticket priority to the highest across all merged tickets
            if ($bestPriorityId) {
                $curPriStmt = $db->prepare('SELECT priority_id FROM tickets WHERE id = ?');
                $curPriStmt->execute([$targetId]);
                $curPriority = $curPriStmt->fetchColumn();
                if ($bestPriorityId != $curPriority) {
                    $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')
                       ->execute([$bestPriorityId, $targetId]);
                    $npStmt = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
                    $npStmt->execute([$bestPriorityId]);
                    $priorityLabel = $npStmt->fetchColumn();
                    $db->prepare('INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)')
                       ->execute([$targetId, Auth::id(), 'priority_changed', "Priority escalated to {$priorityLabel} during merge by {$actor}"]);
                }
            }
            logAudit(
                'ticket.bulk_merged',
                $targetId,
                'ticket',
                'count=' . $merged . '; primary=' . $targetId . '; sources=' . implode(',', $ticketIds)
            );
            flash('success', "{$merged} ticket(s) merged into #{$targetId}.");
            redirect("/admin/tickets/{$targetId}");

        case 'delete':
            Auth::requireAdmin();
            $files = $db->prepare("SELECT stored_name FROM ticket_attachments WHERE ticket_id IN ({$placeholders})");
            $files->execute($ticketIds);
            foreach ($files->fetchAll() as $f) {
                $path = ATTACHMENT_STORAGE_PATH . $f['stored_name'];
                if (file_exists($path)) unlink($path);
            }
            $db->prepare("DELETE FROM tickets WHERE id IN ({$placeholders})")->execute($ticketIds);
            logAudit(
                'ticket.bulk_deleted',
                null,
                'ticket',
                'count=' . count($ticketIds) . '; ids=' . implode(',', $ticketIds)
            );
            flash('success', count($ticketIds) . ' ticket(s) deleted.');
            break;

        default:
            flash('error', 'Unknown action.');
    }

    redirect('/admin/tickets');
});

$router->get('/admin/tickets/{id}', function (array $p) {
    Auth::requireAdmin();
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*,
                tp.name AS priority_name, tp.color AS priority_color,
                l.name  AS location_name,
                tt.name AS type_name, tt.color AS type_color,
                c.first_name AS creator_first_name, c.last_name AS creator_last_name,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name, c.email AS creator_email,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name,
                CONCAT(s.first_name, ' ', s.last_name) AS submitter_name, s.email AS submitter_email,
                g.name AS group_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN ticket_types tt     ON t.type_id     = tt.id
         LEFT JOIN locations l         ON t.location_id  = l.id
         LEFT JOIN users c             ON t.created_by   = c.id
         LEFT JOIN users a             ON t.assigned_to  = a.id
         LEFT JOIN users s             ON t.submitted_by = s.id
         LEFT JOIN `groups` g          ON t.group_id     = g.id
         WHERE t.id = ?"
    );
    $stmt->execute([(int) $p['id']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/admin/tickets');
    }

    // Confidential ticket re-authentication gate
    if (requiresConfidentialReAuth($db, $ticket)) {
        $ticketId   = (int) $ticket['id'];
        $sessionKey = "confidential_access_{$ticketId}";
        $granted    = $_SESSION[$sessionKey] ?? 0;
        $ttl        = 300; // 5-minute window after re-auth

        if (!$granted || (time() - $granted) > $ttl) {
            render('admin/tickets/confidential-reauth', [
                'ticketId' => $ticketId,
            ]);
            return;
        }
    }

    // Tags
    $tags = $db->prepare(
        'SELECT tt.name FROM ticket_tags tt
         INNER JOIN ticket_tag_map ttm ON tt.id = ttm.tag_id
         WHERE ttm.ticket_id = ?'
    );
    $tags->execute([$ticket['id']]);
    $ticket['tags'] = $tags->fetchAll(PDO::FETCH_COLUMN);

    // Timeline
    $tl = $db->prepare(
        "SELECT tl.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_timeline tl
         LEFT JOIN users u ON tl.user_id = u.id
         WHERE tl.ticket_id = ?
         ORDER BY tl.created_at DESC"
    );
    $tl->execute([$ticket['id']]);
    $timeline = $tl->fetchAll();

    // Agents list for @mention suggestions and assignment dropdown
    // If the ticket belongs to a group, only show members of that group
    if (!empty($ticket['group_id'])) {
        $agentStmt = $db->prepare(
            "SELECT u.id, u.first_name, u.last_name
             FROM users u
             INNER JOIN group_user_map gum ON u.id = gum.user_id
             WHERE gum.group_id = ? AND " . staffRoleSqlIn('u.role') . "
             ORDER BY u.first_name"
        );
        $agentStmt->execute([$ticket['group_id']]);
        $agents = $agentStmt->fetchAll();
    } else {
        $agents = $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name")->fetchAll();
    }

    // Priorities for update dropdown
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();

    // Attachments (admins see all including internal)
    $attStmt = $db->prepare(
        'SELECT ta.*, tl.is_internal FROM ticket_attachments ta
         LEFT JOIN ticket_timeline tl ON ta.timeline_id = tl.id
         WHERE ta.ticket_id = ?
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

    $groups = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

    // Ticket types for update dropdown
    $ticketTypes = $db->query('SELECT id, name FROM ticket_types ORDER BY sort_order, name')->fetchAll();

    // Custom form fields + stored values. Only show fields on this ticket
    // type's form — plus any field that already has a stored value, so a
    // value submitted before the form changed is never silently dropped.
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

    // Is the current user watching this ticket?
    $watchStmt = $db->prepare('SELECT 1 FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?');
    $watchStmt->execute([$ticket['id'], Auth::id()]);
    $isWatching = (bool) $watchStmt->fetchColumn();

    // AI classification (if any) — drives the badge / override modal in the sidebar
    $aiClassification = null;
    $aiSkillsForOverride = [];
    if (!empty($ticket['ai_classification_id'])) {
        $cStmt = $db->prepare(
            "SELECT id, ticket_id, provider, model, suggested_skill_ids,
                    overridden_skill_ids, overridden_by, overridden_at, override_reason,
                    confidence, sentiment, reasoning, latency_ms, prompt_tokens, output_tokens, created_at
             FROM ai_classifications WHERE id = ?"
        );
        $cStmt->execute([(int) $ticket['ai_classification_id']]);
        $aiClassification = $cStmt->fetch() ?: null;

        if ($aiClassification) {
            $sugIds = json_decode((string) ($aiClassification['suggested_skill_ids']  ?? '[]'), true) ?: [];
            $ovrIds = json_decode((string) ($aiClassification['overridden_skill_ids'] ?? '[]'), true) ?: [];
            $aiClassification['suggested_skill_ids']  = array_map('intval', $sugIds);
            $aiClassification['overridden_skill_ids'] = array_map('intval', $ovrIds);

            // Skill list for the override modal: same shape as the manager UI
            // (global skills + skills owned by this ticket's group).
            $groupId = $ticket['group_id'] !== null ? (int) $ticket['group_id'] : null;
            if ($groupId !== null) {
                $skStmt = $db->prepare(
                    "SELECT id, name, group_id FROM agent_skills
                     WHERE group_id IS NULL OR group_id = ?
                     ORDER BY (group_id IS NULL) DESC, sort_order, name"
                );
                $skStmt->execute([$groupId]);
            } else {
                $skStmt = $db->query("SELECT id, name, group_id FROM agent_skills WHERE group_id IS NULL ORDER BY sort_order, name");
            }
            $aiSkillsForOverride = $skStmt->fetchAll();
        }
    }

    // AI group routing record (if any) — drives the "No Wrong Door"
    // audit card in the sidebar. Joined to groups so the template can
    // display human-readable names without another query.
    $aiGroupClassification = null;
    if (!empty($ticket['ai_group_classification_id'])) {
        $gcStmt = $db->prepare(
            "SELECT gc.id, gc.ticket_id, gc.provider, gc.model,
                    gc.candidate_group_ids, gc.suggested_group_id, gc.applied_group_id,
                    gc.confidence, gc.reasoning, gc.latency_ms, gc.created_at,
                    sg.name AS suggested_group_name,
                    ag.name AS applied_group_name
             FROM ai_group_classifications gc
             LEFT JOIN `groups` sg ON sg.id = gc.suggested_group_id
             LEFT JOIN `groups` ag ON ag.id = gc.applied_group_id
             WHERE gc.id = ?"
        );
        $gcStmt->execute([(int) $ticket['ai_group_classification_id']]);
        $aiGroupClassification = $gcStmt->fetch() ?: null;
    }

    render('admin/tickets/view', [
        'ticket' => $ticket, 'timeline' => $timeline, 'agents' => $agents,
        'priorities' => $priorities, 'ticketTypes' => $ticketTypes,
        'attachments' => $attachments, 'ccUsers' => $ccUsers, 'groups' => $groups,
        'customFields' => $customFields, 'fieldValues' => $fieldValues,
        'fieldOptions' => $fieldOptions, 'isWatching' => $isWatching,
        'aiClassification' => $aiClassification, 'aiSkillsForOverride' => $aiSkillsForOverride,
        'aiGroupClassification' => $aiGroupClassification,
        'aiEnabled' => getSetting('ai_enabled', '0') === '1',
    ]);
});

/* ==================================================================
 * ADMIN – Confidential Ticket Re-Authentication
 * ================================================================== */

$router->post('/admin/tickets/{id}/confidential-auth', function (array $p) {
    Auth::requireAdmin();
    $ticketId = (int) $p['id'];

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$ticketId}");
    }

    $password = $_POST['password'] ?? '';
    $db = Database::connect();

    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([Auth::id()]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        flash('error', 'Incorrect password. Please try again.');
        redirect("/admin/tickets/{$ticketId}");
    }

    $_SESSION["confidential_access_{$ticketId}"] = time();

    logAudit(
        'ticket.confidential_viewed',
        $ticketId,
        'ticket',
        'Admin ' . Auth::fullName() . ' (ID: ' . Auth::id() . ') accessed confidential ticket #' . $ticketId
    );

    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
         VALUES (?, ?, ?, ?, 1)'
    )->execute([
        $ticketId,
        Auth::id(),
        'confidential_access',
        Auth::fullName() . ' viewed this confidential ticket (IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ')',
    ]);

    notifyConfidentialAccess($db, $ticketId);

    redirect("/admin/tickets/{$ticketId}");
});

/* ==================================================================
 * ADMIN – Watch / Unwatch Ticket
 * ================================================================== */

$router->post('/admin/tickets/{id}/watch', function (array $p) {
    Auth::requireAdmin();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        redirect("/admin/tickets/{$id}");
    }
    $db = Database::connect();
    $check = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        redirect('/admin/tickets');
    }
    $existing = $db->prepare('SELECT 1 FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?');
    $existing->execute([$id, Auth::id()]);
    if ($existing->fetchColumn()) {
        $db->prepare('DELETE FROM ticket_watchers WHERE ticket_id = ? AND user_id = ?')
           ->execute([$id, Auth::id()]);
    } else {
        $db->prepare('INSERT IGNORE INTO ticket_watchers (ticket_id, user_id) VALUES (?, ?)')
           ->execute([$id, Auth::id()]);
    }
    redirect("/admin/tickets/{$id}");
});

/* ==================================================================
 * ADMIN – Mark / Unmark a Comment as the Solution
 *
 * Mirror of the agent route — see src/routes/agent.php for the
 * privacy rationale around rejecting internal notes.
 * ================================================================== */

$router->post('/admin/tickets/{id}/solution', function (array $p) {
    Auth::requireAdmin();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$id}");
    }

    $db = Database::connect();
    $tStmt = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $tStmt->execute([$id]);
    if (!$tStmt->fetch()) {
        flash('error', 'Ticket not found.');
        redirect('/admin/tickets');
    }

    $rawTl = trim((string) ($_POST['timeline_id'] ?? ''));

    if ($rawTl === '' || $rawTl === '0') {
        $db->prepare('UPDATE tickets SET solution_timeline_id = NULL WHERE id = ?')
           ->execute([$id]);
        flash('success', 'Solution unmarked.');
        redirect("/admin/tickets/{$id}");
    }

    $tlId = (int) $rawTl;
    $check = $db->prepare(
        'SELECT id, action, is_internal FROM ticket_timeline WHERE id = ? AND ticket_id = ?'
    );
    $check->execute([$tlId, $id]);
    $row = $check->fetch();
    if (!$row) {
        flash('error', 'That comment is not on this ticket.');
        redirect("/admin/tickets/{$id}");
    }
    if (!in_array($row['action'], ['comment'], true)) {
        flash('error', 'Only customer-visible replies can be marked as the solution.');
        redirect("/admin/tickets/{$id}");
    }
    if ((int) $row['is_internal'] === 1) {
        flash('error', 'Internal notes cannot be marked as the solution — the requester would not be able to see it.');
        redirect("/admin/tickets/{$id}");
    }

    $db->prepare('UPDATE tickets SET solution_timeline_id = ? WHERE id = ?')
       ->execute([$tlId, $id]);
    flash('success', 'Marked as the solution.');
    redirect("/admin/tickets/{$id}#timeline-entry-{$tlId}");
});

/* ==================================================================
 * ADMIN – Merge Ticket into Another
 * ================================================================== */

$router->post('/admin/tickets/{id}/merge', function (array $p) {
    Auth::requireAdmin();
    $sourceId = (int) $p['id'];

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$sourceId}");
    }

    $targetId = (int) ($_POST['merge_into_id'] ?? 0);

    if ($targetId === 0 || $targetId === $sourceId) {
        flash('error', 'Please select a valid ticket to merge into.');
        redirect("/admin/tickets/{$sourceId}");
    }

    $db = Database::connect();

    // Validate source ticket (must exist and not already merged)
    $src = $db->prepare('SELECT id, subject, priority_id, type_id FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $src->execute([$sourceId]);
    $sourceTicket = $src->fetch();
    if (!$sourceTicket) {
        flash('error', 'Source ticket not found or already merged.');
        redirect("/admin/tickets/{$sourceId}");
    }

    // Validate target ticket (must exist and not itself be merged)
    $tgt = $db->prepare('SELECT id, subject, priority_id, type_id FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $tgt->execute([$targetId]);
    $targetTicket = $tgt->fetch();
    if (!$targetTicket) {
        flash('error', 'Target ticket not found or is itself a merged ticket.');
        redirect("/admin/tickets/{$sourceId}");
    }

    // Block merging if either ticket is confidential and admin is not in the group
    if (requiresConfidentialReAuth($db, $sourceTicket) || requiresConfidentialReAuth($db, $targetTicket)) {
        flash('error', 'Cannot merge confidential tickets without proper access. Please view each ticket first.');
        redirect("/admin/tickets/{$sourceId}");
    }

    // Find the highest priority across both tickets
    $hpStmt = $db->prepare(
        'SELECT tp.id FROM ticket_priorities tp
         JOIN tickets t ON t.priority_id = tp.id
         WHERE t.id IN (?, ?)
         ORDER BY tp.sort_order DESC LIMIT 1'
    );
    $hpStmt->execute([$sourceId, $targetId]);
    $bestPriorityId = $hpStmt->fetchColumn() ?: null;

    $db->beginTransaction();
    try {
        $actor = Auth::fullName();

        // Copy CC users from source to target (skip duplicates)
        $db->prepare(
            'INSERT IGNORE INTO ticket_cc (ticket_id, user_id, added_by)
             SELECT ?, user_id, ? FROM ticket_cc WHERE ticket_id = ?'
        )->execute([$targetId, Auth::id(), $sourceId]);

        // Copy tags from source to target (skip duplicates)
        $db->prepare(
            'INSERT IGNORE INTO ticket_tag_map (ticket_id, tag_id)
             SELECT ?, tag_id FROM ticket_tag_map WHERE ticket_id = ?'
        )->execute([$targetId, $sourceId]);

        // Escalate target priority to the highest of all merged tickets
        if ($bestPriorityId && $bestPriorityId != $targetTicket['priority_id']) {
            $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')
               ->execute([$bestPriorityId, $targetId]);
            $npStmt = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
            $npStmt->execute([$bestPriorityId]);
            $priorityLabel = $npStmt->fetchColumn();
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
            )->execute([
                $targetId, Auth::id(), 'priority_changed',
                "Priority escalated to {$priorityLabel} during merge by {$actor}",
            ]);
        }

        // Timeline entry on master ticket
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $targetId, Auth::id(), 'merged',
            "Ticket #{$sourceId} ({$sourceTicket['subject']}) was merged into this ticket by {$actor}",
        ]);

        // Timeline entry on source ticket
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $sourceId, Auth::id(), 'merged',
            "This ticket was merged into #{$targetId} ({$targetTicket['subject']}) by {$actor}",
        ]);

        // Close source ticket and set merged_into_ticket_id
        $db->prepare(
            'UPDATE tickets SET status = ?, merged_into_ticket_id = ? WHERE id = ?'
        )->execute(['closed', $targetId, $sourceId]);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        flash('error', 'Merge failed. Please try again.');
        redirect("/admin/tickets/{$sourceId}");
    }

    notifyTicketMerged($db, $sourceId, $targetId);

    flash('success', "Ticket #{$sourceId} merged into #{$targetId}.");
    redirect("/admin/tickets/{$targetId}");
});

/* ==================================================================
 * ADMIN – Split Ticket
 * ================================================================== */

$router->get('/admin/tickets/{id}/split', function (array $p) {
    Auth::requireAdmin();
    $db = Database::connect();

    $stmt = $db->prepare(
        "SELECT t.*, l.name AS location_name, tt.name AS type_name, tt.color AS type_color,
                CONCAT(c.first_name, ' ', c.last_name) AS creator_name
         FROM tickets t
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         LEFT JOIN locations l     ON t.location_id = l.id
         LEFT JOIN users c         ON t.created_by = c.id
         WHERE t.id = ? AND t.merged_into_ticket_id IS NULL"
    );
    $stmt->execute([(int) $p['id']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found or already merged.');
        redirect('/admin/tickets');
    }

    // Public comments only
    $commentsStmt = $db->prepare(
        "SELECT tl.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM ticket_timeline tl
         LEFT JOIN users u ON tl.user_id = u.id
         WHERE tl.ticket_id = ? AND tl.action = 'comment' AND tl.is_internal = 0
         ORDER BY tl.created_at ASC"
    );
    $commentsStmt->execute([$ticket['id']]);
    $comments = $commentsStmt->fetchAll();

    $agents     = $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name")->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $groups     = $db->query('SELECT * FROM `groups` ORDER BY sort_order, name')->fetchAll();

    render('admin/tickets/split', compact('ticket', 'comments', 'agents', 'priorities', 'types', 'groups'));
});

$router->post('/admin/tickets/{id}/split', function (array $p) {
    Auth::requireAdmin();
    $sourceId = (int) $p['id'];

    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$sourceId}/split");
    }

    $subject  = trim($_POST['subject'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $typeId   = ($_POST['type_id'] ?? '') !== '' ? (int) $_POST['type_id'] : null;
    $priId    = ($_POST['priority_id'] ?? '') !== '' ? (int) $_POST['priority_id'] : null;
    $assignTo = ($_POST['assigned_to'] ?? '') !== '' ? (int) $_POST['assigned_to'] : null;
    $groupId  = ($_POST['group_id'] ?? '') !== '' ? (int) $_POST['group_id'] : null;
    $moveIds  = array_filter(array_map('intval', (array) ($_POST['move_comments'] ?? [])));

    if ($subject === '') {
        flash('error', 'Subject is required for the new ticket.');
        redirect("/admin/tickets/{$sourceId}/split");
    }

    $db = Database::connect();

    $src = $db->prepare('SELECT * FROM tickets WHERE id = ? AND merged_into_ticket_id IS NULL');
    $src->execute([$sourceId]);
    $sourceTicket = $src->fetch();
    if (!$sourceTicket) {
        flash('error', 'Source ticket not found or already merged.');
        redirect('/admin/tickets');
    }

    if (requiresConfidentialReAuth($db, $sourceTicket)) {
        flash('error', 'Re-authenticate to access this confidential ticket before splitting.');
        redirect("/admin/tickets/{$sourceId}");
    }

    $newId = null;
    $db->beginTransaction();
    try {
        $actor = Auth::fullName();

        // Create new ticket (inherit location from source)
        $groupId = resolveTicketGroup($db, $groupId, $typeId);
        $db->prepare(
            'INSERT INTO tickets (subject, description, created_by, type_id, location_id, status, priority_id, assigned_to, group_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$subject, $desc, Auth::id(), $typeId, $sourceTicket['location_id'], 'open', $priId, $assignTo, $groupId]);
        $newId = (int) $db->lastInsertId();

        runPostTicketCreateHooks($db, $newId);

        // Timeline entry on new ticket
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $newId, Auth::id(), 'split',
            "This ticket was created by splitting Ticket #{$sourceId} ({$sourceTicket['subject']}) by {$actor}.",
        ]);

        // Move selected comments to new ticket
        $moved = 0;
        if ($moveIds) {
            $placeholders = implode(',', array_fill(0, count($moveIds), '?'));
            $verifyStmt = $db->prepare(
                "SELECT id FROM ticket_timeline WHERE id IN ({$placeholders}) AND ticket_id = ? AND action = 'comment' AND is_internal = 0"
            );
            $verifyStmt->execute(array_merge(array_values($moveIds), [$sourceId]));
            $validIds = $verifyStmt->fetchAll(PDO::FETCH_COLUMN);

            if ($validIds) {
                $ph2 = implode(',', array_fill(0, count($validIds), '?'));
                $db->prepare("UPDATE ticket_timeline SET ticket_id = ? WHERE id IN ({$ph2})")
                   ->execute(array_merge([$newId], $validIds));
                $db->prepare("UPDATE ticket_attachments SET ticket_id = ? WHERE timeline_id IN ({$ph2})")
                   ->execute(array_merge([$newId], $validIds));
                $moved = count($validIds);
            }
        }

        // Timeline entry on source ticket
        $moveLine = $moved > 0 ? " {$moved} comment(s) moved to new ticket." : '';
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([
            $sourceId, Auth::id(), 'split',
            "Ticket split: new Ticket #{$newId} (\"{$subject}\") created by {$actor}.{$moveLine}",
        ]);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        flash('error', 'Split failed. Please try again.');
        redirect("/admin/tickets/{$sourceId}/split");
    }

    flash('success', "Ticket #{$sourceId} split — new Ticket #{$newId} created.");
    redirect("/admin/tickets/{$newId}");
});

/* ==================================================================
 * ADMIN – Save Custom Field Values on a Ticket
 * ================================================================== */

$router->post('/admin/tickets/{id}/fields', function (array $p) {
    Auth::requireAdmin();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$id}");
    }

    $db = Database::connect();
    // Verify ticket exists
    $t = $db->prepare('SELECT id FROM tickets WHERE id = ?');
    $t->execute([$id]);
    if (!$t->fetch()) {
        flash('error', 'Ticket not found.');
        redirect('/admin/tickets');
    }

    $fields = $db->query('SELECT * FROM ticket_form_fields WHERE deleted_at IS NULL ORDER BY id')->fetchAll();
    $saveStmt = $db->prepare(
        'INSERT INTO ticket_field_values (ticket_id, field_id, value) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );

    foreach ($fields as $field) {
        $key = 'field_' . $field['id'];
        if ($field['field_type'] === 'dependent') {
            $val = json_encode([
                'l1' => $_POST[$key . '_l1'] ?? null,
                'l2' => $_POST[$key . '_l2'] ?? null,
                'l3' => $_POST[$key . '_l3'] ?? null,
            ]);
        } elseif ($field['field_type'] === 'date_range') {
            $from = $_POST[$key . '_from'] ?? '';
            $to   = $_POST[$key . '_to']   ?? '';
            $val  = ($from !== '' || $to !== '') ? json_encode(['from' => $from, 'to' => $to]) : null;
        } elseif ($field['field_type'] === 'checkbox') {
            $val = isset($_POST[$key]) ? '1' : '0';
        } else {
            $val = $_POST[$key] ?? null;
        }
        if ($val === null || $val === '') {
            // Delete stored value if cleared
            $db->prepare('DELETE FROM ticket_field_values WHERE ticket_id = ? AND field_id = ?')
               ->execute([$id, $field['id']]);
            continue;
        }
        $saveStmt->execute([$id, $field['id'], $val]);
    }

    flash('success', 'Custom fields updated.');
    redirect("/admin/tickets/{$id}");
});

/* ==================================================================
 * ADMIN – Add Comment / Internal Note to Ticket
 * ================================================================== */

$router->post('/admin/tickets/{id}/comment', function (array $p) {
    Auth::requireAdmin();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$id}");
    }

    $message    = trim($_POST['message'] ?? '');
    $isInternal = !empty($_POST['is_internal']) ? 1 : 0;

    if ($message === '') {
        flash('error', 'Message cannot be empty.');
        redirect("/admin/tickets/{$id}");
    }

    $db = Database::connect();

    // Verify ticket exists
    $stmt = $db->prepare('SELECT id, assigned_to FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    $commentTicket = $stmt->fetch();
    if (!$commentTicket) {
        flash('error', 'Ticket not found.');
        redirect('/admin/tickets');
    }

    $db->prepare(
        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, ?)'
    )->execute([$id, Auth::id(), 'comment', $message, $isInternal]);
    $timelineId = (int) $db->lastInsertId();

    // Process @mentions and create notifications
    processAtMentions($db, $message, $id, $timelineId, Auth::id());

    // Handle file attachments
    $attachments = handleAttachmentUploads('attachments');
    saveAttachments($db, $id, $timelineId, Auth::id(), $attachments);

    // Email the ticket creator for non-internal comments
    if (!$isInternal) {
        notifyTicketCreator($db, $id, $message, Auth::fullName());
        notifyCcUsers($db, $id, $message, Auth::fullName());
        notifyWatchers($db, $id, $message, Auth::fullName());

        // SLA: record first response if this is the first agent/admin public reply
        $ticket = $db->prepare('SELECT created_by, first_responded_at FROM tickets WHERE id = ?');
        $ticket->execute([$id]);
        $tRow = $ticket->fetch();
        if ($tRow && $tRow['first_responded_at'] === null && (int) $tRow['created_by'] !== Auth::id()) {
            $db->prepare('UPDATE tickets SET first_responded_at = NOW() WHERE id = ?')->execute([$id]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
            )->execute([$id, 'sla_set', 'First response recorded']);
        }
    } else {
        notifyAgentNoteAdded($db, $id, $message);
    }

    // Auto-assign unassigned ticket to the replying admin
    if (!$isInternal && $commentTicket['assigned_to'] === null) {
        $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([Auth::id(), $id]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'assigned', 'Ticket auto-assigned to ' . Auth::fullName() . ' upon reply']);
        flash('info', 'This ticket was unassigned — it has been automatically assigned to you.');
    }

    $base = $isInternal ? 'Internal note added.' : 'Comment added.';
    if (!empty($attachments)) {
        $base .= ' ' . count($attachments) . ' file(s) attached.';
    }

    // Optional: change ticket status after posting
    $statusAfter  = trim($_POST['status_after'] ?? '');
    if ($statusAfter !== '' && in_array($statusAfter, ticketActiveStatusSlugs(), true)) {
        $csStmt = $db->prepare('SELECT status FROM tickets WHERE id = ?');
        $csStmt->execute([$id]);
        $currentTicket = $csStmt->fetch();
        if ($currentTicket && $currentTicket['status'] !== $statusAfter) {
            $oldStatus = $currentTicket['status'];
            $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$statusAfter, $id]);
            $db->prepare(
                'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
            )->execute([$id, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$statusAfter}"]);
            $csatTrigger = getSetting('csat_trigger_status', ticketDefaultResolvedStatusSlug());
            if ($statusAfter === $csatTrigger) {
                sendCsatSurvey($db, $id);
            }
            $pausingStatuses = ticketSlaPausingSlugs();
            if (in_array($statusAfter, $pausingStatuses, true)) {
                Sla::pause($db, $id);
            } elseif (in_array($oldStatus, $pausingStatuses, true)) {
                Sla::resume($db, $id);
            }
            if (in_array($statusAfter, ticketClosedBucketSlugs(), true)) {
                notifyRequesterStatusChanged($db, $id, $statusAfter);
            }
            $base .= ' Status set to ' . ticketStatusLabel($statusAfter) . '.';
        }
    }

    flash('success', $base);
    redirect("/admin/tickets/{$id}");
});

/* ==================================================================
 * ADMIN – Update Ticket (status, priority, assignment)
 * ================================================================== */

$router->post('/admin/tickets/{id}/update', function (array $p) {
    Auth::requireAdmin();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/tickets/{$id}");
    }

    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        flash('error', 'Ticket not found.');
        redirect('/admin/tickets');
    }

    $changes = [];

    // Status change
    $newStatus = $_POST['status'] ?? '';
    if ($newStatus !== '' && in_array($newStatus, ticketActiveStatusSlugs(), true) && $newStatus !== $ticket['status']) {
        $oldStatus = $ticket['status'];
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'status_changed', "Status changed from {$oldStatus} to {$newStatus}"]);
        $changes[] = 'status';

        if (in_array($newStatus, ticketClosedBucketSlugs(), true)) {
            notifyRequesterStatusChanged($db, $id, $newStatus);
        }

        // SLA: pause on waiting statuses, resume when leaving them
        $pausingStatuses = ticketSlaPausingSlugs();
        if (in_array($newStatus, $pausingStatuses, true)) {
            Sla::pause($db, $id);
        } elseif (in_array($oldStatus, $pausingStatuses, true)) {
            Sla::resume($db, $id);
        }
    }

    // Priority change
    $newPriorityRaw = $_POST['priority_id'] ?? '';
    $newPriority = $newPriorityRaw === '' ? null : (int) $newPriorityRaw;
    $oldPriority = $ticket['priority_id'] ? (int) $ticket['priority_id'] : null;
    if ($newPriority !== $oldPriority) {
        $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')->execute([$newPriority, $id]);

        $oldName = 'None';
        $newName = 'None';
        if ($oldPriority) {
            $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
            $s->execute([$oldPriority]);
            $oldName = $s->fetchColumn() ?: 'None';
        }
        if ($newPriority) {
            $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
            $s->execute([$newPriority]);
            $newName = $s->fetchColumn() ?: 'None';
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'priority_changed', "Priority changed from {$oldName} to {$newName}"]);
        $changes[] = 'priority';

        if ($newPriority) {
            Sla::onPriorityChanged($db, $id, $newPriority, $ticket['type_id'] ? (int) $ticket['type_id'] : null);
        }
    }

    // Assignment change
    $newAssignedRaw = $_POST['assigned_to'] ?? '';
    $newAssigned = $newAssignedRaw === '' ? null : (int) $newAssignedRaw;
    $oldAssigned = $ticket['assigned_to'] ? (int) $ticket['assigned_to'] : null;
    if ($newAssigned !== $oldAssigned) {
        $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$newAssigned, $id]);

        $agentName = 'Unassigned';
        if ($newAssigned) {
            $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
            $s->execute([$newAssigned]);
            $agentName = $s->fetchColumn() ?: 'Unknown';
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'assigned', "Assigned to {$agentName}"]);
        $changes[] = 'assignment';
        if ($newAssigned) {
            notifyAssignedAgent($db, $id, $newAssigned);
            notifyRequesterTicketAssigned($db, $id, $newAssigned);
        }
    }

    // Group change
    $newGroupRaw = $_POST['group_id'] ?? '';
    $newGroup = $newGroupRaw === '' ? null : (int) $newGroupRaw;
    $oldGroup = $ticket['group_id'] ? (int) $ticket['group_id'] : null;
    if ($newGroup !== $oldGroup) {
        $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$newGroup, $id]);

        $oldGroupName = 'None';
        $newGroupName = 'None';
        if ($oldGroup) {
            $s = $db->prepare('SELECT name FROM `groups` WHERE id = ?');
            $s->execute([$oldGroup]);
            $oldGroupName = $s->fetchColumn() ?: 'None';
        }
        if ($newGroup) {
            $s = $db->prepare('SELECT name FROM `groups` WHERE id = ?');
            $s->execute([$newGroup]);
            $newGroupName = $s->fetchColumn() ?: 'None';
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'group_changed', "Group changed from {$oldGroupName} to {$newGroupName}"]);
        $changes[] = 'group';
        if ($newGroup) { notifyAssignedGroup($db, $id, $newGroup); }
    }

    // Type change
    $newTypeRaw = $_POST['type_id'] ?? '';
    $newType = $newTypeRaw === '' ? null : (int) $newTypeRaw;
    $oldType = $ticket['type_id'] ? (int) $ticket['type_id'] : null;
    if ($newType !== $oldType) {
        $db->prepare('UPDATE tickets SET type_id = ? WHERE id = ?')->execute([$newType, $id]);

        $oldTypeName = 'None';
        $newTypeName = 'None';
        if ($oldType) {
            $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
            $s->execute([$oldType]);
            $oldTypeName = $s->fetchColumn() ?: 'None';
        }
        if ($newType) {
            $s = $db->prepare('SELECT name FROM ticket_types WHERE id = ?');
            $s->execute([$newType]);
            $newTypeName = $s->fetchColumn() ?: 'None';
        }

        $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, ?, ?, 0)'
        )->execute([$id, Auth::id(), 'type_changed', "Type changed from {$oldTypeName} to {$newTypeName}"]);
        $changes[] = 'type';

        // Recalculate SLA for new type
        Sla::onTypeChanged($db, $id, $newType);
    }

    // Run automations on ticket update
    if (!empty($changes)) {
        runAutomations($db, $id, 'ticket_updated');
        flash('success', 'Ticket updated: ' . implode(', ', $changes) . '.');
    } else {
        flash('info', 'No changes made.');
    }
    redirect("/admin/tickets/{$id}");
});

/* ==================================================================
 * ADMIN – Delete All Tickets
 * ================================================================== */
$router->post('/admin/tickets/delete-all', function () {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/tickets');
    }

    $db = Database::connect();

    // Delete attachment files from disk
    $files = $db->query('SELECT stored_name FROM ticket_attachments')->fetchAll();
    foreach ($files as $f) {
        $path = ATTACHMENT_STORAGE_PATH . $f['stored_name'];
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // Delete all tickets (cascades to timeline, attachments, notifications)
    $count = $db->exec('DELETE FROM tickets');

    logAudit('ticket.delete_all', null, 'ticket', 'count=' . (int) $count);

    flash('success', "Deleted {$count} ticket(s) and all associated data.");
    redirect('/admin/settings/danger-zone');
});

/* ==================================================================
 * ADMIN – Download Attachment
 * ================================================================== */

$router->get('/admin/attachments/{id}/download', function (array $p) {
    Auth::requireAdmin();
    $db = Database::connect();

    $stmt = $db->prepare('SELECT * FROM ticket_attachments WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $att = $stmt->fetch();

    if (!$att) {
        flash('error', 'Attachment not found.');
        redirect('/admin/tickets');
    }

    $filePath = ATTACHMENT_STORAGE_PATH . $att['stored_name'];
    if (!file_exists($filePath)) {
        flash('error', 'File not found on server.');
        redirect('/admin/tickets/' . $att['ticket_id']);
    }

    header('Content-Type: ' . $att['mime_type']);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $att['original_name']) . '"');
    header('Content-Length: ' . $att['file_size']);
    readfile($filePath);
    exit;
});

/* ==================================================================
 * ADMIN – KB Category Management
 * ================================================================== */

$router->get('/admin/kb/categories', function () {
    Auth::requirePermission('kb.structure.manage');
    $categories = Database::connect()->query('SELECT * FROM kb_categories ORDER BY sort_order, name')->fetchAll();
    render('admin/kb/categories/index', ['categories' => $categories]);
});

$router->post('/admin/kb/categories/reorder', function () {
    Auth::requirePermission('kb.structure.manage');
    handleSortableReorder('kb_categories');
});

$router->get('/admin/kb/categories/create', function () {
    Auth::requirePermission('kb.structure.manage');
    render('admin/kb/categories/form', ['editing' => null]);
});

$router->post('/admin/kb/categories/create', function () {
    Auth::requirePermission('kb.structure.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/categories/create');
    }
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Category name is required.');
        redirect('/admin/kb/categories/create');
    }
    $slug = slugify($name);
    $db   = Database::connect();
    // Ensure unique slug
    $existing = $db->prepare('SELECT id FROM kb_categories WHERE slug = ?');
    $existing->execute([$slug]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $db->prepare('INSERT INTO kb_categories (name, slug, description, is_public, sort_order) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $slug, $desc, $isPublic, $order]);
    $newId = (int) $db->lastInsertId();
    logAudit('kb.category.created', $newId, 'kb_category', 'name=' . $name . '; is_public=' . $isPublic);
    flash('success', 'Category created.');
    redirect('/admin/kb/categories');
});

$router->get('/admin/kb/categories/{id}/edit', function (array $p) {
    Auth::requirePermission('kb.structure.manage');
    $stmt = Database::connect()->prepare('SELECT * FROM kb_categories WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Category not found.');
        redirect('/admin/kb/categories');
    }
    render('admin/kb/categories/form', ['editing' => $editing]);
});

$router->post('/admin/kb/categories/{id}/edit', function (array $p) {
    Auth::requirePermission('kb.structure.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/kb/categories/{$id}/edit");
    }
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $order = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Category name is required.');
        redirect("/admin/kb/categories/{$id}/edit");
    }
    $slug = slugify($name);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_categories WHERE slug = ? AND id != ?');
    $existing->execute([$slug, $id]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }
    $beforeStmt = $db->prepare('SELECT name, is_public, sort_order FROM kb_categories WHERE id = ?');
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $db->prepare('UPDATE kb_categories SET name=?, slug=?, description=?, is_public=?, sort_order=? WHERE id=?')
        ->execute([$name, $slug, $desc, $isPublic, $order, $id]);
    logAuditChange(
        'kb.category.updated',
        $id,
        'kb_category',
        $before,
        ['name' => $name, 'is_public' => $isPublic, 'sort_order' => $order]
    );
    flash('success', 'Category updated.');
    redirect('/admin/kb/categories');
});

$router->post('/admin/kb/categories/{id}/delete', function (array $p) {
    Auth::requirePermission('kb.structure.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/categories');
    }
    $id   = (int) $p['id'];
    $db   = Database::connect();
    $nameStmt = $db->prepare('SELECT name FROM kb_categories WHERE id = ?');
    $nameStmt->execute([$id]);
    $cname = (string) ($nameStmt->fetchColumn() ?: '');
    $db->prepare('DELETE FROM kb_categories WHERE id = ?')->execute([$id]);
    logAudit('kb.category.deleted', $id, 'kb_category', 'name=' . $cname);
    flash('success', 'Category deleted.');
    redirect('/admin/kb/categories');
});

/* ==================================================================
 * ADMIN – KB Folder Management
 * ================================================================== */

$router->get('/admin/kb/folders', function () {
    Auth::requirePermission('kb.structure.manage');
    $folders = Database::connect()->query(
        'SELECT f.*, c.name AS category_name
         FROM kb_folders f
         LEFT JOIN kb_categories c ON f.category_id = c.id
         ORDER BY c.sort_order, c.name, f.sort_order, f.name'
    )->fetchAll();
    render('admin/kb/folders/index', ['folders' => $folders]);
});

$router->post('/admin/kb/folders/reorder', function () {
    Auth::requirePermission('kb.structure.manage');
    handleSortableReorder('kb_folders');
});

$router->get('/admin/kb/folders/create', function () {
    Auth::requirePermission('kb.structure.manage');
    $categories = Database::connect()->query('SELECT * FROM kb_categories ORDER BY sort_order, name')->fetchAll();
    render('admin/kb/folders/form', ['editing' => null, 'categories' => $categories]);
});

$router->post('/admin/kb/folders/create', function () {
    Auth::requirePermission('kb.structure.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/folders/create');
    }
    $name       = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $desc       = trim($_POST['description'] ?? '');
    $order      = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '' || $categoryId === null) {
        flashInput($_POST);
        flash('error', 'Folder name and category are required.');
        redirect('/admin/kb/folders/create');
    }
    $slug = slugify($name);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_folders WHERE slug = ?');
    $existing->execute([$slug]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }
    $db->prepare('INSERT INTO kb_folders (category_id, name, slug, description, sort_order) VALUES (?, ?, ?, ?, ?)')
        ->execute([$categoryId, $name, $slug, $desc, $order]);
    $newId = (int) $db->lastInsertId();
    logAudit('kb.folder.created', $newId, 'kb_folder', 'name=' . $name . '; category_id=' . $categoryId);
    flash('success', 'Folder created.');
    redirect('/admin/kb/folders');
});

$router->get('/admin/kb/folders/{id}/edit', function (array $p) {
    Auth::requirePermission('kb.structure.manage');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM kb_folders WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Folder not found.');
        redirect('/admin/kb/folders');
    }
    $categories = $db->query('SELECT * FROM kb_categories ORDER BY sort_order, name')->fetchAll();
    render('admin/kb/folders/form', ['editing' => $editing, 'categories' => $categories]);
});

$router->post('/admin/kb/folders/{id}/edit', function (array $p) {
    Auth::requirePermission('kb.structure.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/kb/folders/{$id}/edit");
    }
    $name       = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $desc       = trim($_POST['description'] ?? '');
    $order      = (int) ($_POST['sort_order'] ?? 0);
    if ($name === '' || $categoryId === null) {
        flashInput($_POST);
        flash('error', 'Folder name and category are required.');
        redirect("/admin/kb/folders/{$id}/edit");
    }
    $slug = slugify($name);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_folders WHERE slug = ? AND id != ?');
    $existing->execute([$slug, $id]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }
    $beforeStmt = $db->prepare('SELECT category_id, name, sort_order FROM kb_folders WHERE id = ?');
    $beforeStmt->execute([$id]);
    $before = $beforeStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    $db->prepare('UPDATE kb_folders SET category_id=?, name=?, slug=?, description=?, sort_order=? WHERE id=?')
        ->execute([$categoryId, $name, $slug, $desc, $order, $id]);
    logAuditChange(
        'kb.folder.updated',
        $id,
        'kb_folder',
        $before,
        ['category_id' => $categoryId, 'name' => $name, 'sort_order' => $order]
    );
    flash('success', 'Folder updated.');
    redirect('/admin/kb/folders');
});

$router->post('/admin/kb/folders/{id}/delete', function (array $p) {
    Auth::requirePermission('kb.structure.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/folders');
    }
    $id   = (int) $p['id'];
    $db   = Database::connect();
    $nameStmt = $db->prepare('SELECT name FROM kb_folders WHERE id = ?');
    $nameStmt->execute([$id]);
    $fname = (string) ($nameStmt->fetchColumn() ?: '');
    $db->prepare('DELETE FROM kb_folders WHERE id = ?')->execute([$id]);
    logAudit('kb.folder.deleted', $id, 'kb_folder', 'name=' . $fname);
    flash('success', 'Folder deleted.');
    redirect('/admin/kb/folders');
});

/* ==================================================================
 * ADMIN – KB Article Management
 * ================================================================== */

$router->get('/admin/kb/articles', function () {
    Auth::requirePermission('kb.articles.manage');
    $db = Database::connect();
    $authorId = isset($_GET['author']) ? (int) $_GET['author'] : 0;
    $sql = "SELECT a.*, f.name AS folder_name, c.name AS category_name,
                CONCAT(u.first_name, ' ', u.last_name) AS author_name
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         LEFT JOIN users u         ON a.created_by  = u.id"
         . ($authorId ? ' WHERE a.created_by = ?' : '')
         . ' ORDER BY a.updated_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($authorId ? [$authorId] : []);
    $articles = $stmt->fetchAll();
    $authorFilter = null;
    if ($authorId) {
        $afStmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM users WHERE id = ?");
        $afStmt->execute([$authorId]);
        $authorFilter = $afStmt->fetchColumn() ?: null;
    }
    render('admin/kb/articles/index', ['articles' => $articles, 'authorFilter' => $authorFilter, 'authorId' => $authorId]);
});

$router->get('/admin/kb/articles/create', function () {
    Auth::requirePermission('kb.articles.manage');
    $folders = Database::connect()->query(
        'SELECT f.id, f.name, c.name AS category_name
         FROM kb_folders f
         LEFT JOIN kb_categories c ON f.category_id = c.id
         ORDER BY c.sort_order, c.name, f.sort_order, f.name'
    )->fetchAll();
    render('admin/kb/articles/form', ['editing' => null, 'folders' => $folders]);
});

$router->post('/admin/kb/articles/create', function () {
    Auth::requirePermission('kb.articles.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/articles/create');
    }
    $title    = trim($_POST['title'] ?? '');
    $folderId = !empty($_POST['folder_id']) ? (int) $_POST['folder_id'] : null;
    $body     = $_POST['body_markdown'] ?? '';
    $status   = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $order    = (int) ($_POST['sort_order'] ?? 0);

    if ($title === '' || $folderId === null || $body === '') {
        flashInput($_POST);
        flash('error', 'Title, folder, and body are required.');
        redirect('/admin/kb/articles/create');
    }

    $slug = slugify($title);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_articles WHERE slug = ?');
    $existing->execute([$slug]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }

    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

    $db->prepare(
        'INSERT INTO kb_articles (folder_id, title, slug, body_markdown, status, published_at, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$folderId, $title, $slug, $body, $status, $publishedAt, Auth::id(), $order]);
    $newId = (int) $db->lastInsertId();
    // Save initial revision
    $db->prepare('INSERT INTO kb_article_revisions (article_id, title, body_markdown, edited_by) VALUES (?, ?, ?, ?)')
       ->execute([$newId, $title, $body, Auth::id()]);
    logAudit('kb.article.created', $newId, 'kb_article', 'title=' . $title . '; folder_id=' . $folderId . '; status=' . $status);
    flash('success', 'Article created.');
    redirect('/admin/kb/articles');
});

$router->get('/admin/kb/articles/{id}/edit', function (array $p) {
    Auth::requireStaff();
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM kb_articles WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Article not found.');
        redirect(Auth::isAdmin() ? '/admin/kb/articles' : '/agent/kb');
    }
    $folders = $db->query(
        'SELECT f.id, f.name, c.name AS category_name
         FROM kb_folders f
         LEFT JOIN kb_categories c ON f.category_id = c.id
         ORDER BY c.sort_order, c.name, f.sort_order, f.name'
    )->fetchAll();
    render('admin/kb/articles/form', ['editing' => $editing, 'folders' => $folders]);
});

$router->post('/admin/kb/articles/{id}/edit', function (array $p) {
    Auth::requireStaff();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/kb/articles/{$id}/edit");
    }
    $title    = trim($_POST['title'] ?? '');
    $folderId = !empty($_POST['folder_id']) ? (int) $_POST['folder_id'] : null;
    $body     = $_POST['body_markdown'] ?? '';
    $status   = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $order    = (int) ($_POST['sort_order'] ?? 0);

    if ($title === '' || $folderId === null || $body === '') {
        flashInput($_POST);
        flash('error', 'Title, folder, and body are required.');
        redirect("/admin/kb/articles/{$id}/edit");
    }

    $slug = slugify($title);
    $db   = Database::connect();
    $existing = $db->prepare('SELECT id FROM kb_articles WHERE slug = ? AND id != ?');
    $existing->execute([$slug, $id]);
    if ($existing->fetch()) {
        $slug .= '-' . time();
    }

    // Determine published_at — also pulls folder_id so the audit-log diff
    // below sees the prior folder when an article is moved between folders.
    $oldStmt = $db->prepare('SELECT status, published_at, folder_id FROM kb_articles WHERE id = ?');
    $oldStmt->execute([$id]);
    $old = $oldStmt->fetch();
    if ($status === 'published' && ($old['status'] ?? '') !== 'published') {
        $publishedAt = date('Y-m-d H:i:s');
    } elseif ($status === 'published') {
        $publishedAt = $old['published_at'];
    } else {
        $publishedAt = null;
    }

    // Save revision snapshot before overwriting
    $snap = $db->prepare('SELECT title, body_markdown FROM kb_articles WHERE id = ?');
    $snap->execute([$id]);
    $snapRow = $snap->fetch();
    if ($snapRow) {
        $db->prepare('INSERT INTO kb_article_revisions (article_id, title, body_markdown, edited_by) VALUES (?, ?, ?, ?)')
           ->execute([$id, $snapRow['title'], $snapRow['body_markdown'], Auth::id()]);
    }

    $db->prepare(
        'UPDATE kb_articles SET folder_id=?, title=?, slug=?, body_markdown=?, status=?, published_at=?, sort_order=? WHERE id=?'
    )->execute([$folderId, $title, $slug, $body, $status, $publishedAt, $order, $id]);
    // $snapRow holds title/body before this write; $old holds status/published_at
    logAuditChange(
        'kb.article.updated',
        $id,
        'kb_article',
        [
            'title'     => $snapRow['title'] ?? null,
            'folder_id' => $old['folder_id'] ?? null,
            'status'    => $old['status'] ?? null,
        ],
        ['title' => $title, 'folder_id' => $folderId, 'status' => $status]
    );
    flash('success', 'Article updated.');
    redirect(Auth::isAdmin() ? '/admin/kb/articles' : '/agent/kb');
});

$router->post('/admin/kb/articles/{id}/delete', function (array $p) {
    Auth::requirePermission('kb.articles.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/articles');
    }
    $id   = (int) $p['id'];
    $db   = Database::connect();
    $tStmt = $db->prepare('SELECT title FROM kb_articles WHERE id = ?');
    $tStmt->execute([$id]);
    $title = (string) ($tStmt->fetchColumn() ?: '');
    $db->prepare('DELETE FROM kb_articles WHERE id = ?')->execute([$id]);
    logAudit('kb.article.deleted', $id, 'kb_article', 'title=' . $title);
    flash('success', 'Article deleted.');
    redirect('/admin/kb/articles');
});

$router->get('/admin/kb/articles/{id}/preview', function (array $p) {
    Auth::requireStaff();
    $db   = Database::connect();
    $stmt = $db->prepare(
        'SELECT a.*, f.name AS folder_name, f.slug AS folder_slug,
                c.name AS category_name, c.slug AS category_slug
         FROM kb_articles a
         LEFT JOIN kb_folders f    ON a.folder_id   = f.id
         LEFT JOIN kb_categories c ON f.category_id = c.id
         WHERE a.id = ?'
    );
    $stmt->execute([(int) $p['id']]);
    $article = $stmt->fetch();
    if (!$article) {
        flash('error', 'Article not found.');
        redirect('/admin/kb/articles');
    }
    $article['body_html'] = renderMarkdown($article['body_markdown']);
    render('admin/kb/articles/preview', ['article' => $article]);
});

/* ==================================================================
 * ADMIN – KB Article Version History
 * ================================================================== */

$router->get('/admin/kb/articles/{id}/history', function (array $p) {
    Auth::requirePermission('kb.articles.manage');
    $db      = Database::connect();
    $id      = (int) $p['id'];
    $stmt    = $db->prepare('SELECT * FROM kb_articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) {
        flash('error', 'Article not found.');
        redirect('/admin/kb/articles');
    }
    $revStmt = $db->prepare(
        "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS editor_name
         FROM kb_article_revisions r
         LEFT JOIN users u ON r.edited_by = u.id
         WHERE r.article_id = ?
         ORDER BY r.created_at DESC"
    );
    $revStmt->execute([$id]);
    $revisions = $revStmt->fetchAll();
    render('admin/kb/articles/history', compact('article', 'revisions'));
});

$router->get('/admin/kb/articles/{id}/history/{rid}', function (array $p) {
    Auth::requirePermission('kb.articles.manage');
    $db      = Database::connect();
    $id      = (int) $p['id'];
    $rid     = (int) $p['rid'];

    $artStmt = $db->prepare('SELECT * FROM kb_articles WHERE id = ?');
    $artStmt->execute([$id]);
    $article = $artStmt->fetch();
    if (!$article) {
        flash('error', 'Article not found.');
        redirect('/admin/kb/articles');
    }

    $revStmt = $db->prepare(
        "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS editor_name
         FROM kb_article_revisions r
         LEFT JOIN users u ON r.edited_by = u.id
         WHERE r.id = ? AND r.article_id = ?"
    );
    $revStmt->execute([$rid, $id]);
    $revision = $revStmt->fetch();
    if (!$revision) {
        flash('error', 'Revision not found.');
        redirect("/admin/kb/articles/{$id}/history");
    }

    // LCS-based line diff (revision body vs current body)
    $computeDiff = function (string $old, string $new): array {
        $a = explode("\n", $old);
        $b = explode("\n", $new);
        $m = count($a);
        $n = count($b);
        if ($m > 800 || $n > 800) {
            return [['type' => 'too_large']];
        }
        // Build LCS table
        $dp = [];
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i] = array_fill(0, $n + 1, 0);
        }
        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $dp[$i][$j] = $a[$i-1] === $b[$j-1]
                    ? $dp[$i-1][$j-1] + 1
                    : max($dp[$i-1][$j], $dp[$i][$j-1]);
            }
        }
        // Traceback
        $diff = [];
        $i = $m; $j = $n;
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i-1] === $b[$j-1]) {
                array_unshift($diff, ['type' => 'eq', 'line' => $a[$i-1]]);
                $i--; $j--;
            } elseif ($j > 0 && ($i === 0 || $dp[$i][$j-1] >= $dp[$i-1][$j])) {
                array_unshift($diff, ['type' => 'add', 'line' => $b[$j-1]]);
                $j--;
            } else {
                array_unshift($diff, ['type' => 'del', 'line' => $a[$i-1]]);
                $i--;
            }
        }
        return $diff;
    };

    $diff = $computeDiff($revision['body_markdown'], $article['body_markdown']);

    render('admin/kb/articles/diff', compact('article', 'revision', 'diff'));
});

/* ==================================================================
 * ADMIN – KB Export (CSV) / Import (JSON)
 * ================================================================== */

$router->get('/admin/kb/export', function () {
    Auth::requirePermission('kb.articles.manage');
    $db = Database::connect();

    $stmt = $db->query(
        "SELECT a.title, a.body_markdown, c.name AS category, a.status
         FROM kb_articles a
         JOIN kb_folders f  ON a.folder_id     = f.id
         JOIN kb_categories c ON f.category_id = c.id
         ORDER BY c.sort_order, c.name, f.sort_order, f.name, a.sort_order, a.title"
    );

    $filename = 'kb-export-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    fputcsv($out, ['title', 'body_markdown', 'category', 'status', 'tags']);

    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['title'],
            $row['body_markdown'],
            $row['category'],
            $row['status'],
            '', // tags — no tag field on KB articles
        ]);
    }

    fclose($out);
    exit;
});

$router->get('/admin/kb/import', function () {
    Auth::requirePermission('import.manage');
    render('admin/kb/import', [
        'layout'       => 'app',
        'pageTitle'    => 'Import Knowledge Base',
        'sidebarItems' => adminSidebar('kb'),
        'breadcrumbs'  => [
            ['label' => 'Admin',          'url' => '/admin'],
            ['label' => 'Knowledge Base', 'url' => '/admin/kb/articles'],
            ['label' => 'Import'],
        ],
    ]);
});

$router->post('/admin/kb/import/preview', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/import');
        return;
    }

    if (empty($_FILES['json_file']['tmp_name']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please upload a valid JSON file.');
        redirect('/admin/kb/import');
        return;
    }

    if ($_FILES['json_file']['size'] > 20 * 1024 * 1024) {
        flash('error', 'File too large. Maximum 20 MB.');
        redirect('/admin/kb/import');
        return;
    }

    $raw = file_get_contents($_FILES['json_file']['tmp_name']);
    if ($raw === false) {
        flash('error', 'Unable to read file.');
        redirect('/admin/kb/import');
        return;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['categories']) || !is_array($data['categories'])) {
        flash('error', 'Invalid format. Please upload a JSON file exported from OpenHelpDesk.');
        redirect('/admin/kb/import');
        return;
    }

    // Collect summary stats and build article preview
    $totalCategories = 0;
    $totalFolders    = 0;
    $totalArticles   = 0;
    $draftCount      = 0;
    $publishedCount  = 0;
    $previewArticles = [];

    foreach ($data['categories'] as $cat) {
        if (empty($cat['name'])) { continue; }
        $totalCategories++;
        foreach ($cat['folders'] ?? [] as $folder) {
            if (empty($folder['name'])) { continue; }
            $totalFolders++;
            foreach ($folder['articles'] ?? [] as $article) {
                if (empty($article['title']) || empty($article['body_markdown'])) { continue; }
                $totalArticles++;
                if (($article['status'] ?? 'draft') === 'published') {
                    $publishedCount++;
                } else {
                    $draftCount++;
                }
                if (count($previewArticles) < 15) {
                    $previewArticles[] = [
                        'title'        => $article['title'],
                        'category'     => $cat['name'],
                        'folder'       => $folder['name'],
                        'status'       => $article['status'] ?? 'draft',
                        'body_preview' => mb_strimwidth($article['body_markdown'], 0, 80, '…'),
                    ];
                }
            }
        }
    }

    if ($totalArticles === 0) {
        flash('error', 'No valid articles found in the JSON file.');
        redirect('/admin/kb/import');
        return;
    }

    // Check which categories already exist
    $db = Database::connect();
    $existingCatKeys = array_map('strtolower', $db->query('SELECT name FROM kb_categories')->fetchAll(PDO::FETCH_COLUMN));

    $newCategories      = [];
    $existingCategories = [];
    foreach ($data['categories'] as $cat) {
        if (empty($cat['name'])) { continue; }
        if (in_array(strtolower($cat['name']), $existingCatKeys, true)) {
            $existingCategories[] = $cat['name'];
        } else {
            $newCategories[] = $cat['name'];
        }
    }

    $_SESSION['kb_json_import_data'] = $data;

    render('admin/kb/import-preview', [
        'layout'       => 'app',
        'pageTitle'    => 'Preview KB Import',
        'sidebarItems' => adminSidebar('kb'),
        'breadcrumbs'  => [
            ['label' => 'Admin',          'url' => '/admin'],
            ['label' => 'Knowledge Base', 'url' => '/admin/kb/articles'],
            ['label' => 'Import',         'url' => '/admin/kb/import'],
            ['label' => 'Preview'],
        ],
        'summary' => [
            'total_categories'   => $totalCategories,
            'total_folders'      => $totalFolders,
            'total_articles'     => $totalArticles,
            'new_categories'     => $newCategories,
            'existing_categories'=> $existingCategories,
            'draft_count'        => $draftCount,
            'published_count'    => $publishedCount,
        ],
        'previewArticles' => $previewArticles,
    ]);
});

$router->post('/admin/kb/import/confirm', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/kb/import');
        return;
    }

    $data = $_SESSION['kb_json_import_data'] ?? null;
    unset($_SESSION['kb_json_import_data']);

    if (!is_array($data) || empty($data['categories'])) {
        flash('error', 'No import data found. Please upload the file again.');
        redirect('/admin/kb/import');
        return;
    }

    $db         = Database::connect();
    $publishAll = !empty($_POST['publish_all']);

    // Build lookup maps (find existing by name, case-insensitive)
    $catLookup = [];
    foreach ($db->query('SELECT id, name FROM kb_categories')->fetchAll() as $c) {
        $catLookup[strtolower($c['name'])] = (int) $c['id'];
    }
    $folderLookup = [];
    foreach ($db->query('SELECT id, category_id, name FROM kb_folders')->fetchAll() as $f) {
        $folderLookup[(int) $f['category_id'] . ':' . strtolower($f['name'])] = (int) $f['id'];
    }

    $db->beginTransaction();
    try {
        $insertCat = $db->prepare(
            'INSERT INTO kb_categories (name, slug, description, is_public, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $insertFolder = $db->prepare(
            'INSERT INTO kb_folders (category_id, name, slug, description, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        $insertArticle = $db->prepare(
            'INSERT INTO kb_articles (folder_id, title, slug, body_markdown, status, published_at, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertRevision = $db->prepare(
            'INSERT INTO kb_article_revisions (article_id, title, body_markdown, edited_by) VALUES (?, ?, ?, ?)'
        );
        $checkCatSlug    = $db->prepare('SELECT id FROM kb_categories WHERE slug = ?');
        $checkFolderSlug = $db->prepare('SELECT id FROM kb_folders WHERE slug = ?');
        $checkArticleSlug = $db->prepare('SELECT id FROM kb_articles WHERE slug = ?');

        $imported     = 0;
        $nextCatOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order),0) FROM kb_categories')->fetchColumn();

        foreach ($data['categories'] as $cat) {
            if (empty($cat['name'])) { continue; }
            $catKey = strtolower($cat['name']);

            // Find or create category
            if (!isset($catLookup[$catKey])) {
                $nextCatOrder++;
                $catSlug = slugify($cat['name']);
                $checkCatSlug->execute([$catSlug]);
                if ($checkCatSlug->fetch()) {
                    $catSlug .= '-' . substr(md5(uniqid('', true)), 0, 6);
                }
                $insertCat->execute([
                    $cat['name'],
                    $catSlug,
                    $cat['description'] ?? null,
                    (int) ($cat['is_public'] ?? 0),
                    (int) ($cat['sort_order'] ?? $nextCatOrder),
                ]);
                $catLookup[$catKey] = (int) $db->lastInsertId();
            }
            $catId = $catLookup[$catKey];

            foreach ($cat['folders'] ?? [] as $folder) {
                if (empty($folder['name'])) { continue; }
                $folderKey = $catId . ':' . strtolower($folder['name']);

                // Find or create folder
                if (!isset($folderLookup[$folderKey])) {
                    $folderSlug = slugify($cat['name'] . '-' . $folder['name']);
                    $checkFolderSlug->execute([$folderSlug]);
                    if ($checkFolderSlug->fetch()) {
                        $folderSlug .= '-' . substr(md5(uniqid('', true)), 0, 6);
                    }
                    $insertFolder->execute([
                        $catId,
                        $folder['name'],
                        $folderSlug,
                        $folder['description'] ?? null,
                        (int) ($folder['sort_order'] ?? 0),
                    ]);
                    $folderLookup[$folderKey] = (int) $db->lastInsertId();
                }
                $folderId = $folderLookup[$folderKey];

                foreach ($folder['articles'] ?? [] as $article) {
                    if (empty($article['title']) || empty($article['body_markdown'])) { continue; }

                    $slug = slugify($article['title']);
                    $checkArticleSlug->execute([$slug]);
                    if ($checkArticleSlug->fetch()) {
                        $slug .= '-' . substr(md5(uniqid('', true)), 0, 6);
                    }

                    $status      = $publishAll ? 'published' : ($article['status'] ?? 'draft');
                    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

                    $insertArticle->execute([
                        $folderId,
                        $article['title'],
                        $slug,
                        $article['body_markdown'],
                        $status,
                        $publishedAt,
                        Auth::id(),
                        (int) ($article['sort_order'] ?? 0),
                    ]);
                    $articleId = (int) $db->lastInsertId();
                    $insertRevision->execute([$articleId, $article['title'], $article['body_markdown'], Auth::id()]);

                    $imported++;
                }
            }
        }

        $db->commit();
        logAudit(
            'kb.import_confirmed',
            null,
            'kb_article',
            'articles_imported=' . $imported . '; publish_all=' . ($publishAll ? 1 : 0)
        );
        flash('success', "Successfully imported {$imported} KB article(s).");
        redirect('/admin/kb/articles');
    } catch (PDOException $e) {
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/kb/import');
    }
});

/* ==================================================================
 * ADMIN – Settings (Email / SMTP Configuration)
 * ================================================================== */

$router->get('/admin/settings', function () {
    Auth::requirePermission('settings.manage');
    $keys = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'mail_from_address', 'mail_from_name', 'smtp_debug',
        'graph_enabled', 'graph_reply_to', 'graph_tenant_id', 'graph_client_id',
        'graph_client_secret', 'graph_mailbox', 'graph_secret_expires_at',
        'email_to_ticket_enabled', 'email_to_ticket_auto_create_users',
        'email_to_ticket_default_type_id', 'email_to_ticket_default_priority_id',
        'default_group_id',
    ];
    $settings = [];
    foreach ($keys as $k) {
        $settings[$k] = getSetting($k);
    }
    // Retrieve and clear any stored processor run output
    $runOutput = $_SESSION['_processor_run'] ?? null;
    unset($_SESSION['_processor_run']);

    $db         = Database::connect();
    $types      = $db->query('SELECT * FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $groups     = $db->query('SELECT id, name FROM `groups` ORDER BY name')->fetchAll();

    render('admin/settings/index', [
        'settings' => $settings,
        'runOutput' => $runOutput,
        'types' => $types,
        'priorities' => $priorities,
        'groups' => $groups,
    ]);
});

$router->post('/admin/settings/email', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $fields = [
        'smtp_host'        => trim($_POST['smtp_host'] ?? ''),
        'smtp_port'        => trim($_POST['smtp_port'] ?? '587'),
        'smtp_encryption'  => in_array($_POST['smtp_encryption'] ?? '', ['none', 'tls', 'ssl'], true) ? $_POST['smtp_encryption'] : 'tls',
        'smtp_username'    => trim($_POST['smtp_username'] ?? ''),
        'mail_from_address' => trim($_POST['mail_from_address'] ?? ''),
        'mail_from_name'   => trim($_POST['mail_from_name'] ?? ''),
        'smtp_debug'       => isset($_POST['smtp_debug']) ? '1' : '0',
    ];

    // Capture prior values for the audit diff (secret excluded — handled separately).
    $smtpBefore = [];
    foreach ($fields as $k => $_) {
        $smtpBefore[$k] = getSetting($k, '');
    }
    $smtpBefore['smtp_password'] = '(unchanged)';
    $smtpAfter = $fields;
    $smtpAfter['smtp_password'] = '(unchanged)';

    // Only update password if a new one was provided (don't blank it on save)
    $password = $_POST['smtp_password'] ?? '';
    if ($password !== '') {
        $fields['smtp_password'] = $password;
        $smtpAfter['smtp_password'] = '(rotated)';
    }

    foreach ($fields as $key => $value) {
        setSetting($key, $value);
    }

    logAuditChange(
        'smtp.settings_changed',
        null,
        null,
        $smtpBefore,
        $smtpAfter,
        ['smtp_password']
    );

    flash('success', 'Email settings saved.');
    redirect('/admin/settings');
});

$router->post('/admin/settings/graph', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $fields = [
        'graph_enabled'   => isset($_POST['graph_enabled']) ? '1' : '0',
        'graph_reply_to'  => trim($_POST['graph_reply_to'] ?? ''),
        'graph_tenant_id' => trim($_POST['graph_tenant_id'] ?? ''),
        'graph_client_id' => trim($_POST['graph_client_id'] ?? ''),
        'graph_mailbox'   => trim($_POST['graph_mailbox'] ?? ''),
    ];

    // Snapshot prior values for the audit diff (secret handled separately).
    $graphBefore = [];
    foreach ($fields as $k => $_) {
        $graphBefore[$k] = getSetting($k, '');
    }
    $graphBefore['graph_secret_expires_at'] = getSetting('graph_secret_expires_at', '');
    $graphBefore['graph_client_secret']     = '(unchanged)';
    $graphAfter  = $fields;
    $graphAfter['graph_secret_expires_at']  = trim($_POST['graph_secret_expires_at'] ?? '');
    $graphAfter['graph_client_secret']      = '(unchanged)';

    // Only update client secret if a new one was provided
    $secret = $_POST['graph_client_secret'] ?? '';
    if ($secret !== '') {
        $fields['graph_client_secret'] = $secret;
        $graphAfter['graph_client_secret'] = '(rotated)';
    }

    foreach ($fields as $key => $value) {
        setSetting($key, $value);
    }

    // Save secret expiry date and reset reminder flags if date changed
    $newExpiry = trim($_POST['graph_secret_expires_at'] ?? '');
    if ($newExpiry !== '') {
        $existing = getSetting('graph_secret_expires_at', '');
        if ($newExpiry !== $existing) {
            setSetting('graph_secret_remind_1month', '0');
            setSetting('graph_secret_remind_1week',  '0');
            setSetting('graph_secret_remind_day',     '0');
        }
        setSetting('graph_secret_expires_at', $newExpiry);
    }

    logAuditChange(
        'graph.settings_changed',
        null,
        null,
        $graphBefore,
        $graphAfter,
        ['graph_client_secret']
    );

    // Ensure log directory exists
    @mkdir(ROOT_DIR . '/storage/logs', 0755, true);

    flash('success', 'Inbound mail settings saved.');
    redirect('/admin/settings');
});

$router->post('/admin/settings/ticket-routing', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $raw = trim($_POST['default_group_id'] ?? '');
    $value = '';
    if ($raw !== '') {
        $candidate = (int) $raw;
        $db = Database::connect();
        $exists = $db->prepare('SELECT 1 FROM `groups` WHERE id = ?');
        $exists->execute([$candidate]);
        if ($exists->fetchColumn()) {
            $value = (string) $candidate;
        } else {
            flash('error', 'Selected default group no longer exists.');
            redirect('/admin/settings');
        }
    }
    setSetting('default_group_id', $value);
    logAudit('settings.default_group_changed', null, null, $value === '' ? 'cleared' : "id={$value}");
    flash('success', 'Default group saved.');
    redirect('/admin/settings');
});

$router->post('/admin/settings/email-to-ticket', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $fields = [
        'email_to_ticket_enabled'            => isset($_POST['email_to_ticket_enabled']) ? '1' : '0',
        'email_to_ticket_auto_create_users'  => isset($_POST['email_to_ticket_auto_create_users']) ? '1' : '0',
        'email_to_ticket_default_type_id'    => trim($_POST['email_to_ticket_default_type_id'] ?? ''),
        'email_to_ticket_default_priority_id' => trim($_POST['email_to_ticket_default_priority_id'] ?? ''),
    ];

    $before = [];
    foreach ($fields as $k => $_) {
        $before[$k] = getSetting($k, '');
    }

    foreach ($fields as $key => $value) {
        setSetting($key, $value);
    }

    logAuditChange('email_to_ticket.settings_changed', null, null, $before, $fields);

    flash('success', 'Email-to-Ticket settings saved.');
    redirect('/admin/settings');
});

$router->get('/admin/settings/email-reply-help', function () {
    Auth::requirePermission('settings.manage');
    render('admin/settings/email-reply-help', []);
});

$router->post('/admin/settings/run-reply-processor', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $script = ROOT_DIR . '/scripts/process-replies.php';
    $cmd    = escapeshellarg(phpBinary()) . ' ' . escapeshellarg($script) . ' 2>&1';

    $outputLines = [];
    $returnCode  = 0;
    exec($cmd, $outputLines, $returnCode);

    $_SESSION['_processor_run'] = [
        'lines' => $outputLines,
        'code'  => $returnCode,
        'time'  => date('Y-m-d H:i:s'),
    ];

    redirect('/admin/settings');
});

$router->post('/admin/settings/test-email', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings');
    }

    $toEmail = trim($_POST['to_email'] ?? '');
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid recipient email address.');
        redirect('/admin/settings');
    }

    $user     = Auth::user();
    $htmlBody = '<h2>It works!</h2><p>This is a test email from <strong>OpenHelpDesk</strong>. Your SMTP configuration is correct.</p>';
    $result   = sendMail(
        $toEmail,
        $toEmail,
        'OpenHelpDesk - Test Email',
        $htmlBody,
        "It works!\n\nThis is a test email from OpenHelpDesk. Your SMTP configuration is correct."
    );

    if ($result !== false) {
        flash('success', 'Test email sent to ' . $toEmail . '.');
    } else {
        flash('error', 'Failed to send test email. Check your SMTP settings and server error log.');
    }

    redirect('/admin/settings');
});

/* ==================================================================
 * ADMIN – Email Templates
 * ================================================================== */

$router->get('/admin/settings/email-templates', function () {
    Auth::requirePermission('settings.manage');

    $keys = [
        'email_subject_ticket_created',   'email_intro_ticket_created',   'email_button_ticket_created',
        'email_subject_ticket_updated',   'email_intro_ticket_updated',   'email_button_ticket_updated',
        'email_subject_ticket_merged',    'email_intro_ticket_merged',    'email_button_ticket_merged',
        'email_subject_csat_survey',      'email_intro_csat_survey',
        'email_subject_ticket_reminder',  'email_intro_ticket_reminder',  'email_button_ticket_reminder',
        'email_subject_group_alerts',          'email_intro_group_alerts',          'email_button_group_alerts',
        'email_subject_ticket_assigned_agent', 'email_intro_ticket_assigned_agent', 'email_button_ticket_assigned_agent',
        'email_subject_ticket_assigned_group', 'email_intro_ticket_assigned_group', 'email_button_ticket_assigned_group',
        'email_subject_escalation_alert',      'email_intro_escalation_alert',      'email_button_escalation_alert',
        'email_footer_text',
    ];
    $tplValues = [];
    foreach ($keys as $k) {
        $tplValues[$k] = getSetting($k);
    }

    $db = Database::connect();
    $groups = $db->query(
        'SELECT g.id, g.name, g.notify_new_ticket, COUNT(gum.user_id) AS member_count
         FROM `groups` g
         LEFT JOIN group_user_map gum ON gum.group_id = g.id
         GROUP BY g.id
         ORDER BY g.sort_order, g.name'
    )->fetchAll();

    render('admin/settings/email-templates', ['tplValues' => $tplValues, 'groups' => $groups]);
});

$router->post('/admin/settings/email-templates', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/email-templates');
    }

    $tab = $_POST['tab'] ?? 'ticket_created';

    // Reset buttons clear settings back to default (empty = use hardcoded default)
    if (isset($_POST['reset_template']) && in_array($_POST['reset_template'], ['ticket_created', 'ticket_updated', 'ticket_merged', 'csat_survey', 'ticket_reminder', 'group_alerts', 'ticket_assigned_agent', 'ticket_assigned_group', 'escalation_alert'], true)) {
        $tpl = $_POST['reset_template'];
        setSetting("email_subject_{$tpl}", '');
        setSetting("email_intro_{$tpl}", '');
        if ($tpl !== 'csat_survey') {
            setSetting("email_button_{$tpl}", '');
        }
        logAudit('email_template.reset', null, null, 'template=' . $tpl);
        flash('success', 'Email template reset to defaults.');
        redirect('/admin/settings/email-templates?tab=' . $tpl);
    }

    if (isset($_POST['reset_footer'])) {
        setSetting('email_footer_text', '');
        logAudit('email_template.reset', null, null, 'template=footer');
        flash('success', 'Footer text reset to default.');
        redirect('/admin/settings/email-templates?tab=shared');
    }

    if ($tab === 'shared') {
        setSetting('email_footer_text', trim($_POST['email_footer_text'] ?? ''));
        flash('success', 'Footer text saved.');
    } elseif ($tab === 'group_alerts') {
        setSetting('email_subject_group_alerts', trim($_POST['email_subject_group_alerts'] ?? ''));
        setSetting('email_intro_group_alerts',   trim($_POST['email_intro_group_alerts']   ?? ''));
        setSetting('email_button_group_alerts',  trim($_POST['email_button_group_alerts']  ?? ''));
        flash('success', 'Group alerts settings saved.');
    } elseif (in_array($tab, ['ticket_assigned_agent', 'ticket_assigned_group', 'escalation_alert'], true)) {
        setSetting("email_subject_{$tab}", trim($_POST["email_subject_{$tab}"] ?? ''));
        setSetting("email_intro_{$tab}",   trim($_POST["email_intro_{$tab}"]   ?? ''));
        setSetting("email_button_{$tab}",  trim($_POST["email_button_{$tab}"]  ?? ''));
        flash('success', 'Email template saved.');
    } elseif (in_array($tab, ['ticket_created', 'ticket_updated', 'ticket_merged', 'csat_survey', 'ticket_reminder'], true)) {
        setSetting("email_subject_{$tab}", trim($_POST["email_subject_{$tab}"] ?? ''));
        setSetting("email_intro_{$tab}",   trim($_POST["email_intro_{$tab}"]   ?? ''));
        if ($tab !== 'csat_survey') {
            setSetting("email_button_{$tab}",  trim($_POST["email_button_{$tab}"]  ?? ''));
        }
        flash('success', 'Email template saved.');
    }

    logAudit('email_template.saved', null, null, 'tab=' . $tab);

    redirect('/admin/settings/email-templates?tab=' . urlencode($tab));
});

/* ==================================================================
 * ADMIN – Email Notifications Settings
 * ================================================================== */

$router->get('/admin/settings/email-notifications', function () {
    Auth::requirePermission('settings.manage');

    $keys = [
        'agent_new_ticket', 'agent_assigned_group', 'agent_assigned_agent',
        'agent_requester_reply', 'agent_note_added',
        'ticket_stale_agent',
        'requester_new_ticket', 'requester_ticket_assigned', 'requester_agent_comment',
        'requester_ticket_resolved', 'requester_ticket_closed',
        'ticket_stale_requester',
        'cc_new_ticket', 'cc_note_added',
    ];

    $settings = [];
    foreach ($keys as $k) {
        $settings[$k] = getSetting('email_notify:' . $k, '1');
    }

    render('admin/settings/email-notifications', ['settings' => $settings]);
});

$router->post('/admin/settings/email-notifications', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/email-notifications');
    }

    $keys = [
        'agent_new_ticket', 'agent_assigned_group', 'agent_assigned_agent',
        'agent_requester_reply', 'agent_note_added',
        'ticket_stale_agent',
        'requester_new_ticket', 'requester_ticket_assigned', 'requester_agent_comment',
        'requester_ticket_resolved', 'requester_ticket_closed',
        'ticket_stale_requester',
        'cc_new_ticket', 'cc_note_added',
    ];

    $before = [];
    $after  = [];
    foreach ($keys as $k) {
        $before[$k] = getSetting('email_notify:' . $k, '1');
        $after[$k]  = isset($_POST[$k]) ? '1' : '0';
        setSetting('email_notify:' . $k, $after[$k]);
    }

    logAuditChange('email_notifications.settings_changed', null, null, $before, $after);

    flash('success', 'Email notification settings saved.');
    redirect('/admin/settings/email-notifications');
});

/* ==================================================================
 * ADMIN – Business Hours Settings
 * ================================================================== */

$router->get('/admin/settings/business-hours', function () {
    Auth::requirePermission('settings.manage');
    $timezone = getSetting('business_hours_timezone');
    $json = getSetting('business_hours_schedule');
    $schedule = $json !== '' ? (json_decode($json, true) ?: []) : [];
    render('admin/settings/business-hours', ['timezone' => $timezone, 'schedule' => $schedule]);
});

$router->post('/admin/settings/business-hours', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/business-hours');
    }

    $timezone = trim($_POST['timezone'] ?? '');
    $days = $_POST['days'] ?? [];

    $schedule = [];
    foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
        if (!empty($days[$day]['active'])) {
            $start = $days[$day]['start'] ?? '09:00';
            $end = $days[$day]['end'] ?? '17:00';
            $schedule[$day] = [$start, $end];
        } else {
            $schedule[$day] = null;
        }
    }

    $beforeTz       = getSetting('business_hours_timezone', '');
    $beforeSchedule = getSetting('business_hours_schedule', '');
    setSetting('business_hours_timezone', $timezone);
    setSetting('business_hours_schedule', json_encode($schedule));
    logAuditChange(
        'business_hours.updated',
        null,
        null,
        ['timezone' => $beforeTz, 'schedule' => $beforeSchedule],
        ['timezone' => $timezone, 'schedule' => json_encode($schedule)]
    );

    flash('success', 'Business hours saved.');
    redirect('/admin/settings/business-hours');
});

/* ==================================================================
 * ADMIN – Holidays / Closed Days Settings
 * ================================================================== */

$router->get('/admin/settings/holidays', function () {
    Auth::requirePermission('settings.manage');
    $db = Database::connect();
    $holidays = $db->query('SELECT * FROM holidays ORDER BY holiday_date ASC')->fetchAll();
    render('admin/settings/holidays', ['holidays' => $holidays]);
});

$router->post('/admin/settings/holidays', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/holidays');
    }

    $dateStr = trim($_POST['holiday_date'] ?? '');
    $name    = trim($_POST['name'] ?? '');
    $exclude = isset($_POST['exclude_from_sla']) ? 1 : 0;

    $parsed = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$parsed || $parsed->format('Y-m-d') !== $dateStr) {
        flash('error', 'Please enter a valid date.');
        redirect('/admin/settings/holidays');
    }
    if ($name === '') {
        flash('error', 'Please enter a name for the holiday.');
        redirect('/admin/settings/holidays');
    }

    $db = Database::connect();
    try {
        $db->prepare('INSERT INTO holidays (holiday_date, name, exclude_from_sla) VALUES (?, ?, ?)')
           ->execute([$dateStr, $name, $exclude]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            flash('error', 'A holiday is already defined for that date.');
        } else {
            flash('error', 'Could not save holiday.');
        }
        redirect('/admin/settings/holidays');
    }
    $newId = (int) $db->lastInsertId();
    logAudit(
        'holiday.created',
        $newId,
        'holiday',
        'date=' . $dateStr . '; name=' . $name . '; exclude_from_sla=' . $exclude
    );

    Sla::recalculateAll($db);
    flash('success', 'Holiday added.');
    redirect('/admin/settings/holidays');
});

$router->post('/admin/settings/holidays/delete', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/holidays');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $db = Database::connect();
    $info = $db->prepare('SELECT holiday_date, name FROM holidays WHERE id = ?');
    $info->execute([$id]);
    $hRow = $info->fetch(\PDO::FETCH_ASSOC) ?: ['holiday_date' => '', 'name' => ''];
    $db->prepare('DELETE FROM holidays WHERE id = ?')->execute([$id]);
    logAudit(
        'holiday.deleted',
        $id,
        'holiday',
        'date=' . $hRow['holiday_date'] . '; name=' . $hRow['name']
    );

    Sla::recalculateAll($db);
    flash('success', 'Holiday removed.');
    redirect('/admin/settings/holidays');
});

$router->post('/admin/settings/holidays/toggle', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/holidays');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $db = Database::connect();
    $db->prepare('UPDATE holidays SET exclude_from_sla = 1 - exclude_from_sla WHERE id = ?')->execute([$id]);
    $stateStmt = $db->prepare('SELECT exclude_from_sla, holiday_date, name FROM holidays WHERE id = ?');
    $stateStmt->execute([$id]);
    $hState = $stateStmt->fetch(\PDO::FETCH_ASSOC) ?: ['exclude_from_sla' => null, 'holiday_date' => '', 'name' => ''];
    logAudit(
        'holiday.toggled',
        $id,
        'holiday',
        'date=' . $hState['holiday_date'] . '; name=' . $hState['name'] . '; exclude_from_sla=' . $hState['exclude_from_sla']
    );

    Sla::recalculateAll($db);
    redirect('/admin/settings/holidays');
});

$router->post('/admin/settings/holidays/auto-populate', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/holidays');
    }

    $country = strtoupper(trim($_POST['country'] ?? ''));
    $year    = (int) ($_POST['year'] ?? date('Y'));

    if (!array_key_exists($country, Holidays::supportedCountries())) {
        flash('error', 'Please select a valid country.');
        redirect('/admin/settings/holidays');
    }
    if ($year < 1990 || $year > 2100) {
        flash('error', 'Please enter a year between 1990 and 2100.');
        redirect('/admin/settings/holidays');
    }

    $holidays = Holidays::getForYear($country, $year);
    if (empty($holidays)) {
        flash('error', 'No holidays found for the selected country and year.');
        redirect('/admin/settings/holidays');
    }

    $db      = Database::connect();
    $stmt    = $db->prepare('INSERT IGNORE INTO holidays (holiday_date, name, exclude_from_sla) VALUES (?, ?, 1)');
    $added   = 0;
    $skipped = 0;

    foreach ($holidays as $h) {
        $stmt->execute([$h['date'], $h['name']]);
        if ($stmt->rowCount() > 0) {
            $added++;
        } else {
            $skipped++;
        }
    }

    Sla::recalculateAll($db);

    logAudit(
        'holiday.auto_populated',
        null,
        'holiday',
        'country=' . $country . '; year=' . $year . '; added=' . $added . '; skipped=' . $skipped
    );

    $countryName = Holidays::supportedCountries()[$country];
    $msg = "Added {$added} " . ($added === 1 ? 'holiday' : 'holidays') . " for {$countryName} ({$year}).";
    if ($skipped > 0) {
        $msg .= " {$skipped} " . ($skipped === 1 ? 'was' : 'were') . " already present and skipped.";
    }
    flash('success', $msg);
    redirect('/admin/settings/holidays');
});

/* ==================================================================
 * ADMIN – SLA Policy Settings
 * ================================================================== */

$router->get('/admin/settings/sla-policies', function () {
    Auth::requirePermission('sla.manage');
    $db = Database::connect();
    $priorities = $db->query('SELECT * FROM ticket_priorities ORDER BY sort_order')->fetchAll();
    $types = $db->query('SELECT * FROM ticket_types ORDER BY name')->fetchAll();

    // Load existing policies keyed by [typeKey][priorityId]
    // typeKey 0 = default (type_id IS NULL)
    $policyStmt = $db->query('SELECT * FROM sla_policies');
    $policies = [];
    while ($row = $policyStmt->fetch()) {
        $typeKey = $row['type_id'] ? (int) $row['type_id'] : 0;
        $policies[$typeKey][(int) $row['priority_id']] = $row;
    }

    render('admin/settings/sla-policies', ['priorities' => $priorities, 'policies' => $policies, 'types' => $types]);
});

$router->post('/admin/settings/sla-policies', function () {
    Auth::requirePermission('sla.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/sla-policies');
    }

    $db = Database::connect();
    $policiesData = $_POST['policies'] ?? [];

    // Delete all existing policies and re-insert (simplest for the nested type+priority structure)
    $db->exec('DELETE FROM sla_policies');

    $insert = $db->prepare(
        'INSERT INTO sla_policies (type_id, priority_id, first_response_minutes, resolution_minutes) VALUES (?, ?, ?, ?)'
    );

    $rowsWritten = 0;
    foreach ($policiesData as $typeKey => $priorities) {
        $typeId = (int) $typeKey === 0 ? null : (int) $typeKey;
        foreach ($priorities as $priorityId => $data) {
            $priorityId = (int) $priorityId;
            $firstResponse = (int) ($data['first_response_minutes'] ?? 0);
            $resolution = (int) ($data['resolution_minutes'] ?? 0);

            if ($firstResponse > 0 && $resolution > 0) {
                $insert->execute([$typeId, $priorityId, $firstResponse, $resolution]);
                $rowsWritten++;
            }
        }
    }

    logAudit(
        'sla.policies_saved',
        null,
        'sla_policy',
        'rules_written=' . $rowsWritten
    );

    flash('success', 'SLA policies saved.');
    redirect('/admin/settings/sla-policies');
});

$router->post('/admin/settings/sla-recalculate', function () {
    Auth::requirePermission('sla.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/sla-policies');
    }

    $count = Sla::recalculateAll(Database::connect());
    logAudit('sla.recalculated', null, 'sla_policy', 'tickets_updated=' . (int) $count);
    flash('success', "SLA recalculated for {$count} ticket(s).");
    redirect('/admin/settings/sla-policies');
});

$router->post('/admin/settings/sla-toggle', function () {
    Auth::requirePermission('sla.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/sla-policies');
    }

    $enabled = isset($_POST['sla_enabled']) ? '1' : '0';
    setSetting('sla_enabled', $enabled);
    logAudit('sla.toggled', null, 'setting', 'sla_enabled=' . $enabled);
    flash('success', $enabled === '1'
        ? 'SLA tracking enabled.'
        : 'SLA tracking disabled site-wide.');
    redirect('/admin/settings/sla-policies');
});

/* ==================================================================
 * ADMIN – Import Tickets from CSV
 * ================================================================== */

$router->get('/admin/settings/import', function () {
    Auth::requirePermission('import.manage');
    $skippedFile = $_SESSION['import_skipped_file'] ?? null;
    render('admin/settings/import', [
        'hasSkippedDownload' => $skippedFile !== null && file_exists($skippedFile),
    ]);
});

$router->get('/admin/settings/import/download-skipped', function () {
    Auth::requirePermission('import.manage');
    $filePath = $_SESSION['import_skipped_file'] ?? '';
    if ($filePath === '' || !file_exists($filePath)) {
        flash('error', 'Skipped rows file not found or already downloaded.');
        redirect('/admin/settings/import');
    }
    unset($_SESSION['import_skipped_file']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="import_skipped_rows.csv"');
    header('Cache-Control: no-store');
    readfile($filePath);
    @unlink($filePath);
    exit;
});

$router->post('/admin/settings/import/preview', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import');
    }

    // Validate upload
    $uploadErr = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadErr !== UPLOAD_ERR_OK) {
        $errMsg = match ($uploadErr) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the allowed upload size. Check your server upload limits.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            default => 'Upload error (code ' . $uploadErr . ').',
        };
        flash('error', $errMsg);
        redirect('/admin/settings/import');
    }
    if ($_FILES['csv_file']['size'] > 64 * 1024 * 1024) {
        flash('error', 'File is too large. Maximum 64 MB.');
        redirect('/admin/settings/import');
    }

    // Save file to disk — avoids storing large CSV data in the PHP session
    $storageDir = ROOT_DIR . '/storage/imports/';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        flash('error', 'Import directory could not be created. On the server run: mkdir -p storage/imports && chmod -R 775 storage/');
        redirect('/admin/settings/import');
    }
    if (!is_writable($storageDir)) {
        flash('error', 'Import directory is not writable. On the server run: chmod -R 775 storage/ && chown -R www-data:www-data storage/');
        redirect('/admin/settings/import');
    }
    $importId   = bin2hex(random_bytes(16));
    $importPath = $storageDir . $importId . '.csv';
    $tmpPath    = $_FILES['csv_file']['tmp_name'];
    // move_uploaded_file() can fail when destination is on a network path;
    // fall back to copy() in that case.
    if (!move_uploaded_file($tmpPath, $importPath) && !copy($tmpPath, $importPath)) {
        flash('error', 'Could not save the uploaded file to storage/imports/. Check web server write permissions.');
        redirect('/admin/settings/import');
    }

    $handle = fopen($importPath, 'r');
    if (!$handle) {
        @unlink($importPath);
        flash('error', 'Could not read the uploaded file.');
        redirect('/admin/settings/import');
    }

    // Auto-detect delimiter: read the first line and pick the most common candidate
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = ',';
    if ($firstLine !== false) {
        $counts = [
            ','  => substr_count($firstLine, ','),
            "\t" => substr_count($firstLine, "\t"),
            ';'  => substr_count($firstLine, ';'),
            '|'  => substr_count($firstLine, '|'),
        ];
        arsort($counts);
        $delimiter = key($counts) ?: ',';
    }

    // Read header row, strip UTF-8 BOM
    $header = fgetcsv($handle, 0, $delimiter);
    if (!$header || count($header) < 2) {
        fclose($handle);
        @unlink($importPath);
        flash('error', 'The CSV file appears to be empty or has too few columns. Ensure the file has a header row and uses comma, tab, or semicolon as its delimiter.');
        redirect('/admin/settings/import');
    }
    $header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

    // Read up to 3 sample rows for the column-mapping preview
    $sampleRows = [];
    $hasData    = false;
    while (count($sampleRows) < 3 && ($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($header as $i => $col) {
            $row[$col] = trim($csvRow[$i] ?? '');
        }
        $sampleRows[] = $row;
        $hasData = true;
    }
    fclose($handle);

    if (!$hasData) {
        @unlink($importPath);
        flash('error', 'No data rows found in the CSV file.');
        redirect('/admin/settings/import');
    }

    // Clean up any previous import file still on disk
    if (!empty($_SESSION['import_file']) && file_exists($_SESSION['import_file'])) {
        @unlink($_SESSION['import_file']);
    }

    unset($_SESSION['import_raw'], $_SESSION['import_data'], $_SESSION['import_summary'], $_SESSION['import_mapping']);
    $_SESSION['import_file']        = $importPath;
    $_SESSION['import_delimiter']   = $delimiter;
    $_SESSION['import_headers']     = $header;
    $_SESSION['import_sample_rows'] = $sampleRows;

    redirect('/admin/settings/import/map');
});

$router->get('/admin/settings/import/map', function () {
    Auth::requirePermission('import.manage');
    if (empty($_SESSION['import_headers'])) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import');
    }

    $headers    = $_SESSION['import_headers'];
    $sampleRows = $_SESSION['import_sample_rows'] ?? [];

    $systemFields = [
        ['key' => 'subject',      'label' => 'Subject',             'required' => true],
        ['key' => 'description',  'label' => 'Description',         'required' => false],
        ['key' => 'legacy_id',    'label' => 'Ticket ID (Legacy)',   'required' => false],
        ['key' => 'email',        'label' => 'Submitter Email',      'required' => true],
        ['key' => 'full_name',    'label' => 'Submitter Name',       'required' => false],
        ['key' => 'status',       'label' => 'Status',               'required' => false],
        ['key' => 'priority',     'label' => 'Priority',             'required' => false],
        ['key' => 'agent',        'label' => 'Assigned Agent',       'required' => false],
        ['key' => 'group',        'label' => 'Group',                'required' => false],
        ['key' => 'type',         'label' => 'Ticket Type',          'required' => false],
        ['key' => 'location',     'label' => 'Location',             'required' => false],
        ['key' => 'created_at',   'label' => 'Created Date',         'required' => false],
        ['key' => 'due_date',     'label' => 'Due Date',             'required' => false],
        ['key' => 'updated_at',   'label' => 'Last Updated',         'required' => false],
        ['key' => 'responded_at', 'label' => 'First Response Date',  'required' => false],
        ['key' => 'tags',         'label' => 'Tags',                 'required' => false],
    ];

    $aliases = [
        'subject'     => ['subject', 'title', 'issue', 'summary', 'ticket subject'],
        'description' => ['description', 'body', 'details', 'content', 'message', 'issue description'],
        'legacy_id'   => ['ticket id', 'id', 'ticket_id', 'legacy id', '#', 'ticket number'],
        'email'       => ['email', 'requester email', 'customer email', 'contact email', 'submitter email'],
        'full_name'   => ['full name', 'full_name', 'name', 'customer name', 'requester name', 'submitter',
                          'submitted by (if using shared computer)', 'requester'],
        'status'      => ['status', 'ticket status', 'state', 'resolution status'],
        'priority'    => ['priority', 'priority name', 'urgency'],
        'agent'       => ['agent', 'assigned agent', 'assignee', 'agent name', 'assigned to'],
        'group'       => ['group', 'group name', 'team'],
        'type'        => ['type of ticket', 'type', 'ticket type', 'category', 'issue type'],
        'location'    => ['location', 'department', 'branch', 'site', 'office'],
        'created_at'  => ['created time', 'created_at', 'created date', 'date created', 'open date', 'created_time'],
        'due_date'    => ['due by time', 'due_date', 'due date', 'deadline', 'due_by_time'],
        'updated_at'  => ['last update time', 'updated_at', 'last updated', 'modified', 'last_update_time'],
        'responded_at'=> ['initial response time', 'responded_at', 'first response', 'initial_response_time'],
        'tags'        => ['tags', 'tag', 'labels', 'label'],
    ];

    $lowerHeaders = array_map('strtolower', $headers);
    $autoMapping  = [];
    foreach ($systemFields as $field) {
        $autoMapping[$field['key']] = null;
        $aliasList = $aliases[$field['key']] ?? [strtolower($field['label'])];
        foreach ($aliasList as $alias) {
            $idx = array_search($alias, $lowerHeaders, true);
            if ($idx !== false) {
                $autoMapping[$field['key']] = $headers[$idx];
                break;
            }
        }
    }

    render('admin/settings/import-map', [
        'headers'      => $headers,
        'systemFields' => $systemFields,
        'autoMapping'  => $autoMapping,
        'sampleRows'   => $sampleRows,
    ]);
});

$router->post('/admin/settings/import/map', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import');
    }

    if (empty($_SESSION['import_file']) || !file_exists($_SESSION['import_file'])) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import');
    }

    $userMapping = $_POST['mapping'] ?? [];
    $subjectCol  = $userMapping['subject'] ?? '';
    $emailCol    = $userMapping['email']   ?? '';

    if ($subjectCol === '' || $emailCol === '') {
        flash('error', 'Subject and Submitter Email are required. Please map them to a CSV column.');
        redirect('/admin/settings/import/map');
    }

    $importPath  = $_SESSION['import_file'];
    $delimiter   = $_SESSION['import_delimiter'] ?? ',';
    $fileHeaders = $_SESSION['import_headers'] ?? [];

    $handle = fopen($importPath, 'r');
    if (!$handle) {
        flash('error', 'Could not read the import file. Please upload again.');
        redirect('/admin/settings/import');
    }
    fgetcsv($handle, 0, $delimiter); // skip header row

    // Build DB lookups for summary stats
    $db = Database::connect();
    $existingUsers = [];
    foreach ($db->query("SELECT id, email, TRIM(CONCAT(first_name, ' ', last_name)) AS full_name FROM users")->fetchAll() as $u) {
        $existingUsers[strtolower($u['email'])] = true;
        $existingUsers['name:' . strtolower($u['full_name'])] = true;
    }
    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = true;
    }

    $totalRows        = 0;
    $newUserEmails    = [];
    $newAgentNames    = [];
    $newLocationNames = [];

    // Stream the file row-by-row — never load all rows into memory
    while (($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) {
            continue;
        }
        $rawRow = [];
        foreach ($fileHeaders as $i => $col) {
            $rawRow[$col] = trim($csvRow[$i] ?? '');
        }
        $get = function (string $fieldKey) use ($rawRow, $userMapping): string {
            $col = $userMapping[$fieldKey] ?? '';
            return $col !== '' ? trim($rawRow[$col] ?? '') : '';
        };

        $subject = $get('subject');
        $email   = strtolower($get('email'));
        if ($subject === '' || $email === '') {
            continue;
        }
        $totalRows++;

        if (!isset($existingUsers[$email])) {
            $newUserEmails[$email] = $get('full_name');
        }
        $agent = $get('agent');
        if ($agent !== '' && $agent !== 'No Agent' && !isset($existingUsers['name:' . strtolower($agent)])) {
            $newAgentNames[strtolower($agent)] = $agent;
        }
        $location = $get('location');
        if ($location !== '' && !isset($existingLocations[strtolower($location)])) {
            $newLocationNames[strtolower($location)] = $location;
        }
    }
    fclose($handle);

    if ($totalRows === 0) {
        flash('error', 'No valid rows found after applying the mapping. Ensure Subject and Submitter Email columns contain data.');
        redirect('/admin/settings/import/map');
    }

    $_SESSION['import_mapping'] = $userMapping;
    $_SESSION['import_summary'] = [
        'total_tickets'     => $totalRows,
        'new_users'         => count($newUserEmails),
        'new_agents'        => count($newAgentNames),
        'new_locations'     => count($newLocationNames),
        'new_user_list'     => array_values($newUserEmails),
        'new_agent_list'    => array_values($newAgentNames),
        'new_location_list' => array_values($newLocationNames),
    ];
    unset($_SESSION['import_data']);
    redirect('/admin/settings/import/preview');
});

$router->get('/admin/settings/import/preview', function () {
    Auth::requirePermission('import.manage');
    $summary = $_SESSION['import_summary'] ?? null;
    if (!$summary || empty($_SESSION['import_file'])) {
        flash('error', 'No import data found. Please start the import again.');
        redirect('/admin/settings/import');
    }

    // Stream up to 15 preview rows from disk — no row data stored in session
    $previewRows = [];
    $importPath  = $_SESSION['import_file'];
    $delimiter   = $_SESSION['import_delimiter'] ?? ',';
    $fileHeaders = $_SESSION['import_headers'] ?? [];
    $userMapping = $_SESSION['import_mapping'] ?? [];
    $statusMap   = [
        'open' => 'open', 'new' => 'open', 'closed' => 'closed',
        'pending' => 'pending', 'resolved' => 'resolved',
        'in progress' => 'in_progress', 'in_progress' => 'in_progress',
        'waiting' => 'waiting_on_customer',
        'waiting on customer' => 'waiting_on_customer',
        'waiting on third party' => 'waiting_on_third_party',
    ];

    if (file_exists($importPath) && !empty($fileHeaders)) {
        $handle = fopen($importPath, 'r');
        if ($handle) {
            fgetcsv($handle, 0, $delimiter); // skip header row
            while (count($previewRows) < 15 && ($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) continue;
                $rawRow = [];
                foreach ($fileHeaders as $i => $col) {
                    $rawRow[$col] = trim($csvRow[$i] ?? '');
                }
                $get = function (string $k) use ($rawRow, $userMapping): string {
                    $col = $userMapping[$k] ?? '';
                    return $col !== '' ? trim($rawRow[$col] ?? '') : '';
                };
                $subject = $get('subject');
                $email   = strtolower($get('email'));
                if ($subject === '' || $email === '') continue;
                $statusRaw   = strtolower($get('status'));
                $previewRows[] = [
                    'legacy_id'      => $get('legacy_id'),
                    'subject'        => $subject,
                    'description'    => $get('description'),
                    'email'          => $email,
                    'submitter_name' => $get('full_name'),
                    'status'         => $statusMap[$statusRaw] ?? ticketDefaultNewStatusSlug(),
                    'priority'       => $get('priority'),
                    'agent'          => $get('agent'),
                    'group'          => $get('group'),
                    'type'           => $get('type'),
                    'location'       => $get('location'),
                    'created_at'     => $get('created_at'),
                    'due_date'       => $get('due_date'),
                    'updated_at'     => $get('updated_at'),
                    'responded_at'   => $get('responded_at'),
                    'tags'           => $get('tags'),
                ];
            }
            fclose($handle);
        }
    }

    render('admin/settings/import-preview', [
        'summary'     => $summary,
        'previewRows' => $previewRows,
    ]);
});

$router->post('/admin/settings/import/confirm', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import');
    }

    $importPath  = $_SESSION['import_file'] ?? '';
    $delimiter   = $_SESSION['import_delimiter'] ?? ',';
    $fileHeaders = $_SESSION['import_headers'] ?? [];
    $userMapping = $_SESSION['import_mapping'] ?? [];

    if ($importPath === '' || !file_exists($importPath) || empty($fileHeaders) || empty($userMapping)) {
        flash('error', 'No import data found. Please upload the CSV again.');
        redirect('/admin/settings/import');
    }

    $handle = fopen($importPath, 'r');
    if (!$handle) {
        flash('error', 'Could not read the import file. Please upload again.');
        redirect('/admin/settings/import');
    }
    fgetcsv($handle, 0, $delimiter); // skip header row

    $statusMap = [
        'open'                   => 'open',
        'new'                    => 'open',
        'closed'                 => 'closed',
        'pending'                => 'pending',
        'resolved'               => 'resolved',
        'in progress'            => 'in_progress',
        'in_progress'            => 'in_progress',
        'waiting'                => 'waiting_on_customer',
        'waiting on customer'    => 'waiting_on_customer',
        'waiting on third party' => 'waiting_on_third_party',
    ];

    // Robust date parser — interprets $raw as a datetime in $sourceTz, stores as UTC.
    // Pass the location's timezone as $sourceTz so imported timestamps from each
    // location are correctly normalised to UTC for storage.
    $parseDateTime = function (string $raw, string $sourceTz = 'UTC'): ?string {
        if ($raw === '') return null;
        try {
            $dt = new DateTime($raw, new DateTimeZone($sourceTz));
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $ts = strtotime($raw);
            return ($ts !== false && $ts > 0) ? gmdate('Y-m-d H:i:s', $ts) : null;
        }
    };
    $parseDateOnly = function (string $raw): ?string {
        if ($raw === '') return null;
        $ts = strtotime($raw);
        return ($ts !== false && $ts > 0) ? date('Y-m-d', $ts) : null;
    };

    $db = Database::connect();

    // Load all lookups
    $existingUsers = [];
    foreach ($db->query("SELECT id, email, TRIM(CONCAT(first_name, ' ', last_name)) AS full_name FROM users")->fetchAll() as $u) {
        $existingUsers[strtolower($u['email'])] = (int) $u['id'];
        $existingUsers['name:' . strtolower($u['full_name'])] = (int) $u['id'];
    }
    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = (int) $l['id'];
    }
    $existingTypes = [];
    foreach ($db->query('SELECT id, name FROM ticket_types')->fetchAll() as $t) {
        $existingTypes[strtolower($t['name'])] = (int) $t['id'];
    }
    $existingGroups = [];
    foreach ($db->query('SELECT id, name FROM `groups`')->fetchAll() as $g) {
        $existingGroups[strtolower($g['name'])] = (int) $g['id'];
    }
    $existingPriorities = [];
    foreach ($db->query('SELECT id, name FROM ticket_priorities')->fetchAll() as $p) {
        $existingPriorities[strtolower($p['name'])] = (int) $p['id'];
    }
    $existingTags = [];
    foreach ($db->query('SELECT id, name FROM ticket_tags')->fetchAll() as $t) {
        $existingTags[strtolower($t['name'])] = (int) $t['id'];
    }

    $imported    = 0;
    $skipped     = 0;
    $skippedRows = []; // raw CSV rows that couldn't be imported, available for download
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $db->beginTransaction();
    try {
        $insertUser = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role, location_id) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertLocation = $db->prepare('INSERT INTO locations (name) VALUES (?)');
        $insertType     = $db->prepare('INSERT INTO ticket_types (name, sort_order) VALUES (?, ?)');
        $insertTicket   = $db->prepare(
            'INSERT INTO tickets (subject, description, legacy_id, created_by, created_at, due_date, type_id, location_id, status, priority_id, assigned_to, group_id, first_responded_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertTimeline = $db->prepare(
            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at) VALUES (?, ?, ?, ?, 0, ?)'
        );
        $insertTag    = $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)');
        $insertTagMap = $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)');

        $nextTypeOrder = (int) ($db->query('SELECT COALESCE(MAX(sort_order), 0) FROM ticket_types')->fetchColumn()) + 1;

        // Stream rows from disk — no large session data
        while (($csvRow = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) continue;

            $rawRow = [];
            foreach ($fileHeaders as $i => $col) {
                $rawRow[$col] = trim($csvRow[$i] ?? '');
            }
            $get = function (string $fieldKey) use ($rawRow, $userMapping): string {
                $col = $userMapping[$fieldKey] ?? '';
                return $col !== '' ? trim($rawRow[$col] ?? '') : '';
            };

            $subject = $get('subject');
            $email   = strtolower($get('email'));
            if ($subject === '' || $email === '') {
                $reason = $subject === '' && $email === '' ? 'Missing subject and email'
                        : ($subject === '' ? 'Missing subject' : 'Missing email');
                $skippedRows[] = ['_reason' => $reason] + $rawRow;
                $skipped++;
                continue;
            }

            $statusRaw = strtolower($get('status'));
            $row = [
                'legacy_id'      => $get('legacy_id'),
                'subject'        => $subject,
                'description'    => $get('description'),
                'email'          => $email,
                'submitter_name' => $get('full_name'),
                'status'         => $statusMap[$statusRaw] ?? 'open',
                'priority'       => $get('priority'),
                'agent'          => $get('agent'),
                'group'          => $get('group'),
                'type'           => $get('type'),
                'location'       => $get('location'),
                'created_at'     => $get('created_at'),
                'due_date'       => $get('due_date'),
                'updated_at'     => $get('updated_at'),
                'responded_at'   => $get('responded_at'),
                'tags'           => $get('tags'),
            ];

            // --- Resolve submitter ---
            $creatorId = null;
            if (isset($existingUsers[$row['email']])) {
                $creatorId = $existingUsers[$row['email']];
            } else {
                $nameParts = splitFullName($row['submitter_name']);
                $locId = $row['location'] !== '' ? ($existingLocations[strtolower($row['location'])] ?? null) : null;
                $insertUser->execute([$nameParts[0], $nameParts[1], $row['email'], $randomPassword, 'user', $locId]);
                $creatorId = (int) $db->lastInsertId();
                $existingUsers[$row['email']] = $creatorId;
                $existingUsers['name:' . strtolower($row['submitter_name'])] = $creatorId;
            }

            if ($creatorId === null) {
                $skipped++;
                continue;
            }

            // --- Resolve agent ---
            $agentId   = null;
            $agentName = $row['agent'];
            if ($agentName !== '' && $agentName !== 'No Agent') {
                $agentKey   = 'name:' . strtolower($agentName);
                $agentEmail = strtolower(str_replace(' ', '.', $agentName)) . '@imported.local';
                if (isset($existingUsers[$agentKey])) {
                    $agentId = $existingUsers[$agentKey];
                } elseif (isset($existingUsers[$agentEmail])) {
                    // Match by generated email — covers re-imports where TRIM mismatch occurred
                    $agentId = $existingUsers[$agentEmail];
                    $existingUsers[$agentKey] = $agentId;
                } else {
                    $nameParts = splitFullName($agentName);
                    $insertUser->execute([$nameParts[0], $nameParts[1], $agentEmail, $randomPassword, 'agent', null]);
                    $agentId = (int) $db->lastInsertId();
                    $existingUsers[$agentKey]  = $agentId;
                    $existingUsers[$agentEmail] = $agentId;
                }
            }

            // --- Resolve location ---
            $locationId = null;
            if ($row['location'] !== '') {
                $locKey = strtolower($row['location']);
                if (isset($existingLocations[$locKey])) {
                    $locationId = $existingLocations[$locKey];
                } else {
                    $insertLocation->execute([$row['location']]);
                    $locationId = (int) $db->lastInsertId();
                    $existingLocations[$locKey] = $locationId;
                }
            }

            // --- Resolve type ---
            $typeId = null;
            if ($row['type'] !== '') {
                $typeKey = strtolower($row['type']);
                if (isset($existingTypes[$typeKey])) {
                    $typeId = $existingTypes[$typeKey];
                } else {
                    $insertType->execute([$row['type'], $nextTypeOrder++]);
                    $typeId = (int) $db->lastInsertId();
                    $existingTypes[$typeKey] = $typeId;
                }
            }

            // --- Resolve priority ---
            $priorityId = $row['priority'] !== '' ? ($existingPriorities[strtolower($row['priority'])] ?? null) : null;

            // --- Resolve group ---
            $groupId = ($row['group'] ?? '') !== '' ? ($existingGroups[strtolower($row['group'])] ?? null) : null;
            // Legacy CSV often has a blank group cell or names a group that
            // no longer exists. resolveTicketGroup() falls through to the
            // type's default group, then the system default_group_id, so an
            // imported ticket never lands with NULL group.
            $groupId = resolveTicketGroup($db, $groupId, $typeId);

            // --- Parse dates — treat source timestamps as being in the location's timezone ---
            $locationTz  = getLocationTimezone($locationId);
            $createdAt   = $parseDateTime($row['created_at'], $locationTz) ?? gmdate('Y-m-d H:i:s');
            $dueDate     = $parseDateOnly($row['due_date']);
            $updatedAt   = $parseDateTime($row['updated_at'], $locationTz) ?? $createdAt;
            $respondedAt = $parseDateTime($row['responded_at'], $locationTz);

            // --- Insert ticket ---
            $insertTicket->execute([
                $row['subject'],
                $row['description'] !== '' ? $row['description'] : '(Imported from legacy system)',
                $row['legacy_id'] !== '' ? $row['legacy_id'] : null,
                $creatorId,
                $createdAt,
                $dueDate,
                $typeId,
                $locationId,
                $row['status'],
                $priorityId,
                $agentId,
                $groupId,
                $respondedAt,
                $updatedAt,
            ]);
            $ticketId = (int) $db->lastInsertId();

            // --- Timeline entry ---
            $insertTimeline->execute([$ticketId, $creatorId, 'created', 'Ticket created (imported from legacy system).', $createdAt]);

            // --- Tags ---
            $tagStr = trim($row['tags'], '" ');
            if ($tagStr !== '') {
                $tagNames = array_filter(array_map('trim', explode(',', $tagStr)));
                foreach ($tagNames as $tagName) {
                    $tagKey = strtolower($tagName);
                    if (!isset($existingTags[$tagKey])) {
                        $insertTag->execute([$tagName]);
                        $existingTags[$tagKey] = (int) $db->lastInsertId();
                    }
                    $insertTagMap->execute([$ticketId, $existingTags[$tagKey]]);
                }
            }

            $imported++;
        }

        fclose($handle);
        $db->commit();
    } catch (PDOException $e) {
        fclose($handle);
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/settings/import');
    }

    // Clean up disk file and session import state
    @unlink($importPath);
    unset(
        $_SESSION['import_file'],
        $_SESSION['import_delimiter'],
        $_SESSION['import_headers'],
        $_SESSION['import_sample_rows'],
        $_SESSION['import_mapping'],
        $_SESSION['import_summary'],
        $_SESSION['import_skipped_file']
    );

    // Write skipped rows to a downloadable CSV if any were skipped
    if (!empty($skippedRows)) {
        $skippedPath = ROOT_DIR . '/storage/imports/' . bin2hex(random_bytes(8)) . '_skipped.csv';
        $fp = fopen($skippedPath, 'w');
        if ($fp) {
            // Header: reason column first, then original CSV columns
            $csvHeaders = array_keys($skippedRows[0]);
            fputcsv($fp, array_map(fn($h) => $h === '_reason' ? 'Skipped Reason' : $h, $csvHeaders));
            foreach ($skippedRows as $sr) {
                fputcsv($fp, array_values($sr));
            }
            fclose($fp);
            $_SESSION['import_skipped_file'] = $skippedPath;
        }
    }

    logAudit(
        'ticket.import_confirmed',
        null,
        'ticket',
        'imported=' . $imported . '; skipped=' . $skipped
    );

    $msg = "Successfully imported {$imported} ticket(s).";
    if ($skipped > 0) {
        $msg .= " {$skipped} row(s) were skipped — see the import page to download them.";
    }
    flash('success', $msg);
    redirect('/admin/tickets');
});

/* ==================================================================
 * ADMIN – Import Users from CSV
 * ================================================================== */

$router->get('/admin/settings/import-users', function () {
    Auth::requirePermission('users.manage');
    render('admin/settings/import-users');
});

$router->post('/admin/settings/import-users/preview', function () {
    Auth::requirePermission('users.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-users');
    }

    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please select a valid CSV file.');
        redirect('/admin/settings/import-users');
    }
    if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
        flash('error', 'File is too large. Maximum 10 MB.');
        redirect('/admin/settings/import-users');
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        flash('error', 'Could not read the uploaded file.');
        redirect('/admin/settings/import-users');
    }

    $header = fgetcsv($handle);
    if (!$header || count($header) < 1) {
        fclose($handle);
        flash('error', 'The CSV file appears to be empty or has no columns.');
        redirect('/admin/settings/import-users');
    }
    $header = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $header);

    $rawRows = [];
    while (($csvRow = fgetcsv($handle)) !== false) {
        if (count(array_filter($csvRow, fn($v) => trim($v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($header as $i => $col) {
            $row[$col] = trim($csvRow[$i] ?? '');
        }
        $rawRows[] = $row;
    }
    fclose($handle);

    if (empty($rawRows)) {
        flash('error', 'No data rows found in the CSV file.');
        redirect('/admin/settings/import-users');
    }

    $_SESSION['user_import_raw'] = ['headers' => $header, 'rows' => $rawRows];
    unset($_SESSION['user_import_data'], $_SESSION['user_import_summary']);
    redirect('/admin/settings/import-users/map');
});

$router->get('/admin/settings/import-users/map', function () {
    Auth::requirePermission('users.manage');
    $raw = $_SESSION['user_import_raw'] ?? null;
    if (!$raw) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import-users');
    }

    $headers = $raw['headers'];

    $systemFields = [
        ['key' => 'email',      'label' => 'Email Address',  'required' => true,  'hint' => 'Required'],
        ['key' => 'full_name',  'label' => 'Full Name',       'required' => false, 'hint' => 'Used if First/Last Name not mapped'],
        ['key' => 'first_name', 'label' => 'First Name',      'required' => false, 'hint' => null],
        ['key' => 'last_name',  'label' => 'Last Name',       'required' => false, 'hint' => null],
        ['key' => 'role',       'label' => 'Role',             'required' => false, 'hint' => 'user / agent / admin'],
        ['key' => 'work_phone', 'label' => 'Work Phone',      'required' => false, 'hint' => null],
        ['key' => 'location',   'label' => label('location.singular'), 'required' => false, 'hint' => 'Matched by name; created if missing'],
    ];

    $aliases = [
        'email'      => ['email', 'email address', 'e-mail', 'user email', 'work email', 'mail'],
        'full_name'  => ['full name', 'full_name', 'name', 'display name', 'contact name'],
        'first_name' => ['first name', 'first_name', 'given name', 'firstname', 'first'],
        'last_name'  => ['last name', 'last_name', 'surname', 'family name', 'lastname', 'last'],
        'role'       => ['role', 'user role', 'account type', 'type', 'access level'],
        'work_phone' => ['phone', 'work phone', 'telephone', 'work_phone', 'phone number', 'tel'],
        'location'   => ['location', 'branch', 'department', 'site', 'office'],
    ];

    $lowerHeaders = array_map('strtolower', $headers);
    $autoMapping  = [];
    foreach ($systemFields as $field) {
        $autoMapping[$field['key']] = null;
        $aliasList = $aliases[$field['key']] ?? [strtolower($field['label'])];
        foreach ($aliasList as $alias) {
            $idx = array_search($alias, $lowerHeaders, true);
            if ($idx !== false) {
                $autoMapping[$field['key']] = $headers[$idx];
                break;
            }
        }
    }

    render('admin/settings/import-users-map', [
        'headers'      => $headers,
        'systemFields' => $systemFields,
        'autoMapping'  => $autoMapping,
        'sampleRows'   => array_slice($raw['rows'], 0, 3),
    ]);
});

$router->post('/admin/settings/import-users/map', function () {
    Auth::requirePermission('users.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-users');
    }

    $raw = $_SESSION['user_import_raw'] ?? null;
    if (!$raw) {
        flash('error', 'No import data found. Please upload a CSV file first.');
        redirect('/admin/settings/import-users');
    }

    $userMapping = $_POST['mapping'] ?? [];
    $emailCol    = $userMapping['email'] ?? '';

    if ($emailCol === '') {
        flash('error', 'Email Address is required. Please map it to a CSV column.');
        redirect('/admin/settings/import-users/map');
    }

    $db = Database::connect();
    $existingEmails = [];
    foreach ($db->query('SELECT LOWER(email) AS email FROM users')->fetchAll(PDO::FETCH_COLUMN) as $e) {
        $existingEmails[$e] = true;
    }
    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = $l['id'];
    }

    $rows = [];
    $duplicateEmails  = [];
    $newLocationNames = [];

    foreach ($raw['rows'] as $rawRow) {
        $get = function (string $fieldKey) use ($rawRow, $userMapping): string {
            $col = $userMapping[$fieldKey] ?? '';
            return $col !== '' ? trim($rawRow[$col] ?? '') : '';
        };

        $email = strtolower($get('email'));
        if ($email === '') {
            continue;
        }

        $isDuplicate = isset($existingEmails[$email]);
        if ($isDuplicate) {
            $duplicateEmails[$email] = true;
        }

        // Resolve name: prefer first_name + last_name, fall back to full_name split
        $firstName = $get('first_name');
        $lastName  = $get('last_name');
        $fullName  = $get('full_name');
        if ($firstName === '' && $lastName === '' && $fullName !== '') {
            [$firstName, $lastName] = splitFullName($fullName);
        }

        $roleRaw = strtolower($get('role'));
        $role    = roleExists($roleRaw) ? $roleRaw : 'user';

        $location = $get('location');
        if ($location !== '' && !isset($existingLocations[strtolower($location)])) {
            $newLocationNames[strtolower($location)] = $location;
        }

        $rows[] = [
            'email'        => $email,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'role'         => $role,
            'work_phone'   => $get('work_phone'),
            'location'     => $location,
            'is_duplicate' => $isDuplicate,
        ];
    }

    if (empty($rows)) {
        flash('error', 'No valid rows found after applying the mapping. Ensure the Email column contains data.');
        redirect('/admin/settings/import-users/map');
    }

    $newCount = count(array_filter($rows, fn($r) => !$r['is_duplicate']));

    $_SESSION['user_import_data']    = $rows;
    $_SESSION['user_import_summary'] = [
        'total_rows'       => count($rows),
        'new_users'        => $newCount,
        'duplicates'       => count($duplicateEmails),
        'new_locations'    => count($newLocationNames),
        'duplicate_list'   => array_keys($duplicateEmails),
        'new_location_list'=> array_values($newLocationNames),
    ];
    redirect('/admin/settings/import-users/preview');
});

$router->get('/admin/settings/import-users/preview', function () {
    Auth::requirePermission('users.manage');
    $rows    = $_SESSION['user_import_data']    ?? null;
    $summary = $_SESSION['user_import_summary'] ?? null;
    if (!$rows || !$summary) {
        flash('error', 'No import data found. Please start the import again.');
        redirect('/admin/settings/import-users');
    }
    render('admin/settings/import-users-preview', [
        'summary'     => $summary,
        'previewRows' => array_slice($rows, 0, 15),
    ]);
});

$router->post('/admin/settings/import-users/confirm', function () {
    Auth::requirePermission('users.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-users');
    }

    $rows = $_SESSION['user_import_data'] ?? [];
    unset($_SESSION['user_import_data'], $_SESSION['user_import_summary']);

    if (empty($rows)) {
        flash('error', 'No import data found. Please upload the CSV again.');
        redirect('/admin/settings/import-users');
    }

    $db = Database::connect();

    $existingEmails = [];
    foreach ($db->query('SELECT LOWER(email) AS email FROM users')->fetchAll(PDO::FETCH_COLUMN) as $e) {
        $existingEmails[$e] = true;
    }
    $existingLocations = [];
    foreach ($db->query('SELECT id, name FROM locations')->fetchAll() as $l) {
        $existingLocations[strtolower($l['name'])] = (int) $l['id'];
    }

    $imported = 0;
    $skipped  = 0;
    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $db->beginTransaction();
    try {
        $insertUser = $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role, work_phone, location_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insertLocation = $db->prepare('INSERT INTO locations (name) VALUES (?)');

        foreach ($rows as $row) {
            $email = $row['email'];
            if ($email === '' || isset($existingEmails[$email])) {
                $skipped++;
                continue;
            }

            // Resolve location
            $locationId = null;
            if ($row['location'] !== '') {
                $locKey = strtolower($row['location']);
                if (isset($existingLocations[$locKey])) {
                    $locationId = $existingLocations[$locKey];
                } else {
                    $insertLocation->execute([$row['location']]);
                    $locationId = (int) $db->lastInsertId();
                    $existingLocations[$locKey] = $locationId;
                }
            }

            $insertUser->execute([
                $row['first_name'],
                $row['last_name'],
                $email,
                $randomPassword,
                $row['role'],
                $row['work_phone'] !== '' ? $row['work_phone'] : null,
                $locationId,
            ]);
            $existingEmails[$email] = true;
            $imported++;
        }

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/settings/import-users');
    }

    logAudit(
        'user.import_confirmed',
        null,
        'user',
        'imported=' . $imported . '; skipped=' . $skipped
    );

    $msg = "Successfully imported {$imported} user(s).";
    if ($skipped > 0) {
        $msg .= " {$skipped} row(s) skipped (duplicate email or missing email).";
    }
    flash('success', $msg);
    redirect('/admin/users');
});

/* ==================================================================
 * ADMIN – Settings: Import KB Articles
 * ================================================================== */

$router->get('/admin/settings/import-kb', function () {
    Auth::requirePermission('import.manage');
    render('admin/settings/import-kb');
});

$router->post('/admin/settings/import-kb/preview', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-kb');
        return;
    }

    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please upload a valid CSV file.');
        redirect('/admin/settings/import-kb');
        return;
    }

    if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
        flash('error', 'File too large. Maximum 10 MB.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$handle) {
        flash('error', 'Unable to read file.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $header = fgetcsv($handle);
    if ($header) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // strip UTF-8 BOM if present
    }
    if (!$header || !in_array('title', $header) || !in_array('body_markdown', $header)) {
        fclose($handle);
        flash('error', 'CSV must contain "title" and "body_markdown" columns.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $colMap = array_flip($header);
    $rows   = [];
    $categories = [];

    while (($csvRow = fgetcsv($handle)) !== false) {
        $title    = trim($csvRow[$colMap['title']] ?? '');
        $body     = trim($csvRow[$colMap['body_markdown']] ?? '');
        $category = trim($csvRow[$colMap['category'] ?? -1] ?? '') ?: 'General';
        $status   = strtolower(trim($csvRow[$colMap['status'] ?? -1] ?? ''));
        $status   = in_array($status, ['published', 'draft']) ? $status : 'draft';
        $tags     = trim($csvRow[$colMap['tags'] ?? -1] ?? '');

        if ($title === '' || $body === '') {
            continue; // skip empty rows
        }

        $categories[$category] = true;

        $rows[] = [
            'title'    => $title,
            'body'     => $body,
            'category' => $category,
            'status'   => $status,
            'tags'     => $tags,
        ];
    }
    fclose($handle);

    if (empty($rows)) {
        flash('error', 'No valid articles found in CSV.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $_SESSION['kb_import_data'] = $rows;

    // Check which categories already exist
    $db = Database::connect();
    $existingCats = $db->query('SELECT name FROM kb_categories')->fetchAll(PDO::FETCH_COLUMN);
    $newCategories = array_diff(array_keys($categories), $existingCats);

    $summary = [
        'total_articles'  => count($rows),
        'categories'      => array_keys($categories),
        'new_categories'  => $newCategories,
        'draft_count'     => count(array_filter($rows, fn($r) => $r['status'] === 'draft')),
        'published_count' => count(array_filter($rows, fn($r) => $r['status'] === 'published')),
    ];

    $previewRows = array_slice($rows, 0, 15);

    render('admin/settings/import-kb-preview', [
        'summary'     => $summary,
        'previewRows' => $previewRows,
    ]);
});

$router->post('/admin/settings/import-kb/confirm', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $rows = $_SESSION['kb_import_data'] ?? [];
    unset($_SESSION['kb_import_data']);

    if (empty($rows)) {
        flash('error', 'No import data found. Please upload the CSV again.');
        redirect('/admin/settings/import-kb');
        return;
    }

    $db = Database::connect();

    // Build lookup maps for existing categories and folders
    $catLookup    = [];
    $folderLookup = [];

    foreach ($db->query('SELECT id, name FROM kb_categories')->fetchAll() as $c) {
        $catLookup[strtolower($c['name'])] = (int) $c['id'];
    }
    foreach ($db->query('SELECT id, category_id, name FROM kb_folders')->fetchAll() as $f) {
        $folderLookup[(int) $f['category_id'] . ':' . strtolower($f['name'])] = (int) $f['id'];
    }

    $db->beginTransaction();
    try {
        $insertCat    = $db->prepare('INSERT INTO kb_categories (name, slug, sort_order) VALUES (?, ?, ?)');
        $insertFolder = $db->prepare('INSERT INTO kb_folders (category_id, name, slug, sort_order) VALUES (?, ?, ?, ?)');
        $insertArticle = $db->prepare(
            'INSERT INTO kb_articles (folder_id, title, slug, body_markdown, status, published_at, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $checkSlug = $db->prepare('SELECT id FROM kb_articles WHERE slug = ?');

        $imported    = 0;
        $publishAll  = !empty($_POST['publish_all']);
        $nextCatOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order),0) FROM kb_categories')->fetchColumn();

        foreach ($rows as $row) {
            $catName = $row['category'];
            $catKey  = strtolower($catName);

            // Find or create category
            if (!isset($catLookup[$catKey])) {
                $nextCatOrder++;
                $insertCat->execute([$catName, slugify($catName), $nextCatOrder]);
                $catLookup[$catKey] = (int) $db->lastInsertId();
            }
            $catId = $catLookup[$catKey];

            // Find or create "General" folder in this category
            $folderKey = $catId . ':general';
            if (!isset($folderLookup[$folderKey])) {
                $insertFolder->execute([$catId, 'General', slugify($catName . '-general'), 0]);
                $folderLookup[$folderKey] = (int) $db->lastInsertId();
            }
            $folderId = $folderLookup[$folderKey];

            // Generate unique slug
            $slug = slugify($row['title']);
            $checkSlug->execute([$slug]);
            if ($checkSlug->fetch()) {
                $slug .= '-' . time() . '-' . $imported;
            }

            $status     = $publishAll ? 'published' : $row['status'];
            $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

            $insertArticle->execute([
                $folderId,
                $row['title'],
                $slug,
                $row['body'],
                $status,
                $publishedAt,
                Auth::id(),
                0,
            ]);
            $imported++;
        }

        $db->commit();
        flash('success', "Successfully imported {$imported} KB article(s).");
        redirect('/admin/kb/articles');
    } catch (PDOException $e) {
        $db->rollBack();
        flash('error', 'Import failed: ' . $e->getMessage());
        redirect('/admin/settings/import-kb');
    }
});

/* ==================================================================
 * ADMIN – Settings: Branding
 * ================================================================== */

$router->get('/admin/settings/branding', function () {
    Auth::requirePermission('settings.manage');
    render('admin/settings/branding', [
        'appName'             => getSetting('branding_app_name', 'OpenHelpDesk'),
        'primaryColor'        => getSetting('branding_primary_color', '#4f46e5'),
        'primaryHover'        => getSetting('branding_primary_hover', '#4338ca'),
        'navbarStart'         => getSetting('branding_navbar_start', '#1e1b4b'),
        'navbarEnd'           => getSetting('branding_navbar_end', '#312e81'),
        'logo'                => getSetting('branding_logo', ''),
        'navbarIcon'          => getSetting('branding_navbar_icon', 'bi-person-raised-hand'),
        'timelineNoteBg'      => getSetting('branding_timeline_note_bg',      '#fefce8'),
        'timelineNoteAccent'  => getSetting('branding_timeline_note_accent',  '#ca8a04'),
        'timelineSystemBg'    => getSetting('branding_timeline_system_bg',    '#eff6ff'),
        'timelineSystemAccent'=> getSetting('branding_timeline_system_accent','#3b82f6'),
    ]);
});

$router->post('/admin/settings/branding', function () {
    Auth::requirePermission('settings.manage');
    verifyCsrf($_POST['_token'] ?? '');

    $appName              = trim($_POST['app_name'] ?? 'OpenHelpDesk');
    $navbarIconRaw        = trim($_POST['navbar_icon'] ?? 'bi-person-raised-hand');
    // Normalise: ensure it starts with bi- and only contains safe chars
    if (!str_starts_with($navbarIconRaw, 'bi-')) {
        $navbarIconRaw = 'bi-' . $navbarIconRaw;
    }
    $navbarIcon = 'bi-' . preg_replace('/[^a-zA-Z0-9\-]/', '', substr($navbarIconRaw, 3));
    if ($navbarIcon === 'bi-') {
        $navbarIcon = 'bi-person-raised-hand';
    }
    $primaryColor         = trim($_POST['primary_color'] ?? '#4f46e5');
    $primaryHover         = trim($_POST['primary_hover'] ?? '#4338ca');
    $navbarStart          = trim($_POST['navbar_start'] ?? '#1e1b4b');
    $navbarEnd            = trim($_POST['navbar_end'] ?? '#312e81');
    $timelineNoteBg       = trim($_POST['timeline_note_bg']       ?? '#fefce8');
    $timelineNoteAccent   = trim($_POST['timeline_note_accent']   ?? '#ca8a04');
    $timelineSystemBg     = trim($_POST['timeline_system_bg']     ?? '#eff6ff');
    $timelineSystemAccent = trim($_POST['timeline_system_accent'] ?? '#3b82f6');

    // Validate hex colors
    $colorPattern = '/^#[0-9a-fA-F]{6}$/';
    foreach ([$primaryColor, $primaryHover, $navbarStart, $navbarEnd,
              $timelineNoteBg, $timelineNoteAccent, $timelineSystemBg, $timelineSystemAccent] as $color) {
        if (!preg_match($colorPattern, $color)) {
            flash('error', 'Invalid color format. Use hex colors like #4f46e5.');
            redirect('/admin/settings/branding');
            return;
        }
    }

    // Handle logo upload
    $currentLogo = basename(getSetting('branding_logo', ''));

    // Remove logo checkbox
    if (!empty($_POST['remove_logo'])) {
        if ($currentLogo && file_exists(ROOT_DIR . '/public/uploads/branding/' . $currentLogo)) {
            unlink(ROOT_DIR . '/public/uploads/branding/' . $currentLogo);
        }
        setSetting('branding_logo', '');
        $currentLogo = '';
    }

    // New logo upload
    if (!empty($_FILES['logo']['tmp_name'])) {
        $file = $_FILES['logo'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            flash('error', 'Invalid logo file type. Allowed: JPG, PNG, GIF, WEBP, SVG.');
            redirect('/admin/settings/branding');
            return;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            flash('error', 'Logo file is too large. Maximum 2 MB.');
            redirect('/admin/settings/branding');
            return;
        }

        // Delete old logo
        if ($currentLogo && file_exists(ROOT_DIR . '/public/uploads/branding/' . $currentLogo)) {
            unlink(ROOT_DIR . '/public/uploads/branding/' . $currentLogo);
        }

        $ext = match ($mime) {
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/gif'     => 'gif',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
            default         => 'png',
        };
        $filename = 'logo_' . uniqid() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], ROOT_DIR . '/public/uploads/branding/' . $filename);
        setSetting('branding_logo', $filename);
    }

    // Capture before-state for non-binary branding settings (logo handled
    // separately above — the audit row marks whether the file changed).
    $brandFields = [
        'branding_app_name'              => $appName,
        'branding_navbar_icon'           => $navbarIcon,
        'branding_primary_color'         => $primaryColor,
        'branding_primary_hover'         => $primaryHover,
        'branding_navbar_start'          => $navbarStart,
        'branding_navbar_end'            => $navbarEnd,
        'branding_timeline_note_bg'      => $timelineNoteBg,
        'branding_timeline_note_accent'  => $timelineNoteAccent,
        'branding_timeline_system_bg'    => $timelineSystemBg,
        'branding_timeline_system_accent' => $timelineSystemAccent,
    ];
    $brandBefore = [];
    foreach ($brandFields as $k => $_) {
        $brandBefore[$k] = getSetting($k, '');
    }
    foreach ($brandFields as $key => $value) {
        setSetting($key, $value);
    }

    logAuditChange('branding.settings_changed', null, null, $brandBefore, $brandFields);

    flash('success', 'Branding settings updated successfully.');
    redirect('/admin/settings/branding');
});

/* ==================================================================
 * ADMIN – Settings: Labels
 * ================================================================== */

$router->get('/admin/settings/labels', function () {
    Auth::requirePermission('settings.manage');
    render('admin/settings/labels');
});

$router->get('/admin/settings/labels/download', function () {
    Auth::requirePermission('settings.manage');
    $defaultFile = ROOT_DIR . '/config/labels.default.json';
    $defaults    = is_file($defaultFile)
        ? (json_decode(file_get_contents($defaultFile), true) ?: [])
        : [];
    $custom  = json_decode(getSetting('custom_labels', '{}'), true) ?: [];
    $merged  = array_merge($defaults, $custom);

    // Remove the internal readme key from the download
    unset($merged['_readme']);
    $merged = array_merge(
        ['_readme' => 'Edit the values (right-hand side) only. Keys must stay exactly as written. Re-upload to apply.'],
        $merged
    );

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="labels.json"');
    echo json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
});

$router->post('/admin/settings/labels/upload', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/labels');
    }

    if (empty($_FILES['labels_file']['tmp_name']) || $_FILES['labels_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please select a valid JSON file.');
        redirect('/admin/settings/labels');
    }

    if ($_FILES['labels_file']['size'] > 512 * 1024) {
        flash('error', 'File is too large. Maximum 512 KB.');
        redirect('/admin/settings/labels');
    }

    $raw = file_get_contents($_FILES['labels_file']['tmp_name']);
    $uploaded = json_decode($raw, true);

    if ($uploaded === null) {
        $_SESSION['label_upload_errors']  = ['The file is not valid JSON: ' . json_last_error_msg()];
        $_SESSION['label_upload_preview'] = $raw;
        redirect('/admin/settings/labels');
    }

    // Load known keys from the default file
    $defaultFile = ROOT_DIR . '/config/labels.default.json';
    $defaults    = is_file($defaultFile)
        ? (json_decode(file_get_contents($defaultFile), true) ?: [])
        : [];

    $errors   = [];
    $custom   = [];

    foreach ($uploaded as $key => $value) {
        if ($key === '_readme') {
            continue;
        }
        if (!array_key_exists($key, $defaults)) {
            $errors[] = "Unknown key: \"$key\" — only keys from the default template are allowed.";
            continue;
        }
        if (!is_string($value)) {
            $errors[] = "Key \"$key\" must have a string value.";
            continue;
        }
        if (trim($value) === '') {
            $errors[] = "Key \"$key\" has an empty value — provide a non-empty string.";
            continue;
        }
        // Only store keys that differ from the default
        if ($value !== $defaults[$key]) {
            $custom[$key] = $value;
        }
    }

    if (!empty($errors)) {
        $_SESSION['label_upload_errors']  = $errors;
        $_SESSION['label_upload_preview'] = json_encode($uploaded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        redirect('/admin/settings/labels');
    }

    setSetting('custom_labels', json_encode($custom));
    logAudit('labels.uploaded', null, null, 'custom_keys=' . count($custom));
    redirect('/admin/settings/labels?saved=1');
});

$router->post('/admin/settings/labels/reset', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/labels');
    }
    setSetting('custom_labels', '{}');
    logAudit('labels.reset', null, null, null);
    redirect('/admin/settings/labels?reset=1');
});

/* ==================================================================
 * ADMIN – Settings: Cron Jobs
 * ================================================================== */

$router->get('/admin/settings/cron-jobs', function () {
    Auth::requirePermission('automations.manage');
    render('admin/settings/cron-jobs');
});

/* ==================================================================
 * ADMIN – Settings: Automations
 * ================================================================== */

$router->get('/admin/settings/automations', function () {
    Auth::requirePermission('automations.manage');
    $db = Database::connect();
    $automations = $db->query('SELECT * FROM automations ORDER BY sort_order, id')->fetchAll();
    $refData     = loadAutomationRefData($db);
    render('admin/automations/index', array_merge(['automations' => $automations], $refData));
});

$router->post('/admin/settings/automations/reorder', function () {
    Auth::requirePermission('automations.manage');
    handleSortableReorder('automations');
});

$router->get('/admin/settings/automations/create', function () {
    Auth::requirePermission('automations.manage');
    $db = Database::connect();
    $refData = loadAutomationRefData($db);
    render('admin/automations/form', $refData);
});

$router->post('/admin/settings/automations/create', function () {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/automations/create');
    }

    $name         = trim($_POST['name'] ?? '');
    $triggerEvent = $_POST['trigger_event'] ?? '';
    $isEnabled    = !empty($_POST['is_enabled']) ? 1 : 0;
    $sortOrder    = (int) ($_POST['sort_order'] ?? 0);

    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Name is required.');
        redirect('/admin/settings/automations/create');
    }
    if (!in_array($triggerEvent, ['ticket_created', 'ticket_updated'], true)) {
        flashInput($_POST);
        flash('error', 'Invalid trigger event.');
        redirect('/admin/settings/automations/create');
    }

    $conditions = buildAutomationConditions($_POST);
    $actions    = buildAutomationActions($_POST);

    if (empty($actions)) {
        flashInput($_POST);
        flash('error', 'At least one action is required.');
        redirect('/admin/settings/automations/create');
    }

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO automations (name, trigger_event, conditions, actions, is_enabled, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$name, $triggerEvent, json_encode($conditions), json_encode($actions), $isEnabled, $sortOrder]);
    $newId = (int) $db->lastInsertId();
    logAudit(
        'automation.created',
        $newId,
        'automation',
        'name=' . $name . '; trigger=' . $triggerEvent . '; enabled=' . $isEnabled . '; actions=' . count($actions)
    );

    flash('success', 'Automation created.');
    redirect('/admin/settings/automations');
});

$router->get('/admin/settings/automations/{id}/edit', function (array $p) {
    Auth::requirePermission('automations.manage');
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM automations WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Automation not found.');
        redirect('/admin/settings/automations');
    }
    $editing['conditions'] = json_decode($editing['conditions'], true) ?: [];
    $editing['actions']    = json_decode($editing['actions'], true) ?: [];

    $refData = loadAutomationRefData($db);
    $refData['editing'] = $editing;
    render('admin/automations/form', $refData);
});

$router->post('/admin/settings/automations/{id}/edit', function (array $p) {
    Auth::requirePermission('automations.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect("/admin/settings/automations/{$id}/edit");
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT id FROM automations WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        flash('error', 'Automation not found.');
        redirect('/admin/settings/automations');
    }

    $name         = trim($_POST['name'] ?? '');
    $triggerEvent = $_POST['trigger_event'] ?? '';
    $isEnabled    = !empty($_POST['is_enabled']) ? 1 : 0;
    $sortOrder    = (int) ($_POST['sort_order'] ?? 0);

    if ($name === '') {
        flashInput($_POST);
        flash('error', 'Name is required.');
        redirect("/admin/settings/automations/{$id}/edit");
    }
    if (!in_array($triggerEvent, ['ticket_created', 'ticket_updated'], true)) {
        flashInput($_POST);
        flash('error', 'Invalid trigger event.');
        redirect("/admin/settings/automations/{$id}/edit");
    }

    $conditions = buildAutomationConditions($_POST);
    $actions    = buildAutomationActions($_POST);

    if (empty($actions)) {
        flashInput($_POST);
        flash('error', 'At least one action is required.');
        redirect("/admin/settings/automations/{$id}/edit");
    }

    $priorStmt = $db->prepare('SELECT name, trigger_event, is_enabled, sort_order FROM automations WHERE id = ?');
    $priorStmt->execute([$id]);
    $autoPrior = $priorStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

    $db->prepare(
        'UPDATE automations SET name = ?, trigger_event = ?, conditions = ?, actions = ?, is_enabled = ?, sort_order = ? WHERE id = ?'
    )->execute([$name, $triggerEvent, json_encode($conditions), json_encode($actions), $isEnabled, $sortOrder, $id]);

    logAuditChange(
        'automation.updated',
        $id,
        'automation',
        $autoPrior,
        ['name' => $name, 'trigger_event' => $triggerEvent, 'is_enabled' => $isEnabled, 'sort_order' => $sortOrder]
    );

    flash('success', 'Automation updated.');
    redirect('/admin/settings/automations');
});

$router->post('/admin/settings/automations/{id}/delete', function (array $p) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/automations');
    }
    $id  = (int) $p['id'];
    $db  = Database::connect();
    $nm  = $db->prepare('SELECT name FROM automations WHERE id = ?');
    $nm->execute([$id]);
    $aname = (string) ($nm->fetchColumn() ?: '');
    $db->prepare('DELETE FROM automations WHERE id = ?')->execute([$id]);
    logAudit('automation.deleted', $id, 'automation', 'name=' . $aname);
    flash('success', 'Automation deleted.');
    redirect('/admin/settings/automations');
});

$router->post('/admin/settings/automations/{id}/toggle', function (array $p) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/automations');
    }
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT is_enabled FROM automations WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Automation not found.');
        redirect('/admin/settings/automations');
    }
    $newState = $row['is_enabled'] ? 0 : 1;
    $db->prepare('UPDATE automations SET is_enabled = ? WHERE id = ?')->execute([$newState, (int) $p['id']]);
    logAudit(
        'automation.toggled',
        (int) $p['id'],
        'automation',
        'is_enabled=' . $newState
    );
    flash('success', $newState ? 'Automation enabled.' : 'Automation disabled.');
    redirect('/admin/settings/automations');
});

$router->post('/admin/settings/automations/{id}/run', function (array $p) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/automations');
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM automations WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $auto = $stmt->fetch();
    if (!$auto) {
        flash('error', 'Automation not found.');
        redirect('/admin/settings/automations');
    }

    $conditions = json_decode($auto['conditions'], true) ?: [];
    $actions    = json_decode($auto['actions'], true) ?: [];

    if (empty($actions)) {
        flash('error', 'Automation has no actions.');
        redirect('/admin/settings/automations');
    }

    // Fetch all non-closed tickets
    $tickets = $db->query("SELECT * FROM tickets WHERE status NOT IN ('closed','resolved') ORDER BY id")->fetchAll();

    $affected = 0;
    foreach ($tickets as $ticket) {
        if (!evalAutomationConditions($conditions, $ticket)) {
            continue;
        }

        // Apply each action — mirrors runAutomations() logic
        foreach ($actions as $act) {
            $action = $act['action'] ?? '';
            $val    = $act['value'] ?? '';

            switch ($action) {
                case 'set_group':
                    $groupId = $val === '' ? null : (int) $val;
                    $db->prepare('UPDATE tickets SET group_id = ? WHERE id = ?')->execute([$groupId, $ticket['id']]);
                    $groupName = 'None';
                    if ($groupId) {
                        $s = $db->prepare('SELECT name FROM groups WHERE id = ?');
                        $s->execute([$groupId]);
                        $groupName = $s->fetchColumn() ?: 'Unknown';
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticket['id'], 'automation', "Automation '{$auto['name']}' (manual run): Group set to {$groupName}"]);
                    break;

                case 'set_assigned_to':
                    $agentId = $val === '' ? null : (int) $val;
                    $db->prepare('UPDATE tickets SET assigned_to = ? WHERE id = ?')->execute([$agentId, $ticket['id']]);
                    $agentName = 'Unassigned';
                    if ($agentId) {
                        $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
                        $s->execute([$agentId]);
                        $agentName = $s->fetchColumn() ?: 'Unknown';
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticket['id'], 'automation', "Automation '{$auto['name']}' (manual run): Assigned to {$agentName}"]);
                    break;

                case 'set_priority':
                    $priorityId = $val === '' ? null : (int) $val;
                    $db->prepare('UPDATE tickets SET priority_id = ? WHERE id = ?')->execute([$priorityId, $ticket['id']]);
                    $priorityName = 'None';
                    if ($priorityId) {
                        $s = $db->prepare('SELECT name FROM ticket_priorities WHERE id = ?');
                        $s->execute([$priorityId]);
                        $priorityName = $s->fetchColumn() ?: 'Unknown';
                        Sla::onPriorityChanged($db, $ticket['id'], $priorityId, $ticket['type_id'] ? (int) $ticket['type_id'] : null);
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticket['id'], 'automation', "Automation '{$auto['name']}' (manual run): Priority set to {$priorityName}"]);
                    break;

                case 'set_status':
                    if (!in_array($val, ticketActiveStatusSlugs(), true)) {
                        break;
                    }
                    $oldStatus = $ticket['status'];
                    $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$val, $ticket['id']]);
                    $pausingStatuses = ticketSlaPausingSlugs();
                    if (in_array($val, $pausingStatuses, true)) {
                        Sla::pause($db, $ticket['id']);
                    } elseif (in_array($oldStatus, $pausingStatuses, true)) {
                        Sla::resume($db, $ticket['id']);
                    }
                    $db->prepare(
                        'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                    )->execute([$ticket['id'], 'automation', "Automation '{$auto['name']}' (manual run): Status set to {$val}"]);
                    break;

                case 'add_tag':
                    $tagName = trim(strtolower(preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $val)));
                    if ($tagName === '') {
                        break;
                    }
                    $findTag = $db->prepare('SELECT id FROM ticket_tags WHERE name = ?');
                    $findTag->execute([$tagName]);
                    $tagId = $findTag->fetchColumn();
                    if (!$tagId) {
                        $db->prepare('INSERT INTO ticket_tags (name) VALUES (?)')->execute([$tagName]);
                        $tagId = (int) $db->lastInsertId();
                    }
                    $exists = $db->prepare('SELECT 1 FROM ticket_tag_map WHERE ticket_id = ? AND tag_id = ?');
                    $exists->execute([$ticket['id'], $tagId]);
                    if (!$exists->fetchColumn()) {
                        $db->prepare('INSERT INTO ticket_tag_map (ticket_id, tag_id) VALUES (?, ?)')->execute([$ticket['id'], $tagId]);
                        $db->prepare(
                            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                        )->execute([$ticket['id'], 'automation', "Automation '{$auto['name']}' (manual run): Tag #{$tagName} added"]);
                    }
                    break;

                case 'add_cc':
                    $ccUserId = (int) $val;
                    if ($ccUserId <= 0) {
                        break;
                    }
                    $exists = $db->prepare('SELECT 1 FROM ticket_cc WHERE ticket_id = ? AND user_id = ?');
                    $exists->execute([$ticket['id'], $ccUserId]);
                    if (!$exists->fetchColumn()) {
                        $db->prepare(
                            'INSERT INTO ticket_cc (ticket_id, user_id, added_by) VALUES (?, ?, ?)'
                        )->execute([$ticket['id'], $ccUserId, $ccUserId]);
                        $s = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
                        $s->execute([$ccUserId]);
                        $ccName = $s->fetchColumn() ?: 'Unknown';
                        $db->prepare(
                            'INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, NULL, ?, ?, 1)'
                        )->execute([$ticket['id'], 'automation', "Automation '{$auto['name']}' (manual run): CC'd {$ccName}"]);
                    }
                    break;
            }
        }
        $affected++;
    }

    logAudit(
        'automation.run_now',
        (int) $auto['id'],
        'automation',
        'name=' . $auto['name'] . '; tickets_affected=' . $affected
    );

    flash('success', "Automation '{$auto['name']}' ran on {$affected} ticket" . ($affected !== 1 ? 's' : '') . '.');
    redirect('/admin/settings/automations');
});

/**
 * Load reference data for automation forms.
 */
function loadAutomationRefData(PDO $db): array
{
    return [
        'types'      => $db->query('SELECT id, name FROM ticket_types ORDER BY sort_order, name')->fetchAll(),
        'priorities' => $db->query('SELECT id, name FROM ticket_priorities ORDER BY sort_order')->fetchAll(),
        'locations'  => $db->query('SELECT id, name FROM locations ORDER BY name')->fetchAll(),
        'groups'     => $db->query('SELECT id, name FROM groups ORDER BY name')->fetchAll(),
        'agents'     => $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name")->fetchAll(),
        'allUsers'   => $db->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name")->fetchAll(),
    ];
}

/**
 * Build conditions structure from POST data (v2 group format).
 * Accepts a JSON blob submitted by the group-based condition builder.
 */
function buildAutomationConditions(array $post): array
{
    $raw = json_decode($post['conditions_json'] ?? '{}', true);
    if (!is_array($raw) || empty($raw['groups'])) {
        return ['v' => 2, 'groups' => []];
    }

    $validFields    = ['type_id', 'priority_id', 'status', 'location_id', 'group_id', 'assigned_to'];
    $validOperators = ['equals', 'not_equals', 'is_empty', 'is_not_empty'];

    $groups = [];
    foreach ($raw['groups'] as $rg) {
        $gMatch = ($rg['match'] ?? 'all') === 'any' ? 'any' : 'all';
        $conds  = [];
        foreach ($rg['conditions'] ?? [] as $c) {
            $f = $c['field']    ?? '';
            $o = $c['operator'] ?? '';
            $v = $c['value']    ?? '';
            if (in_array($f, $validFields, true) && in_array($o, $validOperators, true)) {
                $conds[] = ['field' => $f, 'operator' => $o, 'value' => $v];
            }
        }
        if (!empty($conds)) {
            $groups[] = ['match' => $gMatch, 'conditions' => $conds];
        }
    }

    return ['v' => 2, 'groups' => $groups];
}

/**
 * Build actions array from POST data.
 */
function buildAutomationActions(array $post): array
{
    $actions     = [];
    $actionTypes = $post['act_type'] ?? [];
    $actionVals  = $post['act_value'] ?? [];

    $validActions = ['set_group', 'set_assigned_to', 'set_priority', 'set_status', 'add_tag', 'add_cc'];

    for ($i = 0, $n = count($actionTypes); $i < $n; $i++) {
        $a = $actionTypes[$i] ?? '';
        $v = $actionVals[$i] ?? '';
        if (in_array($a, $validActions, true) && $v !== '') {
            $actions[] = ['action' => $a, 'value' => $v];
        }
    }
    return $actions;
}

/* ==================================================================
 * ADMIN – Settings: Escalation Rules
 * ================================================================== */

$router->get('/admin/settings/escalations', function () {
    Auth::requirePermission('automations.manage');
    $db = Database::connect();
    $rules = $db->query('SELECT * FROM escalation_rules ORDER BY sort_order, id')->fetchAll();
    render('admin/settings/escalations/index', ['rules' => $rules]);
});

$router->post('/admin/settings/escalations/reorder', function () {
    Auth::requirePermission('automations.manage');
    handleSortableReorder('escalation_rules');
});

$router->get('/admin/settings/escalations/create', function () {
    Auth::requirePermission('automations.manage');
    $db      = Database::connect();
    $refData = loadEscalationRefData($db);
    render('admin/settings/escalations/form', $refData);
});

$router->post('/admin/settings/escalations/create', function () {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', 'Rule name is required.');
        redirect('/admin/settings/escalations/create');
    }

    $conditions    = buildEscalationConditions($_POST);
    $actions       = buildEscalationActions($_POST);
    $cooldown      = max(0, (int) ($_POST['cooldown_hours'] ?? 0));
    $isEnabled     = isset($_POST['is_enabled']) ? 1 : 0;
    $sortOrder     = max(0, (int) ($_POST['sort_order'] ?? 0));

    if (empty($actions)) {
        flash('error', 'At least one action is required.');
        redirect('/admin/settings/escalations/create');
    }

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO escalation_rules (name, conditions, actions, cooldown_hours, is_enabled, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$name, json_encode($conditions), json_encode($actions), $cooldown, $isEnabled, $sortOrder]);
    $newId = (int) $db->lastInsertId();
    logAudit(
        'escalation_rule.created',
        $newId,
        'escalation_rule',
        'name=' . $name . '; enabled=' . $isEnabled . '; cooldown_hours=' . $cooldown . '; actions=' . count($actions)
    );

    flash('success', 'Escalation rule created.');
    redirect('/admin/settings/escalations');
});

$router->get('/admin/settings/escalations/{id}/edit', function (array $p) {
    Auth::requirePermission('automations.manage');
    $db = Database::connect();
    $stmt = $db->prepare('SELECT * FROM escalation_rules WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $rule = $stmt->fetch();
    if (!$rule) {
        flash('error', 'Rule not found.');
        redirect('/admin/settings/escalations');
    }
    $refData            = loadEscalationRefData($db);
    $refData['editing'] = $rule;
    $refData['editing']['conditions_decoded'] = json_decode($rule['conditions'], true) ?: [];
    $refData['editing']['actions_decoded']    = json_decode($rule['actions'],    true) ?: [];
    render('admin/settings/escalations/form', $refData);
});

$router->post('/admin/settings/escalations/{id}/edit', function (array $p) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }

    $db = Database::connect();
    $stmt = $db->prepare('SELECT id FROM escalation_rules WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    if (!$stmt->fetch()) {
        flash('error', 'Rule not found.');
        redirect('/admin/settings/escalations');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', 'Rule name is required.');
        redirect('/admin/settings/escalations/' . (int) $p['id'] . '/edit');
    }

    $conditions = buildEscalationConditions($_POST);
    $actions    = buildEscalationActions($_POST);
    $cooldown   = max(0, (int) ($_POST['cooldown_hours'] ?? 0));
    $isEnabled  = isset($_POST['is_enabled']) ? 1 : 0;
    $sortOrder  = max(0, (int) ($_POST['sort_order'] ?? 0));

    if (empty($actions)) {
        flash('error', 'At least one action is required.');
        redirect('/admin/settings/escalations/' . (int) $p['id'] . '/edit');
    }

    $priorRule = $db->prepare('SELECT name, is_enabled, cooldown_hours, sort_order FROM escalation_rules WHERE id = ?');
    $priorRule->execute([(int) $p['id']]);
    $rulePrior = $priorRule->fetch(\PDO::FETCH_ASSOC) ?: [];

    $db->prepare(
        'UPDATE escalation_rules SET name = ?, conditions = ?, actions = ?, cooldown_hours = ?, is_enabled = ?, sort_order = ? WHERE id = ?'
    )->execute([$name, json_encode($conditions), json_encode($actions), $cooldown, $isEnabled, $sortOrder, (int) $p['id']]);

    logAuditChange(
        'escalation_rule.updated',
        (int) $p['id'],
        'escalation_rule',
        $rulePrior,
        ['name' => $name, 'is_enabled' => $isEnabled, 'cooldown_hours' => $cooldown, 'sort_order' => $sortOrder]
    );

    flash('success', 'Escalation rule updated.');
    redirect('/admin/settings/escalations');
});

$router->post('/admin/settings/escalations/{id}/toggle', function (array $p) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }
    $db = Database::connect();
    $db->prepare('UPDATE escalation_rules SET is_enabled = NOT is_enabled WHERE id = ?')
       ->execute([(int) $p['id']]);
    $stateStmt = $db->prepare('SELECT name, is_enabled FROM escalation_rules WHERE id = ?');
    $stateStmt->execute([(int) $p['id']]);
    $rstate = $stateStmt->fetch(\PDO::FETCH_ASSOC) ?: ['name' => '', 'is_enabled' => null];
    logAudit(
        'escalation_rule.toggled',
        (int) $p['id'],
        'escalation_rule',
        'name=' . $rstate['name'] . '; is_enabled=' . $rstate['is_enabled']
    );
    redirect('/admin/settings/escalations');
});

$router->post('/admin/settings/escalations/{id}/delete', function (array $p) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }
    $id   = (int) $p['id'];
    $db   = Database::connect();
    $nm   = $db->prepare('SELECT name FROM escalation_rules WHERE id = ?');
    $nm->execute([$id]);
    $rname = (string) ($nm->fetchColumn() ?: '');
    $db->prepare('DELETE FROM escalation_rules WHERE id = ?')->execute([$id]);
    logAudit('escalation_rule.deleted', $id, 'escalation_rule', 'name=' . $rname);
    flash('success', 'Escalation rule deleted.');
    redirect('/admin/settings/escalations');
});

$router->post('/admin/settings/escalations/run-now', function () {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalations');
    }
    $script = ROOT_DIR . '/scripts/process-escalations.php';
    $cmd    = escapeshellarg(phpBinary()) . ' ' . escapeshellarg($script) . ' 2>&1';
    $outputLines = [];
    $returnCode  = 0;
    exec($cmd, $outputLines, $returnCode);
    $_SESSION['_escalation_run'] = [
        'lines' => $outputLines,
        'code'  => $returnCode,
        'time'  => date('Y-m-d H:i:s'),
    ];
    logAudit('escalation.run_now', null, null, 'exit_code=' . $returnCode);
    redirect('/admin/settings/escalations');
});

/* ── Escalation helper functions ─────────────────────────────────── */

function loadEscalationRefData(PDO $db): array
{
    return [
        'priorities' => $db->query('SELECT id, name FROM ticket_priorities ORDER BY sort_order')->fetchAll(),
        'groups'     => $db->query('SELECT id, name FROM groups ORDER BY name')->fetchAll(),
        'agents'     => $db->query("SELECT id, first_name, last_name FROM users WHERE " . staffRoleSqlIn('role') . " ORDER BY first_name")->fetchAll(),
        'allUsers'   => $db->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name")->fetchAll(),
    ];
}

function buildEscalationConditions(array $post): array
{
    $conditions = [];
    $fields     = $post['cond_field']    ?? [];
    $operators  = $post['cond_operator'] ?? [];
    $values     = $post['cond_value']    ?? [];

    $validFields = ['sla_state', 'hours_open', 'hours_since_update', 'hours_in_status',
                    'is_assigned', 'priority_id', 'status', 'group_id'];
    $noValueOps  = ['is_empty', 'is_not_empty'];

    for ($i = 0, $n = count($fields); $i < $n; $i++) {
        $f = $fields[$i]    ?? '';
        $o = $operators[$i] ?? 'equals';
        $v = $values[$i]    ?? '';
        if (!in_array($f, $validFields, true)) continue;
        if (!in_array($o, $noValueOps, true) && $v === '') continue;
        $conditions[] = ['field' => $f, 'operator' => $o, 'value' => $v];
    }
    return $conditions;
}

function buildEscalationActions(array $post): array
{
    $actions     = [];
    $actionTypes = $post['act_type']  ?? [];
    $actionVals  = $post['act_value'] ?? [];

    $validActions    = ['set_priority', 'set_assigned_to', 'set_group', 'set_status',
                        'notify_user', 'notify_assigned_agent', 'notify_ticket_creator', 'add_internal_note'];
    $noValueRequired = ['notify_assigned_agent', 'notify_ticket_creator'];

    for ($i = 0, $n = count($actionTypes); $i < $n; $i++) {
        $a = $actionTypes[$i] ?? '';
        $v = $actionVals[$i]  ?? '';
        if (!in_array($a, $validActions, true)) continue;
        if (!in_array($a, $noValueRequired, true) && $v === '') continue;
        $actions[] = ['action' => $a, 'value' => $v];
    }
    return $actions;
}

/* ==================================================================
 * ADMIN – Settings: Danger Zone
 * ================================================================== */
$router->get('/admin/settings/danger-zone', function () {
    Auth::requireAdmin();
    $db = Database::connect();
    $ticketCount = (int) $db->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
    render('admin/settings/danger-zone', ['ticketCount' => $ticketCount]);
});

$router->post('/admin/settings/danger-zone/reset', function () {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/danger-zone');
    }

    $db = Database::connect();

    // Discover every table in the current database and truncate them all.
    // Hardcoding the list drifts every time a migration adds a table — the
    // 12-table gap that built up between mig 005 and mig 027 left orphaned
    // api_tokens, agent_skills, ai_classifications, etc. surviving the reset.
    // schema_migrations is preserved so we don't re-run migrations on the
    // empty schema.
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $tables = array_filter($tables, static fn($t) => $t !== 'schema_migrations');

    // Log this BEFORE we wipe the audit_log table — the row will get truncated
    // along with everything else, but a deliberate write here at least gives
    // the running request a record in memory before the destructive call.
    logAudit('system.factory_reset', null, null, 'tables_truncated=' . count($tables));

    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE `{$table}`");
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Destroy the current session so the admin is logged out
    session_unset();
    session_destroy();

    // Start a fresh session and flag that setup is needed
    session_start();
    $_SESSION['setup_allowed'] = true;

    redirect('/setup');
});

/* ==================================================================
 * Reports & Analytics
 * ================================================================== */

/** Helper: format minutes into human-readable string */
function formatMinutes(float $minutes): string
{
    if ($minutes < 1) return '< 1m';
    if ($minutes < 60) return round($minutes) . 'm';
    if ($minutes < 1440) return round($minutes / 60, 1) . 'h';
    return round($minutes / 1440, 1) . 'd';
}

/** Helper: parse date-range query params with defaults */
function reportDateRange(): array
{
    $to   = (!empty($_GET['to']) && strtotime($_GET['to'])) ? $_GET['to'] : date('Y-m-d');
    $from = (!empty($_GET['from']) && strtotime($_GET['from'])) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days', strtotime($to)));
    return [$from, $to];
}

/* ── Reports Overview ─────────────────────────────────────────────── */

$router->get('/admin/reports', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $closedIn    = ticketStatusSqlIn(ticketClosedBucketSlugs(), 'status');
    $notClosedIn = ticketStatusSqlIn(ticketClosedBucketSlugs(), 'status', true);

    $stmt = $db->prepare('SELECT COUNT(*) FROM tickets WHERE created_at BETWEEN ? AND ?');
    $stmt->execute([$from, $toEnd]);
    $ticketsCreated = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE $closedIn AND created_at BETWEEN ? AND ?");
    $stmt->execute([$from, $toEnd]);
    $ticketsResolved = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE $notClosedIn");
    $stmt->execute();
    $unresolvedCount = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_responded_at)) FROM tickets WHERE first_responded_at IS NOT NULL AND created_at BETWEEN ? AND ?');
    $stmt->execute([$from, $toEnd]);
    $avgFirstResponse = formatMinutes((float) $stmt->fetchColumn());

    // Avg resolution: time from creation to the status_changed → resolved timeline entry
    $stmt = $db->prepare(
        "SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at))
         FROM tickets t
         JOIN ticket_timeline tl ON tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
         WHERE t.created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $avgResolution = formatMinutes((float) $stmt->fetchColumn());

    // SLA compliance
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM tickets WHERE first_response_due_at IS NOT NULL AND created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $slaTotal = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM tickets WHERE sla_state = 'breached' AND created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $slaBreached = (int) $stmt->fetchColumn();
    $slaCompliance = $slaTotal > 0 ? round(($slaTotal - $slaBreached) / $slaTotal * 100) : 100;

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND $notClosedIn");
    $stmt->execute();
    $unassignedCount = (int) $stmt->fetchColumn();

    render('admin/reports/index', compact(
        'from', 'to', 'ticketsCreated', 'ticketsResolved', 'unresolvedCount',
        'avgFirstResponse', 'avgResolution', 'slaCompliance', 'slaBreached', 'unassignedCount'
    ));
});

/* ── Agent Performance ────────────────────────────────────────────── */

$router->get('/admin/reports/agent-performance', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $closedInT    = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status');
    $notClosedInT = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status', true);

    $stmt = $db->prepare(
        "SELECT
            u.id AS agent_id,
            CONCAT(u.first_name, ' ', u.last_name) AS agent_name,
            COUNT(t.id) AS assigned,
            SUM(CASE WHEN $closedInT THEN 1 ELSE 0 END) AS resolved,
            SUM(CASE WHEN $notClosedInT THEN 1 ELSE 0 END) AS open_count,
            AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END) AS avg_first_response_min,
            AVG(
                CASE WHEN $closedInT THEN
                    (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                     FROM ticket_timeline tl
                     WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                     ORDER BY tl.created_at DESC LIMIT 1)
                END
            ) AS avg_resolution_min,
            SUM(CASE WHEN t.first_response_due_at IS NOT NULL THEN 1 ELSE 0 END) AS sla_total,
            SUM(CASE WHEN t.sla_state = 'breached' THEN 1 ELSE 0 END) AS sla_breached
         FROM users u
         LEFT JOIN tickets t ON t.assigned_to = u.id AND t.created_at BETWEEN ? AND ?
         WHERE " . staffRoleSqlIn('u.role') . "
         GROUP BY u.id, u.first_name, u.last_name
         ORDER BY resolved DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $agents = $stmt->fetchAll();

    foreach ($agents as &$a) {
        $a['avg_first_response'] = $a['avg_first_response_min'] !== null ? formatMinutes((float) $a['avg_first_response_min']) : '—';
        $a['avg_resolution'] = $a['avg_resolution_min'] !== null ? formatMinutes((float) $a['avg_resolution_min']) : '—';
        $a['sla_compliance'] = $a['sla_total'] > 0
            ? round(($a['sla_total'] - $a['sla_breached']) / $a['sla_total'] * 100)
            : 100;
    }
    unset($a);

    render('admin/reports/agent-performance', compact('from', 'to', 'agents'));
});

/* ── Response Times ───────────────────────────────────────────────── */

$router->get('/admin/reports/response-times', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Overall averages
    $stmt = $db->prepare(
        'SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, first_responded_at)) FROM tickets WHERE first_responded_at IS NOT NULL AND created_at BETWEEN ? AND ?'
    );
    $stmt->execute([$from, $toEnd]);
    $overallFirstResponse = formatMinutes((float) $stmt->fetchColumn());

    $stmt = $db->prepare(
        "SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at))
         FROM tickets t
         JOIN ticket_timeline tl ON tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
         WHERE t.created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $overallResolution = formatMinutes((float) $stmt->fetchColumn());

    $stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE first_responded_at IS NOT NULL AND created_at BETWEEN ? AND ?");
    $stmt->execute([$from, $toEnd]);
    $ticketsMeasured = (int) $stmt->fetchColumn();

    // By priority
    $stmt = $db->prepare(
        "SELECT
            tp.name AS priority_name, tp.color AS priority_color,
            COUNT(t.id) AS ticket_count,
            AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END) AS avg_fr_min,
            AVG(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at DESC LIMIT 1)
            ) AS avg_res_min,
            MIN(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at ASC LIMIT 1)
            ) AS fastest_min,
            MAX(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at DESC LIMIT 1)
            ) AS slowest_min
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY tp.id, tp.name, tp.color, tp.sort_order
         ORDER BY tp.sort_order"
    );
    $stmt->execute([$from, $toEnd]);
    $byPriority = $stmt->fetchAll();

    foreach ($byPriority as &$row) {
        $row['avg_first_response'] = $row['avg_fr_min'] !== null ? formatMinutes((float) $row['avg_fr_min']) : '—';
        $row['avg_resolution'] = $row['avg_res_min'] !== null ? formatMinutes((float) $row['avg_res_min']) : '—';
        $row['fastest'] = $row['fastest_min'] !== null ? formatMinutes((float) $row['fastest_min']) : '—';
        $row['slowest'] = $row['slowest_min'] !== null ? formatMinutes((float) $row['slowest_min']) : '—';
    }
    unset($row);

    // Weekly trend
    $stmt = $db->prepare(
        "SELECT
            DATE_FORMAT(t.created_at, '%Y-%u') AS week_key,
            DATE_FORMAT(MIN(t.created_at), '%b %e') AS week_label,
            ROUND(AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END) / 60, 1) AS avg_response_hrs,
            ROUND(AVG(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at DESC LIMIT 1)
            ) / 60, 1) AS avg_resolution_hrs
         FROM tickets t
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY week_key
         ORDER BY week_key"
    );
    $stmt->execute([$from, $toEnd]);
    $weeklyTrend = $stmt->fetchAll();

    render('admin/reports/response-times', compact(
        'from', 'to', 'overallFirstResponse', 'overallResolution', 'ticketsMeasured',
        'byPriority', 'weeklyTrend'
    ));
});

/* ── SLA Compliance ───────────────────────────────────────────────── */

$router->get('/admin/reports/sla', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Overall compliance
    $stmt = $db->prepare(
        "SELECT
            SUM(CASE WHEN first_responded_at IS NOT NULL AND first_response_due_at IS NOT NULL AND first_responded_at <= first_response_due_at THEN 1 ELSE 0 END) AS response_met,
            SUM(CASE WHEN first_responded_at IS NOT NULL AND first_response_due_at IS NOT NULL AND first_responded_at > first_response_due_at THEN 1 ELSE 0 END) AS response_breached,
            SUM(CASE WHEN first_response_due_at IS NOT NULL AND (first_responded_at IS NULL OR first_responded_at <= first_response_due_at) AND sla_state != 'breached' THEN 1 ELSE 0 END) AS resolution_met_approx,
            SUM(CASE WHEN sla_state = 'breached' THEN 1 ELSE 0 END) AS sla_breached_count,
            COUNT(CASE WHEN first_response_due_at IS NOT NULL THEN 1 END) AS sla_total
         FROM tickets
         WHERE created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $overall = $stmt->fetch();

    $responseMet     = (int) ($overall['response_met'] ?? 0);
    $responseBreached = (int) ($overall['response_breached'] ?? 0);
    $totalBreached    = (int) ($overall['sla_breached_count'] ?? 0);
    $slaTotal         = (int) ($overall['sla_total'] ?? 0);
    $totalMet         = max(0, $slaTotal - $totalBreached);

    $firstResponseCompliance = ($responseMet + $responseBreached) > 0
        ? round($responseMet / ($responseMet + $responseBreached) * 100) : 100;

    $resolutionCompliance = $slaTotal > 0
        ? round($totalMet / $slaTotal * 100) : 100;

    $overallCompliance = $slaTotal > 0
        ? round($totalMet / $slaTotal * 100) : 100;

    // By priority
    $stmt = $db->prepare(
        "SELECT
            tp.name AS priority_name, tp.color AS priority_color,
            COUNT(t.id) AS total,
            SUM(CASE WHEN t.first_responded_at IS NOT NULL AND t.first_response_due_at IS NOT NULL AND t.first_responded_at <= t.first_response_due_at THEN 1 ELSE 0 END) AS response_met,
            SUM(CASE WHEN t.first_responded_at IS NOT NULL AND t.first_response_due_at IS NOT NULL AND t.first_responded_at > t.first_response_due_at THEN 1 ELSE 0 END) AS response_breached,
            SUM(CASE WHEN t.sla_state != 'breached' AND t.first_response_due_at IS NOT NULL THEN 1 ELSE 0 END) AS resolution_met,
            SUM(CASE WHEN t.sla_state = 'breached' THEN 1 ELSE 0 END) AS resolution_breached
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         WHERE t.first_response_due_at IS NOT NULL AND t.created_at BETWEEN ? AND ?
         GROUP BY tp.id, tp.name, tp.color, tp.sort_order
         ORDER BY tp.sort_order"
    );
    $stmt->execute([$from, $toEnd]);
    $byPriority = $stmt->fetchAll();

    foreach ($byPriority as &$row) {
        $row['compliance'] = $row['total'] > 0
            ? round(($row['total'] - $row['resolution_breached']) / $row['total'] * 100) : 100;
    }
    unset($row);

    // Breached tickets
    $stmt = $db->prepare(
        "SELECT t.id, t.subject, t.created_at, t.sla_state,
                t.first_responded_at, t.first_response_due_at,
                tp.name AS priority_name, tp.color AS priority_color,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN users a ON t.assigned_to = a.id
         WHERE t.sla_state = 'breached' AND t.created_at BETWEEN ? AND ?
         ORDER BY t.created_at DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $breachedTickets = $stmt->fetchAll();

    foreach ($breachedTickets as &$bt) {
        $bt['response_breached'] = ($bt['first_responded_at'] && $bt['first_response_due_at'] && $bt['first_responded_at'] > $bt['first_response_due_at']);
        $bt['resolution_breached'] = ($bt['sla_state'] === 'breached');
    }
    unset($bt);

    render('admin/reports/sla', compact(
        'from', 'to', 'overallCompliance', 'firstResponseCompliance', 'resolutionCompliance',
        'totalBreached', 'totalMet', 'byPriority', 'breachedTickets'
    ));
});

/* ── Unresolved Tickets ───────────────────────────────────────────── */

$router->get('/admin/reports/unresolved', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();

    // ── Inputs (drilldown filters + pagination) ────────────────────
    $allowedPerPage = [10, 25, 50, 100];
    $perPage = (isset($_GET['per_page']) && in_array((int) $_GET['per_page'], $allowedPerPage, true))
        ? (int) $_GET['per_page'] : 25;
    $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
    if (in_array($statusFilter, ticketClosedBucketSlugs(), true)) $statusFilter = '';
    $ageFilter = (isset($_GET['age']) && $_GET['age'] !== '' && in_array((int) $_GET['age'], [0, 1, 2, 3, 4], true))
        ? (int) $_GET['age'] : null;
    $isAjax = !empty($_GET['ajax']);

    // ── Filter WHERE for the table query ───────────────────────────
    $where  = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status', true);
    $params = [];
    if ($statusFilter !== '') {
        $where .= " AND t.status = ?";
        $params[] = $statusFilter;
    }
    if ($ageFilter !== null) {
        $bounds = [
            0 => [0, 24],     // < 1 day
            1 => [24, 72],    // 1–3 days
            2 => [72, 168],   // 3–7 days
            3 => [168, 336],  // 7–14 days
            4 => [336, null], // > 14 days
        ];
        [$minH, $maxH] = $bounds[$ageFilter];
        $where .= " AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) >= ?";
        $params[] = $minH;
        if ($maxH !== null) {
            $where .= " AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) < ?";
            $params[] = $maxH;
        }
    }

    // ── Paginated, filtered ticket page ────────────────────────────
    $countStmt = $db->prepare("SELECT COUNT(*) FROM tickets t WHERE $where");
    $countStmt->execute($params);
    $totalFiltered = (int) $countStmt->fetchColumn();
    $totalPages    = max(1, (int) ceil($totalFiltered / $perPage));
    $page          = max(1, min($totalPages, (int) ($_GET['page'] ?? 1)));
    $offset        = ($page - 1) * $perPage;

    $stmt = $db->prepare(
        "SELECT t.*, tp.name AS priority_name, tp.color AS priority_color,
                CONCAT(a.first_name, ' ', a.last_name) AS agent_name
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         LEFT JOIN users a ON t.assigned_to = a.id
         WHERE $where
         ORDER BY t.created_at ASC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    $now = new DateTime();
    foreach ($tickets as &$t) {
        $created = new DateTime($t['created_at']);
        $diffMin = ($now->getTimestamp() - $created->getTimestamp()) / 60;
        $t['age_display'] = formatMinutes($diffMin);
    }
    unset($t);

    // ── AJAX: emit just the partial and exit ───────────────────────
    if ($isAjax) {
        require ROOT_DIR . '/templates/pages/admin/reports/_unresolved-table.php';
        exit;
    }

    // ── Aggregate stats for summary cards + drilldown tiles ────────
    // (UNFILTERED — these are the navigation source.)
    $agg = $db->query(
        "SELECT
            COUNT(*) AS total_unresolved,
            SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) AS unassigned,
            SUM(CASE WHEN sla_state = 'breached' THEN 1 ELSE 0 END) AS breached,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW())) AS avg_age_min,
            SUM(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN 1 ELSE 0 END) AS age_0,
            SUM(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 24  AND TIMESTAMPDIFF(HOUR, created_at, NOW()) < 72  THEN 1 ELSE 0 END) AS age_1,
            SUM(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 72  AND TIMESTAMPDIFF(HOUR, created_at, NOW()) < 168 THEN 1 ELSE 0 END) AS age_2,
            SUM(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 168 AND TIMESTAMPDIFF(HOUR, created_at, NOW()) < 336 THEN 1 ELSE 0 END) AS age_3,
            SUM(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 336 THEN 1 ELSE 0 END) AS age_4
         FROM tickets
         WHERE " . ticketStatusSqlIn(ticketClosedBucketSlugs(), 'status', true)
    )->fetch();

    $totalUnresolved = (int) $agg['total_unresolved'];
    $unassigned      = (int) $agg['unassigned'];
    $breachedCount   = (int) $agg['breached'];
    $avgAge          = $totalUnresolved > 0 ? formatMinutes((float) $agg['avg_age_min']) : '—';
    $agingBuckets    = [
        (int) $agg['age_0'], (int) $agg['age_1'], (int) $agg['age_2'],
        (int) $agg['age_3'], (int) $agg['age_4'],
    ];

    $byStatus = $db->query(
        "SELECT status, COUNT(*) AS count
         FROM tickets
         WHERE " . ticketStatusSqlIn(ticketClosedBucketSlugs(), 'status', true) . "
         GROUP BY status
         ORDER BY count DESC"
    )->fetchAll();

    render('admin/reports/unresolved', compact(
        'tickets', 'totalUnresolved', 'unassigned', 'breachedCount', 'avgAge',
        'agingBuckets', 'byStatus', 'page', 'perPage', 'totalPages',
        'totalFiltered', 'statusFilter', 'ageFilter'
    ));
});

/* ── Ticket Volume ────────────────────────────────────────────────── */

$router->get('/admin/reports/ticket-volume', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Daily volume
    $stmt = $db->prepare(
        "SELECT DATE(created_at) AS date_label, COUNT(*) AS count
         FROM tickets
         WHERE created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY DATE(created_at)"
    );
    $stmt->execute([$from, $toEnd]);
    $dailyVolume = $stmt->fetchAll();

    // Format date labels
    foreach ($dailyVolume as &$row) {
        $row['date_label'] = date('M j', strtotime($row['date_label']));
    }
    unset($row);

    // By priority
    $stmt = $db->prepare(
        "SELECT tp.name, tp.color, COUNT(t.id) AS count
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY tp.id, tp.name, tp.color, tp.sort_order
         ORDER BY tp.sort_order"
    );
    $stmt->execute([$from, $toEnd]);
    $byPriority = $stmt->fetchAll();

    // By type
    $stmt = $db->prepare(
        "SELECT COALESCE(tt.name, 'Untyped') AS name, COUNT(t.id) AS count
         FROM tickets t
         LEFT JOIN ticket_types tt ON t.type_id = tt.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY tt.id, tt.name
         ORDER BY count DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $byType = $stmt->fetchAll();

    // By location
    $stmt = $db->prepare(
        "SELECT COALESCE(l.name, 'No Location') AS name, COUNT(t.id) AS count
         FROM tickets t
         LEFT JOIN locations l ON t.location_id = l.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY l.id, l.name
         ORDER BY count DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $byLocation = $stmt->fetchAll();

    render('admin/reports/ticket-volume', compact(
        'from', 'to', 'dailyVolume', 'byPriority', 'byType', 'byLocation'
    ));
});

/* ── Ticket Lifecycle ─────────────────────────────────────────────── */

$router->get('/admin/reports/lifecycle', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Get status transitions from timeline
    $stmt = $db->prepare(
        "SELECT tl.ticket_id, tl.details, tl.created_at
         FROM ticket_timeline tl
         JOIN tickets t ON t.id = tl.ticket_id
         WHERE tl.action = 'status_changed' AND t.created_at BETWEEN ? AND ?
         ORDER BY tl.ticket_id, tl.created_at"
    );
    $stmt->execute([$from, $toEnd]);
    $changes = $stmt->fetchAll();

    // Also get ticket creation times
    $stmt = $db->prepare(
        "SELECT id, created_at, status FROM tickets WHERE created_at BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $toEnd]);
    $ticketRows = $stmt->fetchAll();

    $ticketCreated = [];
    foreach ($ticketRows as $tr) {
        $ticketCreated[$tr['id']] = $tr['created_at'];
    }

    // Build status duration map per ticket
    $statusTimes = []; // status => [minutes, ...]
    $transitionCounts = []; // "from→to" => [count, total_minutes]

    // Group changes by ticket
    $byTicket = [];
    foreach ($changes as $c) {
        $byTicket[$c['ticket_id']][] = $c;
    }

    foreach ($byTicket as $ticketId => $ticketChanges) {
        // Start from 'open' at created_at
        $prevStatus = 'open';
        $prevTime = $ticketCreated[$ticketId] ?? null;
        if (!$prevTime) continue;

        foreach ($ticketChanges as $change) {
            // Parse "Status → New Status" from details
            if (preg_match('/^(.+?)\s*→\s*(.+)$/', $change['details'] ?? '', $m)) {
                $toStatus = strtolower(trim($m[2]));
                $toStatus = str_replace(' ', '_', $toStatus);

                $minutes = (strtotime($change['created_at']) - strtotime($prevTime)) / 60;
                if ($minutes > 0) {
                    $statusTimes[$prevStatus][] = $minutes;

                    $key = $prevStatus . '→' . $toStatus;
                    if (!isset($transitionCounts[$key])) {
                        $transitionCounts[$key] = ['count' => 0, 'total_min' => 0];
                    }
                    $transitionCounts[$key]['count']++;
                    $transitionCounts[$key]['total_min'] += $minutes;
                }

                $prevStatus = $toStatus;
                $prevTime = $change['created_at'];
            }
        }
    }

    // Build statusDurations array — iterate every configured status in display order.
    $statusOrder = array_map(static fn(array $s) => $s['slug'], ticketStatuses());
    $statusDurations = [];
    foreach ($statusOrder as $status) {
        $times = $statusTimes[$status] ?? [];
        $avg = count($times) > 0 ? array_sum($times) / count($times) : 0;
        $statusDurations[] = [
            'status' => $status,
            'avg_duration' => count($times) > 0 ? formatMinutes($avg) : '—',
            'avg_hours' => round($avg / 60, 1),
            'transitions' => count($times),
        ];
    }

    // Build transitions array
    $transitions = [];
    arsort($transitionCounts);
    foreach ($transitionCounts as $key => $data) {
        [$fromS, $toS] = explode('→', $key);
        $transitions[] = [
            'from_status' => $fromS,
            'to_status' => $toS,
            'count' => $data['count'],
            'avg_duration' => formatMinutes($data['total_min'] / $data['count']),
        ];
    }

    // By priority: avg to first response and resolution
    $stmt = $db->prepare(
        "SELECT
            tp.name AS priority_name, tp.color AS priority_color,
            AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END) AS avg_fr_min,
            AVG(
                (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                 FROM ticket_timeline tl
                 WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                 ORDER BY tl.created_at DESC LIMIT 1)
            ) AS avg_res_min
         FROM tickets t
         LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY tp.id, tp.name, tp.color, tp.sort_order
         ORDER BY tp.sort_order"
    );
    $stmt->execute([$from, $toEnd]);
    $byPriority = $stmt->fetchAll();

    foreach ($byPriority as &$row) {
        $row['avg_to_first_response'] = $row['avg_fr_min'] !== null ? formatMinutes((float) $row['avg_fr_min']) : '—';
        $row['avg_to_resolution'] = $row['avg_res_min'] !== null ? formatMinutes((float) $row['avg_res_min']) : '—';
    }
    unset($row);

    render('admin/reports/lifecycle', compact(
        'from', 'to', 'statusDurations', 'transitions', 'byPriority'
    ));
});

/* ── Location Report ──────────────────────────────────────────────── */

$router->get('/admin/reports/location', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $closedInT    = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status');
    $notClosedInT = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status', true);
    $stmt = $db->prepare(
        "SELECT
            COALESCE(l.name, 'No Location') AS location_name,
            COUNT(t.id) AS total,
            SUM(CASE WHEN $notClosedInT THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN $closedInT THEN 1 ELSE 0 END) AS resolved,
            AVG(
                CASE WHEN $closedInT THEN
                    (SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at)
                     FROM ticket_timeline tl
                     WHERE tl.ticket_id = t.id AND tl.action = 'status_changed' AND tl.details LIKE '%→ Resolved%'
                     ORDER BY tl.created_at DESC LIMIT 1)
                END
            ) AS avg_res_min,
            SUM(CASE WHEN t.first_response_due_at IS NOT NULL THEN 1 ELSE 0 END) AS sla_total,
            SUM(CASE WHEN t.sla_state = 'breached' THEN 1 ELSE 0 END) AS sla_breached
         FROM tickets t
         LEFT JOIN locations l ON t.location_id = l.id
         WHERE t.created_at BETWEEN ? AND ?
         GROUP BY l.id, l.name
         ORDER BY total DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $locations = $stmt->fetchAll();

    foreach ($locations as &$loc) {
        $loc['resolution_rate'] = $loc['total'] > 0
            ? round($loc['resolved'] / $loc['total'] * 100) : 0;
        $loc['avg_resolution'] = $loc['avg_res_min'] !== null
            ? formatMinutes((float) $loc['avg_res_min']) : '—';
        $loc['sla_compliance'] = $loc['sla_total'] > 0
            ? round(($loc['sla_total'] - $loc['sla_breached']) / $loc['sla_total'] * 100) : 100;
    }
    unset($loc);

    render('admin/reports/location', compact('from', 'to', 'locations'));
});

/* ── CSAT Satisfaction Report ─────────────────────────────────────── */

$router->get('/admin/reports/csat', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $stmt = $db->prepare('SELECT COUNT(*) FROM csat_surveys WHERE sent_at BETWEEN ? AND ?');
    $stmt->execute([$from, $toEnd]);
    $totalSent = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM csat_surveys WHERE sent_at BETWEEN ? AND ? AND responded_at IS NOT NULL');
    $stmt->execute([$from, $toEnd]);
    $totalResponded = (int) $stmt->fetchColumn();

    $responseRate = $totalSent > 0 ? round($totalResponded / $totalSent * 100) : 0;

    $stmt = $db->prepare('SELECT AVG(rating) FROM csat_surveys WHERE sent_at BETWEEN ? AND ? AND rating IS NOT NULL');
    $stmt->execute([$from, $toEnd]);
    $avgRating = round((float) $stmt->fetchColumn(), 1);

    // Distribution: count per star rating
    $stmt = $db->prepare(
        'SELECT rating, COUNT(*) AS cnt
         FROM csat_surveys
         WHERE sent_at BETWEEN ? AND ? AND rating IS NOT NULL
         GROUP BY rating ORDER BY rating'
    );
    $stmt->execute([$from, $toEnd]);
    $rawDist = $stmt->fetchAll();
    $distribution = array_fill(1, 5, 0);
    foreach ($rawDist as $row) {
        $distribution[(int) $row['rating']] = (int) $row['cnt'];
    }

    // Responses table
    $stmt = $db->prepare(
        "SELECT cs.rating, cs.comment, cs.responded_at,
                cs.ticket_id, t.subject,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
         FROM csat_surveys cs
         JOIN tickets t ON cs.ticket_id = t.id
         JOIN users u   ON cs.user_id   = u.id
         WHERE cs.sent_at BETWEEN ? AND ? AND cs.responded_at IS NOT NULL
         ORDER BY cs.responded_at DESC
         LIMIT 200"
    );
    $stmt->execute([$from, $toEnd]);
    $responses = $stmt->fetchAll();

    render('admin/reports/csat', compact(
        'from', 'to', 'totalSent', 'totalResponded', 'responseRate',
        'avgRating', 'distribution', 'responses'
    ));
});


/* ==================================================================
 * ADMIN – Tags Settings
 * ================================================================== */

$router->get('/admin/settings/tags', function () {
    Auth::requirePermission('settings.manage');
    $settings = [
        'tags_enabled' => getSetting('tags_enabled', '1'),
    ];
    render('admin/settings/tags', compact('settings'));
});

$router->post('/admin/settings/tags', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/tags');
    }

    $before = getSetting('tags_enabled', '1');
    $after  = isset($_POST['tags_enabled']) ? '1' : '0';
    setSetting('tags_enabled', $after);
    logAuditChange('tags.settings_changed', null, null, ['tags_enabled' => $before], ['tags_enabled' => $after]);

    flash('success', 'Tag settings saved.');
    redirect('/admin/settings/tags');
});

/* ==================================================================
 * ADMIN – Ticket Status Management
 *
 * CRUD + drag-reorder for the `ticket_statuses` lookup table that backs
 * configurable ticket statuses. Slugs are immutable after create so the
 * SQL semantics scattered through the codebase stay stable. Bucket,
 * label, color, sort_order, pauses_sla, is_active, and the three
 * is_default_* flags are freely editable.
 *
 * Guardrails in this phase are deliberately minimal — system rows are
 * protected from outright deletion and in-use slugs block deletion.
 * Richer protections (last-in-bucket, default-reassignment, reference
 * checks against automations/escalations) come in Phase 5.
 * ================================================================== */

$router->get('/admin/settings/ticket-statuses', function () {
    Auth::requirePermission('workflows.manage');
    // Defensive: never let the browser serve a cached copy after a write.
    // Drag-reorder + POST-redirect-GET cycles return to this page expecting
    // to see fresh data; a 304 here would mask successful saves.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    ticketStatusCacheRefresh();
    $statuses = ticketStatuses();   // includes inactive rows for the admin view
    render('admin/settings/ticket-statuses', compact('statuses'));
});

$router->post('/admin/settings/ticket-statuses/reorder', function () {
    Auth::requirePermission('workflows.manage');
    handleSortableReorder('ticket_statuses');
    ticketStatusCacheRefresh();
});

$router->post('/admin/settings/ticket-statuses/create', function () {
    Auth::requirePermission('workflows.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ticket-statuses');
    }

    $slug   = strtolower(trim($_POST['slug'] ?? ''));
    $label  = trim($_POST['label'] ?? '');
    $bucket = in_array($_POST['bucket'] ?? '', ['open', 'closed'], true) ? $_POST['bucket'] : 'open';
    $color  = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#6c757d';
    $pauses = !empty($_POST['pauses_sla']) ? 1 : 0;

    // Slug rules: starts with a lowercase letter, then lowercase letters,
    // digits, and underscores only, max 64. Same shape as the seeded slugs.
    if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $slug)) {
        flashInput($_POST);
        flash('error', 'Slug must start with a lowercase letter and contain only lowercase letters, digits, and underscores (max 64 chars).');
        redirect('/admin/settings/ticket-statuses');
    }
    if ($label === '') {
        flashInput($_POST);
        flash('error', 'Label is required.');
        redirect('/admin/settings/ticket-statuses');
    }

    $db       = Database::connect();
    $maxOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) FROM ticket_statuses')->fetchColumn();

    try {
        $db->prepare(
            'INSERT INTO ticket_statuses
                (slug, label, bucket, pauses_sla, color, sort_order, is_system, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 0, 1)'
        )->execute([$slug, $label, $bucket, $pauses, $color, $maxOrder + 1]);
        $newId = (int) $db->lastInsertId();
        logAudit('status.created', $newId, 'ticket_status', "slug={$slug}; label={$label}; bucket={$bucket}");
        ticketStatusCacheRefresh();
        flash('success', "Status \"{$label}\" created.");
    } catch (\PDOException $e) {
        flashInput($_POST);
        if (str_contains($e->getMessage(), 'uniq_ticket_statuses_slug')) {
            flash('error', "Slug \"{$slug}\" is already in use.");
        } else {
            flash('error', 'Could not create status: ' . $e->getMessage());
        }
    }
    redirect('/admin/settings/ticket-statuses');
});

$router->post('/admin/settings/ticket-statuses/{id}/edit', function (array $p) {
    Auth::requirePermission('workflows.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ticket-statuses');
    }
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM ticket_statuses WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
        flash('error', 'Status not found.');
        redirect('/admin/settings/ticket-statuses');
    }

    $label  = trim($_POST['label'] ?? '');
    $bucket = in_array($_POST['bucket'] ?? '', ['open', 'closed'], true) ? $_POST['bucket'] : (string) $row['bucket'];
    $color  = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : (string) $row['color'];
    $pauses = !empty($_POST['pauses_sla']) ? 1 : 0;
    $active = !empty($_POST['is_active']) ? 1 : 0;

    if ($label === '') {
        flash('error', 'Label is required.');
        redirect('/admin/settings/ticket-statuses');
    }

    // Deactivation guards: refuse if (a) it would leave the bucket with
    // no active statuses, or (b) the row still holds a default flag.
    $wasActive = (int) $row['is_active'] === 1;
    if ($wasActive && $active === 0) {
        // Default-flag check: bucket of the original row (not the submitted
        // one — we're protecting the existing semantic).
        $defaultBlocks = [];
        if ((int) $row['is_default_new'])      $defaultBlocks[] = 'new tickets';
        if ((int) $row['is_default_resolved']) $defaultBlocks[] = 'resolved emails';
        if ((int) $row['is_default_closed'])   $defaultBlocks[] = 'closed emails';
        if (!empty($defaultBlocks)) {
            flash('error', "Cannot deactivate \"{$row['label']}\" — it's the default for " . implode(' + ', $defaultBlocks)
                . '. Promote another status to that role first.');
            redirect('/admin/settings/ticket-statuses');
        }

        // Last-active-in-bucket check.
        $bucketActive = $db->prepare(
            'SELECT COUNT(*) FROM ticket_statuses WHERE bucket = ? AND is_active = 1 AND id != ?'
        );
        $bucketActive->execute([$row['bucket'], $id]);
        if ((int) $bucketActive->fetchColumn() === 0) {
            flash('error', "Cannot deactivate \"{$row['label']}\" — it's the only active status in the «{$row['bucket']}» bucket. Add or activate another status in that bucket first.");
            redirect('/admin/settings/ticket-statuses');
        }
    }

    // Slug is intentionally not read from the request — it's immutable
    // after create so the SQL semantics in the codebase stay stable.
    $db->prepare(
        'UPDATE ticket_statuses SET label=?, bucket=?, color=?, pauses_sla=?, is_active=? WHERE id=?'
    )->execute([$label, $bucket, $color, $pauses, $active, $id]);

    logAuditChange(
        'status.updated', $id, 'ticket_status',
        [
            'label'      => $row['label'],
            'bucket'     => $row['bucket'],
            'color'      => $row['color'],
            'pauses_sla' => $row['pauses_sla'],
            'is_active'  => $row['is_active'],
        ],
        [
            'label'      => $label,
            'bucket'     => $bucket,
            'color'      => $color,
            'pauses_sla' => $pauses,
            'is_active'  => $active,
        ]
    );
    ticketStatusCacheRefresh();
    flash('success', "Status \"{$label}\" updated.");
    redirect('/admin/settings/ticket-statuses');
});

$router->post('/admin/settings/ticket-statuses/{id}/toggle-active', function (array $p) {
    Auth::requirePermission('workflows.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ticket-statuses');
    }
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM ticket_statuses WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
        redirect('/admin/settings/ticket-statuses');
    }

    $wasActive = (int) $row['is_active'] === 1;
    $newActive = $wasActive ? 0 : 1;

    // Same guardrails as the edit deactivation path: don't deactivate a row
    // that holds a default flag or is the only active in its bucket.
    if ($wasActive && $newActive === 0) {
        $defaultBlocks = [];
        if ((int) $row['is_default_new'])      $defaultBlocks[] = 'new tickets';
        if ((int) $row['is_default_resolved']) $defaultBlocks[] = 'resolved emails';
        if ((int) $row['is_default_closed'])   $defaultBlocks[] = 'closed emails';
        if (!empty($defaultBlocks)) {
            flash('error', "Cannot deactivate \"{$row['label']}\" — it's the default for " . implode(' + ', $defaultBlocks)
                . '. Promote another status to that role first.');
            redirect('/admin/settings/ticket-statuses');
        }
        $bucketActive = $db->prepare(
            'SELECT COUNT(*) FROM ticket_statuses WHERE bucket = ? AND is_active = 1 AND id != ?'
        );
        $bucketActive->execute([$row['bucket'], $id]);
        if ((int) $bucketActive->fetchColumn() === 0) {
            flash('error', "Cannot deactivate \"{$row['label']}\" — it's the only active status in the «{$row['bucket']}» bucket.");
            redirect('/admin/settings/ticket-statuses');
        }
    }

    $db->prepare('UPDATE ticket_statuses SET is_active = ? WHERE id = ?')->execute([$newActive, $id]);
    logAuditChange(
        'status.updated', $id, 'ticket_status',
        ['is_active' => $row['is_active']],
        ['is_active' => $newActive]
    );
    ticketStatusCacheRefresh();
    flash('success', "\"{$row['label']}\" " . ($newActive ? 'activated' : 'deactivated') . '.');
    redirect('/admin/settings/ticket-statuses');
});

$router->post('/admin/settings/ticket-statuses/{id}/set-default', function (array $p) {
    Auth::requirePermission('workflows.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ticket-statuses');
    }
    $kind = (string) ($_POST['kind'] ?? '');
    $columnMap = [
        'new'      => ['is_default_new',      'open',   'new tickets'],
        'resolved' => ['is_default_resolved', 'closed', 'resolved emails + CSAT'],
        'closed'   => ['is_default_closed',   'closed', 'closed emails'],
    ];
    if (!isset($columnMap[$kind])) {
        flash('error', 'Unknown default kind.');
        redirect('/admin/settings/ticket-statuses');
    }
    [$col, $requiredBucket, $kindLabel] = $columnMap[$kind];

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT slug, label, bucket, is_active FROM ticket_statuses WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
        flash('error', 'Status not found.');
        redirect('/admin/settings/ticket-statuses');
    }
    if ((int) $row['is_active'] !== 1) {
        flash('error', "\"{$row['label']}\" is inactive — activate it before making it the default.");
        redirect('/admin/settings/ticket-statuses');
    }
    if ($row['bucket'] !== $requiredBucket) {
        flash('error', "Default for {$kindLabel} must be a status in the «{$requiredBucket}» bucket. \"{$row['label']}\" is in «{$row['bucket']}».");
        redirect('/admin/settings/ticket-statuses');
    }

    $db->beginTransaction();
    try {
        $db->exec("UPDATE ticket_statuses SET {$col} = 0");
        $db->prepare("UPDATE ticket_statuses SET {$col} = 1 WHERE id = ?")->execute([$id]);
        $db->commit();
        ticketStatusCacheRefresh();
        logAudit('status.default_changed', $id, 'ticket_status', "kind={$kind}");
        flash('success', "Default for {$kindLabel} is now \"{$row['label']}\".");
    } catch (\PDOException $e) {
        $db->rollBack();
        flash('error', 'Could not update default: ' . $e->getMessage());
    }
    redirect('/admin/settings/ticket-statuses');
});

$router->post('/admin/settings/ticket-statuses/{id}/delete', function (array $p) {
    Auth::requirePermission('workflows.manage');
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/ticket-statuses');
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM ticket_statuses WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    // Silently redirect on missing row — most commonly hit when an accidental
    // double-submit fires a delete POST against a row the first POST already
    // removed. Showing "Status not found" in that case is confusing because
    // the user just saw a "deleted successfully" flash on the previous render.
    if (!$row) {
        redirect('/admin/settings/ticket-statuses');
    }

    if ((int) $row['is_system'] === 1) {
        flash('error', "\"{$row['label']}\" is a built-in status and cannot be deleted. Deactivate it instead.");
        redirect('/admin/settings/ticket-statuses');
    }

    $refs = ticketStatusReferences((string) $row['slug']);

    // 1. Default-flag holder must be reassigned via Set Default first.
    $defaultBlocks = [];
    if ($refs['is_default_new'])      $defaultBlocks[] = 'new tickets';
    if ($refs['is_default_resolved']) $defaultBlocks[] = 'resolved emails';
    if ($refs['is_default_closed'])   $defaultBlocks[] = 'closed emails';
    if (!empty($defaultBlocks)) {
        flash('error', "Cannot delete \"{$row['label']}\" — it's the default for " . implode(' + ', $defaultBlocks)
            . '. Promote another status to that role first.');
        redirect('/admin/settings/ticket-statuses');
    }

    // 2. Automation / escalation references must be cleared first.
    $ruleBlocks = [];
    if (!empty($refs['automations'])) {
        $names = array_slice(array_column($refs['automations'], 'name'), 0, 3);
        $ruleBlocks[] = count($refs['automations']) . ' automation(s): ' . implode(', ', $names)
            . (count($refs['automations']) > 3 ? ', …' : '');
    }
    if (!empty($refs['escalation_rules'])) {
        $names = array_slice(array_column($refs['escalation_rules'], 'name'), 0, 3);
        $ruleBlocks[] = count($refs['escalation_rules']) . ' escalation rule(s): ' . implode(', ', $names)
            . (count($refs['escalation_rules']) > 3 ? ', …' : '');
    }
    if ($refs['csat_trigger']) {
        $ruleBlocks[] = 'the CSAT trigger setting';
    }
    if (!empty($ruleBlocks)) {
        flash('error', "Cannot delete \"{$row['label']}\" — referenced by " . implode('; ', $ruleBlocks)
            . '. Edit those rules or settings to point at a different status first.');
        redirect('/admin/settings/ticket-statuses');
    }

    // 3. Last-active-in-bucket protection. If this row is the only active
    // status in its bucket, deleting it would leave the bucket empty and
    // break dropdowns / new ticket creation.
    $bucketActive = $db->prepare(
        'SELECT COUNT(*) FROM ticket_statuses WHERE bucket = ? AND is_active = 1 AND id != ?'
    );
    $bucketActive->execute([$row['bucket'], $id]);
    if ((int) $bucketActive->fetchColumn() === 0) {
        flash('error', "Cannot delete \"{$row['label']}\" — it's the only active status in the «{$row['bucket']}» bucket. Add or activate another status in that bucket first.");
        redirect('/admin/settings/ticket-statuses');
    }

    // 4. If tickets currently hold this slug, optionally reassign them to
    // a chosen target before deleting. If no target is given, block.
    if ($refs['tickets'] > 0) {
        $reassignTo = trim((string) ($_POST['reassign_to'] ?? ''));
        if ($reassignTo === '') {
            flash('error', "Cannot delete \"{$row['label']}\" — {$refs['tickets']} ticket(s) still have this status. Choose a status to reassign them to.");
            redirect('/admin/settings/ticket-statuses');
        }
        if ($reassignTo === $row['slug']) {
            flash('error', 'Reassign target must differ from the status being deleted.');
            redirect('/admin/settings/ticket-statuses');
        }
        if (!in_array($reassignTo, ticketActiveStatusSlugs(), true)) {
            flash('error', "Reassign target \"{$reassignTo}\" is not an active status.");
            redirect('/admin/settings/ticket-statuses');
        }
        try {
            $db->beginTransaction();
            $db->prepare('UPDATE tickets SET status = ? WHERE status = ?')
               ->execute([$reassignTo, $row['slug']]);
            $db->prepare('DELETE FROM ticket_statuses WHERE id = ?')->execute([$id]);
            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            flash('error', 'Could not reassign + delete: ' . $e->getMessage());
            redirect('/admin/settings/ticket-statuses');
        }
        logAudit(
            'status.deleted',
            $id,
            'ticket_status',
            "slug={$row['slug']}; label={$row['label']}; reassigned {$refs['tickets']} ticket(s) to {$reassignTo}"
        );
        ticketStatusCacheRefresh();
        flash('success', "Status \"{$row['label']}\" deleted. {$refs['tickets']} ticket(s) reassigned to \"" . ticketStatusLabel($reassignTo) . '".');
        redirect('/admin/settings/ticket-statuses');
    }

    // 5. No tickets, no refs — safe to delete outright.
    $db->prepare('DELETE FROM ticket_statuses WHERE id = ?')->execute([$id]);
    logAudit('status.deleted', $id, 'ticket_status', "slug={$row['slug']}; label={$row['label']}");
    ticketStatusCacheRefresh();
    flash('success', "Status \"{$row['label']}\" deleted.");
    redirect('/admin/settings/ticket-statuses');
});

/* ==================================================================
 * ADMIN – Organization Settings
 *
 * Stored as a single key (`organization_type`) in the `settings` table.
 * Values are stable slugs (e.g. `public_library`); the human labels live
 * only in `organizationTypeGroups()` (helpers.php) so they can be
 * relabelled without orphaning saved data.
 * ================================================================== */

$router->get('/admin/settings/organization', function () {
    Auth::requirePermission('settings.manage');
    $settings = [
        'organization_type' => getSetting('organization_type', 'other'),
    ];
    $orgTypeGroups = organizationTypeGroups();
    render('admin/settings/organization', compact('settings', 'orgTypeGroups'));
});

$router->post('/admin/settings/organization', function () {
    Auth::requirePermission('settings.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/organization');
    }

    $submitted = $_POST['organization_type'] ?? '';
    $validValues = [];
    foreach (organizationTypeGroups() as $opts) {
        $validValues = array_merge($validValues, array_keys($opts));
    }

    if (!in_array($submitted, $validValues, true)) {
        flash('error', 'Please choose an organization type from the list.');
        redirect('/admin/settings/organization');
    }

    $before = getSetting('organization_type', 'other');
    setSetting('organization_type', $submitted);
    logAuditChange(
        'organization.settings_changed',
        null,
        null,
        ['organization_type' => $before],
        ['organization_type' => $submitted]
    );
    flash('success', 'Organization settings saved.');
    redirect('/admin/settings/organization');
});

/* ==================================================================
 * ADMIN – CSAT Settings
 * ================================================================== */

$router->get('/admin/settings/csat', function () {
    Auth::requirePermission('csat.manage');
    $settings = [
        'csat_enabled'               => getSetting('csat_enabled', '0'),
        'csat_trigger_status'        => getSetting('csat_trigger_status', ticketDefaultResolvedStatusSlug()),
        'csat_mode'                  => getSetting('csat_mode', 'internal'),
        'csat_external_url'          => getSetting('csat_external_url', ''),
        'csat_external_dashboard_url'=> getSetting('csat_external_dashboard_url', ''),
        'csat_show_reopen'           => getSetting('csat_show_reopen', '1'),
    ];
    render('admin/settings/csat', compact('settings'));
});

$router->post('/admin/settings/csat', function () {
    Auth::requirePermission('csat.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/csat');
    }

    $before = [
        'csat_enabled'               => getSetting('csat_enabled', '0'),
        'csat_trigger_status'        => getSetting('csat_trigger_status', ticketDefaultResolvedStatusSlug()),
        'csat_mode'                  => getSetting('csat_mode', 'internal'),
        'csat_external_url'          => getSetting('csat_external_url', ''),
        'csat_external_dashboard_url'=> getSetting('csat_external_dashboard_url', ''),
        'csat_show_reopen'           => getSetting('csat_show_reopen', '1'),
    ];
    $enabled = isset($_POST['csat_enabled']) ? '1' : '0';
    // CSAT can fire when a ticket enters either of the two "closed-bucket"
    // semantic states. Comparing against the two default flags rather than
    // literal 'resolved'/'closed' lets admins rename the underlying slugs.
    $csatChoices = array_filter([ticketDefaultResolvedStatusSlug(), ticketDefaultClosedStatusSlug()]);
    $trigger = in_array($_POST['csat_trigger_status'] ?? '', $csatChoices, true)
        ? $_POST['csat_trigger_status'] : ticketDefaultResolvedStatusSlug();
    $mode = ($_POST['csat_mode'] ?? '') === 'external' ? 'external' : 'internal';
    $extUrl = trim($_POST['csat_external_url'] ?? '');
    $extDash = trim($_POST['csat_external_dashboard_url'] ?? '');
    $showReopen = isset($_POST['csat_show_reopen']) ? '1' : '0';

    // If admin picked External but left URL blank, flag it and don't switch
    // mode — otherwise the next ticket-resolve would silently send nothing.
    if ($mode === 'external' && $extUrl === '') {
        flash('error', 'External survey URL is required when Survey type is set to External.');
        redirect('/admin/settings/csat');
        return;
    }
    if ($extUrl !== '' && !filter_var($extUrl, FILTER_VALIDATE_URL)) {
        flash('error', 'External survey URL must be a valid URL (e.g. https://...).');
        redirect('/admin/settings/csat');
        return;
    }
    if ($extDash !== '' && !filter_var($extDash, FILTER_VALIDATE_URL)) {
        flash('error', 'External dashboard URL must be a valid URL (e.g. https://...).');
        redirect('/admin/settings/csat');
        return;
    }

    setSetting('csat_enabled', $enabled);
    setSetting('csat_trigger_status', $trigger);
    setSetting('csat_mode', $mode);
    setSetting('csat_external_url', $extUrl);
    setSetting('csat_external_dashboard_url', $extDash);
    setSetting('csat_show_reopen', $showReopen);
    logAuditChange(
        'csat.settings_changed',
        null,
        null,
        $before,
        [
            'csat_enabled'                => $enabled,
            'csat_trigger_status'         => $trigger,
            'csat_mode'                   => $mode,
            'csat_external_url'           => $extUrl,
            'csat_external_dashboard_url' => $extDash,
            'csat_show_reopen'            => $showReopen,
        ]
    );

    flash('success', 'CSAT settings saved.');
    redirect('/admin/settings/csat');
});

$router->post('/admin/settings/csat/test', function () {
    Auth::requirePermission('csat.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/csat');
        return;
    }

    $email = trim($_POST['test_email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
        redirect('/admin/settings/csat');
        return;
    }

    $db = Database::connect();

    // Find a ticket that does not already have a csat_survey so we can create
    // a fully functional test. Fall back to any ticket if all have surveys.
    $stmt = $db->query(
        'SELECT t.id, t.subject
         FROM tickets t
         LEFT JOIN csat_surveys cs ON cs.ticket_id = t.id
         WHERE cs.id IS NULL
         ORDER BY t.id DESC
         LIMIT 1'
    );
    $ticket = $stmt->fetch();

    if (!$ticket) {
        // All tickets have surveys — just pick any ticket for a preview
        $ticket = $db->query('SELECT id, subject FROM tickets ORDER BY id DESC LIMIT 1')->fetch();
    }

    if (!$ticket) {
        flash('error', 'No tickets exist yet. Create at least one ticket before sending a test survey.');
        redirect('/admin/settings/csat');
        return;
    }

    // Create (or reuse) a test survey record
    $existingStmt = $db->prepare('SELECT token FROM csat_surveys WHERE ticket_id = ?');
    $existingStmt->execute([$ticket['id']]);
    $existingToken = $existingStmt->fetchColumn();

    if ($existingToken) {
        $token = $existingToken;
    } else {
        $token = bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO csat_surveys (ticket_id, user_id, token) VALUES (?, ?, ?)')
           ->execute([$ticket['id'], Auth::id(), $token]);
    }

    $appUrl     = env('APP_URL', 'http://localhost:8000');
    $brandColor = getSetting('branding_primary_color', '#4f46e5');
    $appName    = getSetting('app_name', 'OpenHelpDesk');
    $mode       = getSetting('csat_mode', 'internal');
    $showReopen = getSetting('csat_show_reopen', '1') === '1';

    if ($mode === 'external') {
        $extUrl = trim(getSetting('csat_external_url', ''));
        if ($extUrl === '') {
            flash('error', 'External survey URL is not configured — set it above before sending a test.');
            redirect('/admin/settings/csat');
            return;
        }
        $surveyUrl = csatSubstitutePlaceholders($extUrl, [
            'ticket_id'  => (string) $ticket['id'],
            'user_email' => $email,
            'first_name' => 'Admin',
            'last_name'  => '',
            'user_name'  => 'Admin',
            'subject'    => $ticket['subject'],
        ]);
    } else {
        $surveyUrl = $appUrl . '/survey/' . $token;
    }
    $reopenUrl = $showReopen ? $appUrl . '/survey/' . $token . '/reopen' : '';

    $tpl = getEmailTpl('csat_survey', [
        'ticket_id'  => $ticket['id'],
        'subject'    => $ticket['subject'],
        'first_name' => 'Admin',
        'last_name'  => '',
        'user_name'  => 'Admin',
    ]);

    $emailHtml = renderEmail('csat-survey', [
        'ticketId'   => $ticket['id'],
        'subject'    => $ticket['subject'],
        'firstName'  => 'Admin',
        'surveyUrl'  => $surveyUrl,
        'reopenUrl'  => $reopenUrl,
        'showReopen' => $showReopen,
        'brandColor' => $brandColor,
        'appName'    => $appName,
        'introText'  => $tpl['intro'],
        'footerText' => $tpl['footer'],
    ]);

    $sent = sendMail($email, 'Admin', '[TEST] ' . $tpl['subject'], $emailHtml);

    if ($sent) {
        flash('success', "Test survey sent to {$email}. Check your inbox!");
    } else {
        flash('error', 'Failed to send the test email. Check your mail settings.');
    }
    redirect('/admin/settings/csat');
});

/* ==================================================================
 * ADMIN – Report: Agent Workload Heatmap
 * ================================================================== */

$router->get('/admin/reports/workload', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();

    // Workload report breaks out specific status counts. The four breakouts
    // (open / in_progress / pending / waiting-*) are pinned to the literal
    // legacy slugs — they show 0 if those slugs have been renamed or removed.
    // The "non-closed" bucket filter still works on whatever statuses live
    // in the closed bucket today.
    $notClosedInT = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status', true);
    $stmt = $db->query(
        "SELECT COALESCE(CONCAT(u.first_name,' ',u.last_name),'Unassigned') AS agent_name,
                u.id AS agent_id,
                COUNT(t.id) AS open_total,
                SUM(CASE WHEN t.sla_state='breached' THEN 1 ELSE 0 END) AS breached_count,
                SUM(CASE WHEN t.status='open' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN t.status='in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN t.status='pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN t.status IN ('waiting_on_customer','waiting_on_third_party') THEN 1 ELSE 0 END) AS waiting_count
         FROM tickets t
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE $notClosedInT
         GROUP BY u.id, u.first_name, u.last_name
         ORDER BY open_total DESC"
    );
    $agents  = $stmt->fetchAll();
    $maxLoad = !empty($agents) ? (int) $agents[0]['open_total'] : 1;

    render('admin/reports/workload', compact('agents', 'maxLoad'));
});

/* ==================================================================
 * ADMIN – Report: Ticket Trends
 * ================================================================== */

$router->get('/admin/reports/trends', function () {
    Auth::requirePermission('reports.view');
    $db      = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd   = $to . ' 23:59:59';
    $groupBy = in_array($_GET['group_by'] ?? '', ['type', 'location'], true)
        ? $_GET['group_by'] : 'type';

    if ($groupBy === 'type') {
        $stmt = $db->prepare(
            "SELECT DATE(t.created_at) AS day,
                    COALESCE(tt.name,'Untyped') AS segment,
                    COUNT(t.id) AS cnt
             FROM tickets t
             LEFT JOIN ticket_types tt ON t.type_id = tt.id
             WHERE t.created_at BETWEEN ? AND ?
             GROUP BY day, segment
             ORDER BY day, segment"
        );
    } else {
        $stmt = $db->prepare(
            "SELECT DATE(t.created_at) AS day,
                    COALESCE(l.name,'No Location') AS segment,
                    COUNT(t.id) AS cnt
             FROM tickets t
             LEFT JOIN locations l ON t.location_id = l.id
             WHERE t.created_at BETWEEN ? AND ?
             GROUP BY day, segment
             ORDER BY day, segment"
        );
    }
    $stmt->execute([$from, $toEnd]);
    $rows = $stmt->fetchAll();

    // Pivot: labels (dates) + datasets (one per segment)
    $labelSet   = [];
    $segmentSet = [];
    $matrix     = [];
    foreach ($rows as $row) {
        $d = date('M j', strtotime($row['day']));
        $labelSet[$d]               = true;
        $segmentSet[$row['segment']] = true;
        $matrix[$d][$row['segment']] = (int) $row['cnt'];
    }
    $labels   = array_keys($labelSet);
    $segments = array_keys($segmentSet);

    $palette = ['#4f46e5','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#84cc16','#f97316'];
    $datasets = [];
    foreach ($segments as $i => $seg) {
        $data = [];
        foreach ($labels as $lbl) {
            $data[] = $matrix[$lbl][$seg] ?? 0;
        }
        $datasets[] = [
            'label'           => $seg,
            'data'            => $data,
            'borderColor'     => $palette[$i % count($palette)],
            'backgroundColor' => $palette[$i % count($palette)] . '22',
            'fill'            => false,
            'tension'         => 0.3,
        ];
    }

    render('admin/reports/trends', compact('from', 'to', 'groupBy', 'labels', 'datasets'));
});

/* ==================================================================
 * ADMIN – Report: First Contact Resolution (FCR)
 * ================================================================== */

$router->get('/admin/reports/fcr', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    // Overall FCR
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total_resolved,
                SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
         FROM (
             SELECT t.id,
                 (SELECT COUNT(*) FROM ticket_timeline tl
                  WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
             FROM tickets t
             WHERE " . ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status') . "
               AND t.created_at BETWEEN ? AND ?
         ) sub"
    );
    $stmt->execute([$from, $toEnd]);
    $overall = $stmt->fetch();
    $overallFcr = [
        'total'   => (int) $overall['total_resolved'],
        'fcr'     => (int) $overall['fcr_count'],
        'pct'     => $overall['total_resolved'] > 0
            ? round($overall['fcr_count'] / $overall['total_resolved'] * 100)
            : 0,
    ];

    // FCR by agent
    $stmt = $db->prepare(
        "SELECT CONCAT(u.first_name,' ',u.last_name) AS agent_name,
                COUNT(*) AS total_resolved,
                SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
         FROM (
             SELECT t.id, t.assigned_to,
                 (SELECT COUNT(*) FROM ticket_timeline tl
                  WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
             FROM tickets t
             WHERE " . ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status') . "
               AND t.created_at BETWEEN ? AND ?
         ) sub
         LEFT JOIN users u ON sub.assigned_to = u.id
         GROUP BY sub.assigned_to, u.first_name, u.last_name
         ORDER BY total_resolved DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $fcrByAgent = $stmt->fetchAll();
    foreach ($fcrByAgent as &$a) {
        $a['agent_name'] = $a['agent_name'] ?? 'Unassigned';
        $a['fcr_pct']    = $a['total_resolved'] > 0
            ? round($a['fcr_count'] / $a['total_resolved'] * 100) : 0;
    }
    unset($a);

    // FCR by type
    $stmt = $db->prepare(
        "SELECT COALESCE(tt.name,'Untyped') AS type_name,
                COUNT(*) AS total_resolved,
                SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
         FROM (
             SELECT t.id, t.type_id,
                 (SELECT COUNT(*) FROM ticket_timeline tl
                  WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
             FROM tickets t
             WHERE " . ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status') . "
               AND t.created_at BETWEEN ? AND ?
         ) sub
         LEFT JOIN ticket_types tt ON sub.type_id = tt.id
         GROUP BY sub.type_id, tt.name
         ORDER BY total_resolved DESC"
    );
    $stmt->execute([$from, $toEnd]);
    $fcrByType = $stmt->fetchAll();
    foreach ($fcrByType as &$r) {
        $r['fcr_pct'] = $r['total_resolved'] > 0
            ? round($r['fcr_count'] / $r['total_resolved'] * 100) : 0;
    }
    unset($r);

    // Weekly trend
    $stmt = $db->prepare(
        "SELECT DATE_FORMAT(sub.created_at,'%Y-%u') AS week_key,
                DATE_FORMAT(MIN(sub.created_at),'%b %e') AS week_label,
                COUNT(*) AS total_resolved,
                SUM(CASE WHEN reply_count <= 1 THEN 1 ELSE 0 END) AS fcr_count
         FROM (
             SELECT t.id, t.created_at,
                 (SELECT COUNT(*) FROM ticket_timeline tl
                  WHERE tl.ticket_id = t.id AND tl.action = 'reply_sent') AS reply_count
             FROM tickets t
             WHERE " . ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status') . "
               AND t.created_at BETWEEN ? AND ?
         ) sub
         GROUP BY week_key
         ORDER BY week_key"
    );
    $stmt->execute([$from, $toEnd]);
    $weeklyFcr = $stmt->fetchAll();
    foreach ($weeklyFcr as &$w) {
        $w['fcr_pct'] = $w['total_resolved'] > 0
            ? round($w['fcr_count'] / $w['total_resolved'] * 100) : 0;
    }
    unset($w);

    render('admin/reports/fcr', compact('from', 'to', 'overallFcr', 'fcrByAgent', 'fcrByType', 'weeklyFcr'));
});

/* ==================================================================
 * ADMIN – Report: Group Coverage (ticket type → group → members)
 * ================================================================== */

$router->get('/admin/reports/group-coverage', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();

    $rows = $db->query(
        "SELECT
            tt.id                                  AS type_id,
            tt.name                                AS type_name,
            tt.color                               AS type_color,
            g.id                                   AS group_id,
            g.name                                 AS group_name,
            u.id                                   AS user_id,
            CONCAT(u.first_name, ' ', u.last_name) AS member_name,
            u.email                                AS member_email,
            u.role                                 AS user_role,
            gum.is_manager                         AS is_manager
         FROM ticket_types tt
         LEFT JOIN `groups` g         ON g.id = tt.group_id
         LEFT JOIN group_user_map gum ON gum.group_id = g.id
         LEFT JOIN users u            ON u.id = gum.user_id
         ORDER BY tt.sort_order, tt.name, u.last_name, u.first_name"
    )->fetchAll();

    render('admin/reports/group-coverage', compact('rows'));
});

/* ==================================================================
 * ADMIN – Report: Custom Report Builder
 * ================================================================== */

$router->get('/admin/reports/custom', function () {
    Auth::requirePermission('reports.view');
    $db = Database::connect();
    [$from, $to] = reportDateRange();
    $toEnd = $to . ' 23:59:59';

    $metricOptions = [
        'ticket_count'       => 'Ticket Count',
        'avg_first_response' => 'Avg First Response Time',
        'avg_resolution'     => 'Avg Resolution Time',
        'sla_compliance'     => 'SLA Compliance %',
    ];
    $groupByOptions = [
        'agent'    => 'Agent',
        'type'     => 'Ticket Type',
        'location' => 'Location',
        'priority' => 'Priority',
        'status'   => 'Status',
    ];

    $metric  = array_key_exists($_GET['metric'] ?? '', $metricOptions)  ? $_GET['metric']   : null;
    $groupBy = array_key_exists($_GET['group_by'] ?? '', $groupByOptions) ? $_GET['group_by'] : null;
    $rows    = [];
    $metricLabel = $metric ? $metricOptions[$metric] : '';

    if ($metric && $groupBy) {
        // Group-by label and JOIN expressions (whitelisted)
        $groupConfig = [
            'agent'    => ['label' => "COALESCE(CONCAT(u.first_name,' ',u.last_name),'Unassigned')",
                           'join'  => "LEFT JOIN users u ON t.assigned_to = u.id",
                           'group' => 'u.id, u.first_name, u.last_name'],
            'type'     => ['label' => "COALESCE(tt.name,'Untyped')",
                           'join'  => "LEFT JOIN ticket_types tt ON t.type_id = tt.id",
                           'group' => 'tt.id, tt.name'],
            'location' => ['label' => "COALESCE(l.name,'No Location')",
                           'join'  => "LEFT JOIN locations l ON t.location_id = l.id",
                           'group' => 'l.id, l.name'],
            'priority' => ['label' => "COALESCE(tp.name,'None')",
                           'join'  => "LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id",
                           'group' => 'tp.id, tp.name, tp.sort_order'],
            'status'   => ['label' => 't.status',
                           'join'  => '',
                           'group' => 't.status'],
        ];

        $gc  = $groupConfig[$groupBy];
        $lbl = $gc['label'];
        $jn  = $gc['join'];
        $grp = $gc['group'];
        $ord = $groupBy === 'priority' ? 'tp.sort_order, grp_label' : 'value DESC';

        $metricSql = match ($metric) {
            'ticket_count'       => 'COUNT(t.id)',
            'avg_first_response' => 'ROUND(AVG(CASE WHEN t.first_responded_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at) END), 1)',
            'avg_resolution'     => 'ROUND(AVG((SELECT TIMESTAMPDIFF(MINUTE, t.created_at, tl.created_at) FROM ticket_timeline tl WHERE tl.ticket_id = t.id AND tl.action = \'status_changed\' AND tl.details LIKE \'%→ Resolved%\' ORDER BY tl.created_at DESC LIMIT 1)), 1)',
            'sla_compliance'     => 'ROUND(SUM(CASE WHEN t.first_response_due_at IS NOT NULL AND t.sla_state != \'breached\' THEN 1 ELSE 0 END) / NULLIF(SUM(CASE WHEN t.first_response_due_at IS NOT NULL THEN 1 ELSE 0 END),0) * 100, 1)',
        };

        $sql = "SELECT {$lbl} AS grp_label, {$metricSql} AS value
                FROM tickets t
                {$jn}
                WHERE t.created_at BETWEEN ? AND ?
                GROUP BY {$grp}
                ORDER BY {$ord}";

        $stmt = $db->prepare($sql);
        $stmt->execute([$from, $toEnd]);
        $rawRows = $stmt->fetchAll();

        foreach ($rawRows as $row) {
            $displayVal = $row['value'] ?? 0;
            if (in_array($metric, ['avg_first_response', 'avg_resolution'], true) && $displayVal !== null) {
                $displayVal = formatMinutes((float) $displayVal);
            } elseif ($metric === 'sla_compliance') {
                $displayVal = ($displayVal ?? 0) . '%';
            }
            $rows[] = ['label' => $row['grp_label'], 'raw' => $row['value'] ?? 0, 'display' => $displayVal];
        }
    }

    render('admin/reports/custom', compact(
        'from', 'to', 'metric', 'groupBy', 'rows',
        'metricLabel', 'metricOptions', 'groupByOptions'
    ));
});

/* ==================================================================
 * ADMIN – Settings: Scheduled Reports
 * ================================================================== */

$router->get('/admin/settings/scheduled-reports', function () {
    Auth::requirePermission('automations.manage');
    $db      = Database::connect();
    $reports = $db->query('SELECT * FROM scheduled_reports ORDER BY name')->fetchAll();
    render('admin/settings/scheduled-reports', compact('reports'));
});

$router->get('/admin/settings/scheduled-reports/create', function () {
    Auth::requirePermission('automations.manage');
    $report = null;
    render('admin/settings/scheduled-reports-form', compact('report'));
});

$router->post('/admin/settings/scheduled-reports/create', function () {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/scheduled-reports');
    }
    $db             = Database::connect();
    $name           = trim($_POST['name'] ?? '');
    $allowedTypes   = ['overview','agent_performance','ticket_volume','response_times','sla',
                       'unresolved','lifecycle','location','csat','workload','trends','fcr'];
    $reportType     = in_array($_POST['report_type'] ?? '', $allowedTypes, true)
        ? $_POST['report_type'] : 'overview';
    $frequency      = in_array($_POST['frequency'] ?? '', ['daily','weekly','monthly'], true)
        ? $_POST['frequency'] : 'weekly';
    $sendDay        = $frequency === 'daily' ? null : max(0, min(31, (int)($_POST['send_day'] ?? 1)));
    $dateRangeDays  = max(1, min(365, (int)($_POST['date_range_days'] ?? 30)));
    $rawEmails      = trim($_POST['recipients'] ?? '');
    $recipients     = array_filter(array_map('trim', explode("\n", $rawEmails)));
    $enabled        = isset($_POST['is_enabled']) ? 1 : 0;

    if (empty($name) || empty($recipients)) {
        flash('error', 'Name and at least one recipient are required.');
        redirect('/admin/settings/scheduled-reports/create');
    }

    $db->prepare(
        'INSERT INTO scheduled_reports (name, report_type, recipients, frequency, send_day, date_range_days, is_enabled)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$name, $reportType, json_encode(array_values($recipients)), $frequency, $sendDay, $dateRangeDays, $enabled]);
    $newId = (int) $db->lastInsertId();
    logAudit(
        'scheduled_report.created',
        $newId,
        'scheduled_report',
        'name=' . $name . '; type=' . $reportType . '; frequency=' . $frequency . '; recipients=' . count($recipients)
    );

    flash('success', "Scheduled report \"{$name}\" created.");
    redirect('/admin/settings/scheduled-reports');
});

$router->get('/admin/settings/scheduled-reports/{id}/edit', function (array $vars) {
    Auth::requirePermission('automations.manage');
    $db     = Database::connect();
    $stmt   = $db->prepare('SELECT * FROM scheduled_reports WHERE id = ?');
    $stmt->execute([(int)$vars['id']]);
    $report = $stmt->fetch();
    if (!$report) { http_response_code(404); echo 'Not found'; exit; }
    render('admin/settings/scheduled-reports-form', compact('report'));
});

$router->post('/admin/settings/scheduled-reports/{id}/edit', function (array $vars) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/scheduled-reports');
    }
    $db             = Database::connect();
    $id             = (int)$vars['id'];
    $name           = trim($_POST['name'] ?? '');
    $allowedTypes   = ['overview','agent_performance','ticket_volume','response_times','sla',
                       'unresolved','lifecycle','location','csat','workload','trends','fcr'];
    $reportType     = in_array($_POST['report_type'] ?? '', $allowedTypes, true)
        ? $_POST['report_type'] : 'overview';
    $frequency      = in_array($_POST['frequency'] ?? '', ['daily','weekly','monthly'], true)
        ? $_POST['frequency'] : 'weekly';
    $sendDay        = $frequency === 'daily' ? null : max(0, min(31, (int)($_POST['send_day'] ?? 1)));
    $dateRangeDays  = max(1, min(365, (int)($_POST['date_range_days'] ?? 30)));
    $rawEmails      = trim($_POST['recipients'] ?? '');
    $recipients     = array_filter(array_map('trim', explode("\n", $rawEmails)));
    $enabled        = isset($_POST['is_enabled']) ? 1 : 0;

    if (empty($name) || empty($recipients)) {
        flash('error', 'Name and at least one recipient are required.');
        redirect("/admin/settings/scheduled-reports/{$id}/edit");
    }

    $priorRpt = $db->prepare('SELECT name, report_type, frequency, is_enabled FROM scheduled_reports WHERE id = ?');
    $priorRpt->execute([$id]);
    $rptPrior = $priorRpt->fetch(\PDO::FETCH_ASSOC) ?: [];

    $db->prepare(
        'UPDATE scheduled_reports SET name=?, report_type=?, recipients=?, frequency=?, send_day=?, date_range_days=?, is_enabled=? WHERE id=?'
    )->execute([$name, $reportType, json_encode(array_values($recipients)), $frequency, $sendDay, $dateRangeDays, $enabled, $id]);

    logAuditChange(
        'scheduled_report.updated',
        $id,
        'scheduled_report',
        $rptPrior,
        ['name' => $name, 'report_type' => $reportType, 'frequency' => $frequency, 'is_enabled' => $enabled]
    );

    flash('success', "Scheduled report \"{$name}\" updated.");
    redirect('/admin/settings/scheduled-reports');
});

$router->post('/admin/settings/scheduled-reports/{id}/delete', function (array $vars) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/scheduled-reports');
    }
    $id   = (int) $vars['id'];
    $db   = Database::connect();
    $nm   = $db->prepare('SELECT name FROM scheduled_reports WHERE id = ?');
    $nm->execute([$id]);
    $rname = (string) ($nm->fetchColumn() ?: '');
    $db->prepare('DELETE FROM scheduled_reports WHERE id = ?')->execute([$id]);
    logAudit('scheduled_report.deleted', $id, 'scheduled_report', 'name=' . $rname);
    flash('success', 'Scheduled report deleted.');
    redirect('/admin/settings/scheduled-reports');
});

$router->post('/admin/settings/scheduled-reports/{id}/toggle', function (array $vars) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/scheduled-reports');
    }
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT is_enabled FROM scheduled_reports WHERE id = ?');
    $stmt->execute([(int)$vars['id']]);
    $row  = $stmt->fetch();
    if (!$row) { redirect('/admin/settings/scheduled-reports'); }
    $newState = $row['is_enabled'] ? 0 : 1;
    $db->prepare('UPDATE scheduled_reports SET is_enabled = ? WHERE id = ?')
       ->execute([$newState, (int)$vars['id']]);
    logAudit(
        'scheduled_report.toggled',
        (int) $vars['id'],
        'scheduled_report',
        'is_enabled=' . $newState
    );
    redirect('/admin/settings/scheduled-reports');
});

/* ==================================================================
 * ADMIN – Form Builder (per ticket type)
 * ================================================================== */

// Builder page — pick a ticket type from the left rail, edit its form.
$router->get('/admin/workflows/ticket-fields', function () {
    Auth::requirePermission('workflows.manage');
    $db = Database::connect();

    $ticketTypes = $db->query('SELECT id, name, color FROM ticket_types ORDER BY sort_order, name')->fetchAll();
    if (!$ticketTypes) {
        render('admin/workflows/ticket-fields', [
            'layout'       => 'app',
            'pageTitle'    => 'Form Builder',
            'ticketTypes'  => [],
            'selectedType' => null,
            'layout_'      => [],
            'unusedFields' => [],
            'fieldOptions' => [],
        ]);
        return;
    }

    // Resolve selected type from ?type=N, default to first
    $selectedTypeId = isset($_GET['type']) && ctype_digit((string) $_GET['type']) ? (int) $_GET['type'] : 0;
    $validIds = array_map(fn($t) => (int) $t['id'], $ticketTypes);
    if (!in_array($selectedTypeId, $validIds, true)) {
        $selectedTypeId = (int) $ticketTypes[0]['id'];
    }
    $selectedType = null;
    foreach ($ticketTypes as $t) {
        if ((int) $t['id'] === $selectedTypeId) { $selectedType = $t; break; }
    }

    // Seed defaults for this type if no layout rows exist yet
    $hasRows = (int) $db->prepare('SELECT COUNT(*) FROM ticket_type_form_layout WHERE type_id = ?')
                        ->execute([$selectedTypeId]);
    $countStmt = $db->prepare('SELECT COUNT(*) FROM ticket_type_form_layout WHERE type_id = ?');
    $countStmt->execute([$selectedTypeId]);
    if ((int) $countStmt->fetchColumn() === 0) {
        seedDefaultLayoutForType($db, $selectedTypeId);
    }

    $layout_ = getFormLayoutForType($db, $selectedTypeId, false);

    // Custom fields not currently in this type's layout — for the "add existing" picker
    $usedFieldIds = array_values(array_map(
        fn($r) => (int) $r['key'],
        array_filter($layout_, fn($r) => $r['kind'] === 'custom')
    ));
    if ($usedFieldIds) {
        $placeholders = implode(',', array_fill(0, count($usedFieldIds), '?'));
        $unusedStmt = $db->prepare(
            "SELECT * FROM ticket_form_fields
             WHERE deleted_at IS NULL AND id NOT IN ($placeholders)
             ORDER BY label"
        );
        $unusedStmt->execute($usedFieldIds);
    } else {
        $unusedStmt = $db->query(
            'SELECT * FROM ticket_form_fields WHERE deleted_at IS NULL ORDER BY label'
        );
    }
    $unusedFields = $unusedStmt->fetchAll();

    // Options for any dropdown/dependent fields in the layout (used by the edit modal)
    $fieldOptions = [];
    foreach ($layout_ as $row) {
        if ($row['kind'] === 'custom' && $row['field']
            && in_array($row['field']['field_type'], ['dropdown', 'dependent'], true)) {
            $optStmt = $db->prepare(
                'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
            );
            $optStmt->execute([$row['field']['id']]);
            $fieldOptions[$row['field']['id']] = $optStmt->fetchAll();
        }
    }

    render('admin/workflows/ticket-fields', [
        'layout'        => 'app',
        'pageTitle'     => 'Form Builder',
        'ticketTypes'   => $ticketTypes,
        'selectedType'  => $selectedType,
        'layout_'       => $layout_,
        'unusedFields'  => $unusedFields,
        'fieldOptions'  => $fieldOptions,
    ]);
});

/* ─── Per-type layout: reorder + visibility + label ─── */

// Reorder all rows for one type. Body: { order: [{kind, key}, ...] }
$router->post('/admin/forms/{typeId}/layout/save', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $typeId = (int) $p['typeId'];
    $body   = json_decode(file_get_contents('php://input'), true);
    $order  = $body['order'] ?? [];
    if (!is_array($order)) { echo json_encode(['success' => false, 'error' => 'Bad input']); exit; }

    $db = Database::connect();
    $stmt = $db->prepare(
        'UPDATE ticket_type_form_layout SET sort_order = ?
         WHERE type_id = ? AND field_kind = ? AND field_key = ?'
    );
    foreach ($order as $i => $item) {
        $kind = $item['kind'] ?? '';
        $key  = (string) ($item['key'] ?? '');
        if (!in_array($kind, ['system', 'custom'], true)) continue;
        $stmt->execute([$i * 10, $typeId, $kind, $key]);
    }
    echo json_encode(['success' => true]);
    exit;
});

// Toggle visibility for a single row. Body: { kind, key, visibility }
$router->post('/admin/forms/{typeId}/layout/visibility', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $typeId = (int) $p['typeId'];
    $body   = json_decode(file_get_contents('php://input'), true);
    $kind   = $body['kind'] ?? '';
    $key    = (string) ($body['key'] ?? '');
    $vis    = $body['visibility'] ?? '';

    if (!in_array($kind, ['system', 'custom'], true)
        || !in_array($vis, ['required', 'optional', 'hidden'], true)) {
        echo json_encode(['success' => false, 'error' => 'Bad input']); exit;
    }
    // Locked system fields can't be hidden or made non-required
    $sysDefaults = systemFieldDefaults();
    if ($kind === 'system' && isset($sysDefaults[$key]) && $sysDefaults[$key]['lockedVisibility'] && $vis !== 'required') {
        echo json_encode(['success' => false, 'error' => 'This field can\'t be made optional or hidden.']); exit;
    }
    $db = Database::connect();
    $db->prepare(
        'UPDATE ticket_type_form_layout SET visibility = ?
         WHERE type_id = ? AND field_kind = ? AND field_key = ?'
    )->execute([$vis, $typeId, $kind, $key]);
    echo json_encode(['success' => true]);
    exit;
});

// Update label override for a single row. Body: { kind, key, label_override }
// Empty string clears the override (falls back to the field's default label).
$router->post('/admin/forms/{typeId}/layout/label', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $typeId = (int) $p['typeId'];
    $body   = json_decode(file_get_contents('php://input'), true);
    $kind   = $body['kind'] ?? '';
    $key    = (string) ($body['key'] ?? '');
    $label  = trim((string) ($body['label_override'] ?? ''));
    if (!in_array($kind, ['system', 'custom'], true)) {
        echo json_encode(['success' => false, 'error' => 'Bad input']); exit;
    }
    if (mb_strlen($label) > 255) {
        echo json_encode(['success' => false, 'error' => 'Label too long (max 255).']); exit;
    }
    $db = Database::connect();
    $db->prepare(
        'UPDATE ticket_type_form_layout SET label_override = ?
         WHERE type_id = ? AND field_kind = ? AND field_key = ?'
    )->execute([$label === '' ? null : $label, $typeId, $kind, $key]);
    echo json_encode(['success' => true]);
    exit;
});

// Add an existing custom field to this type's form. Body: { field_id }
$router->post('/admin/forms/{typeId}/layout/add-existing', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $typeId  = (int) $p['typeId'];
    $body    = json_decode(file_get_contents('php://input'), true);
    $fieldId = (int) ($body['field_id'] ?? 0);
    if (!$fieldId) { echo json_encode(['success' => false, 'error' => 'Bad input']); exit; }

    $db = Database::connect();
    $exists = $db->prepare('SELECT 1 FROM ticket_form_fields WHERE id = ? AND deleted_at IS NULL');
    $exists->execute([$fieldId]);
    if (!$exists->fetchColumn()) { echo json_encode(['success' => false, 'error' => 'Field not found']); exit; }

    $maxOrder = (int) $db->prepare(
        'SELECT COALESCE(MAX(sort_order), 0) FROM ticket_type_form_layout WHERE type_id = ?'
    )->execute([$typeId]);
    // PDOStatement::execute returns bool; use fetchColumn separately
    $maxStmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM ticket_type_form_layout WHERE type_id = ?');
    $maxStmt->execute([$typeId]);
    $maxOrder = (int) $maxStmt->fetchColumn();

    $db->prepare(
        'INSERT IGNORE INTO ticket_type_form_layout
            (type_id, field_kind, field_key, sort_order, visibility)
         VALUES (?, "custom", ?, ?, "optional")'
    )->execute([$typeId, (string) $fieldId, $maxOrder + 10]);
    echo json_encode(['success' => true]);
    exit;
});

// Remove a row from a type's layout (does NOT delete the field definition).
// Body: { kind, key }
$router->post('/admin/forms/{typeId}/layout/remove', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $typeId = (int) $p['typeId'];
    $body   = json_decode(file_get_contents('php://input'), true);
    $kind   = $body['kind'] ?? '';
    $key    = (string) ($body['key'] ?? '');
    if (!in_array($kind, ['system', 'custom'], true)) {
        echo json_encode(['success' => false, 'error' => 'Bad input']); exit;
    }
    // Locked system fields can't be removed
    $sysDefaults = systemFieldDefaults();
    if ($kind === 'system' && isset($sysDefaults[$key]) && $sysDefaults[$key]['lockedVisibility']) {
        echo json_encode(['success' => false, 'error' => 'This field can\'t be removed.']); exit;
    }
    $db = Database::connect();
    $db->prepare(
        'DELETE FROM ticket_type_form_layout
         WHERE type_id = ? AND field_kind = ? AND field_key = ?'
    )->execute([$typeId, $kind, $key]);
    echo json_encode(['success' => true]);
    exit;
});

/* ─── Custom field definitions (global, used by any type) ─── */

// Create a new custom field AND add it to this type's layout.
// Body: { field_type, label, share_with_types?: [int, ...] }
$router->post('/admin/forms/{typeId}/field/create', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $typeId = (int) $p['typeId'];
    $body   = json_decode(file_get_contents('php://input'), true);

    $allowed = ['text','textarea','checkbox','dropdown','date','number','decimal','dependent','text_block','image','cc','date_range'];
    $fieldType = $body['field_type'] ?? '';
    $label     = trim((string) ($body['label'] ?? ''));
    if (!in_array($fieldType, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid field type']); exit;
    }
    if ($label === '') {
        $labelMap = [
            'text' => 'Text Field', 'textarea' => 'Multi-line Text', 'checkbox' => 'Checkbox',
            'dropdown' => 'Dropdown', 'date' => 'Date', 'number' => 'Number', 'decimal' => 'Decimal',
            'dependent' => 'Dependent Field', 'text_block' => 'Text Block', 'image' => 'Image',
            'cc' => 'CC', 'date_range' => 'Date Range',
        ];
        $label = $labelMap[$fieldType];
    }

    $db = Database::connect();
    $db->prepare(
        'INSERT INTO ticket_form_fields (field_type, label) VALUES (?, ?)'
    )->execute([$fieldType, $label]);
    $newId = (int) $db->lastInsertId();

    // Add to this type's layout
    $maxStmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM ticket_type_form_layout WHERE type_id = ?');
    $maxStmt->execute([$typeId]);
    $maxOrder = (int) $maxStmt->fetchColumn();
    $db->prepare(
        'INSERT INTO ticket_type_form_layout
            (type_id, field_kind, field_key, sort_order, visibility)
         VALUES (?, "custom", ?, ?, "optional")'
    )->execute([$typeId, (string) $newId, $maxOrder + 10]);

    // Optionally also add to other types
    $shareWith = array_unique(array_map('intval', (array) ($body['share_with_types'] ?? [])));
    if ($shareWith) {
        $insertOther = $db->prepare(
            'INSERT IGNORE INTO ticket_type_form_layout
                (type_id, field_kind, field_key, sort_order, visibility)
             VALUES (?, "custom", ?, ?, "optional")'
        );
        $maxOther = $db->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) FROM ticket_type_form_layout WHERE type_id = ?'
        );
        foreach ($shareWith as $otherId) {
            if ($otherId === $typeId || $otherId <= 0) continue;
            $maxOther->execute([$otherId]);
            $maxOO = (int) $maxOther->fetchColumn();
            $insertOther->execute([$otherId, (string) $newId, $maxOO + 10]);
        }
    }

    logAudit(
        'form_field.created',
        $newId,
        'form_field',
        'type=' . $fieldType . '; label=' . $label . '; added_to_type_id=' . $typeId . '; shared_with=' . count($shareWith)
    );

    $row = $db->prepare('SELECT * FROM ticket_form_fields WHERE id = ?');
    $row->execute([$newId]);
    echo json_encode(['success' => true, 'field' => $row->fetch()]);
    exit;
});

// Update field definition + options + share-with-types.
// Body: { label, placeholder?, config?, options?, share_with_types?: [int, ...] }
$router->post('/admin/forms/field/{id}/update', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $id   = (int) $p['id'];
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) { echo json_encode(['success' => false, 'error' => 'Bad input']); exit; }

    $label       = trim((string) ($body['label'] ?? ''));
    $placeholder = trim((string) ($body['placeholder'] ?? ''));
    if ($label === '') { echo json_encode(['success' => false, 'error' => 'Label is required']); exit; }

    $db = Database::connect();
    $check = $db->prepare('SELECT field_type FROM ticket_form_fields WHERE id = ? AND deleted_at IS NULL');
    $check->execute([$id]);
    $fieldType = $check->fetchColumn();
    if (!$fieldType) { echo json_encode(['success' => false, 'error' => 'Field not found']); exit; }

    if ($fieldType === 'image' && !isset($body['config'])) {
        $db->prepare(
            'UPDATE ticket_form_fields SET label = ?, placeholder = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$label, $placeholder ?: null, $id]);
    } else {
        $config = isset($body['config']) ? json_encode($body['config']) : null;
        $db->prepare(
            'UPDATE ticket_form_fields SET label = ?, placeholder = ?, config = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$label, $placeholder ?: null, $config, $id]);
    }

    // Replace options for dropdown / dependent fields
    if (in_array($fieldType, ['dropdown', 'dependent'], true) && isset($body['options'])) {
        $db->prepare('DELETE FROM ticket_form_field_options WHERE field_id = ?')->execute([$id]);
        $insertOpt = $db->prepare(
            'INSERT INTO ticket_form_field_options (field_id, parent_option_id, label, sort_order) VALUES (?, ?, ?, ?)'
        );
        if ($fieldType === 'dropdown') {
            foreach ($body['options'] as $i => $opt) {
                $optLabel = trim($opt['label'] ?? '');
                if ($optLabel !== '') $insertOpt->execute([$id, null, $optLabel, $i]);
            }
        } else {
            $sort1 = 0;
            foreach ($body['options'] as $l1) {
                $l1Label = trim($l1['label'] ?? '');
                if ($l1Label === '') continue;
                $insertOpt->execute([$id, null, $l1Label, $sort1++]);
                $l1Id = (int) $db->lastInsertId();
                $sort2 = 0;
                foreach ($l1['children'] ?? [] as $l2) {
                    $l2Label = trim($l2['label'] ?? '');
                    if ($l2Label === '') continue;
                    $insertOpt->execute([$id, $l1Id, $l2Label, $sort2++]);
                    $l2Id = (int) $db->lastInsertId();
                    $sort3 = 0;
                    foreach ($l2['children'] ?? [] as $l3) {
                        $l3Label = trim($l3['label'] ?? '');
                        if ($l3Label === '') continue;
                        $insertOpt->execute([$id, $l2Id, $l3Label, $sort3++]);
                    }
                }
            }
        }
    }

    // Share/unshare with ticket types: sets the field's layout rows to exactly
    // the supplied set. Sort order is preserved for types it already appeared on.
    if (array_key_exists('share_with_types', $body)) {
        $newTypeIds = array_unique(array_map('intval', (array) $body['share_with_types']));

        $curStmt = $db->prepare(
            'SELECT type_id FROM ticket_type_form_layout WHERE field_kind = "custom" AND field_key = ?'
        );
        $curStmt->execute([(string) $id]);
        $curTypeIds = array_map('intval', $curStmt->fetchAll(PDO::FETCH_COLUMN));

        $toAdd    = array_diff($newTypeIds, $curTypeIds);
        $toRemove = array_diff($curTypeIds, $newTypeIds);

        if ($toRemove) {
            $ph = implode(',', array_fill(0, count($toRemove), '?'));
            $args = array_merge([(string) $id], array_values($toRemove));
            $db->prepare(
                "DELETE FROM ticket_type_form_layout
                 WHERE field_kind = 'custom' AND field_key = ? AND type_id IN ($ph)"
            )->execute($args);
        }
        if ($toAdd) {
            $maxStmt = $db->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM ticket_type_form_layout WHERE type_id = ?'
            );
            $insertNew = $db->prepare(
                'INSERT IGNORE INTO ticket_type_form_layout
                    (type_id, field_kind, field_key, sort_order, visibility)
                 VALUES (?, "custom", ?, ?, "optional")'
            );
            foreach ($toAdd as $tid) {
                $maxStmt->execute([$tid]);
                $insertNew->execute([$tid, (string) $id, ((int) $maxStmt->fetchColumn()) + 10]);
            }
        }
    }

    logAudit('form_field.updated', $id, 'form_field', 'label=' . $label);

    echo json_encode(['success' => true]);
    exit;
});

// Soft-delete a custom field (FK CASCADE removes its layout rows everywhere).
$router->post('/admin/forms/field/{id}/delete', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $id = (int) $p['id'];
    $db = Database::connect();
    $lblStmt = $db->prepare('SELECT label, field_type FROM ticket_form_fields WHERE id = ?');
    $lblStmt->execute([$id]);
    $fInfo = $lblStmt->fetch(\PDO::FETCH_ASSOC) ?: ['label' => '', 'field_type' => ''];
    $db->prepare(
        'UPDATE ticket_form_fields SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
    )->execute([$id]);
    // Layout rows reference field_key as VARCHAR — no FK CASCADE — clean up by hand
    $db->prepare(
        'DELETE FROM ticket_type_form_layout WHERE field_kind = "custom" AND field_key = ?'
    )->execute([(string) $id]);
    logAudit(
        'form_field.deleted',
        $id,
        'form_field',
        'label=' . $fInfo['label'] . '; type=' . $fInfo['field_type']
    );
    echo json_encode(['success' => true]);
    exit;
});

// Get the full config + option tree for a custom field (used by the edit modal).
$router->get('/admin/forms/field/{id}/details', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $id = (int) $p['id'];
    $db = Database::connect();

    $fStmt = $db->prepare('SELECT * FROM ticket_form_fields WHERE id = ? AND deleted_at IS NULL');
    $fStmt->execute([$id]);
    $field = $fStmt->fetch();
    if (!$field) { echo json_encode(['error' => 'Not found']); exit; }

    $oStmt = $db->prepare(
        'SELECT * FROM ticket_form_field_options WHERE field_id = ? ORDER BY parent_option_id, sort_order'
    );
    $oStmt->execute([$id]);
    $options = $oStmt->fetchAll();

    $tStmt = $db->prepare(
        'SELECT type_id FROM ticket_type_form_layout WHERE field_kind = "custom" AND field_key = ?'
    );
    $tStmt->execute([(string) $id]);
    $typeIds = array_map('intval', $tStmt->fetchAll(PDO::FETCH_COLUMN));

    echo json_encode([
        'field'    => $field,
        'options'  => $options,
        'type_ids' => $typeIds,
    ]);
    exit;
});

// Image upload (unchanged structure, new URL)
$router->post('/admin/forms/field/{id}/upload-image', function (array $p) {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $id = (int) $p['id'];
    $db = Database::connect();

    $check = $db->prepare('SELECT field_type FROM ticket_form_fields WHERE id = ? AND deleted_at IS NULL');
    $check->execute([$id]);
    $fieldType = $check->fetchColumn();
    if ($fieldType !== 'image') {
        echo json_encode(['success' => false, 'error' => 'Field is not an image type.']); exit;
    }
    $file = $_FILES['image'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded.']); exit;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Only JPEG, PNG, GIF, and WebP images are allowed.']); exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Image must be under 5 MB.']); exit;
    }
    $uploadDir = ROOT_DIR . '/public/uploads/field-images/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
    $filename = 'field_' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $uploadDir . $filename;

    $cfgRow = $db->prepare('SELECT config FROM ticket_form_fields WHERE id = ?');
    $cfgRow->execute([$id]);
    $oldCfg = json_decode($cfgRow->fetchColumn() ?? '{}', true);
    if (!empty($oldCfg['image_path'])) {
        $oldFile = ROOT_DIR . '/public/uploads/field-images/' . basename($oldCfg['image_path']);
        if (file_exists($oldFile)) unlink($oldFile);
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save image.']); exit;
    }
    $newCfg = array_merge($oldCfg ?? [], ['image_path' => $filename]);
    $db->prepare('UPDATE ticket_form_fields SET config = ?, updated_at = NOW() WHERE id = ?')
       ->execute([json_encode($newCfg), $id]);
    echo json_encode(['success' => true, 'image_path' => $filename]);
    exit;
});

// Update a system-field default label (the sys_field_label_* setting).
// Body: { field, label }
$router->post('/admin/forms/system-label', function () {
    Auth::requirePermission('workflows.manage');
    header('Content-Type: application/json');
    $body  = json_decode(file_get_contents('php://input'), true);
    $field = (string) ($body['field'] ?? '');
    $label = trim((string) ($body['label'] ?? ''));
    $allowed = ['subject', 'description', 'ticket_type', 'location', 'priority', 'tags', 'attachments'];
    if (!in_array($field, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid field']); exit;
    }
    if ($label === '' || mb_strlen($label) > 80) {
        echo json_encode(['success' => false, 'error' => 'Label is required (max 80 chars)']); exit;
    }
    setSetting("sys_field_label_{$field}", $label);
    echo json_encode(['success' => true]);
    exit;
});

/* ==================================================================
 * Backup
 * ================================================================== */

$router->get('/admin/settings/backup', function () {
    Auth::requirePermission('import.manage');
    $backupDir = ROOT_DIR . '/storage/backups/';
    @mkdir($backupDir, 0755, true);

    $backups = [];
    foreach (glob($backupDir . 'localdesk_backup_*.zip') ?: [] as $file) {
        $backups[] = [
            'name'    => basename($file),
            'size'    => filesize($file),
            'created' => filemtime($file),
        ];
    }
    usort($backups, fn ($a, $b) => $b['created'] - $a['created']);

    render('admin/settings/backup', [
        'backups'      => $backups,
        'zipAvailable' => class_exists('ZipArchive'),
    ]);
});

$router->post('/admin/settings/backup/create', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/backup');
    }

    if (!class_exists('ZipArchive')) {
        flash('error', 'ZipArchive PHP extension is not available on this server.');
        redirect('/admin/settings/backup');
    }

    set_time_limit(1800);
    @ini_set('memory_limit', '512M');

    $db        = Database::connect();
    $backupDir = ROOT_DIR . '/storage/backups/';
    @mkdir($backupDir, 0755, true);

    $filename = 'localdesk_backup_' . date('Ymd_His') . '.zip';
    $zipPath  = $backupDir . $filename;

    try {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create zip file. Check storage/backups/ is writable.');
        }

        // --- SQL dump ---
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $sql  = "-- OpenHelpDesk Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Tables: " . count($tables) . "\n";
        $sql .= "-- ------------------------------------------------\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

        foreach ($tables as $table) {
            $sql .= "-- ---- `$table` ----\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sql .= $create[1] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols = implode(', ', array_map(fn ($c) => "`$c`", array_keys($rows[0])));
                foreach (array_chunk($rows, 500) as $chunk) {
                    $vals = [];
                    foreach ($chunk as $row) {
                        $rowVals = array_map(
                            fn ($v) => $v === null ? 'NULL' : $db->quote((string) $v),
                            $row
                        );
                        $vals[] = '(' . implode(', ', $rowVals) . ')';
                    }
                    $sql .= "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $vals) . ";\n";
                }
                $sql .= "\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $zip->addFromString('database.sql', $sql);

        // --- Full website snapshot ---
        // Walks the entire application directory and adds every file under a
        // "website/" prefix inside the zip. The storage/backups/ folder is the
        // one mandatory exclusion — without it the in-progress zip would try
        // to archive itself (and every prior backup).
        $websiteRoot    = rtrim(str_replace('\\', '/', ROOT_DIR), '/');
        $backupAbsolute = rtrim(str_replace('\\', '/', realpath($backupDir) ?: $backupDir), '/');

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(ROOT_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $entry) {
            $path = str_replace('\\', '/', $entry->getPathname());
            if (strpos($path, $backupAbsolute) === 0) {
                continue;
            }
            $rel = 'website/' . substr($path, strlen($websiteRoot) + 1);
            if ($entry->isDir()) {
                $zip->addEmptyDir($rel);
            } elseif ($entry->isFile() && is_readable($entry->getPathname())) {
                $zip->addFile($entry->getPathname(), $rel);
            }
        }

        $zip->close();
        logAudit(
            'backup.created',
            null,
            'backup',
            'file=' . $filename . '; size=' . (file_exists($zipPath) ? filesize($zipPath) : 0)
        );
        flash('success', "Backup created: $filename");
    } catch (Exception $e) {
        @unlink($zipPath);
        flash('error', 'Backup failed: ' . $e->getMessage());
    }

    redirect('/admin/settings/backup');
});

$router->get('/admin/settings/backup/download', function () {
    Auth::requirePermission('import.manage');
    $filename = $_GET['file'] ?? '';
    if (!preg_match('/^localdesk_backup_\d{8}_\d{6}\.zip$/', $filename)) {
        flash('error', 'Invalid backup filename.');
        redirect('/admin/settings/backup');
    }
    $path = ROOT_DIR . '/storage/backups/' . $filename;
    if (!file_exists($path)) {
        flash('error', 'Backup file not found.');
        redirect('/admin/settings/backup');
    }
    // Clear any buffered output before streaming the file
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

$router->post('/admin/settings/backup/delete', function () {
    Auth::requirePermission('import.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/backup');
    }
    $filename = $_POST['filename'] ?? '';
    if (!preg_match('/^localdesk_backup_\d{8}_\d{6}\.zip$/', $filename)) {
        flash('error', 'Invalid backup filename.');
        redirect('/admin/settings/backup');
    }
    $path = ROOT_DIR . '/storage/backups/' . $filename;
    if (file_exists($path)) {
        unlink($path);
        logAudit('backup.deleted', null, 'backup', 'file=' . $filename);
        flash('success', 'Backup deleted.');
    }
    redirect('/admin/settings/backup');
});

/* ==================================================================
 * Audit Log
 * ================================================================== */

/*
 * GET /admin/audit-log
 *
 * The viewer unifies two data sources so admins have one place to ask "who did
 * what":
 *   1. `audit_log` — every callsite that calls `logAudit()` (auth events,
 *      settings changes, admin config CRUD, etc.)
 *   2. `ticket_timeline` — the existing per-ticket history, filtered to a
 *      curated allowlist of state-machine events and prefixed with `ticket.`
 *      in the unified action column. Comments, notes, and bot/automation rows
 *      are excluded so the audit log doesn't drown in routine reply traffic.
 *
 * The two sources are not mirrored at write-time (no double-writes); they're
 * UNION-merged at read-time so each ticket-scoped event lives in exactly one
 * place and the audit-log viewer simply quotes both.
 */
const _AUDIT_LOG_TIMELINE_ALLOWLIST = [
    'created',
    'status_changed',
    'priority_changed',
    'assigned',
    'group_changed',
    'type_changed',
    'merged',
    'escalated',
];

$router->get('/admin/audit-log', function () {
    Auth::requirePermission('audit.view');
    $db = Database::connect();

    // Filters
    $filterUser   = isset($_GET['user_id'])  ? (int) $_GET['user_id']  : null;
    $filterAction = trim($_GET['action']     ?? '');
    $filterFrom   = trim($_GET['from']       ?? '');
    $filterTo     = trim($_GET['to']         ?? '');
    $filterSource = in_array($_GET['source'] ?? '', ['audit', 'history'], true) ? $_GET['source'] : '';
    $page         = max(1, (int) ($_GET['page'] ?? 1));
    $perPage      = 50;
    $offset       = ($page - 1) * $perPage;

    // Decide which sources to include based on the explicit source filter AND
    // (when an action filter is set) whether the action name belongs to that
    // source. Action names from ticket_timeline are surfaced with a `ticket.`
    // prefix so we can route the filter without ambiguity.
    $includeAudit   = $filterSource !== 'history';
    $includeHistory = $filterSource !== 'audit';

    if ($filterAction !== '') {
        if (strpos($filterAction, 'ticket.') === 0) {
            $includeAudit = false;
        } else {
            $includeHistory = false;
        }
    }

    // Build a sub-query for each source we're including. Each yields the same
    // column shape so they can be UNIONed and paged as one stream.
    $parts        = [];
    $params       = [];
    $allowlistSQL = "'" . implode("','", _AUDIT_LOG_TIMELINE_ALLOWLIST) . "'";

    if ($includeAudit) {
        $w = [];
        if ($filterUser)         { $w[] = 'user_id = ?';     $params[] = $filterUser; }
        if ($filterAction !== ''){
            // Expand the canonical action to include any legacy aliases so old
            // rows written before the Phase-4 rename still match the filter.
            $legacy = auditLegacyAliasesFor($filterAction);
            $names  = array_merge([$filterAction], $legacy);
            $ph     = implode(',', array_fill(0, count($names), '?'));
            $w[]    = "action IN ({$ph})";
            foreach ($names as $n) { $params[] = $n; }
        }
        if ($filterFrom !== '')  { $w[] = 'created_at >= ?'; $params[] = $filterFrom . ' 00:00:00'; }
        if ($filterTo   !== '')  { $w[] = 'created_at <= ?'; $params[] = $filterTo   . ' 23:59:59'; }
        $wsql    = $w ? ('WHERE ' . implode(' AND ', $w)) : '';
        $parts[] = "SELECT created_at, user_id, action, target_type, target_id, detail, ip_address,
                           'audit' AS source
                    FROM audit_log {$wsql}";
    }

    if ($includeHistory) {
        // Allowlist + is_internal=0 keep comments/notes/automation chatter out
        // of the audit viewer; the per-ticket page still shows everything.
        $w = ['is_internal = 0', "action IN ({$allowlistSQL})"];
        if ($filterUser)         { $w[] = 'user_id = ?';     $params[] = $filterUser; }
        if ($filterAction !== '' && strpos($filterAction, 'ticket.') === 0) {
            $w[] = 'action = ?';
            $params[] = substr($filterAction, 7);
        }
        if ($filterFrom !== '')  { $w[] = 'created_at >= ?'; $params[] = $filterFrom . ' 00:00:00'; }
        if ($filterTo   !== '')  { $w[] = 'created_at <= ?'; $params[] = $filterTo   . ' 23:59:59'; }
        $wsql    = 'WHERE ' . implode(' AND ', $w);
        $parts[] = "SELECT created_at, user_id, CONCAT('ticket.', action) AS action,
                           'ticket' AS target_type, ticket_id AS target_id,
                           details AS detail, NULL AS ip_address,
                           'ticket_history' AS source
                    FROM ticket_timeline {$wsql}";
    }

    // No source selected (filter contradicts itself, e.g. action=user.create
    // with source=history) — short-circuit to an empty result rather than
    // trying to render a syntactically invalid UNION.
    if (empty($parts)) {
        $total      = 0;
        $totalPages = 0;
        $entries    = [];
    } else {
        $unionSQL = '(' . implode(') UNION ALL (', $parts) . ')';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM ({$unionSQL}) AS combined");
        $countStmt->execute($params);
        $total      = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($total / $perPage);

        $rowsStmt = $db->prepare(
            "SELECT combined.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS actor_name
             FROM ({$unionSQL}) AS combined
             LEFT JOIN users u ON combined.user_id = u.id
             ORDER BY combined.created_at DESC, combined.source DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $rowsStmt->execute($params);
        $entries = $rowsStmt->fetchAll();
    }

    // Actor list — union both sources so an agent who only ever writes to
    // ticket_timeline still appears in the filter dropdown.
    $actorList = $db->query(
        "SELECT DISTINCT user_id, name FROM (
            SELECT al.user_id AS user_id,
                   CONCAT(u.first_name, ' ', u.last_name) AS name
            FROM audit_log al JOIN users u ON al.user_id = u.id
            UNION
            SELECT tt.user_id AS user_id,
                   CONCAT(u.first_name, ' ', u.last_name) AS name
            FROM ticket_timeline tt JOIN users u ON tt.user_id = u.id
            WHERE tt.user_id IS NOT NULL AND tt.is_internal = 0
              AND tt.action IN ({$allowlistSQL})
        ) AS combined_actors
        ORDER BY name"
    )->fetchAll();

    // Action list — audit_log actions verbatim, plus the curated timeline
    // actions with a `ticket.` prefix so the dropdown round-trips into the
    // source-routing logic above. Legacy names are canonicalized so the
    // dropdown shows one entry per logical action even when the DB still
    // contains a mix of pre- and post-rename rows.
    $auditActions = array_map(
        'auditCanonicalAction',
        $db->query('SELECT DISTINCT action FROM audit_log')->fetchAll(\PDO::FETCH_COLUMN)
    );
    $historyActions = $db->query(
        "SELECT DISTINCT CONCAT('ticket.', action) AS action
         FROM ticket_timeline
         WHERE is_internal = 0 AND action IN ({$allowlistSQL})
         ORDER BY action"
    )->fetchAll(\PDO::FETCH_COLUMN);
    $actionList = array_values(array_unique(array_merge($auditActions, $historyActions)));
    sort($actionList);

    // Canonicalize each row's action so legacy entries display under the
    // same name as freshly-written ones (so badge colors and filter chips
    // line up regardless of when the row was written).
    foreach ($entries as &$e) {
        $e['action'] = auditCanonicalAction((string) $e['action']);
    }
    unset($e);

    // Oldest entry timestamp drives the date-picker minimum on the prune form
    // so admins can't pick a cutoff that would delete zero rows. (Prune still
    // only touches audit_log — ticket_timeline owns its own retention.)
    $oldest = $db->query('SELECT MIN(created_at) FROM audit_log')->fetchColumn() ?: null;

    render('admin/audit-log/index', [
        'entries'      => $entries,
        'actorList'    => $actorList,
        'actionList'   => $actionList,
        'filterUser'   => $filterUser,
        'filterAction' => $filterAction,
        'filterFrom'   => $filterFrom,
        'filterTo'     => $filterTo,
        'filterSource' => $filterSource,
        'total'        => $total,
        'page'         => $page,
        'totalPages'   => $totalPages,
        'perPage'      => $perPage,
        'oldestEntry'  => $oldest,
    ]);
});

/*
 * POST /admin/audit-log/prune
 *
 * Bulk-delete audit_log rows whose created_at is strictly less than the supplied
 * YYYY-MM-DD cutoff (i.e. anything from before that date is removed; the cutoff
 * day itself is preserved). Records its own prune as a new audit_log row so the
 * action of pruning is itself permanently visible.
 */
$router->post('/admin/audit-log/prune', function () {
    Auth::requirePermission('audit.view');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/audit-log');
    }

    $cutoff = trim($_POST['before_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff)) {
        flash('error', 'Please pick a valid cutoff date (YYYY-MM-DD).');
        redirect('/admin/audit-log');
    }

    $db    = Database::connect();
    $stmt  = $db->prepare('DELETE FROM audit_log WHERE created_at < ?');
    $stmt->execute([$cutoff . ' 00:00:00']);
    $count = $stmt->rowCount();

    logAudit(
        'audit_log.pruned',
        null,
        'audit_log',
        'cutoff=' . $cutoff . '; deleted=' . $count
    );

    if ($count === 0) {
        flash('info', 'No audit entries were older than ' . $cutoff . '.');
    } else {
        flash('success', 'Pruned ' . number_format($count) . ' audit entr' . ($count === 1 ? 'y' : 'ies') . ' older than ' . $cutoff . '.');
    }
    redirect('/admin/audit-log');
});

/* ==================================================================
 * Avatar upload helper
 * ================================================================== */

function handleAvatarUpload(): ?string
{
    if (empty($_FILES['avatar']['tmp_name']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mime = mime_content_type($_FILES['avatar']['tmp_name']);
    if (!in_array($mime, $allowed, true) || $_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        flash('error', 'Avatar must be a JPG/PNG/GIF/WEBP image under 2 MB.');
        return null;
    }
    $ext       = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $filename  = uniqid('avatar_', true) . '.' . strtolower($ext);
    $uploadDir = ROOT_DIR . '/public/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename);
    return $filename;
}

/* ==================================================================
 * ADMIN — Escalation Paths (manual, per-ticket-type chain)
 * ================================================================== */

$router->get('/admin/settings/escalation-paths', function () {
    Auth::requirePermission('automations.manage');
    $db = Database::connect();

    $types = $db->query(
        'SELECT t.id, t.name, t.color,
                (SELECT COUNT(*) FROM ticket_escalation_steps s WHERE s.ticket_type_id = t.id) AS step_count
         FROM ticket_types t
         ORDER BY t.sort_order, t.name'
    )->fetchAll();

    render('admin/settings/escalation-paths/index', ['types' => $types]);
});

$router->get('/admin/settings/escalation-paths/{typeId}', function (array $p) {
    Auth::requirePermission('automations.manage');
    $typeId = (int) $p['typeId'];
    $db = Database::connect();

    $tStmt = $db->prepare('SELECT * FROM ticket_types WHERE id = ?');
    $tStmt->execute([$typeId]);
    $type = $tStmt->fetch();
    if (!$type) {
        flash('error', 'Ticket type not found.');
        redirect('/admin/settings/escalation-paths');
    }

    $sStmt = $db->prepare(
        "SELECT s.id, s.step_order, s.user_id, s.label,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                u.email AS user_email, u.role AS user_role
         FROM ticket_escalation_steps s
         JOIN users u ON s.user_id = u.id
         WHERE s.ticket_type_id = ?
         ORDER BY s.step_order ASC"
    );
    $sStmt->execute([$typeId]);
    $steps = $sStmt->fetchAll();

    $agents = $db->query(
        "SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, role
         FROM users
         WHERE " . staffRoleSqlIn('role') . "
         ORDER BY first_name, last_name"
    )->fetchAll();

    render('admin/settings/escalation-paths/form', [
        'type'   => $type,
        'steps'  => $steps,
        'agents' => $agents,
    ]);
});

$router->post('/admin/settings/escalation-paths/{typeId}', function (array $p) {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/escalation-paths');
    }
    $typeId = (int) $p['typeId'];
    $db = Database::connect();

    $tStmt = $db->prepare('SELECT id FROM ticket_types WHERE id = ?');
    $tStmt->execute([$typeId]);
    if (!$tStmt->fetch()) {
        flash('error', 'Ticket type not found.');
        redirect('/admin/settings/escalation-paths');
    }

    $rawUsers  = $_POST['user_id'] ?? [];
    $rawLabels = $_POST['label']   ?? [];
    if (!is_array($rawUsers)) { $rawUsers  = []; }
    if (!is_array($rawLabels)) { $rawLabels = []; }

    // Deduplicate: preserve the first occurrence of each user (so re-orders that
    // accidentally add the same agent twice don't break the UNIQUE key).
    $seen  = [];
    $steps = [];
    foreach ($rawUsers as $i => $uid) {
        $uid = (int) $uid;
        if ($uid <= 0 || isset($seen[$uid])) { continue; }
        $seen[$uid] = true;
        $label = trim((string) ($rawLabels[$i] ?? ''));
        if (mb_strlen($label) > 100) { $label = mb_substr($label, 0, 100); }
        $steps[] = ['user_id' => $uid, 'label' => $label === '' ? null : $label];
    }

    // Validate every selected user is an agent-tier role.
    if (!empty($steps)) {
        $ids = array_map(fn($s) => $s['user_id'], $steps);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $cStmt = $db->prepare(
            "SELECT id, role, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE id IN ($ph)"
        );
        $cStmt->execute($ids);
        $byId = [];
        foreach ($cStmt->fetchAll() as $u) { $byId[(int) $u['id']] = $u; }
        foreach ($steps as $s) {
            $u = $byId[$s['user_id']] ?? null;
            if (!$u) {
                flash('error', 'One of the selected users was not found.');
                redirect('/admin/settings/escalation-paths/' . $typeId);
            }
            if (!roleIsStaff($u['role'])) {
                flash('error', $u['name'] . ' is not a staff member. Only staff roles can be escalation targets. Change their role first in User Management.');
                redirect('/admin/settings/escalation-paths/' . $typeId);
            }
        }
    }

    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM ticket_escalation_steps WHERE ticket_type_id = ?')->execute([$typeId]);
        $ins = $db->prepare(
            'INSERT INTO ticket_escalation_steps (ticket_type_id, step_order, user_id, label) VALUES (?, ?, ?, ?)'
        );
        $order = 1;
        foreach ($steps as $s) {
            $ins->execute([$typeId, $order, $s['user_id'], $s['label']]);
            $order++;
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        flash('error', 'Failed to save path: ' . $e->getMessage());
        redirect('/admin/settings/escalation-paths/' . $typeId);
    }

    logAudit('escalation_path.saved', $typeId, 'ticket_type', 'Steps: ' . count($steps));
    flash('success', 'Escalation path saved (' . count($steps) . ' step' . (count($steps) === 1 ? '' : 's') . ').');
    redirect('/admin/settings/escalation-paths/' . $typeId);
});

/* ==================================================================
 * ADMIN – Stale Ticket Notifications
 * ================================================================== */

$router->get('/admin/settings/stale-tickets', function () {
    Auth::requirePermission('automations.manage');
    $db = Database::connect();

    $settings = [
        'stale_threshold_hours'              => getSetting('stale_threshold_hours', '72'),
        'stale_recheck_hours'                => getSetting('stale_recheck_hours', '24'),
        'email_notify:ticket_stale_agent'     => getSetting('email_notify:ticket_stale_agent', '1'),
        'email_notify:ticket_stale_requester' => getSetting('email_notify:ticket_stale_requester', '1'),
    ];

    $types = $db->query(
        'SELECT id, name, color, stale_threshold_hours FROM ticket_types ORDER BY sort_order, name'
    )->fetchAll();

    render('admin/settings/stale-tickets', ['settings' => $settings, 'types' => $types]);
});

$router->post('/admin/settings/stale-tickets', function () {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/stale-tickets');
    }

    $threshold = max(0, (int) ($_POST['stale_threshold_hours'] ?? 72));
    $recheck   = max(1, (int) ($_POST['stale_recheck_hours']   ?? 24));

    $before = [
        'stale_threshold_hours'              => getSetting('stale_threshold_hours', '72'),
        'stale_recheck_hours'                => getSetting('stale_recheck_hours', '24'),
        'email_notify:ticket_stale_agent'    => getSetting('email_notify:ticket_stale_agent', '1'),
        'email_notify:ticket_stale_requester' => getSetting('email_notify:ticket_stale_requester', '1'),
    ];

    setSetting('stale_threshold_hours', (string) $threshold);
    setSetting('stale_recheck_hours',   (string) $recheck);
    setSetting('email_notify:ticket_stale_agent',     isset($_POST['notify_agent'])     ? '1' : '0');
    setSetting('email_notify:ticket_stale_requester', isset($_POST['notify_requester']) ? '1' : '0');

    logAuditChange(
        'stale_tickets.settings_changed',
        null,
        null,
        $before,
        [
            'stale_threshold_hours'              => (string) $threshold,
            'stale_recheck_hours'                => (string) $recheck,
            'email_notify:ticket_stale_agent'    => isset($_POST['notify_agent'])     ? '1' : '0',
            'email_notify:ticket_stale_requester' => isset($_POST['notify_requester']) ? '1' : '0',
        ]
    );

    flash('success', 'Stale ticket settings saved.');
    redirect('/admin/settings/stale-tickets');
});

$router->post('/admin/settings/stale-tickets/run-now', function () {
    Auth::requirePermission('automations.manage');
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/settings/stale-tickets');
    }
    $script = ROOT_DIR . '/scripts/process-stale-tickets.php';
    $cmd    = escapeshellarg(phpBinary()) . ' ' . escapeshellarg($script) . ' 2>&1';
    $outputLines = [];
    $returnCode  = 0;
    exec($cmd, $outputLines, $returnCode);
    $_SESSION['_stale_run'] = [
        'lines' => $outputLines,
        'code'  => $returnCode,
        'time'  => date('Y-m-d H:i:s'),
    ];
    redirect('/admin/settings/stale-tickets');
});

/* ==================================================================
 * ADMIN – Permission Levels (custom roles + granular permissions)
 *
 * Admins can create their own staff permission levels and toggle which
 * capabilities each grants (the matrix maps to the same perm keys the
 * route gates check via Auth::requirePermission()). The four built-in
 * roles are protected from deletion; the admin role's matrix is locked
 * (it bypasses every check). Managing roles is admin-only and is NOT a
 * grantable permission — otherwise a role could escalate its own
 * privileges. Custom roles are staff-side (is_staff=1, lands on /agent).
 * ================================================================== */

/** Validate + persist the posted permission grants for a role. */
function _roleSaveGrants(PDO $db, int $roleId, array $postedKeys): void
{
    $valid = array_values(array_intersect($postedKeys, rolePermissionKeys()));
    $db->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
    if ($valid) {
        $ins = $db->prepare('INSERT IGNORE INTO role_permissions (role_id, perm_key) VALUES (?, ?)');
        foreach ($valid as $key) {
            $ins->execute([$roleId, $key]);
        }
    }
}

$router->get('/admin/roles', function () {
    Auth::requireAdmin();
    $roles = Database::connect()->query(
        "SELECT r.*,
                (SELECT COUNT(*) FROM users u            WHERE u.role    = r.slug) AS user_count,
                (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id)  AS perm_count
         FROM roles r ORDER BY r.sort_order, r.id"
    )->fetchAll();
    render('admin/roles/index', ['roles' => $roles]);
});

$router->get('/admin/roles/create', function () {
    Auth::requireAdmin();
    render('admin/roles/form', [
        'editing'     => null,
        'permissions' => rolePermissionCatalog(),
        'grantedKeys' => [],
    ]);
});

$router->post('/admin/roles/create', function () {
    Auth::requireAdmin();
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/roles/create');
    }

    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '' || mb_strlen($name) > 64) {
        flashInput($_POST);
        flash('error', 'A display name (1–64 characters) is required.');
        redirect('/admin/roles/create');
    }

    // Derive a stable slug from the name; this is the value stored in
    // users.role and used everywhere, so it is scrubbed to [a-z0-9_].
    $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name)), '_');
    if ($slug === '' || strlen($slug) > 64) {
        flashInput($_POST);
        flash('error', 'Please use a name with at least one letter or number.');
        redirect('/admin/roles/create');
    }

    $db  = Database::connect();
    $chk = $db->prepare('SELECT 1 FROM roles WHERE slug = ?');
    $chk->execute([$slug]);
    if ($chk->fetchColumn()) {
        flashInput($_POST);
        flash('error', 'A permission level with a similar name already exists.');
        redirect('/admin/roles/create');
    }

    $maxOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) FROM roles')->fetchColumn();
    $db->prepare(
        "INSERT INTO roles (slug, name, description, is_system, is_admin, is_staff, landing, sort_order)
         VALUES (?, ?, ?, 0, 0, 1, 'agent', ?)"
    )->execute([$slug, $name, $desc, $maxOrder + 10]);
    $roleId = (int) $db->lastInsertId();

    $posted = isset($_POST['perms']) && is_array($_POST['perms']) ? $_POST['perms'] : [];
    _roleSaveGrants($db, $roleId, $posted);

    logAudit('role.created', $roleId, 'role', 'slug=' . $slug . '; perms=' . count($posted));
    flash('success', 'Permission level “' . $name . '” created.');
    redirect('/admin/roles');
});

$router->get('/admin/roles/{id}/edit', function (array $p) {
    Auth::requireAdmin();
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([(int) $p['id']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Permission level not found.');
        redirect('/admin/roles');
    }
    $g = $db->prepare('SELECT perm_key FROM role_permissions WHERE role_id = ?');
    $g->execute([$editing['id']]);
    render('admin/roles/form', [
        'editing'     => $editing,
        'permissions' => rolePermissionCatalog(),
        'grantedKeys' => $g->fetchAll(PDO::FETCH_COLUMN),
    ]);
});

$router->post('/admin/roles/{id}/edit', function (array $p) {
    Auth::requireAdmin();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/roles/' . $id . '/edit');
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([$id]);
    $role = $stmt->fetch();
    if (!$role) {
        flash('error', 'Permission level not found.');
        redirect('/admin/roles');
    }

    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '' || mb_strlen($name) > 64) {
        flashInput($_POST);
        flash('error', 'A display name (1–64 characters) is required.');
        redirect('/admin/roles/' . $id . '/edit');
    }

    $db->prepare('UPDATE roles SET name = ?, description = ? WHERE id = ?')->execute([$name, $desc, $id]);

    // The admin role bypasses every permission check, so its matrix is locked
    // and ignored on save. Every other role (built-in or custom) is editable.
    if (!$role['is_admin']) {
        $posted = isset($_POST['perms']) && is_array($_POST['perms']) ? $_POST['perms'] : [];
        _roleSaveGrants($db, $id, $posted);
    }

    logAudit('role.updated', $id, 'role', 'slug=' . $role['slug']);
    flash('success', 'Permission level updated.');
    redirect('/admin/roles');
});

$router->post('/admin/roles/{id}/delete', function (array $p) {
    Auth::requireAdmin();
    $id = (int) $p['id'];
    if (!verifyCsrf($_POST['_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/admin/roles');
    }

    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([$id]);
    $role = $stmt->fetch();
    if (!$role) {
        flash('error', 'Permission level not found.');
        redirect('/admin/roles');
    }
    if (!empty($role['is_system'])) {
        flash('error', 'Built-in permission levels cannot be deleted.');
        redirect('/admin/roles');
    }

    $uc = $db->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
    $uc->execute([$role['slug']]);
    if ((int) $uc->fetchColumn() > 0) {
        flash('error', 'This permission level is still assigned to users — reassign them before deleting it.');
        redirect('/admin/roles');
    }

    $db->prepare('DELETE FROM roles WHERE id = ?')->execute([$id]); // role_permissions cascade
    logAudit('role.deleted', $id, 'role', 'slug=' . $role['slug']);
    flash('success', 'Permission level deleted.');
    redirect('/admin/roles');
});
