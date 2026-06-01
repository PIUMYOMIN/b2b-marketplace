<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Category;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

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
                // Email verification
                'email_verify_title' => 'Verify Your Email | Pyonea',
                'email_verify_desc'  => 'Please verify your email address to activate your Pyonea account and start trading.',
                // Local deals
                'local_deals_title' => 'Local Deals | Pyonea',
                'local_deals_desc'  => 'Discover exclusive local deals and special offers from verified sellers near you on Pyonea.',
                // Product compare
                'compare_title'     => 'Compare Products | Pyonea',
                'compare_desc'      => 'Compare wholesale products side by side on Pyonea. Evaluate prices, specifications, and sellers to make the best buying decision.',
                // About
                'about_title'       => 'About Us | Pyonea',
                'about_desc'        => 'Learn about Pyonea — Myanmar\'s trusted B2B marketplace connecting businesses with verified wholesale suppliers.',
                // Contact
                'contact_title'     => 'Contact Us | Pyonea',
                'contact_desc'      => 'Get in touch with the Pyonea team. We\'re here to help with any questions about buying, selling, or your account.',
                // Pricing
                'pricing_title'     => 'Pricing & Plans | Pyonea',
                'pricing_desc'      => 'Explore Pyonea\'s seller subscription plans. Find the right plan to grow your wholesale business in Myanmar.',
                // Help
                'help_title'        => 'Help Center | Pyonea',
                'help_desc'         => 'Find answers to common questions about orders, payments, shipping, and selling on Pyonea.',
                // Privacy policy
                'privacy_title'     => 'Privacy Policy | Pyonea',
                'privacy_desc'      => 'Read Pyonea\'s privacy policy to understand how we collect, use, and protect your personal information.',
                // Return policy
                'return_policy_title' => 'Return Policy | Pyonea',
                'return_policy_desc'  => 'Understand Pyonea\'s return and refund policy for wholesale orders placed on the platform.',
                // Legal
                'legal_title'       => 'Legal | Pyonea',
                'legal_desc'        => 'Read the terms of service and legal agreements governing use of the Pyonea marketplace.',
                // Breadcrumb labels
                'home_label'          => 'Home',
                'products_label'      => 'Products',
                'sellers_label'       => 'Sellers',
                'categories_label'    => 'Categories',
                'email_verify_label'  => 'Verify Email',
                'local_deals_label'   => 'Local Deals',
                'compare_label'       => 'Compare Products',
                'about_label'         => 'About Us',
                'contact_label'       => 'Contact',
                'pricing_label'       => 'Pricing',
                'help_label'          => 'Help',
                'privacy_label'       => 'Privacy Policy',
                'return_policy_label' => 'Return Policy',
                'legal_label'         => 'Legal',
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
                // Email verification
                'email_verify_title' => 'အီးမေးလ် အတည်ပြုခြင်း | Pyonea',
                'email_verify_desc'  => 'သင့် Pyonea အကောင့်ကို အသက်သွင်းရန် သင့်အီးမေးလ်လိပ်စာကို အတည်ပြုပါ။',
                // Local deals
                'local_deals_title' => 'ဒေသဆိုင်ရာ ကမ်းလှမ်းချက်များ | Pyonea',
                'local_deals_desc'  => 'Pyonea တွင် သင့်အနီးရှိ အတည်ပြုထားသော ရောင်းချသူများထံမှ သီးသန့် ဒေသဆိုင်ရာ ကမ်းလှမ်းချက်များနှင့် အထူးလျှော့စျေးများကို ရှာဖွေပါ။',
                // Product compare
                'compare_title'     => 'ကုန်ပစ္စည်းများ နှိုင်းယှဉ်ခြင်း | Pyonea',
                'compare_desc'      => 'Pyonea တွင် လက်ကားကုန်ပစ္စည်းများကို တန်းစီနှိုင်းယှဉ်ပါ။ အကောင်းဆုံး ဝယ်ယူမှုဆုံးဖြတ်ချက်ချရန် စျေးနှုန်းများ၊ သတ်မှတ်ချက်များနှင့် ရောင်းချသူများကို စစ်ဆေးပါ။',
                // About
                'about_title'       => 'ကျွန်ုပ်တို့အကြောင်း | Pyonea',
                'about_desc'        => 'Pyonea အကြောင်း လေ့လာပါ — မြန်မာနိုင်ငံ၏ ယုံကြည်ရသော B2B ဈေးကွက်သည် စီးပွားရေးလုပ်ငန်းများကို အတည်ပြုထားသော လက်ကားရောင်းချသူများနှင့် ချိတ်ဆက်ပေးသည်။',
                // Contact
                'contact_title'     => 'ဆက်သွယ်ရန် | Pyonea',
                'contact_desc'      => 'Pyonea အဖွဲ့နှင့် ဆက်သွယ်ပါ။ ဝယ်ယူခြင်း၊ ရောင်းချခြင်း သို့မဟုတ် သင့်အကောင့်နှင့် ပတ်သက်သော မေးခွန်းများအတွက် ကျွန်ုပ်တို့ ကူညီပါမည်။',
                // Pricing
                'pricing_title'     => 'အစီအစဉ်နှင့် စျေးနှုန်း | Pyonea',
                'pricing_desc'      => 'Pyonea ၏ ရောင်းချသူ အသင်းဝင်မှု အစီအစဉ်များကို ကြည့်ရှုပါ။ မြန်မာနိုင်ငံတွင် သင့်လက်ကားစီးပွားရေးကို ကြီးထွားစေရန် သင့်လျော်သော အစီအစဉ်ကို ရွေးချယ်ပါ။',
                // Help
                'help_title'        => 'အကူအညီဗဟိုဌာန | Pyonea',
                'help_desc'         => 'Pyonea တွင် မှာယူခြင်း၊ ငွေပေးချေမှု၊ ပို့ဆောင်ရေးနှင့် ရောင်းချခြင်းတို့နှင့် ပတ်သက်သော မေးလေ့ရှိသောမေးခွန်းများ၏ အဖြေများကို ရှာဖွေပါ။',
                // Privacy policy
                'privacy_title'     => 'လျှို့ဝှက်ချက်မူဝါဒ | Pyonea',
                'privacy_desc'      => 'Pyonea ၏ လျှို့ဝှက်ချက်မူဝါဒကို ဖတ်ရှုပြီး သင်၏ ကိုယ်ရေးကိုယ်တာ အချက်အလက်များကို မည်သို့ စုဆောင်း၊ အသုံးပြု၍ ကာကွယ်ကြောင်း နားလည်ပါ။',
                // Return policy
                'return_policy_title' => 'ပြန်အမ်းမူဝါဒ | Pyonea',
                'return_policy_desc'  => 'Pyonea ပလက်ဖောင်းတွင် တင်ထားသော လက်ကားမှာယူမှုများအတွက် ပြန်အမ်းနှင့် ငွေပြန်အမ်းမူဝါဒကို နားလည်ပါ။',
                // Legal
                'legal_title'       => 'ဥပဒေဆိုင်ရာ | Pyonea',
                'legal_desc'        => 'Pyonea ဈေးကွက် အသုံးပြုမှုကို အုပ်ချုပ်သော ဝန်ဆောင်မှုစည်းမျဉ်းများနှင့် ဥပဒေသဘောတူညီချက်များကို ဖတ်ရှုပါ။',
                // Breadcrumb labels
                'home_label'          => 'ပင်မစာမျက်နှာ',
                'products_label'      => 'ကုန်ပစ္စည်းများ',
                'sellers_label'       => 'ရောင်းချသူများ',
                'categories_label'    => 'အမျိုးအစားများ',
                'email_verify_label'  => 'အီးမေးလ် အတည်ပြုခြင်း',
                'local_deals_label'   => 'ဒေသဆိုင်ရာ ကမ်းလှမ်းချက်များ',
                'compare_label'       => 'ကုန်ပစ္စည်း နှိုင်းယှဉ်ခြင်း',
                'about_label'         => 'ကျွန်ုပ်တို့အကြောင်း',
                'contact_label'       => 'ဆက်သွယ်ရန်',
                'pricing_label'       => 'အစီအစဉ်နှင့် စျေးနှုန်း',
                'help_label'          => 'အကူအညီ',
                'privacy_label'       => 'လျှို့ဝှက်ချက်မူဝါဒ',
                'return_policy_label' => 'ပြန်အမ်းမူဝါဒ',
                'legal_label'         => 'ဥပဒေဆိုင်ရာ',
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
            'pageImage'       => $frontendUrl . '/og-image.png',
            'pageType'        => 'website',
            'canonicalPath'   => $path,
            'noindex'         => false,
            'breadcrumbs'     => [],
            'product'         => null,
            'seller'          => null,
            'categories'      => null,
            'article'         => null,
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
                        fn($img) => is_array($img)
                            && (
                                ($img['is_primary'] ?? false) === true
                                || ($img['is_primary'] ?? null) === 1
                                || ($img['is_primary'] ?? null) === '1'
                                || ($img['primary'] ?? false) === true
                                || ($img['primary'] ?? null) === 1
                                || ($img['primary'] ?? null) === '1'
                            )
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
                $sellerImage = $seller->store_logo
                    ? $this->resolveImageUrl($seller->store_logo)
                    : ($seller->store_banner ? $this->resolveImageUrl($seller->store_banner) : null);
                $metadata['pageImage']  = $sellerImage ?? $metadata['pageImage'];
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

        // ── Blog detail: /blog/some-slug ───────────────────────────────────
        elseif (preg_match('#^blog/([^/]+)$#', $trimmed, $matches)) {
            $slug = $matches[1];
            $post = BlogPost::query()
                ->visible()
                ->with('author:id,name')
                ->where('slug', $slug)
                ->first();

            if ($post) {
                $displayTitle = $locale === 'my'
                    ? ($post->seo_title_mm ?? $post->title_mm ?? $post->seo_title_en ?? $post->title_en)
                    : ($post->seo_title_en ?? $post->title_en ?? $post->seo_title_mm ?? $post->title_mm);

                $rawDescription = $locale === 'my'
                    ? ($post->seo_description_mm ?? $post->excerpt_mm ?? $post->content_mm ?? $post->seo_description_en ?? $post->excerpt_en ?? $post->content_en)
                    : ($post->seo_description_en ?? $post->excerpt_en ?? $post->content_en ?? $post->seo_description_mm ?? $post->excerpt_mm ?? $post->content_mm);

                $pageImage = $post->featured_image
                    ? $this->resolveImageUrl($post->featured_image)
                    : null;

                $metadata['pageTitle']       = $displayTitle . ' | Pyonea Blog';
                $metadata['pageDescription'] = Str::limit(trim(strip_tags((string) $rawDescription)), 155);
                $metadata['pageImage']       = $pageImage ?? $metadata['pageImage'];
                $metadata['pageType']        = 'article';
                $metadata['article']         = $this->formatBlogPostForJsonLd($post, $locale);
                $metadata['breadcrumbs'] = [
                    ['name' => $m['home_label'], 'url' => '/'],
                    ['name' => 'Blog',           'url' => '/blog'],
                    ['name' => $displayTitle,    'url' => $path],
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

        // ── Blog listing: /blog ─────────────────────────────────────────────
        elseif ($trimmed === 'blog') {
            $metadata['pageTitle']       = 'Pyonea Blog | Myanmar B2B Guides';
            $metadata['pageDescription'] = 'Read Myanmar business guides, wholesale tips, supplier advice, and marketplace updates from Pyonea.';
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'], 'url' => '/'],
                ['name' => 'Blog',           'url' => '/blog'],
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
                $metadata['pageImage']       = $category->image
                    ? $this->resolveImageUrl($category->image)
                    : $metadata['pageImage'];
                $metadata['breadcrumbs'] = [
                    ['name' => $m['home_label'],       'url' => '/'],
                    ['name' => $m['categories_label'], 'url' => '/categories'],
                    ['name' => $catName,               'url' => $path],
                ];
            }
        }

        // ── Email verification: /email-verify ──────────────────────────────
        elseif ($trimmed === 'email-verify') {
            $metadata['pageTitle']       = $m['email_verify_title'];
            $metadata['pageDescription'] = $m['email_verify_desc'];
            $metadata['noindex']         = true; // transactional page — no SEO value
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],         'url' => '/'],
                ['name' => $m['email_verify_label'], 'url' => '/email-verify'],
            ];
        }

        // ── Local deals: /local-deals ───────────────────────────────────────
        elseif ($trimmed === 'local-deals') {
            $metadata['pageTitle']       = $m['local_deals_title'];
            $metadata['pageDescription'] = $m['local_deals_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],        'url' => '/'],
                ['name' => $m['local_deals_label'], 'url' => '/local-deals'],
            ];
        }

        // ── Product compare: /compare and /product-comparison ───────────────
        elseif (in_array($trimmed, ['compare', 'product-comparison'], true)) {
            $metadata['pageTitle']       = $m['compare_title'];
            $metadata['pageDescription'] = $m['compare_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],     'url' => '/'],
                ['name' => $m['products_label'], 'url' => '/products'],
                ['name' => $m['compare_label'],  'url' => $path],
            ];
        }

        // ── Bulk order tool: /bulk-order-tool ───────────────────────────────
        elseif ($trimmed === 'bulk-order-tool') {
            $metadata['pageTitle']       = 'Bulk Order Tool | Pyonea';
            $metadata['pageDescription'] = 'Build and submit bulk wholesale orders from multiple Pyonea products in one fast workflow.';
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],     'url' => '/'],
                ['name' => $m['products_label'], 'url' => '/products'],
                ['name' => 'Bulk Order Tool',    'url' => '/bulk-order-tool'],
            ];
        }

        // ── About: /about-us ────────────────────────────────────────────────
        elseif ($trimmed === 'about-us') {
            $metadata['pageTitle']       = $m['about_title'];
            $metadata['pageDescription'] = $m['about_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],  'url' => '/'],
                ['name' => $m['about_label'], 'url' => '/about-us'],
            ];
        }

        // ── Contact: /contact ───────────────────────────────────────────────
        elseif ($trimmed === 'contact') {
            $metadata['pageTitle']       = $m['contact_title'];
            $metadata['pageDescription'] = $m['contact_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],    'url' => '/'],
                ['name' => $m['contact_label'], 'url' => '/contact'],
            ];
        }

        // ── Pricing: /pricing ───────────────────────────────────────────────
        elseif ($trimmed === 'pricing') {
            $metadata['pageTitle']       = $m['pricing_title'];
            $metadata['pageDescription'] = $m['pricing_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],    'url' => '/'],
                ['name' => $m['pricing_label'], 'url' => '/pricing'],
            ];
        }

        // ── Help: /help ─────────────────────────────────────────────────────
        elseif ($trimmed === 'help') {
            $metadata['pageTitle']       = $m['help_title'];
            $metadata['pageDescription'] = $m['help_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'], 'url' => '/'],
                ['name' => $m['help_label'], 'url' => '/help'],
            ];
        }

        // ── FAQ: /faq ───────────────────────────────────────────────────────
        elseif ($trimmed === 'faq') {
            $metadata['pageTitle']       = 'FAQ | Pyonea';
            $metadata['pageDescription'] = 'Find quick answers about buying, selling, payments, shipping, accounts, and wholesale orders on Pyonea.';
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'], 'url' => '/'],
                ['name' => 'FAQ',            'url' => '/faq'],
            ];
        }

        // ── Shipping information: /shipping ─────────────────────────────────
        elseif ($trimmed === 'shipping') {
            $metadata['pageTitle']       = 'Shipping Information | Pyonea';
            $metadata['pageDescription'] = 'Learn about Pyonea shipping options, delivery zones, handling times, packaging, and order tracking across Myanmar.';
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'], 'url' => '/'],
                ['name' => 'Shipping',       'url' => '/shipping'],
            ];
        }

        // ── Seller guidelines: /seller-guidelines ───────────────────────────
        elseif ($trimmed === 'seller-guidelines') {
            $metadata['pageTitle']       = 'Seller Guidelines | Pyonea';
            $metadata['pageDescription'] = 'Review Pyonea seller guidelines for listings, pricing, delivery, service quality, and marketplace policies.';
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],    'url' => '/'],
                ['name' => $m['sellers_label'], 'url' => '/sellers'],
                ['name' => 'Seller Guidelines', 'url' => '/seller-guidelines'],
            ];
        }

        // ── Privacy policy: /privacy-policy ────────────────────────────────
        elseif ($trimmed === 'privacy-policy') {
            $metadata['pageTitle']       = $m['privacy_title'];
            $metadata['pageDescription'] = $m['privacy_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],    'url' => '/'],
                ['name' => $m['privacy_label'], 'url' => '/privacy-policy'],
            ];
        }

        // ── Return policy: /return-policy ──────────────────────────────────
        elseif ($trimmed === 'return-policy') {
            $metadata['pageTitle']       = $m['return_policy_title'];
            $metadata['pageDescription'] = $m['return_policy_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],          'url' => '/'],
                ['name' => $m['return_policy_label'], 'url' => '/return-policy'],
            ];
        }

        // ── Legal / terms: /legal and /terms ────────────────────────────────
        elseif (in_array($trimmed, ['legal', 'terms'], true)) {
            $metadata['pageTitle']       = $m['legal_title'];
            $metadata['pageDescription'] = $m['legal_desc'];
            $metadata['breadcrumbs'] = [
                ['name' => $m['home_label'],  'url' => '/'],
                ['name' => $m['legal_label'], 'url' => $path],
            ];
        }

        // ── Utility/support pages without search value ─────────────────────
        elseif (in_array($trimmed, ['order-tracking', 'track-order', 'report'], true)) {
            $metadata['noindex'] = true;
        }

        return $metadata;
    }

    protected function resolveImageUrl(string $rawPath): string
    {
        if (str_starts_with($rawPath, 'http')) {
            return $rawPath;
        }

        $cleanPath = ltrim(str_replace(['public/', 'storage/'], '', $rawPath), '/');

        $url = Storage::disk('public')->url($cleanPath);

        if (str_starts_with($url, 'http')) {
            return $url;
        }

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return $frontendUrl . '/' . ltrim($url, '/');
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
            'store_logo'        => $seller->store_logo
                ? $this->resolveImageUrl($seller->store_logo)
                : ($seller->store_banner ? $this->resolveImageUrl($seller->store_banner) : null),
            'hasStorefront'     => $seller->has_physical_store,
            'address'           => $seller->address ? [
                'city'  => $seller->city,
                'state' => $seller->state,
            ] : null,
            'sameAs' => $seller->social_links ?? [],
        ];
    }

    protected function formatBlogPostForJsonLd(BlogPost $post, string $locale = 'en'): array
    {
        $title = $locale === 'my'
            ? ($post->title_mm ?? $post->title_en)
            : ($post->title_en ?? $post->title_mm);

        $description = $locale === 'my'
            ? ($post->seo_description_mm ?? $post->excerpt_mm ?? $post->seo_description_en ?? $post->excerpt_en)
            : ($post->seo_description_en ?? $post->excerpt_en ?? $post->seo_description_mm ?? $post->excerpt_mm);

        return [
            'headline'      => $title,
            'description'   => Str::limit(trim(strip_tags((string) $description)), 155),
            'image'         => $post->featured_image ? $this->resolveImageUrl($post->featured_image) : null,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified'  => $post->updated_at?->toIso8601String(),
            'authorName'    => $post->author->name ?? 'Pyonea',
            'slug'          => $post->slug,
        ];
    }
}
