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

  <!-- Breadcrumb JSON-LD -->
  @if (!empty($breadcrumbs))
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
      @foreach ($breadcrumbs as $i => $crumb)
      {
        "@type": "ListItem",
        "position": {{ $i + 1 }},
        "name": "{{ $crumb['name'] }}",
        "item": "https://pyonea.com{{ $crumb['url'] }}"
      }{{ !$loop->last ? ',' : '' }}
      @endforeach
    ]
  }
  </script>
  @endif

  <!-- Product JSON-LD -->
  @if (!empty($product))
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "{{ $product['name'] }}",
    "description": "{{ addslashes($product['description'] ?? '') }}",
    "sku": "{{ $product['sku'] ?? '' }}",
    "image": [
      @foreach (($product['images'] ?? []) as $img)
      "{{ $img['url'] }}"{{ !$loop->last ? ',' : '' }}
      @endforeach
    ],
    "brand": {
      "@type": "Brand",
      "name": "{{ $product['brand'] ?? 'Pyonea Seller' }}"
    },
    "offers": {
      "@type": "Offer",
      "priceCurrency": "MMK",
      "price": "{{ $product['price'] ?? 0 }}",
      "availability": "{{ ($product['inStock'] ?? true) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}",
      "url": "https://pyonea.com/products/{{ $product['slug'] ?? '' }}"
    }
    @if (!empty($product['review_count']))
    ,"aggregateRating": {
      "@type": "AggregateRating",
      "ratingValue": "{{ $product['average_rating'] ?? 0 }}",
      "reviewCount": "{{ $product['review_count'] }}"
    }
    @endif
  }
  </script>
  @endif

  <!-- Seller JSON-LD -->
  @if (!empty($seller))
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Store",
    "name": "{{ $seller['store_name'] }}",
    "description": "{{ addslashes($seller['store_description'] ?? '') }}",
    "url": "https://pyonea.com/shops/{{ $seller['slug'] ?? '' }}",
    "logo": "{{ $seller['store_logo'] ?? '' }}"
    @if (!empty($seller['address']))
    ,"address": {
      "@type": "PostalAddress",
      "addressLocality": "{{ $seller['address']['city'] ?? '' }}",
      "addressRegion": "{{ $seller['address']['state'] ?? '' }}",
      "addressCountry": "MM"
    }
    @endif
  }
  </script>
  @endif

  <!-- Base site JSON-LD (always present) -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "name": "Pyonea Marketplace",
        "url": "https://pyonea.com",
        "logo": "https://pyonea.com/logo.png"
      },
      {
        "@type": "WebSite",
        "name": "Pyonea Marketplace",
        "url": "https://pyonea.com",
        "potentialAction": {
          "@type": "SearchAction",
          "target": "https://pyonea.com/products?search={search_term_string}",
          "query-input": "required name=search_term_string"
        }
      }
    ]
  }
  </script>

  <!-- Performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

  <!-- Icons & PWA -->
  <link rel="icon" href="/favicon.ico">
  <link rel="apple-touch-icon" href="/og-image.png">
  <link rel="manifest" href="/manifest.json" />
  <meta name="theme-color" content="#16a34a" />

  <!-- SPA Assets (resolved dynamically — hash changes on every build) -->
  @php
    $distJs  = collect(glob(public_path('assets/index-*.js')))->first();
    $distCss = collect(glob(public_path('assets/index-*.css')))->first();
  @endphp
  @if($distJs)
    <script type="module" crossorigin src="/assets/{{ basename($distJs) }}"></script>
  @endif
  @if($distCss)
    <link rel="stylesheet" crossorigin href="/assets/{{ basename($distCss) }}">
  @endif
</head>

<body>
  <div id="root"></div>
</body>

</html>