<?php

declare(strict_types=1);

/**
 * LocalDesk — AI ticket classification
 *
 * Provider abstraction. Each provider takes a ticket subject + body
 * plus the candidate skills (filtered to the ticket's destination
 * group) and returns a classification verdict. The interface is
 * intentionally tiny so swapping providers (Anthropic → OpenAI →
 * a local Ollama later) is a one-line settings change.
 *
 * The verdict is a fixed shape regardless of provider:
 *
 *   [
 *     'skill_ids'   => [3, 7],          // ints from the candidate list
 *     'confidence'  => 0.87,            // 0.0–1.0
 *     'sentiment'   => 'frustrated',    // 'neutral'|'positive'|'frustrated'|'angry'|'urgent'
 *     'reasoning'   => 'Mentions ...',  // short
 *     'raw'         => [...],           // full provider response (for debugging)
 *     'latency_ms'  => 842,
 *     'prompt_tokens' => 412,
 *     'output_tokens' => 87,
 *   ]
 *
 * On any error a SoftAIException is thrown — callers always continue
 * with the existing fallback flow rather than blocking ticket creation.
 */

class SoftAIException extends \RuntimeException {}

interface AIClassifier
{
    /**
     * @param string $subject       Ticket subject
     * @param string $body          Ticket description (raw text or markdown)
     * @param array  $skills        Each row: ['id', 'name', 'description'|null]
     * @param int    $maxTokens     Model output cap
     * @param int    $timeoutSec    Hard wall-clock timeout
     */
    public function classify(string $subject, string $body, array $skills, int $maxTokens, int $timeoutSec): array;

    /** @return array{0:string,1:string}[] [[id, name], ...] from the provider catalogue */
    public function listModels(int $timeoutSec = 10): array;

    /** @return array{ok:bool, message:string} for the settings-page Test Connection button */
    public function testConnection(int $timeoutSec = 10): array;
}

/**
 * Shared prompt + JSON-extraction logic. Both providers send the same
 * system + user prompts and parse the same output shape.
 */
abstract class BaseAIClassifier implements AIClassifier
{
    public const SENTIMENT_VALUES = ['neutral', 'positive', 'frustrated', 'angry', 'urgent'];

    /** Keep prompts short — these get sent on every ticket. */
    protected function systemPrompt(): string
    {
        return <<<TXT
You are a help-desk ticket classifier. Given a subject and body plus a list of available agent skills, decide which skills (if any) the ticket needs and assess sentiment. Output one JSON object only — no markdown, no prose.

Schema:
{
  "skill_ids": [<int>, ...],          // subset of the provided skill ids; [] if no clear match
  "confidence": <float 0.0–1.0>,      // your certainty about skill_ids
  "sentiment": "neutral"|"positive"|"frustrated"|"angry"|"urgent",
  "reasoning": "<one sentence>"
}

Rules:
- Pick skills only when the body or subject clearly indicates the expertise needed. When unsure, return [] with low confidence (<0.5) — do NOT guess.
- Confidence reflects skill-match certainty, not sentiment certainty.
- Sentiment "urgent" means the requester signals time pressure or impact (e.g. "system down", "can't help patrons"); "angry"/"frustrated" reflects emotional tone.
- Output JSON only.
TXT;
    }

    protected function userPrompt(string $subject, string $body, array $skills): string
    {
        $catalogue = '';
        foreach ($skills as $s) {
            $line = '- id ' . (int) $s['id'] . ': ' . $s['name'];
            if (!empty($s['description'])) {
                $line .= ' — ' . str_replace(["\n", "\r"], ' ', (string) $s['description']);
            }
            $catalogue .= $line . "\n";
        }
        if ($catalogue === '') {
            $catalogue = "(no skills available — return skill_ids: [])\n";
        }

        // Cap inputs so a pasted log dump can't blow up the prompt
        $subject = mb_substr(trim($subject), 0, 200);
        $body    = mb_substr(trim(strip_tags($body)), 0, 4000);

        return "Available skills:\n{$catalogue}\nTicket subject: {$subject}\n\nTicket body:\n{$body}\n\nRespond with JSON only.";
    }

    /**
     * Parse a provider response string into the standard verdict shape.
     * Tolerates models that wrap JSON in ```json ... ``` fences.
     *
     * @throws SoftAIException on bad JSON or missing keys.
     */
    protected function parseVerdict(string $raw, array $candidateIds): array
    {
        // Strip code fences if the model added them
        $stripped = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $stripped, $m)) {
            $stripped = trim($m[1]);
        }
        // Some models prepend prose; grab the first {...} block
        if (!str_starts_with($stripped, '{')) {
            $start = strpos($stripped, '{');
            $end   = strrpos($stripped, '}');
            if ($start === false || $end === false || $end <= $start) {
                throw new SoftAIException('AI response did not contain JSON: ' . mb_substr($raw, 0, 200));
            }
            $stripped = substr($stripped, $start, $end - $start + 1);
        }

        $decoded = json_decode($stripped, true);
        if (!is_array($decoded)) {
            throw new SoftAIException('AI JSON decode failed: ' . json_last_error_msg());
        }

        $skillIds = [];
        if (isset($decoded['skill_ids']) && is_array($decoded['skill_ids'])) {
            foreach ($decoded['skill_ids'] as $sid) {
                $sid = (int) $sid;
                if ($sid > 0 && in_array($sid, $candidateIds, true)) {
                    $skillIds[] = $sid;
                }
            }
            $skillIds = array_values(array_unique($skillIds));
        }

        $confidence = isset($decoded['confidence']) ? (float) $decoded['confidence'] : 0.0;
        if ($confidence < 0.0) { $confidence = 0.0; }
        if ($confidence > 1.0) { $confidence = 1.0; }

        $sentiment = isset($decoded['sentiment']) ? strtolower((string) $decoded['sentiment']) : 'neutral';
        if (!in_array($sentiment, self::SENTIMENT_VALUES, true)) {
            $sentiment = 'neutral';
        }

        $reasoning = isset($decoded['reasoning']) ? mb_substr((string) $decoded['reasoning'], 0, 500) : '';

        return [
            'skill_ids'  => $skillIds,
            'confidence' => $confidence,
            'sentiment'  => $sentiment,
            'reasoning'  => $reasoning,
        ];
    }

    /**
     * Centralised cURL helper so both providers behave identically on
     * timeout / SSL / decode failures.
     *
     * @throws SoftAIException
     */
    protected function curlPostJson(string $url, array $payload, array $headers, int $timeoutSec): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(2, $timeoutSec),
            CURLOPT_CONNECTTIMEOUT => min(5, max(2, $timeoutSec)),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_FAILONERROR    => false,
        ]);
        $started = microtime(true);
        $response = curl_exec($ch);
        $latency = (int) round((microtime(true) - $started) * 1000);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new SoftAIException("AI HTTP error ({$err})");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $snippet = is_string($response) ? mb_substr($response, 0, 300) : '';
            throw new SoftAIException("AI HTTP {$httpCode}: {$snippet}");
        }
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new SoftAIException('AI response not JSON: ' . mb_substr((string) $response, 0, 200));
        }
        return ['decoded' => $decoded, 'latency_ms' => $latency];
    }

    protected function curlGetJson(string $url, array $headers, int $timeoutSec): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(2, $timeoutSec),
            CURLOPT_CONNECTTIMEOUT => min(5, max(2, $timeoutSec)),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FAILONERROR    => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new SoftAIException("AI HTTP error ({$err})");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $snippet = is_string($response) ? mb_substr($response, 0, 300) : '';
            throw new SoftAIException("AI HTTP {$httpCode}: {$snippet}");
        }
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new SoftAIException('AI response not JSON');
        }
        return $decoded;
    }
}

/**
 * Anthropic Claude classifier. Uses the Messages API with a tight
 * system prompt and JSON-only output.
 */
class AnthropicClassifier extends BaseAIClassifier
{
    public function __construct(private string $apiKey, private string $model)
    {
    }

    public function classify(string $subject, string $body, array $skills, int $maxTokens, int $timeoutSec): array
    {
        $candidateIds = array_map(static fn($s) => (int) $s['id'], $skills);

        $resp = $this->curlPostJson(
            'https://api.anthropic.com/v1/messages',
            [
                'model'      => $this->model,
                'max_tokens' => $maxTokens,
                'system'     => $this->systemPrompt(),
                'messages'   => [
                    ['role' => 'user', 'content' => $this->userPrompt($subject, $body, $skills)],
                ],
            ],
            [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            $timeoutSec
        );

        $decoded = $resp['decoded'];
        $text = '';
        foreach (($decoded['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        if ($text === '') {
            throw new SoftAIException('Anthropic returned no text content');
        }

        $verdict = $this->parseVerdict($text, $candidateIds);
        $verdict['raw']            = $decoded;
        $verdict['latency_ms']     = $resp['latency_ms'];
        $verdict['prompt_tokens']  = (int) ($decoded['usage']['input_tokens']  ?? 0);
        $verdict['output_tokens']  = (int) ($decoded['usage']['output_tokens'] ?? 0);
        return $verdict;
    }

    public function listModels(int $timeoutSec = 10): array
    {
        $decoded = $this->curlGetJson(
            'https://api.anthropic.com/v1/models',
            [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            $timeoutSec
        );
        $out = [];
        foreach (($decoded['data'] ?? []) as $m) {
            $id = (string) ($m['id'] ?? '');
            if ($id === '') { continue; }
            $name = (string) ($m['display_name'] ?? $id);
            $out[] = [$id, $name];
        }
        // Stable, name-sorted with newest-feeling models first (claude-opus-4 etc.)
        usort($out, static fn($a, $b) => strcmp($b[0], $a[0]));
        return $out;
    }

    public function testConnection(int $timeoutSec = 10): array
    {
        try {
            $verdict = $this->classify(
                'Test connection',
                'This is a test ticket — please confirm you are reachable.',
                [['id' => 1, 'name' => 'TestSkill', 'description' => 'placeholder']],
                100,
                $timeoutSec
            );
            return ['ok' => true, 'message' => 'OK — Anthropic responded in ' . ($verdict['latency_ms'] ?? '?') . 'ms (model ' . $this->model . ').'];
        } catch (SoftAIException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

/**
 * OpenAI Chat Completions classifier. Uses response_format=json_object
 * to lock the model into emitting parseable JSON.
 */
class OpenAIClassifier extends BaseAIClassifier
{
    public function __construct(private string $apiKey, private string $model)
    {
    }

    public function classify(string $subject, string $body, array $skills, int $maxTokens, int $timeoutSec): array
    {
        $candidateIds = array_map(static fn($s) => (int) $s['id'], $skills);

        $resp = $this->curlPostJson(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user',   'content' => $this->userPrompt($subject, $body, $skills)],
                ],
                'max_tokens'      => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.0,
            ],
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            $timeoutSec
        );

        $decoded = $resp['decoded'];
        $text = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        if ($text === '') {
            throw new SoftAIException('OpenAI returned no message content');
        }

        $verdict = $this->parseVerdict($text, $candidateIds);
        $verdict['raw']           = $decoded;
        $verdict['latency_ms']    = $resp['latency_ms'];
        $verdict['prompt_tokens'] = (int) ($decoded['usage']['prompt_tokens']     ?? 0);
        $verdict['output_tokens'] = (int) ($decoded['usage']['completion_tokens'] ?? 0);
        return $verdict;
    }

    public function listModels(int $timeoutSec = 10): array
    {
        $decoded = $this->curlGetJson(
            'https://api.openai.com/v1/models',
            ['Authorization: Bearer ' . $this->apiKey],
            $timeoutSec
        );
        $out = [];
        foreach (($decoded['data'] ?? []) as $m) {
            $id = (string) ($m['id'] ?? '');
            if ($id === '') { continue; }
            // Filter to chat-capable families and skip legacy/embeddings/audio variants
            $isChatFamily = (
                str_starts_with($id, 'gpt-') ||
                str_starts_with($id, 'o1-')  ||
                str_starts_with($id, 'o3-')  ||
                str_starts_with($id, 'o4-')  ||
                str_starts_with($id, 'chatgpt-')
            );
            $isExcluded = (
                str_contains($id, 'embedding') ||
                str_contains($id, 'whisper')   ||
                str_contains($id, 'tts')       ||
                str_contains($id, 'realtime')  ||
                str_contains($id, 'audio')     ||
                str_contains($id, 'image')     ||
                str_contains($id, '-search-')  ||
                str_contains($id, 'davinci')   ||
                str_contains($id, 'babbage')
            );
            if (!$isChatFamily || $isExcluded) { continue; }
            $out[] = [$id, $id];
        }
        usort($out, static fn($a, $b) => strcmp($b[0], $a[0]));
        return $out;
    }

    public function testConnection(int $timeoutSec = 10): array
    {
        try {
            $verdict = $this->classify(
                'Test connection',
                'This is a test ticket — please confirm you are reachable.',
                [['id' => 1, 'name' => 'TestSkill', 'description' => 'placeholder']],
                100,
                $timeoutSec
            );
            return ['ok' => true, 'message' => 'OK — OpenAI responded in ' . ($verdict['latency_ms'] ?? '?') . 'ms (model ' . $this->model . ').'];
        } catch (SoftAIException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

/**
 * Factory: builds an AIClassifier matching the current settings.
 * Returns null when the feature is disabled or required keys are missing.
 */
class AIClassifierFactory
{
    public static function fromSettings(): ?AIClassifier
    {
        if (getSetting('ai_enabled', '0') !== '1') {
            return null;
        }
        $provider = getSetting('ai_provider', 'anthropic');
        if ($provider === 'anthropic') {
            $key   = (string) getSetting('ai_anthropic_api_key', '');
            $model = (string) getSetting('ai_anthropic_model', 'claude-haiku-4-5');
            if ($key === '' || $model === '') { return null; }
            return new AnthropicClassifier($key, $model);
        }
        if ($provider === 'openai') {
            $key   = (string) getSetting('ai_openai_api_key', '');
            $model = (string) getSetting('ai_openai_model', 'gpt-4o-mini');
            if ($key === '' || $model === '') { return null; }
            return new OpenAIClassifier($key, $model);
        }
        return null;
    }

    /**
     * Build a classifier for a specific provider with explicit credentials.
     * Used by the settings page (Test Connection / Refresh Models) so the
     * admin can validate before saving.
     */
    public static function forProvider(string $provider, string $apiKey, string $model): ?AIClassifier
    {
        if ($provider === 'anthropic') { return new AnthropicClassifier($apiKey, $model); }
        if ($provider === 'openai')    { return new OpenAIClassifier($apiKey, $model); }
        return null;
    }
}
