<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Handles image resizing, WebP conversion, and storage.
 *
 * Usage:
 *   $result = app(ImageOptimizationService::class)->store($file, 'products/temp/5');
 *   // $result => ['path' => 'products/temp/5/abc123.webp', 'width' => 800, 'height' => 800]
 */
class ImageOptimizationService
{
    // Max dimensions per context (width × height). Images larger than these are scaled down.
    private const PRESETS = [
        'product'       => ['width' => 800,  'height' => 800],
        'product_thumb' => ['width' => 300,  'height' => 300],
        'logo'          => ['width' => 400,  'height' => 400],
        'banner'        => ['width' => 1200, 'height' => 400],
        'default'       => ['width' => 1024, 'height' => 1024],
    ];

    // WebP quality (0–100). 82 gives ~70% size reduction vs JPEG at same visual quality.
    private const WEBP_QUALITY = 82;

    /**
     * Resize, convert to WebP, and store the image.
     *
     * @param  UploadedFile  $file      The incoming uploaded file.
     * @param  string        $directory Storage directory (relative to 'public' disk).
     * @param  string        $preset    Key from PRESETS ('product', 'logo', 'banner', …).
     * @return array{path: string, width: int, height: int, size_bytes: int}
     */
    public function store(UploadedFile $file, string $directory, string $preset = 'default'): array
    {
        $dimensions = self::PRESETS[$preset] ?? self::PRESETS['default'];
        $filename   = Str::uuid() . '.webp';
        $storagePath = $directory . '/' . $filename;

        // Build image, resize (cover = crop to fill, contain = fit within).
        $image = Image::read($file)
            ->scaleDown(
                width:  $dimensions['width'],
                height: $dimensions['height'],
            );

        // Encode to WebP and save to the 'public' disk.
        $encoded = $image->toWebp(self::WEBP_QUALITY);
        Storage::disk('public')->put($storagePath, $encoded);

        return [
            'path'       => $storagePath,
            'width'      => $image->width(),
            'height'     => $image->height(),
            'size_bytes' => strlen((string) $encoded),
        ];
    }

    /**
     * Delete a previously stored image.
     */
    public function delete(string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}