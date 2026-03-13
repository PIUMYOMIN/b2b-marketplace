<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        /* Reset styles */
        body, p, h1, h2, h3, h4, h5, h6, table, td, th, div, span {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
            line-height: 1.5;
            box-sizing: border-box;
        }
        body {
            background-color: #f4f7fa;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0;
            padding: 12px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 32px 24px;
            text-align: center;
        }
        .logo {
            max-width: 140px;
            height: auto;
            display: block;
            margin: 0 auto 12px;
        }
        .header h1 {
            color: #ffffff;
            font-size: 1.75rem;
            font-weight: 300;
            letter-spacing: 0.02em;
            margin-top: 8px;
        }
        .content {
            padding: 40px 32px;
        }
        .content h2 {
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .content p {
            color: #4b5563;
            font-size: 1rem;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
            transition: all 0.3s ease;
            margin: 20px 0 28px;
            border: none;
            cursor: pointer;
        }
        .info-box {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 16px 20px;
            border-radius: 12px;
            margin: 28px 0;
        }
        .info-box p {
            margin-bottom: 0;
            color: #065f46;
            font-size: 0.95rem;
        }
        .info-box strong {
            color: #047857;
            font-weight: 600;
        }
        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 32px 0 0;
        }
        .footer {
            background-color: #f9fafb;
            padding: 32px 24px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 12px;
        }
        .footer a {
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .url-break {
            word-break: break-all;
            color: #10b981;
            font-size: 0.875rem;
            margin-top: 4px;
        }
        @media only screen and (max-width: 480px) {
            body {
                padding: 8px;
            }
            .header {
                padding: 24px 16px;
            }
            .header h1 {
                font-size: 1.5rem;
            }
            .logo {
                max-width: 120px;
            }
            .content {
                padding: 28px 20px;
            }
            .content h2 {
                font-size: 1.3rem;
            }
            .btn {
                display: block;
                width: 100%;
                text-align: center;
                padding: 16px 20px;
                font-size: 1.1rem;
            }
            .info-box {
                padding: 14px 16px;
            }
            .footer {
                padding: 24px 16px;
            }
        }
        @media only screen and (max-width: 360px) {
            .header h1 {
                font-size: 1.3rem;
            }
            .content h2 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body style="background-color: #f4f7fa; margin:0; padding:12px;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#f4f7fa;">
        <tr>
            <td align="center" style="padding:0;">
                <div class="email-container"
                    style="max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 20px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <div class="header"
                        style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); padding:32px 24px; text-align:center;">
                        <img src="{{ config('app.logo_url') ?? asset('images/logo-white.png') }}" alt="{{ config('app.name') }}"
                            class="logo" style="max-width:140px; height:auto; display:block; margin:0 auto 12px;">
                        <h1 style="color:#ffffff; font-size:1.75rem; font-weight:300; letter-spacing:0.02em; margin-top:8px;">🔐 Reset Your
                            Password</h1>
                    </div>

                    <!-- Content -->
                    <div class="content" style="padding:40px 32px;">
                        <h2 style="color:#1f2937; font-size:1.5rem; font-weight:600; margin-bottom:16px;">Hello,
                            {{ $user->name ?? 'there' }}!</h2>
                        <p style="color:#4b5563; font-size:1rem; margin-bottom:20px;">
                            We received a request to reset the password for your account associated with this email address. No changes have
                            been made yet.
                        </p>

                        <!-- Call to action button -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center">
                                    <a href="{{ $url }}" class="btn"
                                        style="display:inline-block; background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#ffffff !important; text-decoration:none; padding:14px 36px; border-radius:50px; font-size:1rem; font-weight:600; letter-spacing:0.02em; box-shadow:0 4px 10px rgba(16,185,129,0.2); margin:20px 0 28px; border:none; cursor:pointer;">
                                        Reset Password
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <!-- Info box -->
                        <div class="info-box"
                            style="background-color:#ecfdf5; border-left:4px solid #10b981; padding:16px 20px; border-radius:12px; margin:28px 0;">
                            <p style="color:#065f46; font-size:0.95rem; margin-bottom:0;">
                                ⏳ This password reset link will expire in <strong>{{ config('auth.passwords.users.expire', 60) }}
                                    minutes</strong>. If you didn't request this, you can safely ignore this email – your password will remain
                                unchanged.
                            </p>
                        </div>

                        <p style="color:#4b5563; font-size:1rem; margin-bottom:0;">
                            If the button above doesn't work, copy and paste this URL into your browser:
                        </p>
                        <p class="url-break" style="color:#10b981; word-break:break-all; font-size:0.875rem; margin-top:4px;">
                            {{ $url }}
                        </p>
                    </div>

                    <!-- Divider -->
                    <div class="divider" style="height:1px; background-color:#e5e7eb; margin:0;"></div>

                    <!-- Footer -->
                    <div class="footer"
                        style="background-color:#f9fafb; padding:32px 24px; text-align:center; border-top:1px solid #e5e7eb;">
                        <p style="color:#6b7280; font-size:0.875rem; margin-bottom:12px;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </p>
                        <p style="color:#6b7280; font-size:0.875rem; margin-bottom:12px;">
                            <a href="{{ config('app.frontend_url') }}" style="color:#10b981; text-decoration:none; font-weight:500;">Visit our
                                website</a>
                            •
                            <a href="{{ config('app.frontend_url') }}/contact" style="color:#10b981; text-decoration:none; font-weight:500;">Contact
                                support</a>
                        </p>
                    </div>
                    </div>
                <p style="color:#9ca3af; font-size:0.75rem; margin-top:20px; text-align:center;">
                    This email was sent to you because a password reset was requested for your account. If you believe this is an error,
                    please contact support.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
