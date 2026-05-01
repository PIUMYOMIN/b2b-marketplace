<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Manages which payment methods are active on the platform.
 *
 * Admin routes  (require role:admin middleware):
 *   GET  /admin/payment-settings          → list all methods with enabled flag
 *   PATCH /admin/payment-settings/{method} → toggle a single method
 *   PUT  /admin/payment-settings           → bulk update all methods at once
 *
 * Public route (no auth required, cached 5 min):
 *   GET  /payment-methods                  → list of enabled method keys
 */
class PaymentSettingController extends Controller
{
    // ── Admin ─────────────────────────────────────────────────────────────────

    /**
     * GET /admin/payment-settings
     * Returns the full list of payment methods with their enabled/disabled state.
     */
    public function adminIndex(): JsonResponse
    {
        $settings = PaymentSetting::orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    /**
     * PATCH /admin/payment-settings/{method}
     * Toggle a single payment method on or off.
     *
     * Body: { enabled: true|false }
     *
     * Note: cash_on_delivery cannot be disabled — it is always kept enabled
     * as the platform fallback.
     */
    public function adminToggle(Request $request, string $method): JsonResponse
    {
        $request->validate(['enabled' => 'required|boolean']);

        $setting = PaymentSetting::where('method', $method)->firstOrFail();

        // COD is always kept enabled; silently ignore disable requests.
        if ($method === 'cash_on_delivery' && !$request->boolean('enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Cash on Delivery cannot be disabled — it is the platform default payment method.',
            ], 422);
        }

        $setting->update(['enabled' => $request->boolean('enabled')]);

        Cache::forget('payment_methods_enabled');

        return response()->json([
            'success' => true,
            'message' => "Payment method '{$setting->label}' has been " . ($setting->enabled ? 'enabled' : 'disabled') . '.',
            'data'    => $setting->fresh(),
        ]);
    }

    /**
     * PUT /admin/payment-settings
     * Bulk update — accepts an array of { method, enabled } objects.
     *
     * Body: { methods: [{ method: "mmqr", enabled: true }, ...] }
     */
    public function adminBulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'methods'           => 'required|array',
            'methods.*.method'  => 'required|string',
            'methods.*.enabled' => 'required|boolean',
        ]);

        foreach ($request->input('methods') as $item) {
            // Always keep COD enabled.
            if ($item['method'] === 'cash_on_delivery') {
                continue;
            }

            PaymentSetting::where('method', $item['method'])
                ->update(['enabled' => $item['enabled']]);
        }

        Cache::forget('payment_methods_enabled');

        return response()->json([
            'success' => true,
            'message' => 'Payment settings updated successfully.',
            'data'    => PaymentSetting::orderBy('sort_order')->get(),
        ]);
    }

    // ── Public ────────────────────────────────────────────────────────────────

    /**
     * GET /payment-methods
     * Returns the list of enabled payment method keys.
     * Cached for 5 minutes to reduce DB hits.
     * cash_on_delivery is always included.
     */
    public function publicIndex(): JsonResponse
    {
        $methods = Cache::remember('payment_methods_enabled', 300, function () {
            return PaymentSetting::where('enabled', true)
                ->orderBy('sort_order')
                ->pluck('method')
                ->toArray();
        });

        // Guarantee COD is always present in the response.
        if (!in_array('cash_on_delivery', $methods, true)) {
            $methods[] = 'cash_on_delivery';
        }

        return response()->json([
            'success' => true,
            'data'    => $methods,
        ]);
    }
}