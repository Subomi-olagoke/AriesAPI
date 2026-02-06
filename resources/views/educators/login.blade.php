<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alexandria Educator | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'primary': {
                            50: '#f6f8fe',
                            100: '#edf1fe',
                            200: '#d6e0fc',
                            300: '#b3c6f9',
                            400: '#849ff4',
                            500: '#5a78ee',
                            600: '#3f5ae3',
                            700: '#324ad0',
                            800: '#2b3ca9',
                            900: '#273782',
                        },
                        'neutral': {
                            50: '#f9f9f9',
                            100: '#f2f2f2',
                            200: '#e6e6e6',
                            300: '#d6d6d6',
                            400: '#a3a3a3',
                            500: '#737373',
                            600: '#525252',
                            700: '#404040',
                            800: '#262626',
                            900: '#171717',
                        },
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-down': 'slideDown 0.5s ease-out',
                        'pulse-subtle': 'pulseSubtle 2s infinite',
                        'float': 'float 3s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideDown: {
                            '0%': { transform: 'translateY(-10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        pulseSubtle: {
                            '0%, 100%': { opacity: '1' },
                            '50%': { opacity: '0.85' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .login-btn {
            transition: all 0.3s ease;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        .animate-slide-down {
            animation: slideDown 0.5s ease-out;
        }
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        @keyframes slideDown {
            0% { transform: translateY(-10px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
    </style>
</head>
<body class="bg-neutral-50 font-sans antialiased text-neutral-900 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md animate-fade-in">
        <div class="bg-white rounded-lg border border-neutral-200 shadow-md p-8 animate-slide-down">
            <div class="text-center mb-6">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 bg-primary-600 rounded-full flex items-center justify-center animate-float">
                        <i class="fa-solid fa-chalkboard-teacher text-white text-2xl"></i>
                    </div>
                </div>
                <h1 class="text-2xl font-semibold text-primary-700">Alexandria Educator</h1>
                <p class="mt-2 text-sm text-neutral-500">Sign in to access your educator dashboard</p>
            </div>
            
            @if(session('error'))
            <div class="mb-6 bg-neutral-100 border-l-4 border-neutral-800 text-neutral-800 px-4 py-3 rounded-md animate-slide-down" role="alert">
                <div class="flex items-center">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
            @endif
            
            <form method="POST" action="{{ route('educator.login') }}" class="space-y-6">
                @csrf
                <!-- Hidden input to force redirect to dashboard -->
                <input type="hidden" name="redirect" value="{{ route('educator.dashboard') }}">
                
                <div>
                    <label for="login" class="block text-sm font-medium text-neutral-700">Email or Username</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-user text-neutral-400"></i>
                        </div>
                        <input id="login" name="login" type="text" required 
                            class="appearance-none block w-full pl-10 pr-3 py-3 border border-neutral-300 rounded-md shadow-sm placeholder-neutral-400 focus:outline-none focus:border-primary-800 focus:ring-1 focus:ring-primary-800 transition-all duration-200 sm:text-sm" 
                            placeholder="Enter your email or username">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-neutral-700">Password</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-neutral-400"></i>
                        </div>
                        <input id="password" name="password" type="password" required 
                            class="appearance-none block w-full pl-10 pr-3 py-3 border border-neutral-300 rounded-md shadow-sm placeholder-neutral-400 focus:outline-none focus:border-primary-800 focus:ring-1 focus:ring-primary-800 transition-all duration-200 sm:text-sm" 
                            placeholder="Enter your password">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember" type="checkbox" 
                            class="h-4 w-4 text-primary-800 focus:ring-primary-700 border-neutral-300 rounded transition-colors duration-200">
                        <label for="remember-me" class="ml-2 block text-sm text-neutral-700">
                            Remember me
                        </label>
                    </div>
                    <div class="text-sm">
                        <a href="{{ route('forgot-password') }}" class="font-medium text-primary-600 hover:text-primary-500">
                            Forgot password?
                        </a>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                        class="login-btn w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Sign in to Dashboard
                    </button>
                </div>
            </form>
        </div>
        <div class="text-center mt-6 animate-fade-in">
            <p class="text-sm text-neutral-500">© {{ date('Y') }} Alexandria. All rights reserved.</p>
        </div>
    </div>
</body>
</html>