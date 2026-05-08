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

    /**
     * Pick the best group (queue/team) for a ticket from a candidate list.
     * Used by the "No Wrong Door" ticket type to route to the right team
     * instead of pinning the ticket to a single default group.
     *
     * Returns the same shape as classify() except `group_id` (?int)
     * replaces `skill_ids` and `sentiment` is omitted:
     *
     *   [
     *     'group_id'    => 7|null,           // null when no confident match
     *     'confidence'  => 0.0–1.0,
     *     'reasoning'   => '<one sentence>',
     *     'raw'         => [...],
     *     'latency_ms'  => 842,
     *     'prompt_tokens' => 412,
     *     'output_tokens' => 47,
     *   ]
     *
     * @param array $groups Each row: ['id', 'name', 'description'|null]
     */
    public function classifyGroup(string $subject, string $body, array $groups, int $maxTokens, int $timeoutSec): array;

    /**
     * Look at a new (not-yet-saved) ticket and decide which, if any, of the
     * supplied candidate open tickets it duplicates. Confidential ticket
     * bodies are NEVER passed in by callers — see checkTicketDuplicates().
     *
     * Returns:
     *   [
     *     'matches' => [
     *       ['ticket_id' => int, 'confidence' => float 0..1, 'reasoning' => string],
     *       ...
     *     ],
     *     'raw'           => [...],
     *     'latency_ms'    => int,
     *     'prompt_tokens' => int,
     *     'output_tokens' => int,
     *   ]
     *
     * @param array $candidates Each row: ['id', 'subject', 'description'|null, 'created_at'|null]
     */
    public function findDuplicates(string $subject, string $body, array $candidates, int $maxTokens, int $timeoutSec): array;

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
     * Group-routing system prompt. Different schema than skill
     * classification — single group_id, no sentiment.
     */
    protected function groupSystemPrompt(): string
    {
        return <<<TXT
You are a help-desk ticket router. Given a subject and body plus a list of available groups (teams/queues), pick the single group most likely to handle this ticket. Output one JSON object only — no markdown, no prose.

Schema:
{
  "group_id": <int>|null,             // id of the chosen group, or null if no clear match
  "confidence": <float 0.0–1.0>,      // your certainty about the chosen group
  "reasoning": "<one sentence>"
}

Rules:
- Pick a group ONLY when the subject or body clearly indicates one team handles this work. When unsure, return null with low confidence (<0.5) — do NOT guess.
- Use the group descriptions to decide; don't assume domain knowledge from group names alone.
- Confidence reflects routing certainty; return below the requester's threshold to leave the ticket in its current queue.
- Output JSON only.
TXT;
    }

    protected function groupUserPrompt(string $subject, string $body, array $groups): string
    {
        $catalogue = '';
        foreach ($groups as $g) {
            $line = '- id ' . (int) $g['id'] . ': ' . $g['name'];
            if (!empty($g['description'])) {
                $line .= ' — ' . str_replace(["\n", "\r"], ' ', (string) $g['description']);
            }
            $catalogue .= $line . "\n";
        }
        if ($catalogue === '') {
            $catalogue = "(no groups available — return group_id: null)\n";
        }

        $subject = mb_substr(trim($subject), 0, 200);
        $body    = mb_substr(trim(strip_tags($body)), 0, 4000);

        return "Available groups:\n{$catalogue}\nTicket subject: {$subject}\n\nTicket body:\n{$body}\n\nRespond with JSON only.";
    }

    /**
     * Parse a provider response for group routing. Mirrors parseVerdict()
     * but for the group-id schema (single id or null + confidence + reasoning).
     *
     * @throws SoftAIException
     */
    protected function parseGroupVerdict(string $raw, array $candidateIds): array
    {
        $stripped = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $stripped, $m)) {
            $stripped = trim($m[1]);
        }
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

        $groupId = null;
        if (isset($decoded['group_id']) && $decoded['group_id'] !== null && $decoded['group_id'] !== '') {
            $gid = (int) $decoded['group_id'];
            if ($gid > 0 && in_array($gid, $candidateIds, true)) {
                $groupId = $gid;
            }
        }

        $confidence = isset($decoded['confidence']) ? (float) $decoded['confidence'] : 0.0;
        if ($confidence < 0.0) { $confidence = 0.0; }
        if ($confidence > 1.0) { $confidence = 1.0; }

        $reasoning = isset($decoded['reasoning']) ? mb_substr((string) $decoded['reasoning'], 0, 500) : '';

        return [
            'group_id'   => $groupId,
            'confidence' => $confidence,
            'reasoning'  => $reasoning,
        ];
    }

    /**
     * Duplicate-detection system prompt. Output schema is a list of
     * {ticket_id, confidence, reasoning} — empty list means no match.
     */
    protected function dupSystemPrompt(): string
    {
        return <<<TXT
You are a help-desk duplicate-ticket detector. A user is about to file a NEW ticket. Given the new ticket's subject + body and a list of OPEN candidate tickets at the same branch, decide which (if any) of the candidates describe the SAME underlying issue. Output one JSON object only — no markdown, no prose.

Schema:
{
  "matches": [
    {
      "ticket_id":  <int>,                  // must be one of the provided ids
      "confidence": <float 0.0-1.0>,        // your certainty THIS is the same issue
      "reasoning":  "<one short sentence>"  // why you think it matches
    },
    ...
  ]
}

Rules:
- Match ONLY when the candidate clearly describes the same underlying problem (same equipment, same symptom, same workflow) — NOT just the same general topic. "Printer is jamming" and "Printer is out of toner" are NOT duplicates even though both are about a printer.
- Generic/vague new tickets ("computer not working", "need help") match nothing — return [].
- Order matches by confidence, highest first. List at most 3.
- Confidence reflects CERTAINTY of being the same issue, not topical similarity.
- If unsure, leave the candidate OUT — false positives waste people's time.
- Output JSON only.
TXT;
    }

    protected function dupUserPrompt(string $subject, string $body, array $candidates): string
    {
        // Cap inputs aggressively — 30 candidate snippets at 200 chars + 4K body
        // gives a generous budget without inflating the prompt.
        $subject = mb_substr(trim($subject), 0, 200);
        $body    = mb_substr(trim(strip_tags($body)), 0, 4000);

        $list = '';
        foreach ($candidates as $c) {
            $cid     = (int) ($c['id'] ?? 0);
            if ($cid <= 0) { continue; }
            $cSubj   = mb_substr(trim((string) ($c['subject'] ?? '')), 0, 200);
            $cBody   = mb_substr(trim(strip_tags((string) ($c['description'] ?? ''))), 0, 400);
            $cAge    = trim((string) ($c['created_at'] ?? ''));
            $list   .= '- id ' . $cid;
            if ($cAge !== '') { $list .= ' (opened ' . $cAge . ')'; }
            $list   .= ': ' . $cSubj;
            if ($cBody !== '') { $list .= ' — ' . str_replace(["\n", "\r"], ' ', $cBody); }
            $list   .= "\n";
        }
        if ($list === '') {
            $list = "(no candidates)\n";
        }

        return "Open candidate tickets at the same branch:\n{$list}\nNEW ticket subject: {$subject}\n\nNEW ticket body:\n{$body}\n\nRespond with JSON only.";
    }

    /**
     * Parse a provider response for duplicate detection. Returns a list of
     * {ticket_id, confidence, reasoning} verdicts, filtered to the supplied
     * candidate IDs.
     *
     * @throws SoftAIException
     */
    protected function parseDupVerdict(string $raw, array $candidateIds): array
    {
        $stripped = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $stripped, $m)) {
            $stripped = trim($m[1]);
        }
        if (!str_starts_with($stripped, '{') && !str_starts_with($stripped, '[')) {
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

        $list = [];
        if (isset($decoded['matches']) && is_array($decoded['matches'])) {
            $list = $decoded['matches'];
        } elseif (array_is_list($decoded)) {
            $list = $decoded;
        }

        $matches = [];
        $seen    = [];
        foreach ($list as $row) {
            if (!is_array($row)) { continue; }
            $tid = (int) ($row['ticket_id'] ?? $row['id'] ?? 0);
            if ($tid <= 0 || !in_array($tid, $candidateIds, true) || isset($seen[$tid])) {
                continue;
            }
            $seen[$tid] = true;

            $conf = isset($row['confidence']) ? (float) $row['confidence'] : 0.0;
            if ($conf < 0.0) { $conf = 0.0; }
            if ($conf > 1.0) { $conf = 1.0; }

            $reason = isset($row['reasoning']) ? mb_substr((string) $row['reasoning'], 0, 300) : '';

            $matches[] = ['ticket_id' => $tid, 'confidence' => $conf, 'reasoning' => $reason];
        }

        usort($matches, static fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        return array_slice($matches, 0, 3);
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

    /**
     * Free-form chat completion against the provider. Used by features
     * that aren't ticket classification (e.g. skill suggestion). Returns
     * the raw assistant text — caller is responsible for parsing.
     *
     * Implemented per-provider since the request/response shapes differ.
     *
     * @throws SoftAIException
     */
    abstract public function chat(string $systemPrompt, string $userPrompt, int $maxTokens, int $timeoutSec): string;

    /**
     * Build the system + user prompts for the "suggest skills for this
     * organisation" feature on the Agent Skills admin page.
     */
    public function skillSuggestionSystemPrompt(): string
    {
        return <<<TXT
You are a help-desk operations consultant. Given an organization's profile, the ticket types they triage, the groups (queues/teams) tickets are routed to, and the agent skills they already have, suggest practical new agent skills that would help route tickets within this organization.

Output ONE JSON object — no markdown fences, no prose, no commentary before or after. The top-level key MUST be exactly "skills" (lowercase plural).

Schema (use these EXACT key names):
{
  "skills": [
    {
      "name": "<short skill name, 1-3 words>",
      "description": "<one short sentence on what this skill represents>",
      "group": "<exact name of one of the provided groups, or null for global>"
    },
    ...
  ]
}

Rules:
- Suggest 8 to 15 distinct skills covering the most common areas of expertise this kind of organization will need to cover its ticket types.
- Do NOT duplicate skills the org already has (case-insensitive name match). If everything common is already covered, return fewer.
- Names should be concise, specific, and human-friendly (e.g. "Network", "Billing", "Cataloging", "Sierra ILS"). Avoid generic catch-alls like "Support" or "General".
- For each skill, suggest a "group" only if a clear owning team exists in the provided groups list — otherwise return null. The string must match a provided group name EXACTLY (case-sensitive).
- Tailor suggestions to the organization type. A public library should get library-flavoured skills (Cataloging, ILS, Programming, Patron Services); a hospital should get healthcare-flavoured skills (EMR, HIPAA, Clinical Apps); etc.
- If a sample of recent ticket subjects is provided, use it to identify real-world issue themes the org actually deals with — frequently appearing topics (specific products, vendors, error patterns, workflows) are strong candidates for new skills.
- Output JSON only, no commentary.
TXT;
    }

    public function skillSuggestionUserPrompt(string $orgTypeLabel, array $ticketTypes, array $groups, array $existingSkills, array $recentSubjects = []): string
    {
        $fmtList = static function (array $rows, string $emptyText): string {
            if (!$rows) { return $emptyText . "\n"; }
            $out = '';
            foreach ($rows as $r) {
                $line = '- ' . trim((string) ($r['name'] ?? ''));
                if (!empty($r['description'])) {
                    $line .= ' — ' . str_replace(["\n", "\r"], ' ', mb_substr((string) $r['description'], 0, 160));
                }
                $out .= $line . "\n";
            }
            return $out;
        };

        $typesBlock    = $fmtList($ticketTypes,    '(none defined yet)');
        $groupsBlock   = $fmtList($groups,         '(none defined yet)');
        $existingBlock = $fmtList($existingSkills, '(none)');

        $org = trim($orgTypeLabel) !== '' ? $orgTypeLabel : 'Unspecified';

        $base = "Organization type: {$org}\n\nTicket types this org triages:\n{$typesBlock}\nGroups (queues/teams) tickets are routed to:\n{$groupsBlock}\nAgent skills already defined (do not duplicate):\n{$existingBlock}";

        if ($recentSubjects) {
            // Hard cap on the subjects block so a "look at the last 5000 tickets"
            // request can't blow up the prompt. Subjects past the cap are dropped.
            $maxChars = 60000;
            $subjBlock = '';
            $included  = 0;
            foreach ($recentSubjects as $subject) {
                $clean = str_replace(["\n", "\r", "\t"], ' ', trim((string) $subject));
                if ($clean === '') { continue; }
                $clean = mb_substr($clean, 0, 140);
                $line  = '- ' . $clean . "\n";
                if (strlen($subjBlock) + strlen($line) > $maxChars) { break; }
                $subjBlock .= $line;
                $included++;
            }
            if ($included > 0) {
                $base .= "\n\nSample of {$included} recent ticket subjects from this org (most recent first — use to identify real-world issue themes):\n" . $subjBlock;
            }
        }

        return $base . "\nSuggest skills now. Respond with JSON only.";
    }

    /**
     * Parse the AI's skill-suggestion response. Tolerant of variation in the
     * top-level shape because models don't always honour the requested key
     * names — accepts {"skills": [...]}, {"suggestions": [...]}, raw arrays,
     * and anything else where we can find a list of objects with a name field.
     *
     * Drops malformed entries and any name that already exists
     * (case-insensitive) in $existingNames. Resolves the "group" string
     * against $groupNameToId for convenience.
     *
     * @return array<int, array{name:string, description:string, group_id:?int, group_name:?string}>
     */
    public function parseSkillSuggestions(string $raw, array $existingNames, array $groupNameToId): array
    {
        $stripped = trim($raw);
        // Strip ```json fences if the model added them
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $stripped, $m)) {
            $stripped = trim($m[1]);
        }
        // Pull the first JSON value (object OR array) out of any prose wrapper
        if ($stripped === '' || ($stripped[0] !== '{' && $stripped[0] !== '[')) {
            $candidates = [];
            $objStart = strpos($stripped, '{');
            $objEnd   = strrpos($stripped, '}');
            if ($objStart !== false && $objEnd !== false && $objEnd > $objStart) {
                $candidates[] = substr($stripped, $objStart, $objEnd - $objStart + 1);
            }
            $arrStart = strpos($stripped, '[');
            $arrEnd   = strrpos($stripped, ']');
            if ($arrStart !== false && $arrEnd !== false && $arrEnd > $arrStart) {
                $candidates[] = substr($stripped, $arrStart, $arrEnd - $arrStart + 1);
            }
            if (!$candidates) {
                error_log('[AI suggest skills] non-JSON response: ' . mb_substr($raw, 0, 500));
                throw new SoftAIException('AI response did not contain JSON. First 200 chars: ' . mb_substr($raw, 0, 200));
            }
            $stripped = $candidates[0];
        }

        // Try strict decode first. If it fails, walk through a series of
        // increasingly permissive fallbacks before giving up. Models emit
        // every variety of broken JSON: raw control chars inside strings,
        // invalid \-escapes, truncated multi-byte UTF-8 from token-limit
        // cuts, stray non-UTF-8 bytes — try to recover from each.
        $decoded = json_decode($stripped, true);
        if (!is_array($decoded)) {
            // Substitute invalid UTF-8 bytes with U+FFFD (handles truncated
            // multi-byte chars when output_tokens caps mid-character).
            $decoded = json_decode($stripped, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if (!is_array($decoded)) {
            // Escape raw control chars + invalid \-escapes inside string
            // literals (handles inlined newlines, stray "\U" / "\D" etc.).
            $sanitized = $this->escapeJsonStringControlChars($stripped);
            $decoded   = json_decode($sanitized, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if (!is_array($decoded)) {
            // Last-ditch: scrub invalid UTF-8 to ?, then sanitize, then decode.
            $scrubbed = function_exists('mb_scrub') ? mb_scrub($stripped, 'UTF-8') : $stripped;
            $sanitized = $this->escapeJsonStringControlChars($scrubbed);
            $decoded   = json_decode($sanitized, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if (!is_array($decoded)) {
            // Log the FULL raw response (truncated only at 8 KB) so a follow-up
            // diagnosis has the actual offending bytes, not just the head.
            error_log('[AI suggest skills] JSON decode failed: ' . json_last_error_msg() . ' — raw len=' . strlen($raw) . ' — raw: ' . mb_substr($raw, 0, 8000));
            throw new SoftAIException('AI returned malformed JSON (' . json_last_error_msg() . '). First 200 chars: ' . mb_substr($raw, 0, 200) . ' — full response logged to PHP error log for diagnosis.');
        }

        $skillsList = $this->extractSkillsArray($decoded);
        if ($skillsList === null) {
            error_log('[AI suggest skills] no skills array in response — keys: ' . implode(',', array_keys($decoded)) . ' — raw: ' . mb_substr($raw, 0, 500));
            $topKeys = array_slice(array_keys($decoded), 0, 6);
            throw new SoftAIException('AI returned JSON but no recognizable skills list (top-level keys: ' . (empty($topKeys) ? '(empty object)' : implode(', ', $topKeys)) . '). First 200 chars: ' . mb_substr($raw, 0, 200));
        }

        $existingLower = array_map(static fn($n) => mb_strtolower(trim((string) $n)), $existingNames);
        $seen = [];
        $out  = [];
        foreach ($skillsList as $row) {
            if (!is_array($row)) { continue; }

            // Name lives under name | skill | skill_name | title (model preference varies)
            $name = '';
            foreach (['name', 'skill', 'skill_name', 'title', 'label'] as $k) {
                if (isset($row[$k]) && is_string($row[$k]) && trim($row[$k]) !== '') {
                    $name = trim($row[$k]);
                    break;
                }
            }
            if ($name === '') { continue; }
            $key = mb_strtolower($name);
            if (in_array($key, $existingLower, true) || isset($seen[$key])) { continue; }
            $seen[$key] = true;

            // Description under description | desc | summary
            $desc = '';
            foreach (['description', 'desc', 'summary', 'details'] as $k) {
                if (isset($row[$k]) && is_string($row[$k])) {
                    $desc = trim($row[$k]);
                    break;
                }
            }
            $desc = mb_substr($desc, 0, 500);

            // Group under group | group_name | owning_group | team — accept null/string
            $groupName = '';
            foreach (['group', 'group_name', 'owning_group', 'team'] as $k) {
                if (array_key_exists($k, $row) && is_string($row[$k])) {
                    $groupName = trim($row[$k]);
                    break;
                }
            }
            $groupId = null;
            if ($groupName !== '' && isset($groupNameToId[$groupName])) {
                $groupId = (int) $groupNameToId[$groupName];
            } else {
                // Try a case-insensitive match before giving up
                if ($groupName !== '') {
                    foreach ($groupNameToId as $gName => $gId) {
                        if (mb_strtolower($gName) === mb_strtolower($groupName)) {
                            $groupId   = (int) $gId;
                            $groupName = (string) $gName;
                            break;
                        }
                    }
                }
                if ($groupId === null) { $groupName = ''; }
            }

            $out[] = [
                'name'        => mb_substr($name, 0, 100),
                'description' => $desc,
                'group_id'    => $groupId,
                'group_name'  => $groupName !== '' ? $groupName : null,
            ];
        }

        return $out;
    }

    /**
     * Walk a JSON string and fix two classes of mistakes that models
     * commonly make inside string literals:
     *
     *   1. Raw control bytes (newline, tab, etc., bytes < 0x20) — JSON
     *      requires these escaped as \n / \t / \uXXXX.
     *   2. Invalid backslash escapes (e.g. "C:\Users", where \U is not a
     *      valid JSON escape) — re-escapes the backslash itself.
     *
     * Outside of string literals the original bytes are preserved, so this
     * is safe to run on already-valid JSON (it returns the input unchanged).
     */
    private function escapeJsonStringControlChars(string $json): string
    {
        // The seven valid characters that may follow a backslash inside a
        // JSON string literal (per RFC 8259). \uXXXX is handled separately
        // since it expects four hex digits to follow.
        static $validEscapeChars = ['"' => 1, '\\' => 1, '/' => 1,
                                    'b' => 1, 'f' => 1, 'n' => 1, 'r' => 1, 't' => 1];

        $out      = '';
        $inString = false;
        $len      = strlen($json);
        $i        = 0;
        while ($i < $len) {
            $ch = $json[$i];

            if (!$inString) {
                if ($ch === '"') { $inString = true; }
                $out .= $ch;
                $i++;
                continue;
            }

            // ---- inside a string literal ----

            if ($ch === '"') {
                $inString = false;
                $out .= $ch;
                $i++;
                continue;
            }

            if ($ch === '\\') {
                $next = $i + 1 < $len ? $json[$i + 1] : '';
                if ($next === '') {
                    // Trailing backslash with nothing after — re-escape it
                    $out .= '\\\\';
                    $i++;
                    continue;
                }
                if (isset($validEscapeChars[$next])) {
                    // Valid 1-char escape — pass both bytes through
                    $out .= $ch . $next;
                    $i  += 2;
                    continue;
                }
                if ($next === 'u') {
                    // \uXXXX needs four hex digits — pass through if valid,
                    // re-escape the backslash if not.
                    $hex = substr($json, $i + 2, 4);
                    if (strlen($hex) === 4 && ctype_xdigit($hex)) {
                        $out .= $ch . $next . $hex;
                        $i  += 6;
                        continue;
                    }
                    $out .= '\\\\';
                    $i++;
                    continue;
                }
                // Anything else (\D, \U, \z, etc.) is an invalid JSON escape.
                // Re-escape the backslash; the next char will be processed
                // as a normal string byte on the next iteration.
                $out .= '\\\\';
                $i++;
                continue;
            }

            if (ord($ch) < 0x20) {
                switch ($ch) {
                    case "\n": $out .= '\\n'; break;
                    case "\r": $out .= '\\r'; break;
                    case "\t": $out .= '\\t'; break;
                    case "\b": $out .= '\\b'; break;
                    case "\f": $out .= '\\f'; break;
                    default:   $out .= sprintf('\\u%04x', ord($ch)); break;
                }
                $i++;
                continue;
            }

            $out .= $ch;
            $i++;
        }
        return $out;
    }

    /**
     * Find the array-of-skill-objects inside whatever shape the model returned.
     * Tries the documented "skills" key first, then common alternatives, then
     * falls back to scanning all top-level array values for a list of objects
     * with a name-ish field.
     */
    private function extractSkillsArray(array $decoded): ?array
    {
        // Top-level array: e.g. [{name: ...}, {name: ...}]
        if (array_is_list($decoded)) {
            return $decoded;
        }

        $preferredKeys = [
            'skills', 'suggestions', 'suggested_skills', 'agent_skills',
            'recommendations', 'recommended_skills', 'items', 'results',
            'data', 'list',
        ];
        foreach ($preferredKeys as $k) {
            if (isset($decoded[$k]) && is_array($decoded[$k]) && array_is_list($decoded[$k])) {
                return $decoded[$k];
            }
        }

        // Last resort: pick the first list-of-objects-with-name value at the top level.
        foreach ($decoded as $value) {
            if (!is_array($value) || !array_is_list($value) || empty($value)) { continue; }
            $first = $value[0] ?? null;
            if (!is_array($first)) { continue; }
            foreach (['name', 'skill', 'skill_name', 'title', 'label'] as $nameKey) {
                if (isset($first[$nameKey])) {
                    return $value;
                }
            }
        }

        return null;
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

    public function classifyGroup(string $subject, string $body, array $groups, int $maxTokens, int $timeoutSec): array
    {
        $candidateIds = array_map(static fn($g) => (int) $g['id'], $groups);

        $resp = $this->curlPostJson(
            'https://api.anthropic.com/v1/messages',
            [
                'model'      => $this->model,
                'max_tokens' => $maxTokens,
                'system'     => $this->groupSystemPrompt(),
                'messages'   => [
                    ['role' => 'user', 'content' => $this->groupUserPrompt($subject, $body, $groups)],
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

        $verdict = $this->parseGroupVerdict($text, $candidateIds);
        $verdict['raw']           = $decoded;
        $verdict['latency_ms']    = $resp['latency_ms'];
        $verdict['prompt_tokens'] = (int) ($decoded['usage']['input_tokens']  ?? 0);
        $verdict['output_tokens'] = (int) ($decoded['usage']['output_tokens'] ?? 0);
        return $verdict;
    }

    public function findDuplicates(string $subject, string $body, array $candidates, int $maxTokens, int $timeoutSec): array
    {
        $candidateIds = array_map(static fn($c) => (int) $c['id'], $candidates);

        $resp = $this->curlPostJson(
            'https://api.anthropic.com/v1/messages',
            [
                'model'      => $this->model,
                'max_tokens' => $maxTokens,
                'system'     => $this->dupSystemPrompt(),
                'messages'   => [
                    ['role' => 'user', 'content' => $this->dupUserPrompt($subject, $body, $candidates)],
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

        $matches = $this->parseDupVerdict($text, $candidateIds);
        return [
            'matches'       => $matches,
            'raw'           => $decoded,
            'latency_ms'    => $resp['latency_ms'],
            'prompt_tokens' => (int) ($decoded['usage']['input_tokens']  ?? 0),
            'output_tokens' => (int) ($decoded['usage']['output_tokens'] ?? 0),
        ];
    }

    public function chat(string $systemPrompt, string $userPrompt, int $maxTokens, int $timeoutSec): string
    {
        $resp = $this->curlPostJson(
            'https://api.anthropic.com/v1/messages',
            [
                'model'      => $this->model,
                'max_tokens' => $maxTokens,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ],
            [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            $timeoutSec
        );

        $text = '';
        foreach (($resp['decoded']['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        if ($text === '') {
            throw new SoftAIException('Anthropic returned no text content');
        }
        return $text;
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

    public function classifyGroup(string $subject, string $body, array $groups, int $maxTokens, int $timeoutSec): array
    {
        $candidateIds = array_map(static fn($g) => (int) $g['id'], $groups);

        $resp = $this->curlPostJson(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->groupSystemPrompt()],
                    ['role' => 'user',   'content' => $this->groupUserPrompt($subject, $body, $groups)],
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

        $verdict = $this->parseGroupVerdict($text, $candidateIds);
        $verdict['raw']           = $decoded;
        $verdict['latency_ms']    = $resp['latency_ms'];
        $verdict['prompt_tokens'] = (int) ($decoded['usage']['prompt_tokens']     ?? 0);
        $verdict['output_tokens'] = (int) ($decoded['usage']['completion_tokens'] ?? 0);
        return $verdict;
    }

    public function findDuplicates(string $subject, string $body, array $candidates, int $maxTokens, int $timeoutSec): array
    {
        $candidateIds = array_map(static fn($c) => (int) $c['id'], $candidates);

        $resp = $this->curlPostJson(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->dupSystemPrompt()],
                    ['role' => 'user',   'content' => $this->dupUserPrompt($subject, $body, $candidates)],
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

        $matches = $this->parseDupVerdict($text, $candidateIds);
        return [
            'matches'       => $matches,
            'raw'           => $decoded,
            'latency_ms'    => $resp['latency_ms'],
            'prompt_tokens' => (int) ($decoded['usage']['prompt_tokens']     ?? 0),
            'output_tokens' => (int) ($decoded['usage']['completion_tokens'] ?? 0),
        ];
    }

    public function chat(string $systemPrompt, string $userPrompt, int $maxTokens, int $timeoutSec): string
    {
        $resp = $this->curlPostJson(
            'https://api.openai.com/v1/chat/completions',
            [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'max_tokens'      => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.2,
            ],
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            $timeoutSec
        );

        $text = (string) ($resp['decoded']['choices'][0]['message']['content'] ?? '');
        if ($text === '') {
            throw new SoftAIException('OpenAI returned no message content');
        }
        return $text;
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

    /**
     * Ask the configured provider to suggest skills given the org's profile.
     * Returns the parsed list of suggestions; throws SoftAIException on
     * any provider/parse error so the caller can surface a flash message.
     *
     * @param array $ticketTypes     [['name' => ..., 'description' => ?...], ...]
     * @param array $groups          [['id' => int, 'name' => ...], ...]
     * @param array $existingSkills  [['name' => ..., 'description' => ?...], ...]
     * @param array $recentSubjects  Optional sample of recent ticket subjects to ground the suggestions in real org data.
     * @return array<int, array{name:string, description:string, group_id:?int, group_name:?string}>
     * @throws SoftAIException
     */
    public static function suggestSkillsFromSettings(string $orgTypeLabel, array $ticketTypes, array $groups, array $existingSkills, array $recentSubjects = []): array
    {
        $classifier = self::fromSettings();
        if (!$classifier instanceof BaseAIClassifier) {
            throw new SoftAIException('AI is not enabled or is not configured. Configure it in Settings → AI Classification.');
        }

        $existingNames   = array_map(static fn($s) => (string) ($s['name'] ?? ''), $existingSkills);
        $groupNameToId   = [];
        foreach ($groups as $g) {
            if (!empty($g['name'])) {
                $groupNameToId[(string) $g['name']] = (int) $g['id'];
            }
        }

        // Suggestion JSON is much larger than a classification verdict —
        // 8-15 skills × (name + description + group + structure) easily runs
        // 1.5-3K tokens. The classification setting (`ai_max_tokens`,
        // default 500) is way too tight here; the response gets truncated
        // mid-string, often splitting a multi-byte UTF-8 char and producing
        // the "Control character error, possibly incorrectly encoded" that
        // json_decode reports. Floor at 4000 to leave headroom.
        $maxTokens  = max(4000, (int) getSetting('ai_max_tokens', '500'));
        // Mining mode pushes a much larger prompt; raise the timeout floor
        // proportionally so a slow API doesn't break the user-facing flow.
        $baseTimeout = max(15, (int) getSetting('ai_timeout_seconds', '5'));
        $timeoutSec  = $recentSubjects ? max($baseTimeout, 45) : $baseTimeout;

        $raw = $classifier->chat(
            $classifier->skillSuggestionSystemPrompt(),
            $classifier->skillSuggestionUserPrompt($orgTypeLabel, $ticketTypes, $groups, $existingSkills, $recentSubjects),
            $maxTokens,
            $timeoutSec
        );

        return $classifier->parseSkillSuggestions($raw, $existingNames, $groupNameToId);
    }
}
