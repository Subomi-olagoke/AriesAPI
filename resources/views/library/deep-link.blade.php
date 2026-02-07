<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $library->name ?? 'Library' }} - Alexandria</title>
    <meta property="og:title" content="{{ $library->name ?? 'Library' }} - Alexandria">
    <meta property="og:description" content="{{ $library->description ?? 'Explore this library on Alexandria' }}">
    <meta property="og:image" content="{{ $library->cover_image_url ?? $library->thumbnail_url ?? '/img/Pompeyy.jpeg' }}">
    <meta property="og:type" content="website">
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
        .button.primary {
            background-color: #4A90D9;
            border-color: #4A90D9;
        }
        .button.primary:hover {
            background-color: #3A80C9;
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
        .library-preview {
            background-color: #333333;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
            border-left: 3px solid #4A90D9;
        }
        .library-cover {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .library-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #f1f1f1;
        }
        .library-description {
            font-size: 14px;
            color: #aaaaaa;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .library-creator {
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
            background-color: #4A90D9;
            margin-right: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 12px;
        }
        .library-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #888888;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #444;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .library-type {
            display: inline-block;
            background-color: #4A90D9;
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/Pompeyy.jpeg" alt="Alexandria Logo" class="logo">
        <h1>View Library in Alexandria</h1>

        <div class="library-preview">
            @if($library->cover_image_url || $library->thumbnail_url)
                <img src="{{ $library->cover_image_url ?? $library->thumbnail_url }}" alt="{{ $library->name }}" class="library-cover">
            @endif

            @if($library->type)
                <span class="library-type">{{ $library->type }}</span>
            @endif

            <div class="library-title">{{ $library->name ?? 'Library' }}</div>

            @if($library->creator)
                <div class="library-creator">
                    <div class="creator-avatar">
                        {{ substr($library->creator->username ?? $library->creator->name ?? 'U', 0, 1) }}
                    </div>
                    <span>Created by {{ $library->creator->username ?? $library->creator->name ?? 'Unknown User' }}</span>
                </div>
            @endif

            <div class="library-description">
                {{ $library->description ?? 'Explore this curated library of resources on Alexandria.' }}
            </div>

            <div class="library-meta">
                <span class="meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    {{ $library->followers_count ?? 0 }} followers
                </span>

                <span class="meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                    {{ $library->contents_count ?? 0 }} items
                </span>

                <span class="meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    {{ $library->views_count ?? 0 }} views
                </span>
            </div>
        </div>

        <a href="ariesapp://library/shared/{{ $shareKey }}" class="button primary">Open in Alexandria App</a>

        <a href="https://apps.apple.com/app/ariess/id6474744109" class="button">Download Alexandria App</a>
    </div>

    <script>
        // Automatically try to open the app when the page loads
        window.onload = function() {
            // Wait a moment before attempting to redirect
            setTimeout(function() {
                window.location.href = "ariesapp://library/shared/{{ $shareKey }}";
            }, 500);
        };
    </script>
</body>
</html>
