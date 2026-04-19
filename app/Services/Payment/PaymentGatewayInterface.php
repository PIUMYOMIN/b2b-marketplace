<?php
// app/Services/Payment/PaymentGatewayInterface.php
// All payment gateways must implement this contract.

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment session.
     * Returns an array containing at minimum:
     *   - 'success'       bool
     *   - 'reference'     string   — internal reference to poll/verify
     *   - 'gateway_ref'   string   — gateway's own transaction ID
     *   - 'qr_string'     string?  — raw QR data (for MMQR / CB Pay)
     *   - 'qr_image_url'  string?  — hosted QR image URL
     *   - 'deep_link'     string?  — mobile deep-link URI (for KBZPay, WavePay)
     *   - 'expires_at'    string   — ISO-8601 expiry time
     *   - 'message'       string?  — error message when success=false
     */
    public function initiatePayment(
        float $amount,
        string $currency,
        string $orderNumber,
        array $metadata = []
    ): array;

    /**
     * Verify a payment by internal reference.
     * Returns:
     *   - 'success'       bool
     *   - 'paid'          bool
     *   - 'gateway_ref'   string
     *   - 'amount'        float
     *   - 'raw'           array  — gateway response (for audit log)
     *   - 'message'       string?
     */
    public function verifyPayment(string $reference): array;

    /**
     * Handle an inbound webhook payload from the gateway.
     * Should update the order status and return a success indicator.
     */
    public function handleWebhook(array $payload, string $signature): array;

    /**
     * Human-readable gateway name (e.g. "MMQR", "KBZPay").
     */
    public function getName(): string;
}