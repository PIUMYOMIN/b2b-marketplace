<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = ['method', 'enabled', 'label', 'sort_order'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Return an array of enabled payment method keys.
     * cash_on_delivery is ALWAYS included regardless of the toggle,
     * because it is the platform default and requires no gateway.
     */
    public static function enabledMethods(): array
    {
        $enabled = static::where('enabled', true)
            ->orderBy('sort_order')
            ->pluck('method')
            ->toArray();

        // Guarantee COD is always present as a fallback.
        if (!in_array('cash_on_delivery', $enabled, true)) {
            $enabled[] = 'cash_on_delivery';
        }

        return $enabled;
    }

    /**
     * Return a full settings map keyed by method.
     *
     * @return array<string, array{method: string, enabled: bool, label: string, sort_order: int}>
     */
    public static function allSettings(): array
    {
        return static::orderBy('sort_order')
            ->get(['method', 'enabled', 'label', 'sort_order'])
            ->keyBy('method')
            ->toArray();
    }
}