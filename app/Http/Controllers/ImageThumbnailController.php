<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * On-demand image thumbnails with a static file cache.
 *
 * URL shape: /storage/thumbs/{width}/{original-path}.webp
 *   e.g. /storage/thumbs/480/products/temp/5/photo.jpg.webp
 *
 * First request generates the resized WebP and writes it to
 * storage/app/public/thumbs/{width}/{original-path}.webp — after that the
 * web server serves the file directly (the route is only hit on cache miss).
 * On any failure the request redirects to the original image.
 */
class ImageThumbnailController extends Controller
{
    /** Allowed thumbnail widths — keep in sync with the frontend. */
    private const ALLOWED_WIDTHS = [160, 300, 480, 800];

    private const WEBP_QUALITY = 78;

    public function show(int $width, string $path)
    {
        // Thumb URLs always end in .webp; the original path is what precedes it.
        $originalPath = preg_replace('/\.webp$/i', '', $path);

        if (
            ! in_array($width, self::ALLOWED_WIDTHS, true)
            || str_contains($originalPath, '..')
            || ! preg_match('/\.(jpe?g|png|gif|webp)$/i', $originalPath)
        ) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($originalPath)) {
            abort(404);
        }

        $thumbPath = 'thumbs/' . $width . '/' . $originalPath . '.webp';

        try {
            if (! $disk->exists($thumbPath)) {
                $manager = \Intervention\Image\ImageManager::gd();

                $encoded = $manager->read($disk->path($originalPath))
                    ->scaleDown(width: $width)
                    ->toWebp(self::WEBP_QUALITY);

                $disk->put($thumbPath, (string) $encoded);
            }

            return response()->file($disk->path($thumbPath), [
                'Content-Type'  => 'image/webp',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Thumbnail generation failed: ' . $e->getMessage(), [
                'path'  => $originalPath,
                'width' => $width,
            ]);

            return redirect('/storage/' . $originalPath);
        }
    }
}
