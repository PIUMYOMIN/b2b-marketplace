<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Primary Meta -->
  <title>{{ $pageTitle ?? 'Pyonea Marketplace | Buy & Sell Products in Myanmar' }}</title>
  <meta name="description" content="{{ $pageDescription ?? 'Buy and sell products easily across Myanmar with Pyonea marketplace. Discover trusted sellers and great deals.' }}" />

  @if (!empty($noindex))
  <meta name="robots" content="noindex,nofollow" />
  @else
  <meta name="robots" content="index, follow" />
  @endif

  <!-- Canonical -->
  <link rel="canonical" href="{{ $pageUrl ?? 'https://pyonea.com/' }}" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en" href="{{ $pageUrl ?? 'https://pyonea.com/' }}" />
  <link rel="alternate" hreflang="my" href="{{ $pageUrl ?? 'https://pyonea.com/' }}" />
  <link rel="alternate" hreflang="x-default" href="{{ $pageUrl ?? 'https://pyonea.com/' }}" />

  <!-- Open Graph -->
  <meta property="og:type"        content="{{ $pageType ?? 'website' }}" />
  <meta property="og:title"       content="{{ $pageTitle ?? 'Pyonea Marketplace' }}" />
  <meta property="og:description" content="{{ $pageDescription ?? 'Buy and sell products easily across Myanmar with Pyonea marketplace.' }}" />
  <meta property="og:image"       content="{{ $pageImage ?? 'https://pyonea.com/og-image.png' }}" />
  <meta property="og:url"         content="{{ $pageUrl ?? 'https://pyonea.com/' }}" />
  <meta property="og:site_name"   content="Pyonea" />

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image" />
  <meta name="twitter:title"       content="{{ $pageTitle ?? 'Pyonea Marketplace' }}" />
  <meta name="twitter:description" content="{{ $pageDescription ?? 'Buy and sell products easily across Myanmar with Pyonea marketplace.' }}" />
  <meta name="twitter:image"       content="{{ $pageImage ?? 'https://pyonea.com/og-image.png' }}" />

  @php
    /*
    |------------------------------------------------------------------
    | Pre-build ALL JSON-LD as PHP arrays, then json_encode() them.
    |
    | Root cause of the original crash: Blade's lexer scans the entire
    | template for @word patterns. Keys like "@context", "@type",
    | "@graph" inside <script> blocks were being matched as unknown
    | Blade directives, corrupting the compiled PHP output and causing
    | "unexpected end of file, expecting elseif or else or endif".
    |
    | Fix: zero @ symbols ever appear inside <script> tags. All
    | JSON-LD is built here in PHP and output with {!! !!}.
    |------------------------------------------------------------------
    */

    // Breadcrumb JSON-LD
    $breadcrumbJson = null;
    if (!empty($breadcrumbs)) {
        $items = [];
        foreach ($breadcrumbs as $i => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $crumb['name'],
                'item'     => 'https://pyonea.com' . $crumb['url'],
            ];
        }
        $breadcrumbJson = json_encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // Product JSON-LD
    $productJson = null;
    if (!empty($product)) {
        $p = $product;
        $pd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $p['name'] ?? '',
            'description' => $p['description'] ?? '',
            'sku'         => $p['sku'] ?? '',
            'image'       => array_column($p['images'] ?? [], 'url'),
            'brand'       => ['@type' => 'Brand', 'name' => $p['brand'] ?? 'Pyonea Seller'],
            'offers'      => [
                '@type'         => 'Offer',
                'priceCurrency' => 'MMK',
                'price'         => $p['price'] ?? 0,
                'availability'  => ($p['inStock'] ?? true)
                                    ? 'https://schema.org/InStock'
                                    : 'https://schema.org/OutOfStock',
                'url'           => 'https://pyonea.com/products/' . ($p['slug'] ?? ''),
            ],
        ];
        if (!empty($p['review_count'])) {
            $pd['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $p['average_rating'] ?? 0,
                'reviewCount' => $p['review_count'],
            ];
        }
        $productJson = json_encode($pd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // Seller / Store JSON-LD
    $sellerJson = null;
    if (!empty($seller)) {
        $s = $seller;
        $sd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Store',
            'name'        => $s['store_name'] ?? '',
            'description' => $s['store_description'] ?? '',
            'url'         => 'https://pyonea.com/sellers/' . ($s['slug'] ?? ''),
            'logo'        => $s['store_logo'] ?? '',
        ];
        if (!empty($s['address'])) {
            $sd['address'] = [
                '@type'           => 'PostalAddress',
                'addressLocality' => $s['address']['city'] ?? '',
                'addressRegion'   => $s['address']['state'] ?? '',
                'addressCountry'  => 'MM',
            ];
        }
        $sellerJson = json_encode($sd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // Site-wide JSON-LD (always output)
    $siteJson = json_encode([
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'Organization',
                'name'  => 'Pyonea Marketplace',
                'url'   => 'https://pyonea.com',
                'logo'  => 'https://pyonea.com/logo.png',
            ],
            [
                '@type'           => 'WebSite',
                'name'            => 'Pyonea Marketplace',
                'url'             => 'https://pyonea.com',
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => 'https://pyonea.com/products?search={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // SPA assets — Vite hashes filenames on every build
    $distJs  = collect(glob(public_path('assets/index-*.js')))->first();
    $distCss = collect(glob(public_path('assets/index-*.css')))->first();
  @endphp

  @if ($breadcrumbJson)
  <script type="application/ld+json">{!! $breadcrumbJson !!}</script>
  @endif

  @if ($productJson)
  <script type="application/ld+json">{!! $productJson !!}</script>
  @endif

  @if ($sellerJson)
  <script type="application/ld+json">{!! $sellerJson !!}</script>
  @endif

  <script type="application/ld+json">{!! $siteJson !!}</script>

  <!-- Performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

  <!-- Icons & PWA -->
  <link rel="icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <link rel="manifest" href="/manifest.json" />
  <meta name="theme-color" content="#16a34a" />

  @if ($distJs)
    <script type="module" crossorigin src="/assets/{{ basename($distJs) }}"></script>
  @endif
  @if ($distCss)
    <link rel="stylesheet" crossorigin href="/assets/{{ basename($distCss) }}">
  @endif
</head>

<body>
  <div id="root"></div>
</body>

</html>