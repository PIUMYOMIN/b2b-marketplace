<?php
// app/Services/Payment/MMQRGateway.php
// Myanmar Mobile QR (MMQR) payment gateway.
// MMQR is the CBM-mandated interoperable QR standard — scan with any
// Myanmar mobile banking app (KBZPay, WaveMoney, AYA, CB Pay, etc.)
//
// Set in .env:
//   MMQR_MERCHANT_ID=
//   MMQR_MERCHANT_KEY=
//   MMQR_API_URL=https://api.mmqr.com.mm/v1   (replace with actual endpoint)
//   MMQR_WEBHOOK_SECRET=

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MMQRGateway implements PaymentGatewayInterface
{
    private string $merchantId;
    private string $merchantKey;
    private string $apiUrl;
    private string $webhookSecret;

    public function __construct()
    {
        $this->merchantId    = config('services.mmqr.merchant_id', '');
        $this->merchantKey   = config('services.mmqr.merchant_key', '');
        $this->apiUrl        = config('services.mmqr.api_url', 'https://api.mmqr.com.mm/v1');
        $this->webhookSecret = config('services.mmqr.webhook_secret', '');
    }

    public function getName(): string { return 'MMQR'; }

    public function initiatePayment(
        float $amount,
        string $currency,
        string $orderNumber,
        array $metadata = []
    ): array {
        // If credentials are not configured, return sandbox/demo response
        if (empty($this->merchantId)) {
            return $this->sandboxResponse($amount, $orderNumber);
        }

        try {
            $reference = 'MMQR-' . strtoupper(Str::random(12));
            $payload   = [
                'merchant_id'  => $this->merchantId,
                'reference'    => $reference,
                'amount'       => (int) round($amount),   // MMK is integer-based
                'currency'     => 'MMK',
                'order_number' => $orderNumber,
                'description'  => $metadata['description'] ?? "Order {$orderNumber}",
                'callback_url' => url('/api/v1/webhooks/mmqr'),
                'expires_in'   => 900,   // 15 minutes
            ];

            $signature = $this->sign($payload);
            $response  = Http::timeout(10)
                ->withHeaders([
                    'X-Merchant-ID'  => $this->merchantId,
                    'X-Signature'    => $signature,
                    'Content-Type'   => 'application/json',
                ])
                ->post("{$this->apiUrl}/qr/create", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success'      => true,
                    'reference'    => $reference,
                    'gateway_ref'  => $data['transaction_id'] ?? $reference,
                    'qr_string'    => $data['qr_string'] ?? null,
                    'qr_image_url' => $data['qr_image_url'] ?? null,
                    'expires_at'   => now()->addMinutes(15)->toIso8601String(),
                ];
            }

            Log::error('MMQR initiate failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => 'Payment gateway error. Please try again.'];

        } catch (\Exception $e) {
            Log::error('MMQR exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Could not connect to payment gateway.'];
        }
    }

    public function verifyPayment(string $reference): array
    {
        if (empty($this->merchantId)) {
            // Sandbox: simulate paid after first verification
            return [
                'success'    => true,
                'paid'       => true,
                'gateway_ref'=> $reference,
                'amount'     => 0,
                'raw'        => ['sandbox' => true],
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Merchant-ID' => $this->merchantId])
                ->get("{$this->apiUrl}/qr/status/{$reference}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success'     => true,
                    'paid'        => ($data['status'] ?? '') === 'paid',
                    'gateway_ref' => $data['transaction_id'] ?? $reference,
                    'amount'      => (float) ($data['amount'] ?? 0),
                    'raw'         => $data,
                ];
            }
            return ['success' => false, 'paid' => false, 'message' => 'Verification failed.'];
        } catch (\Exception $e) {
            Log::error('MMQR verify exception: ' . $e->getMessage());
            return ['success' => false, 'paid' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        // Verify webhook signature
        $expected = $this->sign($payload);
        if (!hash_equals($expected, $signature)) {
            Log::warning('MMQR webhook signature mismatch');
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        return [
            'success'     => true,
            'reference'   => $payload['reference'] ?? null,
            'paid'        => ($payload['status'] ?? '') === 'paid',
            'gateway_ref' => $payload['transaction_id'] ?? null,
            'amount'      => (float) ($payload['amount'] ?? 0),
            'raw'         => $payload,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function sign(array $payload): string
    {
        ksort($payload);
        $string = implode('|', array_map(
            fn($k, $v) => "{$k}={$v}",
            array_keys($payload), array_values($payload)
        ));
        return hash_hmac('sha256', $string, $this->merchantKey);
    }

    private function sandboxResponse(float $amount, string $orderNumber): array
    {
        $ref = 'MMQR-SANDBOX-' . strtoupper(Str::random(8));
        // Generate a real-looking QR using a public service for development only
        $qrData = urlencode("PYONEA|{$ref}|{$amount}|MMK");
        return [
            'success'      => true,
            'reference'    => $ref,
            'gateway_ref'  => $ref,
            'qr_string'    => "PYONEA|{$ref}|{$amount}|MMK",
            'qr_image_url' => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$qrData}",
            'expires_at'   => now()->addMinutes(15)->toIso8601String(),
            'sandbox'      => true,
        ];
    }
}