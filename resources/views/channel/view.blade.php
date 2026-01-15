<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Channel - Alexandria</title>
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
            max-width: 800px;
            margin: 0 auto;
            background-color: #2a2a2a;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        h1 {
            color: #f1f1f1;
            margin-bottom: 20px;
            font-weight: 600;
        }
        h2 {
            color: #f1f1f1;
            font-size: 20px;
            margin-top: 30px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        p {
            color: #aaaaaa;
            margin-bottom: 20px;
            font-size: 16px;
            line-height: 1.5;
        }
        .button {
            display: inline-block;
            background-color: #000000;
            color: white;
            font-weight: bold;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            margin: 20px 0;
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
        .channel-info {
            margin-top: 30px;
            padding: 20px;
            background-color: #333333;
            border-radius: 8px;
            text-align: left;
            border-left: 3px solid #555;
        }
        .member-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-top: 15px;
        }
        .member {
            display: inline-flex;
            align-items: center;
            background-color: #3a3a3a;
            padding: 8px 12px;
            border-radius: 20px;
            margin: 5px;
        }
        .member-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #555;
            margin-right: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 12px;
        }
        .banner {
            background-color: #333;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/Pompeyy.jpeg" alt="Alexandria Logo" class="logo">
        <h1>{{ $channel->title ?? 'Channel' }}</h1>
        
        <div class="banner">
            <p>This channel is available exclusively in the Alexandria app</p>
        </div>
        
        <div class="channel-info">
            <h2>About this channel</h2>
            <p>{{ $channel->description ?? 'No description available.' }}</p>
            
            @if(isset($channel->members) && count($channel->members) > 0)
                <h2>Members ({{ count($channel->members) }})</h2>
                <div class="member-list">
                    @foreach($channel->members->take(10) as $member)
                        <div class="member">
                            <div class="member-avatar">
                                {{ substr($member->user->name ?? 'U', 0, 1) }}
                            </div>
                            <span>{{ $member->user->name ?? 'Unknown User' }}</span>
                        </div>
                    @endforeach
                    
                    @if(count($channel->members) > 10)
                        <div class="member">
                            <span>+{{ count($channel->members) - 10 }} more</span>
                        </div>
                    @endif
                </div>
            @else
                <p>No members information available.</p>
            @endif
        </div>
        
        <a href="https://apps.apple.com/app/ariess/id6474744109" class="button">Download Alexandria App</a>
    </div>
</body>
</html>