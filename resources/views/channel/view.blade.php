<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Channel - Aries</title>
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
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            padding: 40px;
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
            display: inline-block;
            background-color: #5965e0;
            color: white;
            font-weight: bold;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            margin: 10px;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #4c56c5;
        }
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            margin: 0 auto 20px;
            display: block;
        }
        .channel-info {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/app-icon.png" alt="Aries Logo" class="logo">
        <h1>Channel Details</h1>
        
        <div class="channel-info">
            <p><strong>Channel ID:</strong> {{ $channelId }}</p>
            <p><strong>Status:</strong> To view this channel, please log in or use our mobile app.</p>
        </div>
        
        <p>For the best experience, download our app or log in to the web version.</p>
        
        <a href="/" class="button">Log In</a>
        <a href="https://apps.apple.com/app/ariess/id6474744109" class="button">Download App</a>
    </div>
</body>
</html>