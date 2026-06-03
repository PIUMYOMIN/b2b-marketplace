<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use MMPay\MMPay;

class MMQRGateway implements PaymentGatewayInterface
{
    private string $appId;
    private string $publicKey;
    private string $secretKey;
    private string $apiUrl;
    private bool $sandbox;

    public function __construct()
    {
        $this->appId     = (string) config('services.mmqr.app_id', '');
        $this->publicKey = (string) (config('services.mmqr.public_key') ?: config('services.mmqr.merchant_id', ''));
        $this->secretKey = (string) (config('services.mmqr.secret_key') ?: config('services.mmqr.merchant_key', ''));
        $this->apiUrl    = rtrim((string) config('services.mmqr.api_url', ''), '/');
        $this->sandbox   = str_starts_with($this->publicKey, 'pk_test_');
    }

    public function getName(): string
    {
        return 'MyanMyanPay MMQR';
    }

    public function initiatePayment(
        float $amount,
        string $currency,
        string $orderNumber,
        array $metadata = []
    ): array {
        if ($this->missingConfig()) {
            return $this->localResponse($amount, $orderNumber);
        }

        try {
            $payload = [
                'orderId'       => $orderNumber,
                'amount'        => (int) round($amount),
                'currency'      => $currency ?: 'MMK',
                'callbackUrl'   => url('/api/v1/webhooks/mmqr'),
                'customMessage' => $metadata['description'] ?? "Pyonea order {$orderNumber}",
                'items'         => $metadata['items'] ?? [[
                    'name'     => "Order {$orderNumber}",
                    'amount'   => (int) round($amount),
                    'quantity' => 1,
                ]],
            ];

            $data = $this->sandbox
                ? $this->client()->sandboxPay($payload)
                : $this->client()->pay($payload);

            $qr = $data['qr'] ?? $data['qrImage'] ?? $data['qr_image'] ?? null;

            return [
                'success'      => true,
                'reference'    => $data['orderId'] ?? $orderNumber,
                'gateway_ref'  => $data['transactionRefId'] ?? $data['transaction_id'] ?? $data['orderId'] ?? $orderNumber,
                'qr_string'    => $data['qrString'] ?? $data['qr_string'] ?? null,
                'qr_image_url' => $this->normalizeQrImage($qr),
                'expires_at'   => now()->addMinutes(15)->toIso8601String(),
                'raw'          => $data,
                'sandbox'      => $this->sandbox,
            ];
        } catch (\Throwable $e) {
            Log::error('MyanMyanPay MMQR initiate failed: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
            ]);

            return [
                'success' => false,
                'message' => 'Could not generate the MMQR payment. Please try again.',
            ];
        }
    }

    public function verifyPayment(string $reference): array
    {
        return [
            'success'     => true,
            'paid'        => false,
            'gateway_ref' => $reference,
            'amount'      => 0,
            'raw'         => ['message' => 'Waiting for MyanMyanPay callback.'],
        ];
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        if ($this->missingConfig()) {
            return ['success' => false, 'message' => 'MMQR gateway is not configured'];
        }

        $rawPayload = (string) ($payload['__raw'] ?? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $nonce = (string) ($payload['__nonce'] ?? '');

        if (! $this->client()->verifyCb($rawPayload, $nonce, $signature)) {
            Log::warning('MyanMyanPay MMQR webhook signature mismatch');
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        unset($payload['__raw'], $payload['__nonce']);
        $status = strtoupper((string) ($payload['status'] ?? ''));

        return [
            'success'     => true,
            'reference'   => $payload['orderId'] ?? null,
            'paid'        => $status === 'SUCCESS',
            'gateway_ref' => $payload['transactionRefId'] ?? null,
            'amount'      => (float) ($payload['amount'] ?? 0),
            'raw'         => $payload,
        ];
    }

    private function client(): MMPay
    {
        return new MMPay([
            'appId'          => $this->appId,
            'publishableKey' => $this->publicKey,
            'secretKey'      => $this->secretKey,
            'apiBaseUrl'     => $this->apiUrl,
        ]);
    }

    private function missingConfig(): bool
    {
        return $this->appId === ''
            || $this->publicKey === ''
            || $this->secretKey === ''
            || $this->apiUrl === '';
    }

    private function normalizeQrImage(?string $qr): ?string
    {
        if (! $qr) {
            return null;
        }

        if (str_starts_with($qr, 'http://') || str_starts_with($qr, 'https://') || str_starts_with($qr, 'data:image/')) {
            return $qr;
        }

        return 'data:image/png;base64,' . $qr;
    }

    private function localResponse(float $amount, string $orderNumber): array
    {
        $qrString = "PYONEA|LOCAL-MMQR|{$orderNumber}|{$amount}|MMK";

        return [
            'success'      => true,
            'reference'    => $orderNumber,
            'gateway_ref'  => $orderNumber,
            'qr_string'    => $qrString,
            'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString),
            'expires_at'   => now()->addMinutes(15)->toIso8601String(),
            'sandbox'      => true,
            'raw'          => ['local_fallback' => true],
        ];
    }
}
