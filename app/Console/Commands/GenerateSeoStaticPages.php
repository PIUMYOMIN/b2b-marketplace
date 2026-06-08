<?php

namespace App\Console\Commands;

use App\Http\Controllers\FrontendController;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class GenerateSeoStaticPages extends Command
{
    protected $signature = 'seo:generate-static
        {--type=all : Page group to generate: all, public, products, sellers, blog}
        {--slug= : Generate one dynamic slug for the selected type}
        {--output= : Absolute output directory, defaults to SEO_STATIC_OUTPUT_PATH or public/seo-static}
        {--delete-missing : Remove stale generated files for selected dynamic groups}';

    protected $description = 'Generate static SEO HTML files for public Pyonea pages without rebuilding the React app.';

    private array $languages = ['en', 'my'];

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $slug = $this->option('slug') ? trim((string) $this->option('slug')) : null;
        $output = rtrim((string) ($this->option('output') ?: env('SEO_STATIC_OUTPUT_PATH', public_path('seo-static'))), DIRECTORY_SEPARATOR);

        File::ensureDirectoryExists($output);

        $routes = $this->routesFor($type, $slug);
        if ($routes === []) {
            $this->warn('No SEO routes matched the current options.');
            return self::SUCCESS;
        }

        $written = [];
        foreach ($routes as $route) {
            foreach ($this->languages as $locale) {
                $relativePath = $this->writeRoute($route, $locale, $output);
                $written[] = $relativePath;
                $this->line("Wrote {$relativePath}");
            }
        }

        if ($this->option('delete-missing')) {
            $this->deleteMissing($type, $output, $written);
        }

        $this->info('Generated ' . count($written) . ' static SEO files in ' . $output);
        return self::SUCCESS;
    }

    private function routesFor(string $type, ?string $slug): array
    {
        return match ($type) {
            'public' => $this->publicRoutes(),
            'products' => $this->productRoutes($slug),
            'sellers' => $this->sellerRoutes($slug),
            'blog' => $this->blogRoutes($slug),
            'all' => array_merge(
                $this->publicRoutes(),
                $this->productRoutes($slug),
                $this->sellerRoutes($slug),
                $this->blogRoutes($slug),
            ),
            default => [],
        };
    }

    private function publicRoutes(): array
    {
        return [
            '/', '/products', '/categories', '/sellers', '/blog', '/bulk-order-tool', '/local-deals',
            '/pricing', '/about-us', '/help', '/faq', '/shipping', '/contact', '/seller-guidelines',
            '/compare', '/legal', '/terms', '/privacy-policy', '/return-policy',
        ];
    }

    private function productRoutes(?string $slug): array
    {
        $query = Product::approved()->whereNull('deleted_at')->select('slug_en', 'slug_mm');
        if ($slug) {
            $query->where(fn ($q) => $q->where('slug_en', $slug)->orWhere('slug_mm', $slug));
        }

        return $query->get()
            ->flatMap(fn (Product $product) => array_filter([$product->slug_en, $product->slug_mm]))
            ->unique()
            ->map(fn (string $value) => '/products/' . $value)
            ->values()
            ->all();
    }

    private function sellerRoutes(?string $slug): array
    {
        $query = SellerProfile::query()
            ->whereIn('status', ['approved', 'active'])
            ->whereNotNull('store_slug')
            ->select('store_slug');

        if ($slug) {
            $query->where('store_slug', $slug);
        }

        return $query->get()
            ->pluck('store_slug')
            ->filter()
            ->unique()
            ->map(fn (string $value) => '/sellers/' . $value)
            ->values()
            ->all();
    }

    private function blogRoutes(?string $slug): array
    {
        $query = BlogPost::published()->select('slug');
        if ($slug) {
            $query->where('slug', $slug);
        }

        return $query->get()
            ->pluck('slug')
            ->filter()
            ->unique()
            ->map(fn (string $value) => '/blog/' . $value)
            ->values()
            ->all();
    }

    private function writeRoute(string $path, string $locale, string $output): string
    {
        app()->setLocale($locale);

        $request = Request::create($path . '?lang=' . $locale, 'GET', ['lang' => $locale]);
        $metadata = $this->metadataFor($path, $locale, $request);
        $html = view('app', $metadata)->render();

        $relativePath = $this->relativeHtmlPath($path, $locale);
        $absolutePath = $output . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, $html);

        return $relativePath;
    }

    private function metadataFor(string $path, string $locale, Request $request): array
    {
        $controller = app(FrontendController::class);
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('resolveMetadata');
        $method->setAccessible(true);

        return $method->invoke($controller, $path, $locale, $request);
    }

    private function relativeHtmlPath(string $path, string $locale): string
    {
        $cleanPath = trim($path, '/');
        if ($cleanPath === '') {
            return $locale . '/index.html';
        }

        return $locale . '/' . $cleanPath . '/index.html';
    }

    private function deleteMissing(string $type, string $output, array $written): void
    {
        if (! in_array($type, ['all', 'products', 'sellers', 'blog'], true)) {
            return;
        }

        $keep = array_flip(array_map(fn ($path) => str_replace('/', DIRECTORY_SEPARATOR, $path), $written));
        $groups = $type === 'all' ? ['products', 'sellers', 'blog'] : [$type];

        foreach ($this->languages as $locale) {
            foreach ($groups as $group) {
                $dir = $output . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $group;
                if (! File::isDirectory($dir)) {
                    continue;
                }

                foreach (File::allFiles($dir) as $file) {
                    $relative = ltrim(str_replace($output, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                    if (! isset($keep[$relative])) {
                        File::delete($file->getPathname());
                        $this->line('Deleted stale ' . $relative);
                    }
                }
            }
        }
    }
}
