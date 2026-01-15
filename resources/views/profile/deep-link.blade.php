<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile - Alexandria</title>
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
        .profile-preview {
            background-color: #333333;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
            border-left: 3px solid #555;
        }
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #444;
            overflow: hidden;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            font-weight: bold;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #f1f1f1;
        }
        .profile-username {
            font-size: 14px;
            color: #888888;
        }
        .profile-bio {
            font-size: 14px;
            color: #aaaaaa;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .profile-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #aaaaaa;
            margin-bottom: 20px;
        }
        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .stat-value {
            font-weight: bold;
            font-size: 16px;
            color: #f1f1f1;
            margin-bottom: 5px;
        }
        .profile-meta {
            font-size: 13px;
            color: #888888;
            display: flex;
            gap: 15px;
        }
        .profile-meta span {
            display: flex;
            align-items: center;
        }
        .profile-meta svg {
            width: 16px;
            height: 16px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/img/Pompeyy.jpeg" alt="Alexandria Logo" class="logo">
        <h1>View Profile in Alexandria</h1>
        
        <div class="profile-preview">
            <div class="profile-header">
                <div class="profile-avatar">
                    @if(isset($profile->avatar) && !empty($profile->avatar))
                        <img src="{{ $profile->avatar }}" alt="Profile image">
                    @else
                        {{ isset($profile->name) ? substr($profile->name, 0, 1) : 'U' }}
                    @endif
                </div>
                <div>
                    <div class="profile-name">{{ $profile->name ?? 'User' }}</div>
                    <div class="profile-username">@{{ $profile->username ?? 'username' }}</div>
                </div>
            </div>
            
            <div class="profile-bio">
                {{ $profile->bio ?? 'No bio available.' }}
            </div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value">{{ $profile->posts_count ?? '0' }}</div>
                    <div>Posts</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{ $profile->followers_count ?? '0' }}</div>
                    <div>Followers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">{{ $profile->following_count ?? '0' }}</div>
                    <div>Following</div>
                </div>
            </div>
            
            <div class="profile-meta">
                @if(isset($profile->educator) && $profile->educator)
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                    Educator
                </span>
                @endif
                
                @if(isset($profile->verified) && $profile->verified)
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Verified
                </span>
                @endif
                
                @if(isset($profile->joined_date) && !empty($profile->joined_date))
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    Joined {{ $profile->joined_date }}
                </span>
                @endif
            </div>
        </div>
        
        <a href="aries://profile/{{ isset($profile->username) ? $profile->username : $profileId }}" class="button">Open in Alexandria App</a>
        
        <a href="https://apps.apple.com/app/ariess/id6474744109" class="button">Download Alexandria App</a>
    </div>

    <script>
        // Automatically try to open the app when the page loads
        window.onload = function() {
            // Wait a moment before attempting to redirect
            setTimeout(function() {
                window.location.href = "aries://profile/{{ isset($profile->username) ? $profile->username : $profileId }}";
            }, 300);
        };
    </script>
</body>
</html>