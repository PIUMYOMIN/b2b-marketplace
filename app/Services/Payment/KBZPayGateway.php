<?php
// app/Services/Payment/KBZPayGateway.php
// KBZ Pay gateway (Myanmar's largest mobile wallet).
// Docs: https://developers.kbzpay.com
//
// Set in .env:
//   KBZPAY_APP_ID=
//   KBZPAY_APP_KEY=
//   KBZPAY_MERCHANT_CODE=
//   KBZPAY_API_URL=https://api.kbzpay.com/payment/gateway/uat  (uat → prod: /payment/gateway)
//   KBZPAY_WEBHOOK_SECRET=

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KBZPayGateway implements PaymentGatewayInterface
{
    private string $appId;
    private string $appKey;
    private string $merchantCode;
    private string $apiUrl;

    public function __construct()
    {
        $this->appId        = config('services.kbzpay.app_id', '');
        $this->appKey       = config('services.kbzpay.app_key', '');
        $this->merchantCode = config('services.kbzpay.merchant_code', '');
        $this->apiUrl       = config('services.kbzpay.api_url',
            'https://api.kbzpay.com/payment/gateway/uat');
    }

    public function getName(): string { return 'KBZPay'; }

    public function initiatePayment(
        float $amount,
        string $currency,
        string $orderNumber,
        array $metadata = []
    ): array {
        if (empty($this->appId)) {
            return $this->sandboxResponse($amount, $orderNumber);
        }

        try {
            $merTradeNo = 'KBZ-' . $orderNumber . '-' . time();
            $timestamp  = now()->format('YmdHis');

            $params = [
                'appid'        => $this->appId,
                'merch_code'   => $this->merchantCode,
                'merch_order_id' => $merTradeNo,
                'trade_type'   => 'PWAAPP',   // PWA + deep-link
                'total_amount' => (string) (int) round($amount),
                'trans_currency' => 'MMK',
                'title'        => "Pyonea Order {$orderNumber}",
                'callback_url' => url('/api/v1/webhooks/kbzpay'),
                'return_url'   => url('/payment-success'),
                'timestamp'    => $timestamp,
            ];
            $params['sign'] = $this->sign($params);

            $response = Http::timeout(15)
                ->asForm()
                ->post("{$this->apiUrl}/preCreate", $params);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['Response']['code'] ?? '') === '0') {
                    $prepayId = $data['Response']['prepay_id'] ?? null;
                    return [
                        'success'     => true,
                        'reference'   => $merTradeNo,
                        'gateway_ref' => $prepayId,
                        'deep_link'   => "kbzpay://payment?prepay_id={$prepayId}",
                        'expires_at'  => now()->addMinutes(15)->toIso8601String(),
                    ];
                }
                return [
                    'success' => false,
                    'message' => $data['Response']['msg'] ?? 'KBZPay error',
                ];
            }
            return ['success' => false, 'message' => 'Gateway unreachable'];
        } catch (\Exception $e) {
            Log::error('KBZPay initiate: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment gateway error.'];
        }
    }

    public function verifyPayment(string $reference): array
    {
        if (empty($this->appId)) {
            return ['success' => true, 'paid' => true, 'gateway_ref' => $reference, 'amount' => 0, 'raw' => []];
        }

        try {
            $params = [
                'appid'          => $this->appId,
                'merch_code'     => $this->merchantCode,
                'merch_order_id' => $reference,
                'timestamp'      => now()->format('YmdHis'),
            ];
            $params['sign'] = $this->sign($params);

            $response = Http::timeout(10)
                ->asForm()
                ->post("{$this->apiUrl}/query", $params);

            if ($response->successful()) {
                $data   = $response->json();
                $resp   = $data['Response'] ?? [];
                $status = $resp['trade_status'] ?? '';
                return [
                    'success'     => true,
                    'paid'        => $status === 'PAY_SUCCESS',
                    'gateway_ref' => $resp['kyc_reference'] ?? $reference,
                    'amount'      => (float) ($resp['total_amount'] ?? 0),
                    'raw'         => $resp,
                ];
            }
            return ['success' => false, 'paid' => false, 'message' => 'Query failed'];
        } catch (\Exception $e) {
            Log::error('KBZPay verify: ' . $e->getMessage());
            return ['success' => false, 'paid' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        $computed = $this->sign($payload);
        if (!hash_equals($computed, $signature)) {
            return ['success' => false, 'message' => 'Bad signature'];
        }
        $status = $payload['trade_status'] ?? $payload['Response']['trade_status'] ?? '';
        return [
            'success'     => true,
            'reference'   => $payload['merch_order_id'] ?? null,
            'paid'        => $status === 'PAY_SUCCESS',
            'gateway_ref' => $payload['kyc_reference'] ?? null,
            'amount'      => (float) ($payload['total_amount'] ?? 0),
            'raw'         => $payload,
        ];
    }

    private function sign(array $params): string
    {
        ksort($params);
        $str = implode('&', array_map(
            fn($k, $v) => "{$k}={$v}",
            array_keys($params), array_values($params)
        ));
        return strtoupper(md5($str . $this->appKey));
    }

    private function sandboxResponse(float $amount, string $orderNumber): array
    {
        $ref = 'KBZ-SANDBOX-' . strtoupper(Str::random(8));
        return [
            'success'     => true,
            'reference'   => $ref,
            'gateway_ref' => $ref,
            'deep_link'   => "kbzpay://payment?prepay_id=SANDBOX_{$ref}",
            'expires_at'  => now()->addMinutes(15)->toIso8601String(),
            'sandbox'     => true,
        ];
    }
}