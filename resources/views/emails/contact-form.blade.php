<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Contact Form Submission</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">New Contact Message</h2>

        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 10px 0; font-weight: bold; width: 100px;">Name:</td>
                <td style="padding: 10px 0;">{{ $data['name'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; font-weight: bold;">Email:</td>
                <td style="padding: 10px 0;">{{ $data['email'] }}</td>
            </tr>
            @if(!empty($data['phone']))
                <tr>
                    <td style="padding: 10px 0; font-weight: bold;">Phone:</td>
                    <td style="padding: 10px 0;">{{ $data['phone'] }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding: 10px 0; font-weight: bold;">Subject:</td>
                <td style="padding: 10px 0;">{{ $data['subject'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; font-weight: bold; vertical-align: top;">Message:</td>
                <td style="padding: 10px 0;">{{ nl2br(e($data['message'])) }}</td>
            </tr>
        </table>

        <p style="margin-top: 30px; font-size: 12px; color: #777; text-align: center;">
            This email was sent from the contact form on {{ config('app.name') }}.
        </p>
    </div>
</body>

</html>
