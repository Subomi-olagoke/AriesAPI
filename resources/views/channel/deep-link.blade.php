<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open in Aries App</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            padding: 20px;
            text-align: center;
            background-color: #f5f8fa;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.5;
        }
        .button {
            display: block;
            background-color: #5965e0;
            color: white;
            font-weight: bold;
            padding: 15px 20px;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 15px;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #4c56c5;
        }
        .button.secondary {
            background-color: #f3f3f3;
            color: #333;
        }
        .button.secondary:hover {
            background-color: #e6e6e6;
        }
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            margin: 0 auto 20px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/app-icon.png" alt="Aries Logo" class="logo">
        <h1>Open this channel in the Aries app</h1>
        <p>Get the best experience by viewing this channel in our mobile app. If you already have the app installed, tap the button below to open it directly.</p>
        
        <a href="aries://channel/{{ $channelId }}" class="button">Open in Aries App</a>
        
        <p>Don't have the app yet?</p>
        
        <a href="https://apps.apple.com/app/ariess/id6474744109" class="button secondary">Download from App Store</a>
        <a href="https://play.google.com/store/apps/details?id=com.Oubomi.Ariess" class="button secondary">Download from Google Play</a>
    </div>

    <script>
        // Automatically try to open the app when the page loads
        window.onload = function() {
            // Wait a moment before attempting to redirect
            setTimeout(function() {
                window.location.href = "aries://channel/{{ $channelId }}";
            }, 100);
        };
    </script>
</body>
</html>