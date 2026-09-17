<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
    ) {}

    public static function available(): bool
    {
        return config('services.openai.key') !== null && config('services.openai.key') !== '';
    }

    /**
     * Chat completion returning plain text.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, ?int $maxTokens = 400): string
    {
        if (! self::available()) {
            throw new \RuntimeException('AI is not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(45)
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => 0.8,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('AI request failed: '.$response->status());
        }

        return trim((string) ($response->json('choices.0.message.content') ?? ''));
    }

    /**
     * @return array{risk: float, reasons: string[], suggestion: string}
     */
    public function moderate(string $text): array
    {
        if (! self::available()) {
            return $this->heuristicModerate($text);
        }

        try {
            $raw = $this->chat([
                ['role' => 'system', 'content' => 'You are a content moderation assistant for a social network. Score the risk of the text on a scale of 0.0 to 1.0, list short reasons (max 3), and suggest one action: allow, review, or remove. Reply ONLY with JSON: {"risk": <number>, "reasons": [...], "suggestion": "allow|review|remove"}. When unsure, say review.'],
                ['role' => 'user', 'content' => mb_substr($text, 0, 1500)],
            ], 200);

            $json = json_decode(trim($raw, " \n\r\t\"`"), true);
            if (! is_array($json) || ! isset($json['risk'])) {
                return $this->heuristicModerate($text);
            }
            return [
                'risk' => min(1.0, max(0.0, (float) $json['risk'])),
                'reasons' => array_slice(array_map('strval', $json['reasons'] ?? []), 0, 3),
                'suggestion' => in_array($json['suggestion'] ?? '', ['allow', 'review', 'remove']) ? $json['suggestion'] : 'review',
            ];
        } catch (\Throwable) {
            return $this->heuristicModerate($text);
        }
    }

    /**
     * Keyword heuristic fallback when no AI key is configured.
     *
     * @return array{risk: float, reasons: string[], suggestion: string}
     */
    public function heuristicModerate(string $text): array
    {
        $lower = mb_strtolower($text);
        $high = ['kill yourself', 'kys', 'send nudes', 'i will find you', 'child porn', 'terrorist attack'];
        $medium = ['idiot', 'stupid', 'hate you', 'scam', 'buy followers', 'crypto giveaway', 'free money'];

        foreach ($high as $phrase) {
            if (str_contains($lower, $phrase)) {
                return ['risk' => 0.9, 'reasons' => ['matches harmful-language patterns'], 'suggestion' => 'remove'];
            }
        }
        foreach ($medium as $phrase) {
            if (str_contains($lower, $phrase)) {
                return ['risk' => 0.5, 'reasons' => ['matches spam/insult patterns'], 'suggestion' => 'review'];
            }
        }
        return ['risk' => 0.05, 'reasons' => [], 'suggestion' => 'allow'];
    }
}
