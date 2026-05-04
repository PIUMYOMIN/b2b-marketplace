<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Category;
use Illuminate\Support\Str;

class FrontendController extends Controller
{
    public function index(Request $request)
    {
        $path = $request->path() === '/' ? '/' : '/' . $request->path();
        $data = $this->resolveMetadata($path);

        return view('app', $data);
    }

    protected function resolveMetadata(string $path): array
    {
        // Default metadata
        $metadata = [
            'lang' => app()->getLocale(),
            'pageTitle' => 'Pyonea Marketplace',
            'pageDescription' => 'Myanmar B2B Marketplace',
            'pageKeywords' => '',
            'pageUrl' => url($path),
            'pageImage' => asset('og-image.jpg'),
            'pageType' => 'website',
            'canonicalPath' => $path,
            'noindex' => false,
            'breadcrumbs' => [],
            'product' => null,
            'seller' => null,
            'categories' => null,
        ];

        // Remove leading slash for matching
        $trimmed = ltrim($path, '/');

        // Home page
        if ($trimmed === '') {
            $metadata['pageTitle'] = 'Pyonea | Myanmar B2B Marketplace';
            $metadata['pageDescription'] = 'Pyonea connects Myanmar businesses with verified suppliers.';
            // Add breadcrumb if desired
        }

        // Product detail: /products/some-slug
        elseif (preg_match('#^products/([^/]+)$#', $trimmed, $matches)) {
            $slug = $matches[1];
            $product = Product::where('slug_en', $slug)
                ->orWhere('slug_mm', $slug)
                ->with(['seller', 'images', 'reviews.user'])
                ->first();

            if ($product) {
                $displayName = $product->name_en ?? $product->name_mm ?? 'Product';
                $displayDesc = $product->description_en ?? $product->description_mm ?? '';

                // Resolve first image URL safely (images is a JSON array of objects or strings)
                $firstImage = null;
                $images = is_array($product->images) ? $product->images : [];
                if (!empty($images)) {
                    $img = $images[0];
                    $rawPath = is_array($img) ? ($img['url'] ?? $img['path'] ?? null) : $img;
                    if ($rawPath) {
                        $firstImage = str_starts_with($rawPath, 'http')
                            ? $rawPath
                            : rtrim(env('APP_URL', 'https://api.pyonea.com'), '/') . '/storage/' . ltrim(str_replace('public/', '', $rawPath), '/');
                    }
                }

                $metadata['pageTitle']       = $displayName . ' | Pyonea';
                $metadata['pageDescription'] = Str::limit($displayDesc, 155);
                $metadata['pageImage']       = $firstImage ?? $metadata['pageImage'];
                $metadata['pageType']        = 'product';
                $metadata['product']         = $this->formatProductForJsonLd($product);
                $metadata['breadcrumbs'] = [
                    ['name' => 'Home',     'url' => '/'],
                    ['name' => 'Products', 'url' => '/products'],
                    ['name' => $displayName, 'url' => $path],
                ];
            } else {
                abort(404);
            }
        }

        // Seller profile: /sellers/some-slug
        elseif (preg_match('#^sellers/([^/]+)$#', $trimmed, $matches)) {
            $slug = $matches[1];
            $seller = SellerProfile::where('store_slug', $slug)->first();

            if ($seller) {
                $metadata['pageTitle'] = $seller->store_name . ' | Pyonea';
                $metadata['pageDescription'] = Str::limit($seller->description, 150);
                $metadata['pageImage'] = $seller->logo ?? $metadata['pageImage'];
                $metadata['pageType'] = 'profile';
                $metadata['seller'] = $this->formatSellerForJsonLd($seller);
                $metadata['breadcrumbs'] = [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Sellers', 'url' => '/sellers'],
                    ['name' => $seller->store_name, 'url' => $path],
                ];
            } else {
                abort(404);
            }
        }

        // Category listing
        elseif ($trimmed === 'categories') {
            $categories = Category::all(); // or paginate
            $metadata['pageTitle'] = 'Categories | Pyonea';
            $metadata['categories'] = $categories->map(fn($cat) => [
                'slug' => $cat->slug,
                'name' => $cat->name,
            ])->toArray();
        }

        // Add other routes as needed...

        return $metadata;
    }

    protected function formatProductForJsonLd($product): array
    {
        // images is stored as a JSON array — may be array of strings or array of objects
        $images = is_array($product->images) ? $product->images : [];
        $imageUrls = collect($images)->map(function ($img) {
            $rawPath = is_array($img) ? ($img['url'] ?? $img['path'] ?? null) : $img;
            if (!$rawPath) return null;
            return str_starts_with($rawPath, 'http')
                ? $rawPath
                : rtrim(env('APP_URL', 'https://api.pyonea.com'), '/') . '/storage/' . ltrim(str_replace('public/', '', $rawPath), '/');
        })->filter()->values()->toArray();

        return [
            'name'           => $product->name_en ?? $product->name_mm,
            'images'         => array_map(fn($url) => ['url' => $url], $imageUrls),
            'description'    => $product->description_en ?? $product->description_mm,
            'sku'            => $product->sku,
            'brand'          => $product->seller->store_name ?? null,
            'slug'           => $product->slug_en ?? $product->slug_mm,
            'price'          => $product->price ?? 0,
            'inStock'        => ($product->quantity ?? 0) > 0,
            'average_rating' => $product->reviews_avg_rating ?? 0,
            'review_count'   => $product->reviews_count ?? 0,
            'reviews'        => $product->relationLoaded('reviews')
                ? $product->reviews->map(fn($r) => [
                    'user_name'  => $r->user->name ?? 'Anonymous',
                    'created_at' => $r->created_at->toIso8601String(),
                    'comment'    => $r->comment,
                    'rating'     => $r->rating,
                ])->toArray()
                : [],
        ];
    }

    protected function formatSellerForJsonLd($seller): array
    {
        return [
            'store_name' => $seller->store_name,
            'slug' => $seller->slug,
            'store_description' => $seller->description,
            'store_logo' => $seller->logo,
            'hasStorefront' => $seller->has_physical_store,
            'address' => $seller->address ? [
                'city' => $seller->city,
                'state' => $seller->state,
            ] : null,
            'sameAs' => $seller->social_links ?? [],
        ];
    }
}