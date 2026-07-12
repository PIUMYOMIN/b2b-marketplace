<?php

namespace App\Services\Messaging;

class MessageSanitizer
{
    /** @var list<string> */
    private array $patterns = [
        '/\b(?:whatsapp|wa\.me|telegram|t\.me|viber|line\.me|messenger|signal\.org)\b/i',
        '/(?:https?:\/\/)?(?:www\.)?(?:wa\.me|t\.me|telegram\.me|m\.me)\/[^\s]+/i',
        '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
        '/(?:\+?95|0)?9[\d\s\-]{7,12}/',
    ];

    /**
     * @return array{body: string, flags: list<string>, blocked: bool}
     */
    public function sanitize(string $body): array
    {
        $flags = [];
        $sanitized = $body;

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $sanitized)) {
                $flags[] = $this->flagForPattern($pattern);
                $sanitized = preg_replace(
                    $pattern,
                    '[removed — use Pyonea messaging only]',
                    $sanitized
                ) ?? $sanitized;
            }
        }

        $sanitized = trim(preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized);

        return [
            'body' => $sanitized,
            'flags' => array_values(array_unique($flags)),
            'blocked' => trim($sanitized) === '' || trim($sanitized) === '[removed — use Pyonea messaging only]',
        ];
    }

    private function flagForPattern(string $pattern): string
    {
        if (str_contains($pattern, 'whatsapp') || str_contains($pattern, 'wa.me')) {
            return 'external_messaging_app';
        }
        if (str_contains($pattern, 'telegram') || str_contains($pattern, 't.me')) {
            return 'external_messaging_app';
        }
        if (str_contains($pattern, '@')) {
            return 'email_address';
        }

        return 'phone_number';
    }
}
