<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email - TO YOU</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4361EE; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { background: #eee; padding: 10px; text-align: center; font-size: 12px; }
        .button { background: #4361EE; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verify Your Email Address</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $user->name }}</strong>,</p>
            <p>Thank you for registering with TO YOU! Please verify your email address to complete your registration.</p>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="{{ $verificationUrl }}" class="button">
                    Verify Email Address
                </a>
            </div>
            
            <p>If the button doesn't work, copy and paste this link in your browser:</p>
            <p><code style="background: #f5f5f5; padding: 10px; display: block; word-break: break-all;">{{ $verificationUrl }}</code></p>
            
            <p>If you did not create an account, no further action is required.</p>
        </div>
        <div class="footer">
            <p>TO YOU Delivery Service</p>
            <p>This verification link will expire in 24 hours.</p>
        </div>
    </div>
</body>
</html>