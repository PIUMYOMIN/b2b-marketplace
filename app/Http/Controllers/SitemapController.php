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
     * Referenced in public/robots.txt as: Sitemap: https://pyonea.com/sitemap.xml
     */
    public function index(): Response
    {
        $baseUrl = rtrim(env('APP_FRONTEND_URL', 'https://pyonea.com'), '/');

        // Static pages
        $static = [
            ['loc' => $baseUrl . '/',                  'priority' => '1.0',  'changefreq' => 'daily'],
            ['loc' => $baseUrl . '/products',          'priority' => '0.9',  'changefreq' => 'daily'],
            ['loc' => $baseUrl . '/sellers',           'priority' => '0.8',  'changefreq' => 'daily'],
            ['loc' => $baseUrl . '/categories',        'priority' => '0.8',  'changefreq' => 'weekly'],
            ['loc' => $baseUrl . '/about',             'priority' => '0.5',  'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/contact',           'priority' => '0.5',  'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/pricing',           'priority' => '0.6',  'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/help',              'priority' => '0.4',  'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/privacy-policy',    'priority' => '0.3',  'changefreq' => 'yearly'],
            ['loc' => $baseUrl . '/return-policy',     'priority' => '0.3',  'changefreq' => 'yearly'],
            ['loc' => $baseUrl . '/legal',             'priority' => '0.3',  'changefreq' => 'yearly'],
        ];

        // Products — only active, non-deleted
        $products = Product::where('is_active', true)
            ->whereNull('deleted_at')
            ->select('slug', 'updated_at')
            ->get();

        // Sellers — only approved
        $sellers = SellerProfile::where('status', 'approved')
            ->select('slug', 'updated_at')
            ->get();

        // Categories
        $categories = Category::select('slug', 'updated_at')->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

  foreach ($static as $page) {
  $xml .= $this->urlEntry($page['loc'], null, $page['changefreq'], $page['priority']);
  }

  foreach ($categories as $cat) {
  if (!$cat->slug) continue;
  $xml .= $this->urlEntry(
  $baseUrl . '/categories/' . $cat->slug,
  $cat->updated_at,
  'weekly',
  '0.7'
  );
  }

  foreach ($products as $product) {
  if (!$product->slug) continue;
  $xml .= $this->urlEntry(
  $baseUrl . '/products/' . $product->slug,
  $product->updated_at,
  'weekly',
  '0.8'
  );
  }

  foreach ($sellers as $seller) {
  if (!$seller->slug) continue;
  $xml .= $this->urlEntry(
  $baseUrl . '/sellers/' . $seller->slug,
  $seller->updated_at,
  'weekly',
  '0.7'
  );
  }

  $xml .= '</urlset>';

return response($xml, 200)
->header('Content-Type', 'application/xml')
->header('Cache-Control', 'public, max-age=3600'); // Cache 1hr
}

private function urlEntry(string $loc, $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5'): string
{
$entry = " <url>\n";
  $entry .= " <loc>" . htmlspecialchars($loc) . "</loc>\n";
  if ($lastmod) {
  $entry .= " <lastmod>" . (is_string($lastmod) ? $lastmod : $lastmod->toDateString()) . "</lastmod>\n";
  }
  $entry .= " <changefreq>{$changefreq}</changefreq>\n";
  $entry .= " <priority>{$priority}</priority>\n";
  $entry .= " </url>\n";
return $entry;
}
}
