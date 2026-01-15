<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .content {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Aries API</h1>
        </div>
        <div class="content">
            <p>Dear {{ $recipientName }},</p>
            
            {!! $content !!}
            
            <p>Thank you,<br>The Aries Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Aries API. All rights reserved.</p>
        </div>
    </div>
</body>
</html>