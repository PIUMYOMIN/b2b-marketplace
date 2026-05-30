<!DOCTYPE html>
{{--
  app.blade.php — SPA shell served by FrontendController for every public route.

  Language / SEO strategy
  ────────────────────────
  We use ?lang=en / ?lang=my as URL differentiators so Google can index both
  language versions of every page independently.

  • SetLocale middleware reads ?lang= (or Accept-Language) and sets app()->getLocale().
  • FrontendController resolves locale-aware titles / descriptions and passes them here.
  • hreflang links tell Google which URL to show for each language.
  • The React app reads ?lang= via i18next-browser-languagedetector (querystring order)
    and switches the UI language to match what Google indexed.
--}}
@php
  $locale    = $lang ?? 'en';   // 'en' or 'my'
  $isMyanmar = $locale === 'my';

  // Build language-specific alternates from the canonical page URL.
  // $pageUrl already includes the path; we append / replace the lang param.
  $basePageUrl = preg_replace('/([?&])lang=[^&]*/','', $pageUrl ?? 'https://pyonea.com/');
  $basePageUrl = rtrim($basePageUrl, '?&');
  $sep         = str_contains($basePageUrl, '?') ? '&' : '?';

  $enUrl       = $basePageUrl . $sep . 'lang=en';
  $myUrl       = $basePageUrl . $sep . 'lang=my';

  $ogLocale    = $isMyanmar ? 'my_MM' : 'en_US';
  $ogLocaleAlt = $isMyanmar ? 'en_US' : 'my_MM';
  $facebookAppId = config('services.facebook.client_id');
@endphp
<html lang="{{ $locale }}">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Primary Meta -->
  <title>{{ $pageTitle ?? 'Pyonea Marketplace | Buy & Sell Products in Myanmar' }}</title>
  <meta name="description" content="{{ $pageDescription ?? 'Buy and sell products easily across Myanmar with Pyonea marketplace. Discover trusted sellers and great deals.' }}" />
  @if (!empty($pageKeywords))
  <meta name="keywords" content="{{ $pageKeywords }}" />
  @endif

  @if (!empty($noindex))
  <meta name="robots" content="noindex,nofollow" />
  @else
  <meta name="robots" content="index, follow" />
  @endif

  <!-- Canonical — points to this page in its current language -->
  <link rel="canonical" href="{{ $pageUrl ?? 'https://pyonea.com/' }}" />

  <!-- hreflang — distinct URLs per language so Google indexes both separately -->
  <link rel="alternate" hreflang="en"        href="{{ $enUrl }}" />
  <link rel="alternate" hreflang="my"        href="{{ $myUrl }}" />
  <link rel="alternate" hreflang="x-default" href="{{ $enUrl }}" />

  <!-- Open Graph -->
  <meta property="og:type"             content="{{ $pageType ?? 'website' }}" />
  <meta property="og:title"            content="{{ $pageTitle ?? 'Pyonea Marketplace' }}" />
  <meta property="og:description"      content="{{ $pageDescription ?? 'Buy and sell products easily across Myanmar with Pyonea marketplace.' }}" />
  <meta property="og:image"            content="{{ $pageImage ?? 'https://pyonea.com/og-image.png' }}" />
  <meta property="og:image:secure_url" content="{{ $pageImage ?? 'https://pyonea.com/og-image.png' }}" />
  <meta property="og:image:width"      content="1200" />
  <meta property="og:image:height"     content="630" />
  <meta property="og:image:alt"        content="{{ $pageTitle ?? 'Pyonea Marketplace' }}" />
  <meta property="og:url"              content="{{ $pageUrl ?? 'https://pyonea.com/' }}" />
  <meta property="og:site_name"        content="Pyonea" />
  @if (!empty($facebookAppId))
  <meta property="fb:app_id"           content="{{ $facebookAppId }}" />
  @endif
  <!-- og:locale reflects the language actually on this page -->
  <meta property="og:locale"           content="{{ $ogLocale }}" />
  <meta property="og:locale:alternate" content="{{ $ogLocaleAlt }}" />

  <!-- Twitter Card -->
  <meta name="twitter:card"            content="summary_large_image" />
  <meta name="twitter:site"            content="@PyoneaMarket" />
  <meta name="twitter:title"           content="{{ $pageTitle ?? 'Pyonea Marketplace' }}" />
  <meta name="twitter:description"     content="{{ $pageDescription ?? 'Buy and sell products easily across Myanmar with Pyonea marketplace.' }}" />
  <meta name="twitter:image"           content="{{ $pageImage ?? 'https://pyonea.com/og-image.png' }}" />
  <meta name="twitter:image:alt"       content="{{ $pageTitle ?? 'Pyonea Marketplace' }}" />

  <!-- Breadcrumb JSON-LD -->
  @if (!empty($breadcrumbs))
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "BreadcrumbList",
    "itemListElement": [
      @foreach ($breadcrumbs as $i => $crumb)
      {
        "@@type": "ListItem",
        "position": {{ $i + 1 }},
        "name": "{{ addslashes($crumb['name']) }}",
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
    "@@context": "https://schema.org",
    "@@type": "Product",
    "name": "{{ addslashes($product['name']) }}",
    "description": "{{ addslashes($product['description'] ?? '') }}",
    "sku": "{{ $product['sku'] ?? '' }}",
    "image": [
      @foreach (($product['images'] ?? []) as $img)
      "{{ $img['url'] }}"{{ !$loop->last ? ',' : '' }}
      @endforeach
    ],
    "brand": {
      "@@type": "Brand",
      "name": "{{ addslashes($product['brand'] ?? 'Pyonea Seller') }}"
    },
    "offers": {
      "@@type": "Offer",
      "priceCurrency": "MMK",
      "price": "{{ $product['price'] ?? 0 }}",
      "availability": "{{ ($product['inStock'] ?? true) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}",
      "url": "{{ $pageUrl ?? 'https://pyonea.com/products/' . ($product['slug'] ?? '') }}"
    }
    @if (!empty($product['review_count']))
    ,"aggregateRating": {
      "@@type": "AggregateRating",
      "ratingValue": "{{ $product['average_rating'] ?? 0 }}",
      "reviewCount": "{{ $product['review_count'] }}"
    }
    @endif
  }
  </script>
  @endif

  <!-- Blog Article JSON-LD -->
  @if (!empty($article))
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "Article",
    "headline": "{{ addslashes($article['headline'] ?? '') }}",
    "description": "{{ addslashes($article['description'] ?? '') }}",
    @if (!empty($article['image']))
    "image": "{{ $article['image'] }}",
    @else
    "image": "https://pyonea.com/og-image.png",
    @endif
    "datePublished": "{{ $article['datePublished'] ?? '' }}",
    "dateModified": "{{ $article['dateModified'] ?? '' }}",
    "author": {
      "@@type": "Person",
      "name": "{{ addslashes($article['authorName'] ?? 'Pyonea') }}"
    },
    "publisher": {
      "@@type": "Organization",
      "name": "Pyonea",
      "logo": {
        "@@type": "ImageObject",
        "url": "https://pyonea.com/logo.png"
      }
    },
    "mainEntityOfPage": "{{ $pageUrl ?? 'https://pyonea.com/blog/' . ($article['slug'] ?? '') }}"
  }
  </script>
  @endif

  <!-- Seller JSON-LD -->
  @if (!empty($seller))
  <script type="application/ld+json">
  {
    "@@context": "https://schema.org",
    "@@type": "Store",
    "name": "{{ addslashes($seller['store_name']) }}",
    "description": "{{ addslashes($seller['store_description'] ?? '') }}",
    "url": "https://pyonea.com/sellers/{{ $seller['slug'] ?? '' }}",
    "logo": "{{ $seller['store_logo'] ?? '' }}"
    @if (!empty($seller['address']))
    ,"address": {
      "@@type": "PostalAddress",
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
    "@@context": "https://schema.org",
    "@@graph": [
      {
        "@@type": "Organization",
        "name": "Pyonea Marketplace",
        "url": "https://pyonea.com",
        "logo": "https://pyonea.com/logo.png",
        "sameAs": []
      },
      {
        "@@type": "WebSite",
        "name": "Pyonea Marketplace",
        "url": "https://pyonea.com",
        "inLanguage": ["en", "my"],
        "potentialAction": {
          "@@type": "SearchAction",
          "target": "https://pyonea.com/products?search={search_term_string}&lang={{ $locale }}",
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
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <link rel="manifest" href="/manifest.json" />
  <meta name="theme-color" content="#16a34a" />

  <!-- SPA Assets -->
  @php
    $distJsFiles  = glob(public_path('assets/index-*.js'))  ?: [];
    $distCssFiles = glob(public_path('assets/index-*.css')) ?: [];
    $distJs       = count($distJsFiles)  > 0 ? $distJsFiles[0]  : null;
    $distCss      = count($distCssFiles) > 0 ? $distCssFiles[0] : null;
  @endphp
  @if(!empty($distJs))
    <script type="module" crossorigin src="/assets/{{ basename($distJs) }}"></script>
  @endif
  @if(!empty($distCss))
    <link rel="stylesheet" crossorigin href="/assets/{{ basename($distCss) }}">
  @endif
</head>

<body>
  <div id="root"></div>
</body>

</html>
