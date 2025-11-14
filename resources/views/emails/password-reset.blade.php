<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - TO YOU App</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            background: linear-gradient(135deg, #4361EE 0%, #3A0CA3 100%);
            border-radius: 10px 10px 0 0;
            margin: -20px -20px 20px -20px;
        }
        .logo {
            color: white;
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        .content {
            padding: 20px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
        }
        .reset-button {
            display: block;
            width: 200px;
            margin: 30px auto;
            padding: 12px 24px;
            background: linear-gradient(135deg, #4361EE 0%, #3A0CA3 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
        }
        .reset-button:hover {
            background: linear-gradient(135deg, #3A0CA3 0%, #4361EE 100%);
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #4361EE;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 14px;
        }
        .warning {
            color: #dc3545;
            font-weight: bold;
        }
        .steps {
            margin: 20px 0;
            padding-left: 20px;
        }
        .steps li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="logo">TO YOU</h1>
        </div>
        
        <div class="content">
            @if($userName)
                <p class="greeting">Hello <strong>{{ $userName }}</strong>,</p>
            @else
                <p class="greeting">Hello,</p>
            @endif
            
            <p>You are receiving this email because we received a password reset request for your account.</p>
            
            <div class="info-box">
                <p><strong>Click the button below to reset your password:</strong></p>
            </div>
            
            <a href="{{ $resetLink }}" class="reset-button">Reset Password</a>
            
            <p>If you're having trouble clicking the button, copy and paste the URL below into your web browser:</p>
            <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px;">
                <a href="{{ $resetLink }}" style="color: #4361EE;">{{ $resetLink }}</a>
            </p>
            
            <div class="info-box">
                <p class="warning">⚠️ Important Security Information:</p>
                <ul class="steps">
                    <li>This password reset link will expire in <strong>1 hour</strong></li>
                    <li>If you didn't request this reset, please ignore this email</li>
                    <li>Your password will not change until you create a new one</li>
                </ul>
            </div>
            
            <p>If you did not request a password reset, no further action is required.</p>
            
            <p>Thank you for using TO YOU!</p>
            <p><strong>The TO YOU Team</strong></p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} TO YOU App. All rights reserved.</p>
            <p>This is an automated message, please do not reply to this email.</p>
            <p>If you need assistance, please contact our support team.</p>
        </div>
    </div>
</body>
</html>