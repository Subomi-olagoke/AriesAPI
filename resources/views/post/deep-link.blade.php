<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Post - Alexandria</title>
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
        .post-preview {
            background-color: #333333;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
            border-left: 3px solid #555;
        }
        .post-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #f1f1f1;
        }
        .post-content {
            font-size: 14px;
            color: #aaaaaa;
            margin-bottom: 10px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 5;
        }
        .post-author {
            font-size: 13px;
            color: #888888;
            display: flex;
            align-items: center;
        }
        .post-author-avatar {
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
        .post-image {
            width: 100%;
            max-height: 250px;
            border-radius: 8px;
            object-fit: cover;
            margin-bottom: 15px;
        }
        .post-stats {
            display: flex;
            gap: 15px;
            color: #888888;
            font-size: 13px;
            margin-top: 15px;
        }
        .post-stats span {
            display: flex;
            align-items: center;
        }
        .post-stats svg {
            margin-right: 5px;
            width: 16px;
            height: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/Pompeyy.jpeg" alt="Alexandria Logo" class="logo">
        <h1>View Post in Alexandria</h1>
        
        <div class="post-preview">
            @if(isset($post->media_link) && !empty($post->media_link))
                <img src="{{ $post->media_link }}" alt="Post image" class="post-image">
            @endif
            
            <div class="post-author">
                <div class="post-author-avatar">
                    {{ isset($post->user->name) ? substr($post->user->name, 0, 1) : 'U' }}
                </div>
                <span>{{ $post->user->name ?? 'Unknown User' }}</span>
            </div>
            
            <div class="post-content">
                {{ $post->content ?? 'No content available.' }}
            </div>
            
            <div class="post-stats">
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    {{ $post->likes_count ?? '0' }} likes
                </span>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    {{ $post->comments_count ?? '0' }} comments
                </span>
            </div>
        </div>
        
        <a href="aries://post/{{ $postId }}" class="button">Open in Alexandria App</a>
        
        <a href="https://apps.apple.com/app/ariess/id6474744109" class="button">Download Alexandria App</a>
    </div>

    <script>
        // Automatically try to open the app when the page loads
        window.onload = function() {
            // Wait a moment before attempting to redirect
            setTimeout(function() {
                window.location.href = "aries://post/{{ $postId }}";
            }, 300);
        };
    </script>
</body>
</html>