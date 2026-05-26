<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Category;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate and return sitemap.xml covering all public indexable URLs.
     *
     * Each URL is listed twice — once for English (?lang=en) and once for
     * Myanmar (?lang=my) — with xhtml:link alternate annotations so Google
     * can discover and index both language versions independently.
     *
     * Referenced in public/robots.txt as: Sitemap: https://pyonea.com/sitemap.xml
     */
    public function index(): Response
    {
        $baseUrl = rtrim(env('APP_FRONTEND_URL', 'https://pyonea.com'), '/');

        // Static pages — one entry per language
        $static = [
            ['path' => '/',               'priority' => '1.0',  'changefreq' => 'daily'],
            ['path' => '/products',       'priority' => '0.9',  'changefreq' => 'daily'],
            ['path' => '/sellers',        'priority' => '0.8',  'changefreq' => 'daily'],
            ['path' => '/categories',     'priority' => '0.9',  'changefreq' => 'weekly'],
            ['path' => '/bulk-order-tool','priority' => '0.7',  'changefreq' => 'weekly'],
            ['path' => '/local-deals',    'priority' => '0.7',  'changefreq' => 'daily'],
            ['path' => '/compare',        'priority' => '0.5',  'changefreq' => 'weekly'],
            ['path' => '/about-us',       'priority' => '0.6',  'changefreq' => 'monthly'],
            ['path' => '/contact',        'priority' => '0.5',  'changefreq' => 'monthly'],
            ['path' => '/pricing',        'priority' => '0.7',  'changefreq' => 'monthly'],
            ['path' => '/help',           'priority' => '0.6',  'changefreq' => 'monthly'],
            ['path' => '/faq',            'priority' => '0.6',  'changefreq' => 'monthly'],
            ['path' => '/shipping',       'priority' => '0.6',  'changefreq' => 'monthly'],
            ['path' => '/seller-guidelines','priority' => '0.6','changefreq' => 'monthly'],
            ['path' => '/terms',          'priority' => '0.4',  'changefreq' => 'yearly'],
            ['path' => '/legal',          'priority' => '0.4',  'changefreq' => 'yearly'],
            ['path' => '/privacy-policy', 'priority' => '0.4',  'changefreq' => 'yearly'],
            ['path' => '/return-policy',  'priority' => '0.4',  'changefreq' => 'yearly'],
        ];

        $products   = Product::approved()
            ->whereNull('deleted_at')
            ->select('slug_en', 'slug_mm', 'updated_at')
            ->get();

        $sellers    = SellerProfile::where('status', 'approved')
            ->select('store_slug', 'updated_at')
            ->get();

        $categories = Category::where('is_active', true)
            ->select('id', 'updated_at')
            ->get();

        // ── Build XML ──────────────────────────────────────────────────────
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        // xhtml namespace is required for hreflang alternate links in sitemaps
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Static pages
        foreach ($static as $page) {
            $baseLoc = $baseUrl . $page['path'];
            $xml .= $this->urlEntry(
                $baseLoc,
                null,
                $page['changefreq'],
                $page['priority']
            );
        }

        // Category-filtered product listing pages
        foreach ($categories as $cat) {
            $baseLoc = $baseUrl . '/products?category=' . $cat->id;
            $xml .= $this->urlEntry($baseLoc, $cat->updated_at, 'weekly', '0.7');
        }

        // Product pages — use slug_en as canonical path; also emit slug_mm if different
        foreach ($products as $product) {
            $slugEn = $product->slug_en;
            $slugMm = $product->slug_mm;

            if ($slugEn) {
                $baseLoc = $baseUrl . '/products/' . $slugEn;
                $xml .= $this->urlEntry($baseLoc, $product->updated_at, 'weekly', '0.8');
            }

            // If there is a distinct Myanmar slug, add it as a separate entry
            if ($slugMm && $slugMm !== $slugEn) {
                $baseLoc = $baseUrl . '/products/' . $slugMm;
                $xml .= $this->urlEntry($baseLoc, $product->updated_at, 'weekly', '0.8');
            }
        }

        // Seller pages
        foreach ($sellers as $seller) {
            if (!$seller->store_slug) continue;
            $baseLoc = $baseUrl . '/sellers/' . $seller->store_slug;
            $xml .= $this->urlEntry($baseLoc, $seller->updated_at, 'weekly', '0.7');
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Build one <url> block.
     *
     * We add xhtml:link alternate entries for every URL so Googlebot can
     * discover both language versions from any entry in the sitemap.
     *
     * The canonical <loc> always uses ?lang=en (English as default), and
     * the Myanmar alternate points to ?lang=my.
     */
    private function urlEntry(
        string  $baseLoc,
        $lastmod      = null,
        string  $changefreq = 'weekly',
        string  $priority   = '0.5'
    ): string {
        $sep   = str_contains($baseLoc, '?') ? '&' : '?';
        $enUrl = $baseLoc . $sep . 'lang=en';
        $myUrl = $baseLoc . $sep . 'lang=my';

        $entry  = "  <url>\n";
        // Canonical location = English version
        $entry .= "    <loc>" . htmlspecialchars($enUrl) . "</loc>\n";

        if ($lastmod) {
            $entry .= "    <lastmod>" . (is_string($lastmod) ? $lastmod : $lastmod->toDateString()) . "</lastmod>\n";
        }

        $entry .= "    <changefreq>{$changefreq}</changefreq>\n";
        $entry .= "    <priority>{$priority}</priority>\n";

        // hreflang alternates — required by Google for multilingual sites
        $entry .= "    <xhtml:link rel=\"alternate\" hreflang=\"en\"        href=\"" . htmlspecialchars($enUrl) . "\" />\n";
        $entry .= "    <xhtml:link rel=\"alternate\" hreflang=\"my\"        href=\"" . htmlspecialchars($myUrl) . "\" />\n";
        $entry .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . htmlspecialchars($enUrl) . "\" />\n";

        $entry .= "  </url>\n";

        // Also emit the Myanmar URL as its own <url> so it gets crawled
        $entry .= "  <url>\n";
        $entry .= "    <loc>" . htmlspecialchars($myUrl) . "</loc>\n";

        if ($lastmod) {
            $entry .= "    <lastmod>" . (is_string($lastmod) ? $lastmod : $lastmod->toDateString()) . "</lastmod>\n";
        }

        $entry .= "    <changefreq>{$changefreq}</changefreq>\n";
        $entry .= "    <priority>{$priority}</priority>\n";
        $entry .= "    <xhtml:link rel=\"alternate\" hreflang=\"en\"        href=\"" . htmlspecialchars($enUrl) . "\" />\n";
        $entry .= "    <xhtml:link rel=\"alternate\" hreflang=\"my\"        href=\"" . htmlspecialchars($myUrl) . "\" />\n";
        $entry .= "    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"" . htmlspecialchars($enUrl) . "\" />\n";
        $entry .= "  </url>\n";

        return $entry;
    }
}
