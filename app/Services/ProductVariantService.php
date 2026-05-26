<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductOptionValue;
use Illuminate\Support\Facades\DB;

class ProductVariantService
{
    /**
     * Auto-generate all possible variant combinations from a product's options.
     *
     * Example: Color [Red, Blue] × Size [S, M, L] → 6 variants
     *
     * @param  Product  $product
     * @param  array    $defaults  Base fields applied to every generated variant
     *                             (price, quantity, quantity_unit, moq)
     * @return ProductVariant[]
     */
    public function generateCombinations(Product $product, array $defaults = []): array
    {
        $options = $product->options()->with('values')->get();

        if ($options->isEmpty()) {
            throw new \RuntimeException('Product has no options defined. Add options before generating variants.');
        }

        // Build a list of value-ID arrays per option
        // e.g. [[1,2], [4,5,6]]  → Color values × Size values
        $valueSets = $options->map(fn($option) => $option->values->pluck('id')->toArray())->toArray();

        // Compute cartesian product
        $combinations = $this->cartesian($valueSets);

        $created = [];

        DB::transaction(function () use ($product, $combinations, $defaults, &$created) {
            $position = $product->variants()->max('position') ?? 0;

            foreach ($combinations as $valueIds) {
                // Skip if this exact combination already exists
                if ($this->variantExistsForValues($product->id, $valueIds)) {
                    continue;
                }

                $variant = ProductVariant::create([
                    'product_id'    => $product->id,
                    'price'         => $defaults['price'] ?? $product->price,
                    'quantity'      => $defaults['quantity'] ?? 0,
                    'quantity_unit' => $defaults['quantity_unit'] ?? null,
                    'moq'           => $defaults['moq'] ?? null,
                    'quantity_step' => $defaults['quantity_step'] ?? $defaults['moq'] ?? null,
                    'is_active'     => true,
                    'position'      => ++$position,
                ]);

                // Attach option values via pivot table
                $variant->optionValues()->attach($valueIds);

                $created[] = $variant->load('optionValues.option');
            }
        });

        return $created;
    }

    /**
     * Sync a single variant's option value combination.
     * Detaches old values and attaches new ones.
     */
    public function syncVariantOptions(ProductVariant $variant, array $optionValueIds): void
    {
        $variant->optionValues()->sync($optionValueIds);
    }

    /**
     * Delete all variants for a product and regenerate.
     * Use with caution — will zero out all stock if not passed defaults.
     */
    public function regenerate(Product $product, array $defaults = []): array
    {
        DB::transaction(function () use ($product) {
            $product->variants()->forceDelete();
        });

        return $this->generateCombinations($product, $defaults);
    }

    /**
     * Build a human-readable label from a list of option value IDs.
     * e.g. [1, 5] → "Red / M"
     */
    public function buildLabel(array $optionValueIds): string
    {
        return ProductOptionValue::whereIn('id', $optionValueIds)
            ->orderBy('id')
            ->pluck('label')
            ->join(' / ');
    }

    /**
     * Check whether a variant with exactly these option values already exists
     * for this product (order-independent).
     *
     * Uses a single aggregating subquery instead of chained whereHas /
     * whereDoesntHave calls, which avoids N+1-style correlated subqueries and
     * is safe regardless of variant count.
     *
     * Strategy: for each variant that belongs to this product, count how many
     * of its pivot rows match the requested value IDs. A variant is an exact
     * match when:
     *   - the number of matching pivot rows == count($valueIds)  (all requested values present)
     *   - the total pivot row count for that variant also == count($valueIds) (no extra values)
     */
    public function variantExistsForValues(int $productId, array $valueIds): bool
    {
        sort($valueIds);
        $count = count($valueIds);

        if ($count === 0) {
            return false;
        }

        $exists = DB::table('product_variants as pv')
            ->join('product_variant_option_values as pvov', 'pv.id', '=', 'pvov.variant_id')
            ->where('pv.product_id', $productId)
            ->whereNull('pv.deleted_at')
            ->groupBy('pv.id')
            ->havingRaw('COUNT(CASE WHEN pvov.option_value_id IN (' . implode(',', array_fill(0, $count, '?')) . ') THEN 1 END) = ?', [...$valueIds, $count])
            ->havingRaw('COUNT(pvov.option_value_id) = ?', [$count])
            ->exists();

        return $exists;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Compute the cartesian product of multiple arrays.
     *
     * cartesian([[1,2], [4,5,6]]) → [[1,4],[1,5],[1,6],[2,4],[2,5],[2,6]]
     */
    private function cartesian(array $sets): array
    {
        $result = [[]];

        foreach ($sets as $set) {
            $append = [];
            foreach ($result as $existing) {
                foreach ($set as $item) {
                    $append[] = array_merge($existing, [$item]);
                }
            }
            $result = $append;
        }

        return $result;
    }
}
