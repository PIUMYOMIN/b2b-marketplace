<?php
// app/Services/Payment/PaymentService.php
// Central payment service — resolves the correct gateway and
// orchestrates initiate → verify → update order.

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /** Map payment_method slugs to gateway classes */
    private static array $gatewayMap = [
        'mmqr'             => MMQRGateway::class,
        'kbz_pay'          => KBZPayGateway::class,
        'wave_pay'         => WavePayGateway::class,
        'cash_on_delivery' => null,
    ];

    /**
     * Resolve gateway for a given method slug.
     * Throws \InvalidArgumentException for unknown methods.
     */
    public static function gateway(string $method): ?PaymentGatewayInterface
    {
        if (!array_key_exists($method, self::$gatewayMap)) {
            throw new \InvalidArgumentException("Unknown payment method: {$method}");
        }
        $class = self::$gatewayMap[$method];
        return $class ? new $class() : null;
    }

    /**
     * Initiate payment for an order.
     * Stores the reference on the order immediately so webhooks can match it.
     */
    public static function initiate(Order $order): array
    {
        $gateway = self::gateway($order->payment_method);

        if (!$gateway) {
            // COD — mark as pending, no gateway needed
            $order->update(['payment_initiated_at' => now()]);
            return ['success' => true, 'method' => 'cash_on_delivery'];
        }

        $result = $gateway->initiatePayment(
            amount:      (float) $order->total_amount,
            currency:    'MMK',
            orderNumber: $order->order_number,
            metadata:    [
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
            ]
        );

        if ($result['success']) {
            $order->update([
                'payment_reference'    => $result['reference'],
                'transaction_id'       => $result['gateway_ref'] ?? null,
                'payment_gateway'      => $gateway->getName(),
                'payment_initiated_at' => now(),
                'payment_data'         => $result,
            ]);
        }

        return $result;
    }

    /**
     * Verify payment status and update order if confirmed.
     * Called by the frontend polling endpoint or webhook handler.
     */
    public static function verify(Order $order): array
    {
        $gateway = self::gateway($order->payment_method);
        if (!$gateway) {
            return ['success' => false, 'message' => 'No gateway for COD'];
        }

        $reference = $order->payment_reference;
        if (!$reference) {
            return ['success' => false, 'message' => 'No payment reference on order'];
        }

        $result = $gateway->verifyPayment($reference);

        if ($result['success'] && $result['paid']) {
            self::markPaid($order, $result);
        }

        return $result;
    }

    /**
     * Mark an order as paid and update all related fields.
     * Called from verify() or directly from a webhook handler.
     */
    public static function markPaid(Order $order, array $gatewayResult): void
    {
        if ($order->payment_status === 'paid') return; // idempotent

        $order->update([
            'payment_status'       => 'paid',
            'transaction_id'       => $gatewayResult['gateway_ref'] ?? $order->transaction_id,
            'payment_confirmed_at' => now(),
            'payment_data'         => array_merge(
                is_array($order->payment_data) ? $order->payment_data : [],
                ['confirmed' => $gatewayResult['raw'] ?? $gatewayResult]
            ),
        ]);

        Log::info("Payment confirmed", [
            'order'   => $order->order_number,
            'method'  => $order->payment_method,
            'gateway_ref' => $gatewayResult['gateway_ref'] ?? null,
        ]);
    }

    /**
     * Mark an order payment as failed.
     */
    public static function markFailed(Order $order, string $reason = ''): void
    {
        $order->update([
            'payment_status'   => 'failed',
            'payment_failed_at'=> now(),
            'payment_data'     => array_merge(
                is_array($order->payment_data) ? $order->payment_data : [],
                ['failure_reason' => $reason]
            ),
        ]);
    }
}