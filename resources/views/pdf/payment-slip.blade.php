{{--
  Pyonea payment / order slip PDF template.
  Expected variables:
    $order          object/array with order_number, created_at, payment_*, customer, shipping, items, totals
    $labels         translated label strings (platform_name, receipt_heading, ...)
    $statusLabel    e.g. Paid / Order Confirmed
    $methodNote     short payment-method note
    $accentColor    hex color (default #308B49)
    $logoDataUri    optional data-uri for logo (preferred for DomPDF)
    $fontFacesCss   optional @font-face CSS with Torus + Noto Sans Myanmar
--}}
@php
    $accent = $accentColor ?? '#308B49';
    $logo = $logoDataUri ?? asset('images/brand/icon.png');
    $esc = fn ($value) => e((string) ($value ?? ''));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>{{ $labels['document_title'] ?? 'Payment Receipt' }} - {{ $order['order_number'] ?? '' }}</title>
    <style>
        {!! $fontFacesCss ?? '' !!}
        .pyo-slip, .pyo-slip * { box-sizing: border-box; }
        .pyo-slip {
            color: #111827;
            font-family: "Noto Sans Myanmar", DejaVu Sans, Arial, sans-serif;
        }
        .pyo-slip .receipt {
            max-width: 760px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
        }
        .pyo-slip .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 24px 26px;
            border-bottom: 3px solid {{ $accent }};
        }
        .pyo-slip .brand { display: flex; gap: 12px; align-items: flex-start; }
        .pyo-slip .brand img { width: 58px; height: 58px; object-fit: contain; }
        .pyo-slip .brand h1 {
            margin: 2px 0 4px;
            font-size: 22px;
            font-weight: 600;
            font-family: "Torus-SemiBold", "Noto Sans Myanmar", DejaVu Sans, Arial, sans-serif;
            color: #308B49;
        }
        .pyo-slip .brand p,
        .pyo-slip .muted { margin: 0; color: #6b7280; font-size: 11px; line-height: 1.55; }
        .pyo-slip .title { text-align: right; min-width: 190px; }
        .pyo-slip .title h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            font-family: "Torus-SemiBold", "Noto Sans Myanmar", DejaVu Sans, Arial, sans-serif;
        }
        .pyo-slip .badge {
            display: inline-block;
            margin-top: 10px;
            padding: 7px 13px;
            border-radius: 999px;
            background: #ecfdf5;
            color: {{ $accent }};
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .pyo-slip .method-note {
            margin: 0 0 18px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-left: 4px solid {{ $accent }};
            border-radius: 10px;
            background: #f9fafb;
            color: #374151;
            font-size: 12px;
            line-height: 1.5;
        }
        .pyo-slip .body { padding: 24px 26px 22px; }
        .pyo-slip .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }
        .pyo-slip .box {
            padding: 11px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f9fafb;
        }
        .pyo-slip .box span {
            display: block;
            color: #6b7280;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .pyo-slip .box strong {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            word-break: break-word;
        }
        .pyo-slip .parties {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 20px 0;
        }
        .pyo-slip .party {
            min-height: 116px;
            padding: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }
        .pyo-slip .party h3 { margin: 0 0 10px; font-size: 13px; }
        .pyo-slip .party p { margin: 4px 0; color: #374151; font-size: 12px; line-height: 1.45; }
        .pyo-slip table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
        .pyo-slip th {
            padding: 9px 8px;
            border-bottom: 1px solid #d1d5db;
            background: #f3f4f6;
            color: #374151;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            text-align: left;
        }
        .pyo-slip td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .pyo-slip td span { display: block; margin-top: 4px; color: #6b7280; font-size: 10px; }
        .pyo-slip th:nth-child(n+2),
        .pyo-slip td:nth-child(n+2) { text-align: right; white-space: nowrap; }
        .pyo-slip .empty { text-align: center !important; color: #6b7280; }
        .pyo-slip .summary { width: 320px; margin: 20px 0 0 auto; }
        .pyo-slip .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 7px 0;
            color: #4b5563;
            font-size: 12px;
        }
        .pyo-slip .row strong { color: #111827; }
        .pyo-slip .total {
            margin-top: 8px;
            padding-top: 12px;
            border-top: 1px solid #d1d5db;
            font-size: 15px;
            font-weight: 800;
        }
        .pyo-slip .total strong { color: {{ $accent }}; font-size: 17px; }
        .pyo-slip .footer {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 10px;
        }
    </style>
</head>
<body>
<div class="pyo-slip">
    <main class="receipt">
        <section class="header">
            <div class="brand">
                <img src="{{ $logo }}" alt="Pyonea" />
                <div>
                    <h1>{{ $labels['platform_name'] ?? 'Pyonea B2B Marketplace' }}</h1>
                    <p>{{ $labels['platform_address'] ?? '' }}</p>
                </div>
            </div>
            <div class="title">
                <h2>{{ $labels['receipt_heading'] ?? 'PAYMENT RECEIPT' }}</h2>
                <p class="muted">{{ $labels['generated_receipt'] ?? '' }}</p>
                <span class="badge">{{ $statusLabel ?? ($labels['status'] ?? 'Confirmed') }}</span>
            </div>
        </section>
        <section class="body">
            @if(!empty($methodNote))
                <p class="method-note">{{ $methodNote }}</p>
            @endif
            <div class="grid">
                <div class="box"><span>{{ $labels['order_number'] ?? 'Order Number' }}</span><strong>{{ $order['order_number'] ?? '—' }}</strong></div>
                <div class="box"><span>{{ $labels['order_date'] ?? 'Order Date' }}</span><strong>{{ $order['order_date'] ?? '—' }}</strong></div>
                <div class="box"><span>{{ $labels['payment_method'] ?? 'Payment Method' }}</span><strong>{{ $order['payment_method_label'] ?? '—' }}</strong></div>
                @if(!empty($order['payment_reference']))
                    <div class="box"><span>{{ $labels['reference_id'] ?? 'Reference ID' }}</span><strong>{{ $order['payment_reference'] }}</strong></div>
                @endif
                <div class="box"><span>{{ $labels['status'] ?? 'Status' }}</span><strong>{{ $statusLabel ?? '—' }}</strong></div>
                <div class="box"><span>{{ $labels['payment_date'] ?? 'Payment Date' }}</span><strong>{{ $order['payment_date'] ?? '—' }}</strong></div>
            </div>
            <div class="parties">
                <div class="party">
                    <h3>{{ $labels['customer_information'] ?? 'Customer Information' }}</h3>
                    @forelse(($order['customer_lines'] ?? []) as $line)
                        <p>{{ $line }}</p>
                    @empty
                        <p>{{ $labels['not_available'] ?? 'N/A' }}</p>
                    @endforelse
                </div>
                <div class="party">
                    <h3>{{ $labels['shipping_address'] ?? 'Shipping Address' }}</h3>
                    @forelse(($order['shipping_lines'] ?? []) as $line)
                        <p>{{ $line }}</p>
                    @empty
                        <p>{{ $labels['not_available'] ?? 'N/A' }}</p>
                    @endforelse
                </div>
            </div>
            <h3>{{ $labels['order_items'] ?? 'Order Items' }}</h3>
            <table>
                <thead>
                <tr>
                    <th>{{ $labels['item'] ?? 'Item' }}</th>
                    <th>{{ $labels['qty'] ?? 'Qty' }}</th>
                    <th>{{ $labels['price'] ?? 'Price' }}</th>
                    <th>{{ $labels['total'] ?? 'Total' }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse(($order['items'] ?? []) as $item)
                    <tr>
                        <td>
                            <strong>{{ $item['product_name'] ?? '—' }}</strong>
                            <span>SKU: {{ $item['product_sku'] ?? ($labels['not_available'] ?? 'N/A') }}</span>
                        </td>
                        <td>{{ $item['quantity'] ?? 0 }}</td>
                        <td>{{ $item['price'] ?? '—' }}</td>
                        <td>{{ $item['subtotal'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">{{ $labels['no_items'] ?? 'No items found in this order.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="summary">
                <div class="row"><span>{{ $labels['subtotal'] ?? 'Subtotal' }}</span><strong>{{ $order['subtotal_amount'] ?? '—' }}</strong></div>
                <div class="row"><span>{{ $labels['shipping'] ?? 'Shipping' }}</span><strong>{{ $order['shipping_fee'] ?? '—' }}</strong></div>
                <div class="row"><span>{{ $labels['tax_label'] ?? 'Tax' }}</span><strong>{{ $order['tax_amount'] ?? '—' }}</strong></div>
                <div class="row total"><span>{{ $labels['total_label'] ?? 'Total' }}</span><strong>{{ $order['total_amount'] ?? '—' }}</strong></div>
            </div>
            <div class="footer">
                <p>{{ $labels['footer_thanks'] ?? 'Thank you for your business!' }}</p>
                <p>{{ $labels['support_line'] ?? '' }}</p>
            </div>
        </section>
    </main>
</div>
</body>
</html>
