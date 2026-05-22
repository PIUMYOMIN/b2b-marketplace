<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Category;
use Illuminate\Support\Str;

class FrontendController extends Controller
{
    public function index(Request $request)
    {
        // ── Locale resolution ──────────────────────────────────────────────
        // SetLocale middleware (now applied to the web group) has already set
        // app()->getLocale() from ?lang= or Accept-Language. We just read it.
        $locale = app()->getLocale(); // 'en' or 'my'

        $path = $request->path() === '/' ? '/' : '/' . $request->path();
        $data = $this->resolveMetadata($path, $locale, $request);

        return view('app', $data);
    }

    // ── Localised string tables ────────────────────────────────────────────
    // Keep titles / descriptions here so FrontendController is the single
    // source of truth for server-rendered SEO copy. The React i18n locales
    // handle the in-app UI copy separately.

    private function meta(string $locale): array
    {
        $strings = [
            'en' => [
                'home_title'       => 'Pyonea | Myanmar B2B Marketplace',
                'home_desc'        => 'Pyonea connects Myanmar businesses with verified suppliers. Buy wholesale products, find trusted sellers, and grow your business.',
                'products_title'   => 'Products | Pyonea',
                'products_desc'    => 'Browse wholesale products from Myanmar suppliers across all categories.',
                'product_desc'     => 'Buy %s at wholesale price from verified Myanmar suppliers. Check MOQ, reviews, and order securely on Pyonea.',
                'sellers_title'    => 'Sellers | Pyonea',
                'sellers_desc'     => 'Discover verified sellers on Pyonea. Connect with trusted suppliers and grow your business.',
                'seller_desc'      => 'Shop from %s on Pyonea. View products, ratings, and contact the seller directly.',
                'categories_title' => 'Categories | Pyonea',
                'categories_desc'  => 'Browse product categories on Pyonea – electronics, fashion, home & more.',
                'category_desc'    => 'Browse %s products from Myanmar suppliers. Wholesale prices, bulk orders, and verified sellers.',
                'default_title'    => 'Pyonea Marketplace | Buy & Sell Products in Myanmar',
                'default_desc'     => "Myanmar's trusted B2B marketplace for wholesale trade.",
                // Breadcrumb labels
                'home_label'       => 'Home',
                'products_label'   => 'Products',
                'sellers_label'    => 'Sellers',
                'categories_label' => 'Categories',
            ],
            'my' => [
                'home_title'       => 'Pyonea | မြန်မာ့ B2B ဈေးကွက်',
                'home_desc'        => 'Pyonea သည် မြန်မာ့စီးပွားရေးလုပ်ငန်းများကို အတည်ပြုထားသော ရောင်းချသူများနှင့် ချိတ်ဆက်ပေးသည်။ လက်ကားကုန်ပစ္စည်းများ ဝယ်ယူပါ၊ ယုံကြည်ရသော ရောင်းချသူများကို ရှာဖွေပါ၊ သင့်စီးပွားရေးကို ကြီးထွားစေပါ။',
                'products_title'   => 'ကုန်ပစ္စည်းများ | Pyonea',
                'products_desc'    => 'အမျိုးအစားအားလုံးမှ မြန်မာ့ရောင်းချသူများ၏ လက်ကားကုန်ပစ္စည်းများကို ကြည့်ရှုပါ။',
                'product_desc'     => '%s ကို အတည်ပြုထားသော မြန်မာ့ရောင်းချသူများထံမှ လက်ကားစျေးနှုန်းဖြင့် ဝယ်ယူပါ။ MOQ၊ သုံးသပ်ချက်များကို စစ်ဆေးပြီး Pyonea တွင် လုံခြုံစွာ မှာယူပါ။',
                'sellers_title'    => 'ရောင်းချသူများ | Pyonea',
                'sellers_desc'     => 'Pyonea ရှိ အတည်ပြုထားသော ရောင်းချသူများကို ရှာဖွေပါ။ ယုံကြည်ရသော ရောင်းချသူများနှင့် ချိတ်ဆက်ပြီး သင့်စီးပွားရေးကို ချဲ့ထွင်ပါ။',
                'seller_desc'      => '%s မှ Pyonea တွင် စျေးဝယ်ပါ။ ကုန်ပစ္စည်းများ၊ အဆင့်သတ်မှတ်ချက်များကို ကြည့်ရှုပြီး ရောင်းချသူကို တိုက်ရိုက်ဆက်သွယ်ပါ။',
                'categories_title' => 'အမျိုးအစားများ | Pyonea',
                'categories_desc'  => 'Pyonea ရှိ ကုန်ပစ္စည်းအမျိုးအစားများကို ကြည့်ရှုပါ – အီလက်ထရောနစ်၊ ဖက်ရှင်၊ အိမ်သုံးပစ္စည်းနှင့် အခြားအရာများ။',
                'category_desc'    => 'မြန်မာ့ရောင်းချသူများထံမှ %s ကုန်ပစ္စည်းများကို ကြည့်ရှုပါ။ လက်ကားစျေးနှုန်းများ၊ အစုလိုက်မှာယူမှုများနှင့် အတည်ပြုထားသော ရောင်းချသူများ။',
                'default_title'    => 'Pyonea | မြန်မာ့ B2B ဈေးကွက်',
                'default_desc'     => 'လက်ကားကုန်သွယ်မှုအတွက် မြန်မာနိုင်ငံ၏ ယုံကြည်ရသော B2B ဈေးကွက်။',
                // Breadcrumb labels
                'home_label'       => 'ပင်မစာမျက်နှာ',
                'products_label'   => 'ကုန်ပစ္စည်းများ',
                'sellers_label'    => 'ရောင်းချသူများ',
                'categories_label' => 'အမျိုးအစားများ',
            ],
        ];

        return $strings[$locale] ?? $strings['en'];
    }

    protected function resolveMetadata(string $path, string $locale, Request $request): array
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $m = $this->meta($locale);

        // Build the canonical URL for this page *including* the lang param so
        // Google gets a stable, language-specific URL to index.
        $canonicalBase = $frontendUrl . ($path === '/' ? '' : $path);
        $sep           = str_contains($canonicalBase, '?') ? '&' : '?';
        $canonicalUrl  = $canonicalBase . $sep . 'lang=' . $locale;

        $metadata = [
            'lang'            => $locale,
            'pageTitle'       => $m['default_title'],
            'pageDescription' => $m['default_desc'],
            'pageKeywords'    => '',
            'pageUrl'         => $canonicalUrl,
            'pageImage'       => asset('og-image.jpg'),
            'pageType'        => 'website',
            'canonicalPath'   => $path,
            'noindex'         => false,
            'breadcrumbs'     => [],
            'product'         => null,
            'seller'          => null,
            'categories'      => null,
        ];

        $trimmed = ltrim($path, '/');

        // ── Home ────────────────────────────────────────────────────────────
        if ($trimmed === '') {
            $metadata['pageTitle']       = $m['home_title'];
            $metadata['pageDescription'] = $m['home_desc'];
        }

        // ── Product detail: /products/some-slug ─────────────────────────────
        elseif (preg_match('#^products/([^/]+)$#', $trimmed, $matches)) {
            $slug = $matches[1];
            $product = Product::where('slug_en', $slug)
                ->orWhere('slug_mm', $slug)
                ->with(['seller', 'reviews.user'])
                ->first();

            if ($product) {
                // Pick localised name / description, fall back to the other language
                $displayName = $locale === 'my'
                    ? ($product->name_mm    ?? $product->name_en    ?? 'Product')
                    : ($product->name_en    ?? $product->name_mm    ?? 'Product');
                $displayDesc = $locale === 'my'
                    ? ($product->description_mm ?? $product->description_en ?? '')
                    : ($product->description_en ?? $product->description_mm ?? '');

                $firstImage = null;
                $images = is_array($product->images) ? $product->images : [];
                if (!empty($images)) {
                    // Prefer the image explicitly marked as primary; fall back to index 0.
                    $primaryImg = collect($images)->first(
                        fn($img) => is_array($img) && !empty($img['is_primary'])
                    ) ?? $images[0];

                    $rawPath = is_array($primaryImg)
                        ? ($primaryImg['url'] ?? $primaryImg['path'] ?? null)
                        : $primaryImg;

                    if ($rawPath) {
                        $firstImage = $this->resolveImageUrl($rawPath);
                    }
                }

                $metadata['pageTitle']       = $displayName . ' | Pyonea';
                $metadata['pageDescription'] = Str::limit(
                    sprintf($m['product_desc'], $displayName) . ' ' . $displayDesc,
                    155
                );
                $metadata['pageImage']  = $firstImage ?? $metadata['pageImage'];
                $metadata['pageType']   = 'product';
                $metadata['product']    = $this->formatProductForJsonLd($product, $locale);
                $metadata['breadcrumbs'] = [
                    ['name' => $m['home_label'],     'url' => '/'],
                    ['name' => $m['products_label'], 'url' => '/products'],
                    ['name' => $displayName,         'url' => $path],
                ];
            } else {
                abort(404);
            }
        }

        // ── Seller profile: /sellers/some-slug ──────────────────────────────
        elseif (preg_match('#^sellers/([^/]+)$#', $trimmed, $matches)) {
            $slug   = $matches[1];
            $seller = SellerProfile::where('store_slug', $slug)->first();

            if ($seller) {
                $storeName = $seller->store_name;
                $storeDesc = Str::limit($seller->store_description ?? '', 150);

                $metadata['pageTitle']       = $storeName . ' | Pyonea';
                $metadata['pageDescription'] = sprintf($m['seller_desc'], $storeName)
                    . ($storeDesc ? ' ' . $storeDesc : '');
                $sellerLogo = $seller->store_logo
                    ? $this->resolveImageUrl($seller->store_logo)
                    : null;
                $metadata['pageImage']  = $sellerLogo ?? $metadata['pageImage'];
                $metadata['pageType']   = 'profile';
                $metadata['seller']     = $this->formatSellerForJsonLd($seller);
                $metadata['breadcrumbs'] = [
                    ['name' => $m['home_label'],    'url' => '/'],
                    ['name' => $m['sellers_label'], 'url' => '/sellers'],
                    ['name' => $storeName,          'url' => $path],
                ];
            } else {
                abort(404);
            }
        }

        // ── Products listing: /products ─────────────────────────────────────
        elseif ($trimmed === 'products') {
            $metadata['pageTitle']       = $m['products_title'];
            $metadata['pageDescription'] = $m['products_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],     'url' => '/'],
                ['name' => $m['products_label'], 'url' => '/products'],
            ];
        }

        // ── Sellers listing: /sellers ───────────────────────────────────────
        elseif ($trimmed === 'sellers') {
            $metadata['pageTitle']       = $m['sellers_title'];
            $metadata['pageDescription'] = $m['sellers_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],    'url' => '/'],
                ['name' => $m['sellers_label'], 'url' => '/sellers'],
            ];
        }

        // ── Categories listing: /categories ────────────────────────────────
        elseif ($trimmed === 'categories') {
            $categories = Category::all();
            $metadata['pageTitle']       = $m['categories_title'];
            $metadata['pageDescription'] = $m['categories_desc'];
            $metadata['categories']      = $categories->map(fn($cat) => [
                'slug' => $cat->slug,
                'name' => $locale === 'my' ? ($cat->name_mm ?? $cat->name) : ($cat->name_en ?? $cat->name),
            ])->toArray();
        }

        // ── Category detail: /categories/some-slug ─────────────────────────
        elseif (preg_match('#^categories/([^/]+)$#', $trimmed, $matches)) {
            $slug     = $matches[1];
            $category = Category::where('slug', $slug)->first();
            if ($category) {
                $catName = $locale === 'my'
                    ? ($category->name_mm ?? $category->name_en ?? $category->name)
                    : ($category->name_en ?? $category->name);

                $metadata['pageTitle']       = $catName . ' | Pyonea';
                $metadata['pageDescription'] = sprintf($m['category_desc'], $catName);
                $metadata['breadcrumbs'] = [
                    ['name' => $m['home_label'],       'url' => '/'],
                    ['name' => $m['categories_label'], 'url' => '/categories'],
                    ['name' => $catName,               'url' => $path],
                ];
            }
        }

        return $metadata;
    }

    protected function resolveImageUrl(string $rawPath): string
    {
        if (str_starts_with($rawPath, 'http')) {
            return $rawPath;
        }

        return rtrim(config('app.url', 'https://api.pyonea.com'), '/') . '/storage/' . ltrim(str_replace('public/', '', $rawPath), '/');
    }

    protected function formatProductForJsonLd($product, string $locale = 'en'): array
    {
        $images = is_array($product->images) ? $product->images : [];
        $imageUrls = collect($images)->map(function ($img) {
            $rawPath = is_array($img) ? ($img['url'] ?? $img['path'] ?? null) : $img;
            if (!$rawPath) return null;
            return $this->resolveImageUrl($rawPath);
        })->filter()->values()->toArray();

        $name = $locale === 'my'
            ? ($product->name_mm ?? $product->name_en)
            : ($product->name_en ?? $product->name_mm);

        $description = $locale === 'my'
            ? ($product->description_mm ?? $product->description_en)
            : ($product->description_en ?? $product->description_mm);

        return [
            'name'           => $name,
            'images'         => array_map(fn($url) => ['url' => $url], $imageUrls),
            'description'    => $description,
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
            'store_name'        => $seller->store_name,
            'slug'              => $seller->store_slug,
            'store_description' => $seller->store_description,
            'store_logo'        => $seller->store_logo ? $this->resolveImageUrl($seller->store_logo) : null,
            'hasStorefront'     => $seller->has_physical_store,
            'address'           => $seller->address ? [
                'city'  => $seller->city,
                'state' => $seller->state,
            ] : null,
            'sameAs' => $seller->social_links ?? [],
        ];
    }
}