<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Alexandria</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f7;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .email-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .logo {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
        }
        .tagline {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }
        .content {
            padding: 40px 30px;
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
        }
        p {
            margin-bottom: 16px;
            color: #4a4a4a;
            font-size: 15px;
        }
        .highlight {
            color: #6366f1;
            font-weight: 600;
        }
        .features {
            margin: 30px 0;
            padding: 0;
            list-style: none;
        }
        .features li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 16px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .feature-icon {
            width: 24px;
            height: 24px;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .feature-text {
            font-size: 14px;
            color: #333;
        }
        .feature-text strong {
            display: block;
            margin-bottom: 2px;
            color: #1a1a1a;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            transition: transform 0.2s;
        }
        .cta-button:hover {
            transform: scale(1.02);
        }
        .footer {
            background: #f8f9fa;
            padding: 24px 30px;
            text-align: center;
            border-top: 1px solid #eee;
        }
        .footer p {
            font-size: 13px;
            color: #888;
            margin-bottom: 8px;
        }
        .social-links {
            margin-top: 16px;
        }
        .social-links a {
            color: #6366f1;
            text-decoration: none;
            margin: 0 8px;
            font-size: 13px;
        }
        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }
            .header {
                padding: 30px 20px;
            }
            .content {
                padding: 30px 20px;
            }
            .footer {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="email-card">
            <div class="header">
                <div class="logo">Alexandria</div>
                <div class="tagline">Discover. Learn. Grow.</div>
            </div>

            <div class="content">
                <h1>Welcome aboard, {{ $username }}!</h1>

                <p>
                    We're thrilled to have you join the Alexandria community. You've just taken the first step
                    towards a smarter way of learning and discovering knowledge.
                </p>

                <p>
                    Alexandria is your personal learning companion, designed to help you:
                </p>

                <ul class="features">
                    <li>
                        <svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <div class="feature-text">
                            <strong>Curated Libraries</strong>
                            Browse collections of educational content curated by learners like you
                        </div>
                    </li>
                    <li>
                        <svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                            <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                        </svg>
                        <div class="feature-text">
                            <strong>Personal Readlists</strong>
                            Save and organize content for later with your own reading lists
                        </div>
                    </li>
                    <li>
                        <svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                            <path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        <div class="feature-text">
                            <strong>AI-Powered Discovery</strong>
                            Let our AI help you find the perfect resources for your learning journey
                        </div>
                    </li>
                    <li>
                        <svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <div class="feature-text">
                            <strong>Learning Community</strong>
                            Connect with fellow learners and share your discoveries
                        </div>
                    </li>
                </ul>

                <p style="text-align: center;">
                    <a href="https://alexandria.app" class="cta-button">Start Exploring</a>
                </p>

                <p>
                    If you have any questions or need help getting started, feel free to reach out to our support team.
                    We're here to help you succeed!
                </p>

                <p>
                    Happy learning!<br>
                    <span class="highlight">The Alexandria Team</span>
                </p>
            </div>

            <div class="footer">
                <p>You're receiving this email because you signed up for Alexandria.</p>
                <p>&copy; {{ date('Y') }} Alexandria. All rights reserved.</p>
                <div class="social-links">
                    <a href="#">Twitter</a>
                    <a href="#">Instagram</a>
                    <a href="#">Support</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
