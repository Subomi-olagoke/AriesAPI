<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aries Admin Login</title>
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
                            50: '#f9fafb',
                            100: '#f4f5f7',
                            200: '#e5e7eb',
                            300: '#d2d6dc',
                            400: '#9fa6b2',
                            500: '#6b7280',
                            600: '#4b5563',
                            700: '#374151',
                            800: '#252f3f',
                            900: '#161e2e',
                        },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-neutral-50 font-sans antialiased text-neutral-900 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg border border-neutral-200 shadow-sm p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-semibold text-neutral-900">Aries Admin</h1>
                <p class="mt-2 text-sm text-neutral-500">Sign in to your admin account</p>
            </div>
            
            @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md" role="alert">
                <div class="flex items-center">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
            @endif
            
            <form method="POST" action="{{ route('admin.login') }}" class="space-y-6">
                @csrf
                <div>
                    <label for="login" class="block text-sm font-medium text-neutral-700">Email or Username</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-user text-neutral-400"></i>
                        </div>
                        <input id="login" name="login" type="text" required class="appearance-none block w-full pl-10 pr-3 py-2 border border-neutral-300 rounded-md shadow-sm placeholder-neutral-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm" placeholder="Enter your email or username">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-neutral-700">Password</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-neutral-400"></i>
                        </div>
                        <input id="password" name="password" type="password" required class="appearance-none block w-full pl-10 pr-3 py-2 border border-neutral-300 rounded-md shadow-sm placeholder-neutral-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm" placeholder="Enter your password">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember" type="checkbox" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-neutral-300 rounded">
                        <label for="remember-me" class="ml-2 block text-sm text-neutral-700">
                            Remember me
                        </label>
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-150 ease-in-out">
                        Sign in
                    </button>
                </div>
            </form>
        </div>
        <div class="text-center mt-4">
            <p class="text-xs text-neutral-500">© {{ date('Y') }} Aries. All rights reserved.</p>
        </div>
    </div>
</body>
</html>