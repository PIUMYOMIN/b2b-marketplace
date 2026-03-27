{{-- resources/views/emails/verify-email.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Verify Your Email — Pyonea</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background-color: #f4f7fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #374151; }
    .wrapper  { width: 100%; background-color: #f4f7fa; padding: 32px 16px; }
    .card     { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
    .header   { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 28px 40px; text-align: center; }
    .logo     { font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
    .logo span{ color: rgba(255,255,255,.7); }
    .body     { padding: 36px 40px; }
    h2        { font-size: 22px; font-weight: 700; color: #111827; margin-bottom: 10px; }
    p         { font-size: 15px; color: #4b5563; line-height: 1.7; margin-bottom: 14px; }

    /* ── Code block ── */
    .code-box { background: #f0fdf4; border: 2px dashed #10b981; border-radius: 14px; padding: 24px; text-align: center; margin: 24px 0; }
    .code-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
    .code     { font-size: 42px; font-weight: 800; letter-spacing: 10px; color: #059669; font-family: 'Courier New', Courier, monospace; }
    .code-expires { font-size: 12px; color: #9ca3af; margin-top: 8px; }

    .divider  { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }

    /* ── Button ── */
    .btn-wrap { text-align: center; margin: 20px 0; }
    .btn      { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff !important; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 40px; border-radius: 10px; }

    .info-box { background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 14px 18px; margin: 20px 0; }
    .info-box p { font-size: 13px; color: #92400e; margin: 0; }
    .small    { font-size: 13px; color: #9ca3af; }
    .footer   { background: #f9fafb; padding: 20px 40px; text-align: center; border-top: 1px solid #e5e7eb; }
    .footer p { font-size: 12px; color: #9ca3af; margin: 3px 0; }
    .footer a { color: #059669; text-decoration: none; }
    @media (max-width: 600px) {
      .body, .footer { padding: 24px 20px; }
      .header { padding: 20px 24px; }
      .code { font-size: 32px; letter-spacing: 6px; }
    }
</style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="logo">Pyonea<span>.com</span></div>
            </div>
            <div class="body">
                <h2>Verify your email address ✉️</h2>
                <p>Hi {{ $user->name }}, thanks for signing up! Use the code below to verify your account, or click the
                    button to verify automatically.</p>

    {{-- ── 6-Digit Code ── --}}
    <div class="code-box">
        <div class="code-label">Your verification code</div>
        <div class="code">{{ $code }}</div>
        <div class="code-expires">Expires in 15 minutes</div>
    </div>

    <p style="text-align:center; font-size:13px; color:#6b7280; margin-bottom:4px;">— or —</p>

    {{-- ── Link Button ── --}}
    <div class="btn-wrap">
        <a href="{{ $url }}" class="btn">Verify My Email</a>
    </div>

    <hr class="divider">
    
    <div class="info-box">
        <p>⏱ The code expires in <strong>15 minutes</strong>. If you need a new one, use the "Resend code" option on the
            verification page.</p>
    </div>

    <p class="small">If you didn't create a Pyonea account, you can safely ignore this email.</p>
    </div>
    <div class="footer">
        <p><strong>Pyonea Marketplace</strong> · Yangon, Myanmar</p>
        <p><a href="https://pyonea.com">pyonea.com</a> · <a href="mailto:support@pyonea.com">support@pyonea.com</a></p>
    </div>
</div>
</div>
</body>
</html>
