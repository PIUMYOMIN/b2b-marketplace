<?php
// app/Http/Controllers/Api/PaymentController.php
// Handles payment initiation, status polling, and gateway webhooks.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MMPay\MMPay;

class PaymentController extends Controller
{
    /**
     * POST /payments/initiate
     * Body: { order_id: int }
     *
     * Initiates a payment session for the given order.
     * Returns gateway-specific data (QR string, deep link, etc.)
     */
    public function initiate(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);

        $user  = $request->user();
        $order = Order::where('id', $request->order_id)
            ->where('buyer_id', $user->id)
            ->firstOrFail();

        if ($order->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Order is already paid.'], 422);
        }

        $result = PaymentService::initiate($order);

        return response()->json($result, $result['success'] ? 200 : 502);
    }

    /**
     * POST /payments/verify
     * Body: { order_id: int }
     *
     * Polls the gateway to check if the payment has been completed.
     * Called by the frontend every few seconds while the QR is displayed.
     * Rate-limited to prevent gateway hammering.
     */
    public function verify(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);

        $user  = $request->user();
        $order = Order::where('id', $request->order_id)
            ->where('buyer_id', $user->id)
            ->firstOrFail();

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => true, 'paid' => true,
                'order_number' => $order->order_number,
            ]);
        }

        $result = PaymentService::verify($order);

        return response()->json(array_merge($result, [
            'order_number' => $order->order_number,
        ]));
    }

    /**
     * GET /payments/history
     * Returns the authenticated buyer's payment history.
     */
    public function history(Request $request)
    {
        $orders = Order::where('buyer_id', $request->user()->id)
            ->whereNotNull('payment_reference')
            ->select([
                'id', 'order_number', 'payment_method', 'payment_status',
                'payment_gateway', 'transaction_id', 'total_amount',
                'payment_initiated_at', 'payment_confirmed_at', 'created_at',
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // ── Webhooks ───────────────────────────────────────────────────────────────
    // Webhooks are called by the payment gateway directly (not by the user).
    // They do NOT require auth:sanctum but DO verify gateway signatures.

    /**
     * POST /webhooks/myanpay
     * Called by the MyanPay gateway when a QR payment completes.
     */
    public function handleMyanPayWebhook(Request $request)
    {
        $mmpay = new MMPay([
            'appId'          => env('MMPAY_APP_ID'),
            'publishableKey' => env('MMPAY_PUBLISHABLE_KEY'),
            'secretKey'      => env('MMPAY_SECRET_KEY'),
            'apiBaseUrl'     => env('MMPAY_API_URL'),
        ]);
        $rawPayload = $request->getContent();
        $nonce      = $request->header('X-Mmpay-Nonce');
        $signature  = $request->header('X-Mmpay-Signature');
        Log::info('MMPay Callback Received', [
            'nonce' => $nonce,
            'signature' => $signature
        ]);
        $isValid = $mmpay->verifyCb($rawPayload, $nonce, $signature);
        if (!$isValid) {
            Log::error('MMPay Signature Verification Failed');
            return response()->json(['message' => 'Invalid signature'], 403);
        }
        $data = json_decode($rawPayload, true);
        // ---------------------------------------------------
        // TODO: Add your business logic here
        // ---------------------------------------------------
        // Example:
        // $order = Order::where('order_id', $data['orderId'])->first();
        // if ($data['status'] === 'PAID') {
        //     $order->update(['status' => 'completed']);
        // }
        // ---------------------------------------------------

        return response()->json(['status' => 'success']);
    }

    /**
     * POST /webhooks/mmqr
     * Called by the MMQR gateway when a QR payment completes.
     */
    public function handleMMQRWebhook(Request $request)
    {
        $rawPayload = $request->getContent();
        $payload    = json_decode($rawPayload, true) ?: $request->all();
        $signature  = $request->header('X-Mmpay-Signature', $request->header('X-Signature', ''));
        $nonce      = $request->header('X-Mmpay-Nonce', '');

        try {
            $gateway = PaymentService::gateway('mmqr');
            $result  = $gateway->handleWebhook(array_merge($payload, [
                '__raw'   => $rawPayload,
                '__nonce' => $nonce,
            ]), $signature);

            Log::error('MMQR webhook debug result', [
                'payload' => $payload,
                'result' => $result,
            ]);

            if (!$result['success']) {
                return response()->json(['code' => 'FAIL'], 400);
            }

            if (! $this->applyWebhookResult($result, 'mmqr')) {
                return response()->json(['code' => 'ORDER_NOT_PROCESSED'], 422);
            }

            return response()->json(['code' => 'SUCCESS']);

        } catch (\Exception $e) {
            Log::error('MMQR webhook error: ' . $e->getMessage(), $payload);
            return response()->json(['code' => 'ERROR'], 500);
        }
    }

    /**
     * POST /webhooks/kbzpay
     * Called by KBZPay when a payment completes.
     */
    public function handleKBZPayWebhook(Request $request)
    {
        $payload   = $request->all();
        $signature = $request->header('X-Signature', $payload['sign'] ?? '');

        try {
            $gateway = PaymentService::gateway('kbz_pay');
            $result  = $gateway->handleWebhook($payload, $signature);

            if (!$result['success']) {
                return response('FAIL', 400);
            }

            if (! $this->applyWebhookResult($result, 'kbz_pay')) {
                return response('ORDER_NOT_PROCESSED', 422);
            }

            return response('SUCCESS');

        } catch (\Exception $e) {
            Log::error('KBZPay webhook error: ' . $e->getMessage(), $payload);
            return response('ERROR', 500);
        }
    }

    /**
     * POST /webhooks/wavepay
     * Called by Wave Money when a payment completes.
     */
    public function handleWavePayWebhook(Request $request)
    {
        $payload   = $request->all();
        $signature = $payload['hash'] ?? '';

        try {
            $gateway = PaymentService::gateway('wave_pay');
            $result  = $gateway->handleWebhook($payload, $signature);

            if (!$result['success']) {
                return response()->json(['status' => 'FAIL'], 400);
            }

            if (! $this->applyWebhookResult($result, 'wave_pay')) {
                return response()->json(['status' => 'ORDER_NOT_PROCESSED'], 422);
            }

            return response()->json(['status' => 'SUCCESS']);

        } catch (\Exception $e) {
            Log::error('WavePay webhook error: ' . $e->getMessage(), $payload);
            return response()->json(['status' => 'ERROR'], 500);
        }
    }

    // ── Private helper ─────────────────────────────────────────────────────────

    private function applyWebhookResult(array $result, string $method): bool
    {
        $reference = $result['reference'] ?? null;
        if (!$reference) {
            Log::error("{$method} webhook missing reference", $result);
            return false;
        }

        $order = Order::where('payment_reference', $reference)
            ->orWhere('order_number', $reference)
            ->first();

        $gatewayRef = $result['gateway_ref'] ?? null;
        if (! $order && $gatewayRef && $gatewayRef !== 'MMPX_MANUAL') {
            $order = Order::where('transaction_id', $gatewayRef)->first();
        }

        if (!$order) {
            Log::error("{$method} webhook: order not found for reference {$reference}", [
                'reference' => $reference,
                'gateway_ref' => $result['gateway_ref'] ?? null,
                'paid' => $result['paid'] ?? null,
            ]);
            return false;
        }

        if ($method === 'mmqr') {
            Log::error('MMQR webhook matched order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'paid' => $result['paid'] ?? null,
                'reference' => $reference,
                'gateway_ref' => $result['gateway_ref'] ?? null,
            ]);
        }

        if ($result['paid']) {
            PaymentService::markPaid($order, $result);
        } else {
            PaymentService::markFailed($order, $result['message'] ?? 'Gateway reported failure');
        }

        return true;
    }
}
