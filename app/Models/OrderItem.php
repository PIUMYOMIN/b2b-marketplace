<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'product_sku',
        'variant_sku',
        'selected_options',
        'quantity_unit',
        'price',
        'quantity',
        'subtotal',
        'product_data',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'quantity'         => 'decimal:3',
        'subtotal'         => 'decimal:2',
        'selected_options' => 'array',
        'product_data'     => 'array',
    ];

    protected $appends = [
        'variant_options',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a snapshot of the product + variant at time of order.
     * Call this before saving the order item.
     */
    public static function buildSnapshot(Product $product, ?ProductVariant $variant): array
    {
        $variant?->loadMissing('optionValues.option');

        return [
            'product_id'       => $product->id,
            'name_en'          => $product->name_en,
            'name_mm'          => $product->name_mm,
            'sku'              => $product->sku,
            'brand'            => $product->brand,
            'images'           => $product->images,
            'original_price'   => $variant ? (float) $variant->price : (float) $product->price,
            'variant_id'       => $variant?->id,
            'variant_sku'      => $variant?->sku,
            'variant_price'    => $variant?->price,
            'variant_unit'     => $variant?->effectiveUnit(),
            'variant_options'  => $variant
                ? $variant->optionValues->map(fn ($v) => [
                    'option' => $v->option?->name ?? 'Option',
                    'label'  => $v->label ?: ($v->value ?? ''),
                    'value'  => $v->value,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * Flatten stored option payloads (object maps, snapshot arrays, JSON strings)
     * into a name => label map for receipts and order details.
     */
    public static function normalizeOptionMap(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                $trimmed = trim($value);
                return $trimmed !== '' ? ['Variant' => $trimmed] : [];
            }
        }

        if (! is_array($value) || $value === []) {
            return [];
        }

        $out = [];
        $isList = array_is_list($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $name = trim((string) (
                    $item['option']
                    ?? $item['option_name']
                    ?? $item['name']
                    ?? ($isList ? 'Option' : $key)
                ));
                $label = trim((string) (
                    $item['label']
                    ?? $item['value']
                    ?? $item['option_value']
                    ?? ''
                ));
                if ($name === '') {
                    $name = 'Option';
                }
                if ($label !== '') {
                    $out[$name] = $label;
                }
                continue;
            }

            if (is_string($item) || is_numeric($item)) {
                $label = trim((string) $item);
                if ($label !== '') {
                    $out[(string) $key] = $label;
                }
            }
        }

        return $out;
    }

    public static function optionsFromVariant(?ProductVariant $variant): array
    {
        if (! $variant) {
            return [];
        }

        $variant->loadMissing('optionValues.option');
        $fromValues = self::normalizeOptionMap(
            $variant->optionValues
                ->mapWithKeys(function ($value) {
                    $optionName = $value->option?->name ?? 'Option';
                    $label = $value->label ?: ($value->value ?? '');

                    return [$optionName => $label];
                })
                ->all()
        );

        if ($fromValues) {
            return $fromValues;
        }

        $label = trim((string) $variant->label());

        return $label !== '' ? ['Variant' => $label] : [];
    }

    public static function mergeSelectedOptions(?ProductVariant $variant, mixed $buyerOptions): array
    {
        return array_filter(
            array_merge(
                self::optionsFromVariant($variant),
                self::normalizeOptionMap($buyerOptions),
            ),
            fn ($label) => trim((string) $label) !== ''
        );
    }

    public function resolvedSelectedOptions(): array
    {
        $fromSelected = self::normalizeOptionMap($this->selected_options);
        if ($fromSelected) {
            return $fromSelected;
        }

        $productData = is_array($this->product_data) ? $this->product_data : [];
        $fromSnapshot = self::normalizeOptionMap(
            $productData['variant_options'] ?? $productData['selected_options'] ?? null
        );
        if ($fromSnapshot) {
            return $fromSnapshot;
        }

        return self::optionsFromVariant($this->variant);
    }

    public function getVariantOptionsAttribute(): ?array
    {
        $resolved = $this->resolvedSelectedOptions();

        return $resolved ?: null;
    }
}
