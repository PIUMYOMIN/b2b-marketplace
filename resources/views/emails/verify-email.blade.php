<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        /* Reset styles */
        body,
        p,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        table,
        td,
        th {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            line-height: 1.6;
        }

        body {
            background-color: #f4f7fa;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 40px 30px;
            text-align: center;
        }

        .logo {
            max-width: 160px;
            height: auto;
            display: block;
            margin: 0 auto 10px;
        }

        .header h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 0.5px;
        }

        .content {
            padding: 40px 30px;
        }

        .content h2 {
            color: #1f2937;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .content p {
            color: #4b5563;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.2);
            transition: all 0.3s ease;
            margin: 20px 0 25px;
        }

        .btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 6px 12px rgba(5, 150, 105, 0.25);
        }

        .info-box {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 16px 20px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .info-box p {
            margin-bottom: 0;
            color: #065f46;
            font-size: 15px;
        }

        .info-box strong {
            color: #047857;
        }

        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 30px 0;
        }

        .footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .footer a {
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media only screen and (max-width: 600px) {
            .header {
                padding: 30px 20px;
            }

            .content {
                padding: 30px 20px;
            }

            .footer {
                padding: 20px;
            }

            .btn {
                display: block;
                text-align: center;
            }
        }
    </style>
</head>

<body style="background-color: #f4f7fa; padding: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#f4f7fa;">
        <tr>
            <td align="center" style="padding:20px;">
                <div class="email-container"
                    style="max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <div class="header"
                        style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); padding:40px 30px; text-align:center;">
                        <img src="{{ config('app.logo_url') ?? asset('images/logo-white.png') }}"
                            alt="{{ config('app.name') }}" class="logo"
                            style="max-width:160px; height:auto; display:block; margin:0 auto 10px;">
                        <h1 style="color:#ffffff; font-size:28px; font-weight:300; letter-spacing:0.5px;">📧 Verify Your
                            Email</h1>
                    </div>

                    <!-- Content -->
                    <div class="content" style="padding:40px 30px;">
                        <h2 style="color:#1f2937; font-size:22px; font-weight:600; margin-bottom:20px;">Welcome,
                            {{ $user->name ?? 'there' }}!</h2>
                        <p style="color:#4b5563; font-size:16px; margin-bottom:20px;">
                            Thank you for signing up for {{ config('app.name') }}! We're excited to have you on board.
                        </p>
                        <p style="color:#4b5563; font-size:16px; margin-bottom:20px;">
                            To complete your registration and access your account, please verify your email address by clicking the button
                            below.
                        </p>
                    
                        <!-- Call to action button -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center">
                                    <a href="{{ $url }}" class="btn"
                                        style="display:inline-block; background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#ffffff !important; text-decoration:none; padding:14px 36px; border-radius:50px; font-size:16px; font-weight:600; letter-spacing:0.5px; box-shadow:0 4px 8px rgba(16,185,129,0.2); margin:20px 0 25px;">
                                        Verify Email Address
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <!-- Info box -->
                        <div class="info-box"
                            style="background-color:#ecfdf5; border-left:4px solid #10b981; padding:16px 20px; border-radius:8px; margin:25px 0;">
                            <p style="color:#065f46; font-size:15px; margin-bottom:0;">
                                ⏳ This verification link will expire in <strong>{{ config('auth.verification.expire', 60) }} minutes</strong>.
                                If you didn't create an account, you can safely ignore this email.
                            </p>
                        </div>

                        <p style="color:#4b5563; font-size:16px; margin-bottom:0;">
                            If the button above doesn't work, copy and paste this URL into your browser:
                        </p>
                        <p style="color:#10b981; word-break:break-all; font-size:14px; margin-top:5px;">
                            {{ $url }}
                        </p>
                        </div>

                    <!-- Divider -->
                    <div class="divider" style="height:1px; background-color:#e5e7eb; margin:0;"></div>

                    <!-- Footer -->
                    <div class="footer" style="background-color:#f9fafb; padding:30px; text-align:center; border-top:1px solid #e5e7eb;">
                        <p style="color:#6b7280; font-size:14px; margin-bottom:10px;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                        </p>
                        <p style="color:#6b7280; font-size:14px; margin-bottom:10px;">
                            <a href="{{ config('app.frontend_url') }}" style="color:#10b981; text-decoration:none; font-weight:500;">Visit
                                our website</a>
                            •
                            <a href="{{ config('app.frontend_url') }}/contact"
                                style="color:#10b981; text-decoration:none; font-weight:500;">Contact support</a>
                        </p>
                    </div>
                    </div>
                    <p style="color:#9ca3af; font-size:12px; margin-top:20px; text-align:center;">
                        This email was sent to confirm your new account. If you believe this is an error, please contact support.
                    </p>
                    </td>
                    </tr>
                    </table>
                    </body>
                    
                    </html>
