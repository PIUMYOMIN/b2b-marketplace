<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Recaptcha implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty(config('services.recaptcha.secret_key'))) {
            Log::error('reCAPTCHA secret_key is not configured (services.recaptcha.secret_key).');
            $fail('Security verification is not configured. Please contact support.');
            return;
        }

        if (!is_string($value) || $value === '') {
            $fail('The reCAPTCHA verification failed. Please try again.');
            return;
        }

        $response = Http::timeout(10)->asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        $body = $response->json() ?? [];

        if (!($body['success'] ?? false)) {
            $codes = $body['error-codes'] ?? [];
            Log::warning('reCAPTCHA siteverify failed', [
                'error_codes' => $codes,
                'http_status' => $response->status(),
            ]);
            $fail('The reCAPTCHA verification failed. Please try again.');
            return;
        }

        // Optional: check score for reCAPTCHA v3
        if (isset($body['score']) && $body['score'] < 0.5) {
            $fail('Suspicious activity detected. Please try again.');
        }
    }
}