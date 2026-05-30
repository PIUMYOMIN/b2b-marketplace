<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MoveTempProductImages extends Command
{
    protected $signature = 'products:move-temp-images
                            {--dry-run : Show what would be moved without changing files or database records}';

    protected $description = 'Move product images from products/temp/{sellerId} into products/{productId}.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');
        $checked = 0;
        $productsChanged = 0;
        $imagesMoved = 0;
        $missingFiles = 0;

        Product::whereNotNull('images')
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($disk, $dryRun, &$checked, &$productsChanged, &$imagesMoved, &$missingFiles) {
                foreach ($products as $product) {
                    $checked++;
                    $images = $this->normalizeImages($product->images);

                    if (empty($images)) {
                        continue;
                    }

                    $changed = false;
                    $updatedImages = [];

                    foreach ($images as $image) {
                        if (! is_array($image)) {
                            $updatedImages[] = $image;
                            continue;
                        }

                        $path = $this->relativePath($image['url'] ?? $image['path'] ?? '');
                        $tempPrefix = "products/temp/{$product->seller_id}/";

                        if (! $path || ! str_starts_with($path, $tempPrefix)) {
                            $updatedImages[] = $image;
                            continue;
                        }

                        if (! $disk->exists($path)) {
                            $missingFiles++;
                            $this->warn("Missing file for product {$product->id}: {$path}");
                            $updatedImages[] = $image;
                            continue;
                        }

                        $target = $this->targetPath($path, (int) $product->id, $disk);
                        $this->line(($dryRun ? '[dry-run] ' : '') . "Product {$product->id}: {$path} -> {$target}");

                        if (! $dryRun) {
                            $disk->move($path, $target);
                        }

                        $updatedImages[] = [
                            ...$image,
                            'url' => $target,
                        ];
                        $changed = true;
                        $imagesMoved++;
                    }

                    if ($changed) {
                        $productsChanged++;

                        if (! $dryRun) {
                            $product->update(['images' => $updatedImages]);
                        }
                    }
                }
            });

        $this->info(($dryRun ? 'Dry run complete.' : 'Move complete.'));
        $this->table(
            ['Products checked', 'Products changed', 'Images moved', 'Missing files'],
            [[$checked, $productsChanged, $imagesMoved, $missingFiles]]
        );

        return self::SUCCESS;
    }

    private function normalizeImages(mixed $images): array
    {
        if (is_array($images)) {
            return $images;
        }

        if (is_string($images) && $images !== '') {
            return json_decode($images, true) ?: [];
        }

        return [];
    }

    private function relativePath(mixed $path): string
    {
        if (! is_string($path) || $path === '') {
            return '';
        }

        if (str_starts_with($path, 'http')) {
            $path = preg_replace('#^https?://[^/]+/storage/#', '', $path);
        }

        return ltrim(str_replace(['public/', 'storage/'], '', $path), '/');
    }

    private function targetPath(string $source, int $productId, $disk): string
    {
        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $basename = pathinfo($source, PATHINFO_FILENAME);
        $suffix = $extension ? ".{$extension}" : '';
        $target = "products/{$productId}/{$basename}{$suffix}";

        if (! $disk->exists($target)) {
            return $target;
        }

        return "products/{$productId}/{$basename}-" . Str::random(8) . $suffix;
    }
}
