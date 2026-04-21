{{-- resources/views/emails/report_notification.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject }}</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 24px; color: #1f2937; }
  .card { background: #fff; border-radius: 16px; max-width: 540px; margin: 0 auto; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
  .header { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); padding: 28px 32px; }
  .header h1 { margin: 0; font-size: 20px; font-weight: 700; color: #fff; }
  .header p  { margin: 4px 0 0; font-size: 13px; color: #bbf7d0; }
  .body  { padding: 28px 32px; }
  .ticket-id { display: inline-block; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 8px; padding: 10px 20px; font-family: monospace; font-size: 20px; font-weight: 800; color: #16a34a; letter-spacing: 2px; margin-bottom: 20px; }
  .meta  { background: #f9fafb; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; font-size: 13px; }
  .meta strong { color: #374151; }
  .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
    background:
      @switch($status)
        @case('resolved') #dcfce7; color: #166534; @break
        @case('closed')   #f3f4f6; color: #6b7280; @break
        @case('rejected') #fee2e2; color: #991b1b; @break
        @case('in_review')#f3e8ff; color: #7e22ce; @break
        @case('waiting')  #fef9c3; color: #854d0e; @break
        @default          #dbeafe; color: #1e40af;
      @endswitch
  }
  .message { font-size: 14px; line-height: 1.7; color: #4b5563; margin-bottom: 20px; }
  .cta  { text-align: center; margin: 24px 0; }
  .btn  { display: inline-block; background: #16a34a; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 10px; font-size: 15px; font-weight: 700; }
  .footer { border-top: 1px solid #f3f4f6; padding: 16px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>Pyonea Support</h1>
    <p>Support Ticket Update</p>
  </div>
  <div class="body">
    <p style="font-size:15px; margin-bottom:16px;">Hi <strong>{{ $name }}</strong>,</p>

    <div class="ticket-id">{{ $ticket_id }}</div>

    <div class="meta">
      <div><strong>Subject:</strong> {{ $subject }}</div>
      <div style="margin-top:6px;">
        <strong>Status:</strong>
        <span class="status-badge">{{ ucwords(str_replace('_',' ', $status)) }}</span>
      </div>
    </div>

    @if($event === 'created')
      <p class="message">
        Your report has been received and is now in our queue. Our support team will review it
        and get back to you based on priority level. You can track progress using the ticket ID above.
      </p>

    @elseif($event === 'status_changed')
      <p class="message">
        The status of your ticket has been updated.
        @if($status === 'resolved')
          <strong>Your issue has been resolved.</strong>
          @if($extra)
            Here is a summary: <em>{{ $extra }}</em>
          @endif
        @elseif($status === 'in_review')
          Our support team has started reviewing your report.
        @elseif($status === 'waiting')
          We need additional information from you. Please reply to this email or log in to add a comment.
        @elseif($status === 'rejected')
          After review, we were unable to process this report. This may be because it is a duplicate
          or falls outside our support scope.
        @else
          Please log in to view the latest update.
        @endif
      </p>

    @elseif($event === 'admin_replied')
      <p class="message">
        Our support team has replied to your ticket. Please log in to read the full reply and respond if needed.
      </p>
    @endif

    <div class="cta">
      <a href="{{ $url }}" class="btn">View Ticket</a>
    </div>
  </div>
  <div class="footer">
    Pyonea Marketplace · Myanmar B2B Platform<br>
    This is an automated message. Do not reply to this email — use the portal instead.
  </div>
</div>
</body>
</html>