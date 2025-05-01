<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open in Alexandria App</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            padding: 20px;
            text-align: center;
            background-color: #1a1a1a;
            color: #f1f1f1;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: #2a2a2a;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        h1 {
            color: #f1f1f1;
            margin-bottom: 20px;
            font-weight: 600;
        }
        p {
            color: #aaaaaa;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.5;
        }
        .button {
            display: block;
            background-color: #000000;
            color: white;
            font-weight: bold;
            padding: 15px 20px;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 15px;
            transition: all 0.2s;
            border: 1px solid #333;
        }
        .button:hover {
            background-color: #1a1a1a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            margin: 0 auto 30px;
            display: block;
            background-color: #333;
            padding: 10px;
        }
        .channel-preview {
            background-color: #333333;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
            border-left: 3px solid #555;
        }
        .channel-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #f1f1f1;
        }
        .channel-description {
            font-size: 14px;
            color: #aaaaaa;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/Pompeyy.jpeg" alt="Alexandria Logo" class="logo">
        <h1>Join the conversation in Alexandria</h1>
        
        <div class="channel-preview">
            <div class="channel-title">{{ $channel->title ?? 'Channel' }}</div>
            <p class="channel-description">{{ $channel->description ?? 'No description available.' }}</p>
        </div>
        
        <a href="aries://channel/{{ $channelId }}" class="button">Open in Alexandria App</a>
        
        <a href="https://apps.apple.com/app/ariess/id6474744109" class="button">Download Alexandria App</a>
    </div>

    <script>
        // Automatically try to open the app when the page loads
        window.onload = function() {
            // Wait a moment before attempting to redirect
            setTimeout(function() {
                window.location.href = "aries://channel/{{ $channelId }}";
            }, 300);
        };
    </script>
</body>
</html>