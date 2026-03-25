<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Category;

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
            $product = Product::where('slug', $slug)
                ->with(['seller', 'reviews.user'])
                ->first();

            if ($product) {
                $metadata['pageTitle'] = $product->name . ' | Pyonea';
                $metadata['pageDescription'] = str_limit($product->description, 150);
                $metadata['pageImage'] = $product->primary_image ?? $metadata['pageImage'];
                $metadata['pageType'] = 'product';
                $metadata['product'] = $this->formatProductForJsonLd($product);
                $metadata['breadcrumbs'] = [
                    ['name' => 'Home', 'url' => '/'],
                    ['name' => 'Products', 'url' => '/products'],
                    ['name' => $product->name, 'url' => $path],
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
                $metadata['pageDescription'] = str_limit($seller->description, 150);
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
        return [
            'name' => $product->name_en,
            'images' => $product->images->map(fn($img) => ['url' => $img->url])->toArray(),
            'description' => $product->description_en,
            'sku' => $product->sku,
            'brand' => $product->seller->store_name,
            'slug' => $product->slug,
            'price' => $product->price,
            'inStock' => $product->quantity > 0,
            'average_rating' => $product->reviews_avg_rating,
            'review_count' => $product->reviews_count,
            'reviews' => $product->reviews->map(fn($r) => [
                'user_name' => $r->user->name,
                'created_at' => $r->created_at->toIso8601String(),
                'comment' => $r->comment,
                'rating' => $r->rating,
            ])->toArray(),
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