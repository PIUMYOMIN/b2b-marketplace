<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BulkImportController extends Controller
{
    // Maximum rows allowed per upload — configurable via config/subscription.php
    private function maxRows(): int
    {
        return (int) config('subscription.bulk_import.max_rows', 500);
    }

    // Required CSV column headers (case-insensitive).
    private const REQUIRED_HEADERS = ['name_en', 'price', 'category_id'];

    // All accepted headers and their validation rules.
    private const ROW_RULES = [
        'name_en'       => 'required|string|max:255',
        'name_mm'       => 'nullable|string|max:255',
        'description_en'=> 'nullable|string',
        'description_mm'=> 'nullable|string',
        'product_type'  => 'nullable|in:physical,digital,service',
        'price'         => 'required|numeric|min:0',
        'category_id'   => 'required|integer|exists:categories,id',
        'sku'           => 'nullable|string|max:100',
        'brand'         => 'nullable|string|max:100',
        'model'         => 'nullable|string|max:100',
        'material'      => 'nullable|string|max:100',
        'origin'        => 'nullable|string|max:100',
        'moq'           => 'nullable|integer|min:1',
        'min_order_unit'=> 'nullable|string|max:50',
        'condition'     => 'nullable|in:new,used_like_new,used_good,used_fair',
        'weight_kg'     => 'nullable|numeric|min:0',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // POST /seller/products/bulk-import
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        // 1. Validate the uploaded file.
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120', // 5 MB
        ]);

        $seller   = $request->user();
        $sellerId = $seller->id;

        // 2. Parse the file into rows.
        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        try {
            $rows = $ext === 'csv' || $ext === 'txt'
                ? $this->parseCsv($file->getRealPath())
                : $this->parseExcel($file->getRealPath());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse file: ' . $e->getMessage(),
            ], 422);
        }

        if (empty($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'The uploaded file is empty or has no valid rows.',
            ], 422);
        }

        if (count($rows) > $this->maxRows()) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum ' . $this->maxRows() . ' rows per upload. Your file has ' . count($rows) . ' rows.',
            ], 422);
        }

        // 3. Validate required headers.
        $headers = array_keys($rows[0]);
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if (!empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required columns: ' . implode(', ', $missing),
            ], 422);
        }

        // 4. Enforce plan product limit.
        $limitError = $this->checkProductLimit($sellerId, count($rows));
        if ($limitError) {
            return response()->json($limitError, 422);
        }

        // 5. Validate & import rows inside a transaction.
        $imported = [];
        $errors   = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 = header row + 1-based

                // Trim all values.
                $row = array_map('trim', $row);

                // Per-row validation.
                $validator = validator($row, self::ROW_RULES);
                if ($validator->fails()) {
                    $errors[] = [
                        'row'    => $rowNumber,
                        'errors' => $validator->errors()->all(),
                    ];
                    continue;
                }

                $data = $validator->validated();

                // Duplicate SKU guard (within this batch and existing DB records).
                if (!empty($data['sku'])) {
                    $skuExists = Product::where('sku', $data['sku'])->exists()
                        || collect($imported)->contains(fn($p) => $p['sku'] === $data['sku']);

                    if ($skuExists) {
                        $errors[] = [
                            'row'    => $rowNumber,
                            'errors' => ["SKU '{$data['sku']}' is already in use."],
                        ];
                        continue;
                    }
                }

                // Build the product record.
                $product = Product::create([
                    'seller_id'      => $sellerId,
                    'name_en'        => $data['name_en'],
                    'name_mm'        => $data['name_mm']        ?? null,
                    'slug_en'        => $this->generateSlug($data['name_en']),
                    'description_en' => $data['description_en'] ?? null,
                    'description_mm' => $data['description_mm'] ?? null,
                    'product_type'   => $data['product_type']   ?? 'physical',
                    'price'          => $data['price'],
                    'category_id'    => $data['category_id'],
                    'sku'            => $data['sku']            ?? null,
                    'brand'          => $data['brand']          ?? null,
                    'model'          => $data['model']          ?? null,
                    'material'       => $data['material']       ?? null,
                    'origin'         => $data['origin']         ?? null,
                    'moq'            => $data['moq']            ?? 1,
                    'min_order_unit' => $data['min_order_unit'] ?? 'piece',
                    'condition'      => $data['condition']      ?? 'new',
                    'weight_kg'      => $data['weight_kg']      ?? null,
                    'status'         => 'pending',
                    'is_active'      => true,
                    'listed_at'      => now(),
                ]);

                $imported[] = ['id' => $product->id, 'sku' => $product->sku, 'name_en' => $product->name_en];
            }

            // Roll back the entire batch if every row failed.
            if (empty($imported)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No products were imported. Please fix the errors and try again.',
                    'errors'  => $errors,
                ], 422);
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Bulk import failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed due to a server error. No products were saved.',
            ], 500);
        }

        return response()->json([
            'success'        => true,
            'message'        => count($imported) . ' product(s) imported successfully. '
                . (count($errors) ? count($errors) . ' row(s) skipped due to errors.' : ''),
            'data' => [
                'imported_count' => count($imported),
                'skipped_count'  => count($errors),
                'imported'       => $imported,
                'errors'         => $errors,
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /seller/products/bulk-import/template
    // ─────────────────────────────────────────────────────────────────────────

    public function template(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'name_en', 'name_mm', 'description_en', 'description_mm',
            'product_type', 'price', 'category_id', 'sku',
            'brand', 'model', 'material', 'origin',
            'moq', 'min_order_unit', 'condition', 'weight_kg',
        ];

        $example = [
            'Sample Product', 'ဥပမာ ကုန်ပစ္စည်း', 'This is a description', 'ဖော်ပြချက်',
            'physical', '25000', '1', 'SKU-001',
            'MyBrand', 'Model-X', 'Plastic', 'Myanmar',
            '1', 'piece', 'new', '0.5',
        ];

        return response()->streamDownload(function () use ($headers, $example) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $example);
            fclose($handle);
        }, 'bulk_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        $rows    = [];
        $headers = [];
        $handle  = fopen($path, 'r');

        while (($line = fgetcsv($handle)) !== false) {
            if (empty($headers)) {
                // Normalise headers: lowercase + trim
                $headers = array_map(fn($h) => strtolower(trim($h)), $line);
                continue;
            }
            // Skip completely blank rows.
            if (count(array_filter($line)) === 0) continue;

            $rows[] = array_combine($headers, array_pad($line, count($headers), null));
        }

        fclose($handle);
        return $rows;
    }

    private function parseExcel(string $path): array
    {
        // Requires the PhpSpreadsheet package (composer require phpoffice/phpspreadsheet).
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $data        = $sheet->toArray(null, true, true, false);

        if (empty($data)) return [];

        $headers = array_map(fn($h) => strtolower(trim((string) $h)), array_shift($data));
        $rows    = [];

        foreach ($data as $line) {
            if (count(array_filter($line)) === 0) continue;
            $rows[] = array_combine($headers, array_pad($line, count($headers), null));
        }

        return $rows;
    }

    /**
     * Check whether the seller has enough product-limit headroom for $newCount rows.
     * Returns an error payload array on failure, null on success.
     */
    private function checkProductLimit(int $sellerId, int $newCount): ?array
    {
        $subscription = SellerSubscription::with('plan')
            ->where('user_id', $sellerId)
            ->active()
            ->first();

        $plan = $subscription?->plan
            ?? SubscriptionPlan::where('slug', 'basic')->first()
            ?? new SubscriptionPlan(['product_limit' => 20, 'name' => 'Basic']);

        if ($plan->product_limit === -1) return null; // unlimited

        $currentCount = Product::where('seller_id', $sellerId)->whereNull('deleted_at')->count();
        $available    = $plan->product_limit - $currentCount;

        if ($newCount > $available) {
            return [
                'success' => false,
                'message' => "Your {$plan->name} plan allows {$plan->product_limit} products total. "
                    . "You currently have {$currentCount}, so you can only import {$available} more. "
                    . "Your file has {$newCount} rows.",
                'error' => 'product_limit_reached',
                'data'  => [
                    'plan_limit'    => $plan->product_limit,
                    'current_count' => $currentCount,
                    'available'     => max(0, $available),
                    'requested'     => $newCount,
                    'upgrade_url'   => '/seller/dashboard?tab=subscription',
                ],
            ];
        }

        return null;
    }

    private function generateSlug(string $text): string
    {
        $base = Str::slug($text);
        $slug = $base;
        $i    = 1;

        while (DB::table('products')->where('slug_en', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}