<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;

/**
 * Simple Gemini AI service wrapper.
 *
 * This service expects an environment variable GEMINI_API_KEY to be set.
 * It provides a method to send a short analysis prompt and return the AI's summary.
 */
class GeminiService
{
    private ?string $apiKey;
    private LoggerInterface $logger;

    public function __construct(?string $apiKey, LoggerInterface $logger)
    {
        $this->apiKey = $apiKey;
        $this->logger = $logger;
    }

    /**
     * Send league stats to Gemini and receive a short summary.
     * Returns a short string summary on success, or an error message on failure.
     *
     * @param array $stats Computed statistics/summary data
     * @return string
     */
    public function analyzeLeagueSummary(array $stats): string
    {
        if (empty($this->apiKey)) {
            $this->logger->warning('Gemini API key not configured.');
            return 'AI summary not available (API key missing).';
        }

        $prompt = $this->buildPrompt($stats);

        try {
            $response = $this->callGeminiApi($prompt);

            // Google Generative Models (and generativelanguage) commonly return a
            // 'candidates' array. For the generativelanguage generateContent API the
            // shape is: candidates[].content.parts[].text. Handle that first.
            if (isset($response['candidates']) && is_array($response['candidates']) && !empty($response['candidates'])) {
                $candidate = $response['candidates'][0];

                // generativelanguage: content -> parts -> text
                if (isset($candidate['content']) && is_array($candidate['content'])) {
                    $content = $candidate['content'];
                    if (isset($content['parts']) && is_array($content['parts']) && !empty($content['parts'])) {
                        $texts = [];
                        foreach ($content['parts'] as $part) {
                            if (is_array($part) && isset($part['text'])) {
                                $texts[] = $part['text'];
                            } elseif (is_string($part)) {
                                $texts[] = $part;
                            }
                        }
                        if (!empty($texts)) {
                            // join parts with real newlines so paragraphs survive
                            $joined = implode("\n", $texts);
                            // normalize CRLF to LF and trim
                            $joined = str_replace("\r\n", "\n", $joined);
                            return trim($joined);
                        }
                    }
                }

                // older generative models sometimes provide 'content' as a string
                if (isset($candidate['content']) && is_string($candidate['content'])) {
                    return trim($candidate['content']);
                }

                // legacy 'output' style
                if (isset($candidate['output']) && is_array($candidate['output'])) {
                    foreach ($candidate['output'] as $out) {
                        if (is_array($out) && isset($out['content']) && is_string($out['content'])) {
                            return trim($out['content']);
                        }
                    }
                }
            }

            // Fallbacks for other providers (OpenAI-like)
            if (isset($response['choices'][0]['text'])) {
                return trim($response['choices'][0]['text']);
            }

            $this->logger->warning('Unexpected Gemini/AI Studio response structure.', $response ?: []);
            return 'AI summary unavailable (unexpected response).';
        } catch (\Throwable $e) {
            $this->logger->error('Gemini API request failed: ' . $e->getMessage());
            return 'AI summary unavailable (request failed).';
        }
    }

    private function buildPrompt(array $stats): string
    {
        // Build a clear instruction: always respond in German, produce at least two paragraphs,
        // and reference some match notes when present. Provide a short dashboard-ready summary.
        $summaryParts = [];
        if (!empty($stats['top_players'])) {
            $summaryParts[] = 'Top players: ' . implode(', ', array_map(fn($p) => $p['name'] . ' (' . $p['points'] . ')', $stats['top_players']));
        }
        if (!empty($stats['hot_players'])) {
            $summaryParts[] = 'Hot streaks: ' . implode(', ', array_map(fn($p) => $p['name'] . ' (' . $p['streak'] . ')', $stats['hot_players']));
        }
        $recentCount = 0;
        if (!empty($stats['recent_matches'])) {
            $recentCount = count($stats['recent_matches']);
            $summaryParts[] = 'Recent matches: ' . $recentCount;
        }

        // Prepare a short sample of match notes to let the model reference them.
        // We prefer notes from the larger 'full_matches_context' if provided, but
        // fall back to the 'recent_matches' entries.
        $notes = [];
        $contextMatches = [];
        if (!empty($stats['full_matches_context']) && is_array($stats['full_matches_context'])) {
            $contextMatches = $stats['full_matches_context'];
        } elseif (!empty($stats['recent_matches']) && is_array($stats['recent_matches'])) {
            $contextMatches = $stats['recent_matches'];
        }

        if (!empty($contextMatches)) {
            // take up to 10 notes from the larger context to give the model more to work with
            $take = min(10, count($contextMatches));
            $slice = array_slice($contextMatches, 0, $take);
            foreach ($slice as $m) {
                // Expect match entries to possibly contain 'notes' or a textual summary.
                if (is_array($m) && !empty($m['notes'])) {
                    $notes[] = trim($m['notes']);
                } elseif (is_array($m) && isset($m['summary'])) {
                    $notes[] = trim($m['summary']);
                } else {
                    // Fallback: build a tiny line from players and score if available
                    if (is_array($m) && isset($m['players']) && isset($m['score'])) {
                        $notes[] = implode(' & ', $m['players']) . ' - ' . $m['score'];
                    } elseif (is_array($m) && isset($m['player1Id']) && isset($m['player2Id'])) {
                        $notes[] = ($m['player1Id'] ?? 'P1') . ' vs ' . ($m['player2Id'] ?? 'P2') . ' - ' . ($m['scorePlayer1'] ?? '') . ':' . ($m['scorePlayer2'] ?? '');
                    }
                }
            }
        }

        $body = implode("; ", $summaryParts);

        // Instruction to the model in German to ensure consistent output.
    $instruction = <<<'TXT'
Du bist ein energiegeladener Sportmoderator und Fan für ein Ligendashboard. Antworte ausnahmslos auf Deutsch in einem lebendigen, kommentierenden Ton (leicht fan‑artig, aber professionell). Schreibe mindestens zwei Absätze: Der erste Absatz soll eine prägnante Zusammenfassung der aktuellen Ligensituation (Top-Spieler, aktuelle Form/Hot‑Streaks, wichtigste Kennzahlen) liefern. Der zweite Absatz soll mindestens eine konkrete Beobachtung oder Empfehlung enthalten und sich dabei auf reale Spielnotizen stützen: Greife mindestens eine der folgenden Spielnotizen auf und beziehe dich konkret darauf.

WICHTIG: Die ELO-Skala startet bei 1000 als Ausgangswert. Beziehe bei deiner Bewertung die Anzahl der gespielten Matches mit ein: Spieler mit genau 1000 Punkten und 0 gespielten Matches gelten NICHT automatisch als herausragend. Wenn ein Spieler wenige oder keine Matches hat, weise darauf hin, dass die Bewertung aufgrund geringer Stichprobe mit Vorsicht zu genießen ist.

Optional: Verwende sparsam 1–4 Emojis zur optischen Hervorhebung (z. B. 🔥, 🏆, ⚠️, 📈). Du kannst außerdem kurze deutsche Zwischenüberschriften verwenden (z. B. "📊 Übersicht:", "🔥 Hot-Form:") — diese sollten sehr kurz sein und ebenfalls als reiner Text (keine HTML-Tags) erscheinen.

Gib nur lesbaren Fließtext zurück (kein JSON, keine zusätzlichen Metadaten). Verwende echte Absatztrenner (doppelter Newline) zwischen Absätzen. Halte die Gesamtlänge so, dass sie gut ins Dashboard passt.
TXT;


        $notesBlock = '';
        if (!empty($notes)) {
            $notesBlock = "Match notes (context, newest first):\n- " . implode("\n- ", $notes);
        }

        // Clarify to the model that it receives a larger context but should focus its
        // commentary on the most recent matches (the dashboard will display the most recent ones).
        $focusInstruction = "Wichtig: Du erhältst einen größeren Kontext mit vielen jüngsten Spielen. Beziehe dich in deiner Ausgabe primär auf die aktuellsten Spiele und nenne explizit zumindest ein Beispiel aus den neuesten Matches. Nutze die übrigen Spiele als Hintergrundwissen.";

        $prompt = $instruction . "\n\n" . $focusInstruction . "\n\nDashboard-Daten: " . $body . "\n\n" . $notesBlock . "\n\nAntwort:";

        return $prompt;
    }

    private function callGeminiApi(string $prompt): array
    {
        // Use Google Generative Models REST API (AI Studio / Vertex AI)
        // Model: text-bison-001 (text style). We use API key query param for simplicity.
        $configuredModel = getenv('GEMINI_MODEL') ?: null;

        // Preferred model list. Start with configured model (if any), then common Gemini models,
        // then legacy text-bison as last resort.
        $modelCandidates = [];
        if (!empty($configuredModel)) {
            $modelCandidates[] = $configuredModel;
        }
        // common Gemini family
        $modelCandidates = array_merge($modelCandidates, [
            'gemini-2.0-flash',
            'gemini-2.0',
            'gemini-1.5',
            'text-bison-001'
        ]);

        // Build endpoint+payload tries for each model candidate
        $tries = [];
        foreach ($modelCandidates as $model) {
            // generativelanguage generateContent endpoints (preferred for Gemini family)
            $tries[] = [
                'endpoint' => sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', $model, $this->apiKey),
                'payload' => [ 'contents' => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ] ]
            ];
            $tries[] = [
                'endpoint' => sprintf('https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', $model, $this->apiKey),
                'payload' => [ 'contents' => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ] ]
            ];

            // fall back to generativemodels generate endpoints used by text-bison style
            $tries[] = [
                'endpoint' => sprintf('https://generativemodels.googleapis.com/v1/models/%s:generate?key=%s', $model, $this->apiKey),
                'payload' => [ 'prompt' => [ 'text' => $prompt ], 'temperature' => 0.2, 'maxOutputTokens' => 256 ]
            ];
            $tries[] = [
                'endpoint' => sprintf('https://generativemodels.googleapis.com/v1beta2/models/%s:generate?key=%s', $model, $this->apiKey),
                'payload' => [ 'prompt' => [ 'text' => $prompt ], 'temperature' => 0.2, 'maxOutputTokens' => 256 ]
            ];
        }

        $attempts = 3;
        $lastException = null;
        foreach ($tries as $try) {
            $endpoint = $try['endpoint'];
            $payload = $try['payload'];

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $resp = curl_exec($ch);
                $err = curl_error($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($resp === false || $err) {
                    $lastException = new \RuntimeException('cURL error: ' . $err);
                    $this->logger->warning('GeminiService attempt ' . $attempt . ' failed: ' . $err);
                    $this->writeDebugLog(['endpoint' => $endpoint, 'attempt' => $attempt, 'error' => $err, 'code' => $code, 'resp' => $resp]);
                } else {
                    if ($code >= 200 && $code < 300) {
                        $data = json_decode($resp, true);
                        if ($data === null) {
                            $lastException = new \RuntimeException('Failed to decode Gemini response.');
                            $this->logger->warning('GeminiService invalid JSON response.');
                            $this->writeDebugLog(['endpoint' => $endpoint, 'attempt' => $attempt, 'error' => 'invalid_json', 'resp' => $resp]);
                        } else {
                            return $data;
                        }
                    } else {
                        $lastException = new \RuntimeException('Non-2xx response from Gemini API: ' . $code . ' - ' . $resp);
                        $this->logger->warning('GeminiService non-2xx: ' . $code);
                        $this->writeDebugLog(['endpoint' => $endpoint, 'attempt' => $attempt, 'error' => 'non_2xx', 'code' => $code, 'resp' => $resp]);
                        // If 404, try next endpoint variant immediately
                        if ($code === 404) {
                            break; // break attempt loop to try next endpoint variant
                        }
                    }
                }

                // Exponential backoff before retrying
                if ($attempt < $attempts) {
                    sleep((int) pow(2, $attempt));
                }
            }
            // continue to next try (endpoint+payload)
        }

        throw $lastException ?: new \RuntimeException('Gemini API request failed');
    }

    private function writeDebugLog(array $payload): void
    {
        try {
            $dataDir = __DIR__ . '/../../data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            $file = $dataDir . '/ai_summary_error.log';
            $entry = '[' . date('c') . '] ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }
}
