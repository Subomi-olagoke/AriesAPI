<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Readlist - Alexandria</title>
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
        .readlist-preview {
            background-color: #333333;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
            border-left: 3px solid #555;
        }
        .readlist-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #f1f1f1;
        }
        .readlist-description {
            font-size: 14px;
            color: #aaaaaa;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .readlist-creator {
            font-size: 13px;
            color: #888888;
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .creator-avatar {
            width: 24px;
            height: 24px;
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
        .readlist-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        .readlist-item {
            background-color: #3a3a3a;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
            color: #d5d5d5;
            border-left: 2px solid #555;
        }
        .readlist-item-title {
            font-weight: 500;
            margin-bottom: 3px;
        }
        .readlist-item-type {
            font-size: 12px;
            color: #888888;
        }
        .readlist-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #888888;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #444;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/Pompeyy.jpeg" alt="Alexandria Logo" class="logo">
        <h1>View Readlist in Alexandria</h1>
        
        <div class="readlist-preview">
            <div class="readlist-title">{{ $readlist->title ?? 'Readlist' }}</div>
            
            <div class="readlist-creator">
                <div class="creator-avatar">
                    {{ isset($readlist->user->name) ? substr($readlist->user->name, 0, 1) : 'U' }}
                </div>
                <span>Created by {{ $readlist->user->name ?? 'Unknown User' }}</span>
            </div>
            
            <div class="readlist-description">
                {{ $readlist->description ?? 'No description available.' }}
            </div>
            
            @if(isset($readlist->items) && count($readlist->items) > 0)
                <div class="readlist-items">
                    <div style="font-size: 15px; font-weight: 500; margin-bottom: 10px; color: #d5d5d5;">
                        Items ({{ count($readlist->items) }})
                    </div>
                    
                    @foreach($readlist->items->take(3) as $item)
                        <div class="readlist-item">
                            <div class="readlist-item-title">{{ $item->title ?? 'Item Title' }}</div>
                            <div class="readlist-item-type">
                                {{ $item->type ?? 'Content' }}
                            </div>
                        </div>
                    @endforeach
                    
                    @if(count($readlist->items) > 3)
                        <div style="text-align: center; color: #888888; font-size: 13px; margin-top: 5px;">
                            +{{ count($readlist->items) - 3 }} more items
                        </div>
                    @endif
                </div>
            @else
                <p>This readlist has no items.</p>
            @endif
            
            <div class="readlist-meta">
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    {{ $readlist->created_at ?? 'Recently' }}
                </span>
                
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    {{ $readlist->views_count ?? '0' }} views
                </span>
            </div>
        </div>
        
        <a href="aries://readlist/{{ $readlistId }}" class="button">Open in Alexandria App</a>
        
        <a href="https://apps.apple.com/app/ariess/id6474744109" class="button">Download Alexandria App</a>
    </div>

    <script>
        // Automatically try to open the app when the page loads
        window.onload = function() {
            // Wait a moment before attempting to redirect
            setTimeout(function() {
                window.location.href = "aries://readlist/{{ $readlistId }}";
            }, 300);
        };
    </script>
</body>
</html>