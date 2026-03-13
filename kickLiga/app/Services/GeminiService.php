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

            $text = $this->extractTextFromResponse($response);
            if ($text !== null) {
                return $text;
            }

            $this->logger->warning('Unexpected Gemini/AI Studio response structure.', $response ?: []);
            return 'AI summary unavailable (unexpected response).';
        } catch (\Throwable $e) {
            $this->logger->error('Gemini API request failed: ' . $e->getMessage());
            
            // Provide more specific error messages based on exception content
            $errorMsg = $e->getMessage();
            if (stripos($errorMsg, '429') !== false || stripos($errorMsg, 'quota') !== false) {
                return 'AI summary unavailable (API quota exceeded - check billing or wait until reset).';
            } elseif (stripos($errorMsg, '503') !== false || stripos($errorMsg, 'overloaded') !== false) {
                return 'AI summary unavailable (Google AI servers overloaded - try again later).';
            } elseif (stripos($errorMsg, '404') !== false) {
                return 'AI summary unavailable (model not found - check configuration).';
            } elseif (stripos($errorMsg, 'timeout') !== false || stripos($errorMsg, 'timed out') !== false) {
                return 'AI summary unavailable (request timeout - slow network or overloaded servers).';
            }
            
            return 'AI summary unavailable (API error: ' . substr($errorMsg, 0, 100) . ')';
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
Du bist ein energiegeladener Sportmoderator und Fan für ein Ligendashboard. Antworte ausnahmslos auf Deutsch in einem lebendigen, kommentierenden Ton (leicht fan‑artig, aber professionell).

WICHTIG: Gib exakt drei Absätze zurück (genau 3). Verwende echte Absatztrenner (doppelter Newline) zwischen den Absätzen. Struktur:
- Erster Absatz: Kurze Einleitung zur aktuellen Ligensituation UND eine eingehende, konkrete Besprechung des NEUESTEN (aktuellsten) Spiels — beschreibe Ergebnis, auffällige Spielszenen/Notizen und was daraus folgt.
- Zweiter Absatz: Kurze Übersicht / Kontext (Top‑Spieler, Hot‑Streaks, Trends) — nutze die bereitgestellten Match‑Notizen als Hintergrund.
- Dritter Absatz: Konkrete Empfehlung oder Fazit (Handlungsoptionen, auf was Teams/Spieler achten sollten).

WICHTIG: Die ELO‑Skala startet bei 1000 als Ausgangswert. Berücksichtige die Anzahl gespielter Matches: Spieler mit genau 1000 Punkten und 0 gespielten Matches sind nicht automatisch herausragend; weise auf geringe Stichprobenqualität hin, wenn relevant.

Optional: Verwende sparsam 1–4 Emojis zur optischen Hervorhebung (z. B. 🔥, 🏆, ⚠️, 📈). Du kannst kurze deutsche Zwischenüberschriften verwenden (z. B. "📊 Übersicht:", "🔥 Hot-Form:"), aber bleibe bei einfachem Text (keine HTML‑Tags).

Style‑Erweiterung für Tischkicker: Verwende gelegentlich moderne, lockere Sprache und tischkicker bezogene jokes / wortwitze (z. B. das Thema "kurbeln"). Solche Sprüche sollen RAR sein — maximal 0–2 pro Text — und humorvoll, nicht beleidigend. Beispiele (nur zur Orientierung, soll nicht wortwörtlich zwingend verwendet werden): "hat bodenlos gekurbelt", "gottlose Kurblerei", "der König der Kurbel". Verwende diese Ausdrücke sehr sparsam und nur wenn sie kontextuell passen - aber auf keinen fall als einzelner satz stehen

Gib nur reinen Fließtext zurück (kein JSON, keine Metadaten). Halte die Gesamtlänge dashboard‑freundlich.
TXT;


        $notesBlock = '';
        if (!empty($notes)) {
            $notesBlock = "Match notes (context, newest first):\n- " . implode("\n- ", $notes);
        }

        // Build a strictly formatted block describing the single newest match so the model
        // cannot misidentify which one is the latest. We prefer 'recent_matches' first.
        $newestBlock = '';
        $newest = null;
        if (!empty($stats['recent_matches']) && is_array($stats['recent_matches'])) {
            $newest = $stats['recent_matches'][0];
        } elseif (!empty($stats['full_matches_context']) && is_array($stats['full_matches_context'])) {
            $newest = $stats['full_matches_context'][0];
        }

        if (is_array($newest)) {
            $nmPlayer1 = $newest['player1Name'] ?? ($newest['player1Id'] ?? 'P1');
            $nmPlayer2 = $newest['player2Name'] ?? ($newest['player2Id'] ?? 'P2');
            $nmScore = (isset($newest['scorePlayer1']) || isset($newest['scorePlayer2'])) ? (($newest['scorePlayer1'] ?? '') . ':' . ($newest['scorePlayer2'] ?? '')) : ($newest['score'] ?? '');
            $nmDate = $newest['playedAt'] ?? ($newest['date'] ?? 'unbekannt');
            $nmNotes = 'Keine Notizen.';
            if (!empty($newest['notes'])) {
                $nmNotes = trim($newest['notes']);
            } elseif (!empty($newest['summary'])) {
                $nmNotes = trim($newest['summary']);
            }

            $newestBlock = "NEUESTES_SPIEL:\nDate: " . $nmDate . "\nPlayers: " . $nmPlayer1 . " vs " . $nmPlayer2 . "\nScore: " . $nmScore . "\nNotes: " . $nmNotes . "\n";
        } else {
            $newestBlock = "NEUESTES_SPIEL:\nKein aktuelles Spiel im Kontext.\n";
        }

        // Clarify to the model that it receives a larger context but should focus its
        // commentary on the most recent matches (the dashboard will display the most recent ones).
    $focusInstruction = "Wichtig: Du erhältst einen größeren Kontext mit vielen jüngsten Spielen. Der erste Absatz MUSS sich ausdrücklich auf das hier eindeutig markierte 'NEUESTES_SPIEL' beziehen (siehe Block 'NEUESTES_SPIEL' weiter unten). Beziehe dich in deiner Ausgabe primär auf dieses aktuellste Spiel und nenne explizit Details daraus. Nutze die übrigen Spiele als Hintergrundwissen.";

    // Assemble prompt: include the explicit newest match block so the model cannot
    // mistake which match is the newest.
    $prompt = $instruction . "\n\n" . $focusInstruction . "\n\nDashboard-Daten: " . $body . "\n\n" . $newestBlock . "\n" . $notesBlock . "\n\nAntwort:";

        return $prompt;
    }

    private function callGeminiApi(string $prompt): array
    {
        $configuredModel = getenv('GEMINI_MODEL') ?: null;

        // Prefer stable, production-ready Gemini models. Preview or experimental
        // variants often have stricter quotas and are more likely to trigger 429s.
        $modelCandidates = [
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite'
        ];

        if (!empty($configuredModel)) {
            if (in_array($configuredModel, $modelCandidates, true)) {
                array_unshift($modelCandidates, $configuredModel);
            } else {
                $this->logger->warning('Configured Gemini model is not part of the stable default list and will only be used after stable fallbacks.', [
                    'configured_model' => $configuredModel
                ]);
                $modelCandidates[] = $configuredModel;
            }
        }

        $modelCandidates = array_values(array_unique($modelCandidates));

        $attempts = 3;
        $lastException = null;
        foreach ($modelCandidates as $model) {
            $tries = [
                [
                    'endpoint' => sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', $model, $this->apiKey),
                    'payload' => [
                        'contents' => [[
                            'parts' => [[
                                'text' => $prompt
                            ]]
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 256
                        ]
                    ]
                ],
                [
                    'endpoint' => sprintf('https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', $model, $this->apiKey),
                    'payload' => [
                        'contents' => [[
                            'parts' => [[
                                'text' => $prompt
                            ]]
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 256
                        ]
                    ]
                ]
            ];

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
                    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

                    $resp = curl_exec($ch);
                    $err = curl_error($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($resp === false || $err) {
                        $lastException = new \RuntimeException('cURL error: ' . $err);
                        $this->logger->warning('GeminiService attempt ' . $attempt . ' failed: ' . $err);
                        $this->writeDebugLog([
                            'model' => $model,
                            'endpoint' => $endpoint,
                            'attempt' => $attempt,
                            'error' => $err,
                            'code' => $code,
                            'resp' => $resp
                        ]);
                    } else {
                        if ($code >= 200 && $code < 300) {
                            $data = json_decode($resp, true);
                            if ($data === null) {
                                $lastException = new \RuntimeException('Failed to decode Gemini response.');
                                $this->logger->warning('GeminiService invalid JSON response.');
                                $this->writeDebugLog([
                                    'model' => $model,
                                    'endpoint' => $endpoint,
                                    'attempt' => $attempt,
                                    'error' => 'invalid_json',
                                    'resp' => $resp
                                ]);
                            } else {
                                $text = $this->extractTextFromResponse($data);
                                if ($text !== null && $this->isSummarySuspiciouslyShort($text)) {
                                    $this->logger->warning('GeminiService returned suspiciously short text, trying next fallback.', [
                                        'model' => $model,
                                        'text_length' => strlen($text)
                                    ]);
                                    $this->writeDebugLog([
                                        'model' => $model,
                                        'endpoint' => $endpoint,
                                        'attempt' => $attempt,
                                        'error' => 'suspiciously_short_success',
                                        'text_length' => strlen($text),
                                        'text_preview' => $text,
                                        'finish_reason' => $data['candidates'][0]['finishReason'] ?? null,
                                        'usage_metadata' => $data['usageMetadata'] ?? null,
                                        'resp' => $resp
                                    ]);
                                    continue 3;
                                }

                                return $data;
                            }
                        } else {
                            $lastException = new \RuntimeException('Non-2xx response from Gemini API: ' . $code . ' - ' . $resp);
                            $this->logger->warning('GeminiService non-2xx: ' . $code);
                            $this->writeDebugLog([
                                'model' => $model,
                                'endpoint' => $endpoint,
                                'attempt' => $attempt,
                                'error' => 'non_2xx',
                                'code' => $code,
                                'resp' => $resp
                            ]);

                            // Model-specific rate limits should not block stable fallbacks.
                            if ($code === 429 || $code === 503) {
                                continue 3;
                            }

                            // Try the next endpoint variant for unsupported model versions.
                            if ($code === 404) {
                                break;
                            }
                        }
                    }

                    if ($attempt < $attempts) {
                        sleep((int) pow(2, $attempt));
                    }
                }
            }
        }

        throw $lastException ?: new \RuntimeException('Gemini API request failed');
    }

    private function extractTextFromResponse(array $response): ?string
    {
        if (isset($response['candidates']) && is_array($response['candidates']) && !empty($response['candidates'])) {
            $candidate = $response['candidates'][0];

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
                        $joined = implode("\n", $texts);
                        $joined = str_replace("\r\n", "\n", $joined);

                        return trim($joined);
                    }
                }
            }

            if (isset($candidate['content']) && is_string($candidate['content'])) {
                return trim($candidate['content']);
            }

            if (isset($candidate['output']) && is_array($candidate['output'])) {
                foreach ($candidate['output'] as $out) {
                    if (is_array($out) && isset($out['content']) && is_string($out['content'])) {
                        return trim($out['content']);
                    }
                }
            }
        }

        if (isset($response['choices'][0]['text'])) {
            return trim($response['choices'][0]['text']);
        }

        return null;
    }

    private function isSummarySuspiciouslyShort(string $text): bool
    {
        return strlen(trim($text)) < 80;
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
