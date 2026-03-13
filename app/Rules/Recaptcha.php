<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Recaptcha implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        $body = $response->json();

        if (!($body['success'] ?? false)) {
            $fail('The reCAPTCHA verification failed. Please try again.');
            return;
        }

        // Optional: check score for reCAPTCHA v3
        if (isset($body['score']) && $body['score'] < 0.5) {
            $fail('Suspicious activity detected. Please try again.');
        }
    }
}