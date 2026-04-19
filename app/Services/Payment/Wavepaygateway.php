<?php
// app/Services/Payment/WavePayGateway.php
// Wave Money payment gateway.
// Docs: https://developer.wavemoney.com.mm
//
// Set in .env:
//   WAVEPAY_MERCHANT_ID=
//   WAVEPAY_SECRET_KEY=
//   WAVEPAY_API_URL=https://api.wavemoney.com.mm/payment  (sandbox: /sandbox/payment)
//   WAVEPAY_WEBHOOK_SECRET=

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WavePayGateway implements PaymentGatewayInterface
{
    private string $merchantId;
    private string $secretKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->merchantId = config('services.wavepay.merchant_id', '');
        $this->secretKey  = config('services.wavepay.secret_key', '');
        $this->apiUrl     = config('services.wavepay.api_url',
            'https://api.wavemoney.com.mm/sandbox/payment');
    }

    public function getName(): string { return 'WavePay'; }

    public function initiatePayment(
        float $amount,
        string $currency,
        string $orderNumber,
        array $metadata = []
    ): array {
        if (empty($this->merchantId)) {
            return $this->sandboxResponse($amount, $orderNumber);
        }

        try {
            $reference = 'WAVE-' . $orderNumber . '-' . time();
            $payload   = [
                'timestamp'        => now()->format('Y-m-d H:i:s'),
                'merchant_id'      => $this->merchantId,
                'order_id'         => $reference,
                'amount'           => (int) round($amount),
                'callback_url'     => url('/api/v1/webhooks/wavepay'),
                'merchant_name'    => config('app.name', 'Pyonea'),
                'payment_description' => "Order {$orderNumber}",
                'merchant_deep_link'  => url('/payment-success'),
                'items' => json_encode($metadata['items'] ?? [
                    ['name' => "Order {$orderNumber}", 'amount' => (int) round($amount)]
                ]),
            ];
            $payload['hash'] = $this->hash($payload);

            $response = Http::timeout(15)
                ->post("{$this->apiUrl}/initiate", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success'    => true,
                    'reference'  => $reference,
                    'gateway_ref'=> $data['transaction_id'] ?? $reference,
                    'deep_link'  => $data['deep_link'] ?? null,
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                ];
            }
            return ['success' => false, 'message' => 'WavePay error'];
        } catch (\Exception $e) {
            Log::error('WavePay initiate: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment gateway error.'];
        }
    }

    public function verifyPayment(string $reference): array
    {
        if (empty($this->merchantId)) {
            return ['success' => true, 'paid' => true, 'gateway_ref' => $reference, 'amount' => 0, 'raw' => []];
        }

        try {
            $response = Http::timeout(10)->get("{$this->apiUrl}/check/{$reference}", [
                'merchant_id' => $this->merchantId,
            ]);
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success'     => true,
                    'paid'        => ($data['status'] ?? '') === 'success',
                    'gateway_ref' => $data['transaction_id'] ?? $reference,
                    'amount'      => (float) ($data['amount'] ?? 0),
                    'raw'         => $data,
                ];
            }
            return ['success' => false, 'paid' => false, 'message' => 'Query failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'paid' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        // WavePay sends hash in payload — verify it
        $computed = $this->hash(array_diff_key($payload, ['hash' => '']));
        if (!hash_equals($computed, $payload['hash'] ?? '')) {
            return ['success' => false, 'message' => 'Bad signature'];
        }
        return [
            'success'     => true,
            'reference'   => $payload['order_id'] ?? null,
            'paid'        => ($payload['status'] ?? '') === 'success',
            'gateway_ref' => $payload['transaction_id'] ?? null,
            'amount'      => (float) ($payload['amount'] ?? 0),
            'raw'         => $payload,
        ];
    }

    private function hash(array $params): string
    {
        ksort($params);
        $str = implode('', array_map(
            fn($k, $v) => (string) $v,
            array_keys($params), array_values($params)
        ));
        return hash_hmac('sha256', $str, $this->secretKey);
    }

    private function sandboxResponse(float $amount, string $orderNumber): array
    {
        $ref = 'WAVE-SANDBOX-' . strtoupper(Str::random(8));
        return [
            'success'    => true,
            'reference'  => $ref,
            'gateway_ref'=> $ref,
            'deep_link'  => "wavemoney://payment?ref={$ref}",
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
            'sandbox'    => true,
        ];
    }
}