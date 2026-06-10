<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles image resizing, WebP conversion, and storage.
 * Falls back to plain storage if intervention/image is not installed.
 *
 * Usage:
 *   $result = app(ImageOptimizationService::class)->store($file, 'products/temp/5');
 *   // $result => ['path' => 'products/temp/5/abc123.webp', 'width' => 800, 'height' => 800]
 */
class ImageOptimizationService
{
    private const PRESETS = [
        'product'       => ['width' => 800,  'height' => 800],
        'product_thumb' => ['width' => 300,  'height' => 300],
        'logo'          => ['width' => 400,  'height' => 400],
        'banner'        => ['width' => 1200, 'height' => 400],
        'proof'         => ['width' => 1200, 'height' => 1200, 'quality' => 80],
        'default'       => ['width' => 1024, 'height' => 1024],
    ];

    private const WEBP_QUALITY = 82;

    public function store(UploadedFile $file, string $directory, string $preset = 'default'): array
    {
        // Use Intervention Image if available, otherwise fall back to plain storage.
        if (class_exists(\Intervention\Image\ImageManager::class)) {
            try {
                return $this->storeOptimized($file, $directory, $preset);
            } catch (\Throwable $e) {
                \Log::warning('Image optimization failed, storing original: ' . $e->getMessage());
            }
        }

        return $this->storePlain($file, $directory);
    }

    public function delete(string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // ─── Optimized path (intervention/image installed) ───────────────────────

    private function storeOptimized(UploadedFile $file, string $directory, string $preset): array
    {
        $dimensions  = self::PRESETS[$preset] ?? self::PRESETS['default'];
        $filename    = Str::uuid() . '.webp';
        $storagePath = $directory . '/' . $filename;

        $image = \Intervention\Image\ImageManager::gd()
            ->read($file)
            ->scaleDown(
                width:  $dimensions['width'],
                height: $dimensions['height'],
            );

        $encoded = $image->toWebp($dimensions['quality'] ?? self::WEBP_QUALITY);
        Storage::disk('public')->put($storagePath, $encoded);

        return [
            'path'       => $storagePath,
            'width'      => $image->width(),
            'height'     => $image->height(),
            'size_bytes' => strlen((string) $encoded),
        ];
    }

    // ─── Fallback path (plain storage, no optimization) ──────────────────────

    private function storePlain(UploadedFile $file, string $directory): array
    {
        $storagePath = $file->store($directory, 'public');

        return [
            'path'       => $storagePath,
            'width'      => 0,
            'height'     => 0,
            'size_bytes' => $file->getSize(),
        ];
    }
}
