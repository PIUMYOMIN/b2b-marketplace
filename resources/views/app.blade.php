<!DOCTYPE html>
<html lang="{{ $lang }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="keywords" content="{{ $pageKeywords }}">
    <link rel="canonical" href="{{ $pageUrl }}">

    <!-- hreflang (adjust if your language uses subdirectories) -->
    <link rel="alternate" hreflang="en" href="https://pyonea.com{{ $canonicalPath }}" />
    <link rel="alternate" hreflang="my" href="https://pyonea.com/my{{ $canonicalPath }}" />
    <link rel="alternate" hreflang="x-default" href="https://pyonea.com{{ $canonicalPath }}" />

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:image" content="{{ $pageImage }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:type" content="{{ $pageType }}">
    <meta property="og:site_name" content="Pyonea Marketplace">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $pageImage }}">
    <meta name="twitter:site" content="@pyonea">

    <!-- Icons & manifest -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    @if($noindex)
        <meta name="robots" content="noindex,follow">
    @endif

    <!-- JSON‑LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Organization",
                "name": "Pyonea Marketplace",
                "url": "{{ url('/') }}",
                "logo": "{{ asset('logo.png') }}",
                "sameAs": [
                    "https://www.facebook.com/pyonea",
                    "https://twitter.com/pyonea",
                    "https://www.instagram.com/pyonea"
                ]
            },
            {
                "@type": "WebSite",
                "name": "Pyonea Marketplace",
                "url": "{{ url('/') }}",
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": {
                        "@type": "EntryPoint",
                        "urlTemplate": "{{ url('/search?q={search_term_string}') }}"
                    },
                    "query-input": "required name=search_term_string"
                }
            }

            @if(!empty($breadcrumbs))
                ,{
                    "@type": "BreadcrumbList",
                    "itemListElement": [
                        @foreach($breadcrumbs as $index => $crumb)
                            {
                                "@type": "ListItem",
                                "position": {{ $index + 1 }},
                                "name": "{{ $crumb['name'] }}",
                                "item": "{{ url($crumb['url']) }}"
                            }@if(!$loop->last),@endif
                        @endforeach
                    ]
                }
            @endif

            @if(!empty($categories))
                ,{
                    "@type": "ItemList",
                    "itemListElement": [
                        @foreach($categories as $index => $cat)
                            {
                                "@type": "ListItem",
                                "position": {{ $index + 1 }},
                                "url": "{{ url('categories/' . $cat['slug']) }}",
                                "name": "{{ $cat['name'] }}"
                            }@if(!$loop->last),@endif
                        @endforeach
                    ]
                }
            @endif

            @if(!empty($product))
                ,{
                    "@type": "Product",
                    "name": "{{ $product['name'] }}",
                    "image": [
                        @foreach($product['images'] as $img)
                            "{{ $img['url'] }}"@if(!$loop->last),@endif
                        @endforeach
                    ],
                    "description": "{{ $product['description'] }}",
                    "sku": "{{ $product['sku'] }}",
                    "brand": { "@type": "Brand", "name": "{{ $product['brand'] }}" },
                    "offers": {
                        "@type": "Offer",
                        "url": "{{ url('products/' . $product['slug']) }}",
                        "priceCurrency": "MMK",
                        "price": "{{ $product['price'] }}",
                        "availability": "{{ $product['inStock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' }}",
                        "itemCondition": "https://schema.org/NewCondition"
                    }
                    @if($product['review_count'])
                        ,"aggregateRating": {
                            "@type": "AggregateRating",
                            "ratingValue": "{{ $product['average_rating'] }}",
                            "reviewCount": "{{ $product['review_count'] }}"
                        }
                    @endif
                    @if(!empty($product['reviews']))
                        ,"review": [
                            @foreach($product['reviews'] as $review)
                                {
                                    "@type": "Review",
                                    "author": { "@type": "Person", "name": "{{ $review['user_name'] }}" },
                                    "datePublished": "{{ $review['created_at'] }}",
                                    "reviewBody": "{{ $review['comment'] }}",
                                    "reviewRating": {
                                        "@type": "Rating",
                                        "ratingValue": "{{ $review['rating'] }}",
                                        "bestRating": "5",
                                        "worstRating": "1"
                                    }
                                }@if(!$loop->last),@endif
                            @endforeach
                        ]
                    @endif
                }
            @endif

            @if(!empty($seller))
                ,{
                    "@type": "{{ $seller['hasStorefront'] ? 'LocalBusiness' : 'Organization' }}",
                    "name": "{{ $seller['store_name'] }}",
                    "url": "{{ url('sellers/' . $seller['slug']) }}",
                    "description": "{{ $seller['store_description'] }}",
                    "image": "{{ $seller['store_logo'] }}"
                    @if($seller['address'])
                        ,"address": {
                            "@type": "PostalAddress",
                            "addressLocality": "{{ $seller['address']['city'] }}",
                            "addressRegion": "{{ $seller['address']['state'] }}",
                            "addressCountry": "MM"
                        }
                    @endif
                    @if(!empty($seller['sameAs']))
                        ,"sameAs": [
                            @foreach($seller['sameAs'] as $link)
                                "{{ $link }}"@if(!$loop->last),@endif
                            @endforeach
                        ]
                    @endif
                }
            @endif
        ]
    }
    </script>

    <!-- Vite or Mix assets – adjust according to your build tool -->
    @viteReactRefresh
    @vite('resources/js/app.js')
</head>

<body>
    <div id="root"></div>
</body>

</html>
