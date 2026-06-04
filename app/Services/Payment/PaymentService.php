<?php
// app/Services/Payment/PaymentService.php
// Central payment service — resolves the correct gateway and
// orchestrates initiate → verify → update order.

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\SellerWallet;
use App\Models\Cart;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderPaymentConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $shouldNotifyBuyer = false;
        $shouldNotifySeller = false;

        DB::transaction(function () use ($order, $gatewayResult, &$shouldNotifyBuyer, &$shouldNotifySeller) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $lockedOrder) {
                return;
            }

            $paidAmount = isset($gatewayResult['amount']) && is_numeric($gatewayResult['amount'])
                ? (float) $gatewayResult['amount']
                : 0.0;
            $expectedAmount = (float) $lockedOrder->total_amount;

            if ($paidAmount > 0 && abs($paidAmount - $expectedAmount) > 1) {
                Log::critical('Payment amount mismatch', [
                    'order_id' => $lockedOrder->id,
                    'order_number' => $lockedOrder->order_number,
                    'payment_method' => $lockedOrder->payment_method,
                    'expected_amount' => $expectedAmount,
                    'paid_amount' => $paidAmount,
                    'gateway_ref' => $gatewayResult['gateway_ref'] ?? null,
                    'payment_reference' => $lockedOrder->payment_reference,
                ]);

                return;
            }

            $escrowRequired = $lockedOrder->payment_method !== Order::PAYMENT_CASH_ON_DELIVERY;
            $isFullyApplied = $lockedOrder->payment_status === Order::PAYMENT_STATUS_PAID
                && $lockedOrder->status === Order::STATUS_CONFIRMED
                && (! $escrowRequired || $lockedOrder->escrow_status === 'held');

            if ($isFullyApplied) {
                return;
            }

            $shouldNotifySeller = $lockedOrder->payment_status !== Order::PAYMENT_STATUS_PAID
                || $lockedOrder->status !== Order::STATUS_CONFIRMED;
            $shouldNotifyBuyer = $lockedOrder->payment_status !== Order::PAYMENT_STATUS_PAID;

            $updates = [
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'status'         => Order::STATUS_CONFIRMED,
                'payment_data'   => array_merge(
                    is_array($lockedOrder->payment_data) ? $lockedOrder->payment_data : [],
                    ['confirmed' => $gatewayResult['raw'] ?? $gatewayResult]
                ),
            ];

            if ($lockedOrder->payment_status !== Order::PAYMENT_STATUS_PAID) {
                $updates['transaction_id'] = $gatewayResult['gateway_ref'] ?? $lockedOrder->transaction_id;
                $updates['payment_confirmed_at'] = now();
            }

            if ($escrowRequired && $lockedOrder->escrow_status !== 'held') {
                $wallet = SellerWallet::lockForSeller($lockedOrder->seller_id);
                $wallet->holdEscrow((float) $lockedOrder->total_amount, $lockedOrder->id);
                $updates['escrow_status'] = 'held';
            }

            $lockedOrder->update($updates);
        });

        $order->refresh();

        if ($shouldNotifyBuyer) {
            $order->loadMissing('buyer');
            Cart::where('user_id', $order->buyer_id)->delete();

            try {
                $order->buyer?->notify(new OrderPlaced($order));
            } catch (Throwable $e) {
                Log::warning('Buyer order confirmation notification failed', [
                    'order_id' => $order->id,
                    'buyer_id' => $order->buyer_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($shouldNotifySeller) {
            $order->loadMissing('seller');
            try {
                $order->seller?->notify(new NewOrderForSeller($order));
            } catch (Throwable $e) {
                Log::warning('Seller new order notification after payment failed', [
                    'order_id' => $order->id,
                    'seller_id' => $order->seller_id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $order->seller?->notify(new OrderPaymentConfirmed($order));
            } catch (Throwable $e) {
                Log::warning('Seller payment confirmation notification failed', [
                    'order_id' => $order->id,
                    'seller_id' => $order->seller_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
