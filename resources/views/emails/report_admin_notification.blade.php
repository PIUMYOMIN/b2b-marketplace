{{-- resources/views/emails/report_admin_notification.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $email_subject }}</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 24px; color: #1f2937; }
  .card { background: #fff; border-radius: 16px; max-width: 560px; margin: 0 auto; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
  .header { background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); padding: 28px 32px; }
  .header h1 { margin: 0; font-size: 20px; font-weight: 700; color: #fff; }
  .header p  { margin: 4px 0 0; font-size: 13px; color: #bfdbfe; }
  .body  { padding: 28px 32px; }
  .ticket-id { display: inline-block; background: #eff6ff; border: 1.5px solid #93c5fd; border-radius: 8px; padding: 10px 20px; font-family: monospace; font-size: 20px; font-weight: 800; color: #1d4ed8; letter-spacing: 2px; margin-bottom: 20px; }
  .meta  { background: #f9fafb; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; font-size: 13px; line-height: 1.8; }
  .meta strong { color: #374151; }
  .priority-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
  .priority-critical { background: #fee2e2; color: #991b1b; }
  .priority-high     { background: #ffedd5; color: #9a3412; }
  .priority-medium   { background: #fef9c3; color: #854d0e; }
  .priority-low      { background: #f0fdf4; color: #166534; }
  .description { background: #f8fafc; border-left: 3px solid #93c5fd; border-radius: 0 8px 8px 0; padding: 12px 16px; font-size: 13px; color: #4b5563; line-height: 1.7; margin-bottom: 20px; white-space: pre-wrap; word-break: break-word; }
  .cta  { text-align: center; margin: 24px 0; }
  .btn  { display: inline-block; background: #1d4ed8; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 10px; font-size: 15px; font-weight: 700; }
  .footer { border-top: 1px solid #f3f4f6; padding: 16px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>Pyonea Admin — Support Ticket</h1>
    <p>
      @if($event === 'new_report')
        New report submitted
      @elseif($event === 'reporter_replied')
        Reporter replied to a ticket
      @endif
    </p>
  </div>
  <div class="body">
    <div class="ticket-id">{{ $ticket_id }}</div>

    <div class="meta">
      <div><strong>Subject:</strong> {{ $subject }}</div>
      <div><strong>Category:</strong> {{ ucfirst($category) }}</div>
      <div>
        <strong>Priority:</strong>
        <span class="priority-badge priority-{{ $priority }}">{{ $priority }}</span>
      </div>
      <div><strong>Reporter:</strong> {{ $reporter_name }} ({{ $reporter_email ?? 'guest' }})</div>
      @if($event === 'reporter_replied')
      <div><strong>Event:</strong> Reporter added a new comment</div>
      @endif
    </div>

    @if($event === 'new_report')
      <p style="font-size:14px; color:#4b5563; margin-bottom:10px;"><strong>Description:</strong></p>
      <div class="description">{{ $description }}</div>
    @elseif($event === 'reporter_replied')
      <p style="font-size:14px; color:#4b5563; margin-bottom:10px;"><strong>New comment from reporter:</strong></p>
      <div class="description">{{ $extra }}</div>
    @endif

    <div class="cta">
      <a href="{{ $url }}" class="btn">Open in Admin Panel</a>
    </div>
  </div>
  <div class="footer">
    Pyonea Marketplace · Internal Admin Notification<br>
    This message was sent automatically. Log in to the admin panel to respond.
  </div>
</div>
</body>
</html>
