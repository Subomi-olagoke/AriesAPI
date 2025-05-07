<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Failed | Aries Premium</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset & Base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        :root {
            /* Monochrome Color Palette */
            --color-black: #000000;
            --color-dark-gray: #222222;
            --color-medium-gray: #666666;
            --color-light-gray: #999999;
            --color-extra-light-gray: #f5f5f5;
            --color-white: #ffffff;
            
            /* Font sizes */
            --font-xs: 0.75rem;
            --font-sm: 0.875rem;
            --font-md: 1rem;
            --font-lg: 1.25rem;
            --font-xl: 1.5rem;
            --font-2xl: 2rem;
            --font-3xl: 3rem;
            
            /* Spacing */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-12: 3rem;
            --space-16: 4rem;
            
            /* Border radius */
            --radius-sm: 0.125rem;
            --radius-md: 0.25rem;
            --radius-lg: 0.5rem;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-white);
            color: var(--color-dark-gray);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Typography */
        h1 {
            font-size: var(--font-2xl);
            font-weight: 700;
            margin-bottom: var(--space-6);
            color: var(--color-black);
        }
        
        p {
            margin-bottom: var(--space-4);
            font-size: var(--font-md);
        }
        
        /* Layout */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 var(--space-4);
            text-align: center;
        }
        
        .header {
            background-color: var(--color-black);
            color: var(--color-white);
            padding: var(--space-6) 0;
            text-align: center;
        }
        
        .content {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-12) 0;
        }
        
        .failed-card {
            background-color: var(--color-white);
            border: 1px solid var(--color-light-gray);
            border-radius: var(--radius-md);
            padding: var(--space-8);
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .failed-icon {
            font-size: 5rem;
            margin-bottom: var(--space-6);
            color: #000;
        }
        
        .btn {
            display: inline-block;
            padding: var(--space-3) var(--space-6);
            background-color: var(--color-black);
            color: var(--color-white);
            border: none;
            border-radius: var(--radius-md);
            font-size: var(--font-md);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
            margin-top: var(--space-4);
        }
        
        .btn:hover {
            background-color: var(--color-dark-gray);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--color-black);
            border: 1px solid var(--color-black);
            margin-top: var(--space-2);
            margin-bottom: var(--space-4);
        }
        
        .btn-outline:hover {
            background-color: var(--color-black);
            color: var(--color-white);
        }
        
        .footer {
            background-color: var(--color-black);
            color: var(--color-white);
            padding: var(--space-6) 0;
            text-align: center;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>ARIES</h1>
        </div>
    </header>

    <!-- Content -->
    <section class="content">
        <div class="container">
            <div class="failed-card">
                <div class="failed-icon">✕</div>
                <h1>Subscription Failed</h1>
                <p>We're sorry, but your subscription payment could not be processed at this time.</p>
                <p>This could be due to insufficient funds, an expired card, or other payment issues.</p>
                <a href="/premium" class="btn">Try Again</a>
                <a href="/" class="btn btn-outline">Return to Home</a>
                <p>If you continue to experience issues, please contact our support team for assistance.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>© 2025 Aries. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>