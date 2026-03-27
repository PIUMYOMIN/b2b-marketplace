{{-- resources/views/emails/layout.blade.php --}}
{{-- Shared Pyonea email layout. Usage: @extends('emails.layout') --}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $subject ?? config('app.name') }}</title>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f4f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            color: #374151;
        }

        .wrapper {
            width: 100%;
            background-color: #f4f7fa;
            padding: 32px 16px;
        }

        .card {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
        }

        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 28px 40px;
            text-align: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .logo span {
            color: rgba(255, 255, 255, .7);
        }

        .tagline {
            font-size: 12px;
            color: rgba(255, 255, 255, .8);
            margin-top: 4px;
        }

        .body {
            padding: 36px 40px;
        }

        .greeting {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
        }

        .text {
            font-size: 15px;
            color: #4b5563;
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff !important;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            padding: 14px 32px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .btn-outline {
            display: inline-block;
            border: 2px solid #10b981;
            color: #059669 !important;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 10px;
        }

        .divider {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 24px 0;
        }

        .info-box {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
        }

        .info-box p {
            font-size: 14px;
            color: #065f46;
            margin: 4px 0;
        }

        .warning-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
        }

        .warning-box p {
            font-size: 14px;
            color: #92400e;
            margin: 4px 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin: 16px 0;
        }

        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
        }

        .data-table td:first-child {
            color: #6b7280;
            font-weight: 500;
            width: 45%;
        }

        .data-table td:last-child {
            color: #111827;
            font-weight: 600;
            text-align: right;
        }

        .data-table .total td {
            border-top: 2px solid #e5e7eb;
            border-bottom: none;
            font-size: 16px;
        }

        .data-table .total td:last-child {
            color: #059669;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .footer {
            background: #f9fafb;
            padding: 24px 40px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer p {
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.6;
            margin: 4px 0;
        }

        .footer a {
            color: #059669;
            text-decoration: none;
        }

        .social {
            margin: 12px 0;
        }

        .social a {
            display: inline-block;
            margin: 0 6px;
            color: #059669 !important;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
        }

        @media (max-width: 600px) {

            .body,
            .footer {
                padding: 24px 20px;
            }

            .header {
                padding: 20px 24px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="logo">Pyonea<span>.com</span></div>
                <div class="tagline">Myanmar's Trusted B2B Marketplace</div>
            </div>
            <div class="body">
                @yield('content')
            </div>
            <div class="footer">
                <p>You're receiving this email from <strong>Pyonea Marketplace</strong></p>
                <p>Yangon, Myanmar &nbsp;·&nbsp; <a href="https://pyonea.com">pyonea.com</a></p>
                @hasSection('unsubscribe_url')
                    <p style="margin-top:10px;"><a href="@yield('unsubscribe_url')">Unsubscribe</a> &nbsp;·&nbsp; <a
                            href="https://pyonea.com/privacy-policy">Privacy Policy</a></p>
                @endif
                @unless(isset($hide_unsubscribe) && $hide_unsubscribe)
                    @hasSection('footer_note')
                        <p style="margin-top:8px; font-size:11px;">@yield('footer_note')</p>
                    @endif
                @endunless
            </div>
        </div>
    </div>
</body>

</html>