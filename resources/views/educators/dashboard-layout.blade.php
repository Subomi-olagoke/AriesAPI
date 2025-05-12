<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alexandria Educator @yield('title', 'Dashboard')</title>
    <!-- Force reload the app CSS and JS -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
                        'secondary': {
                            50: '#f8f9fa',
                            100: '#eaedf0',
                            200: '#d1d6db',
                            300: '#acb5bf',
                            400: '#717d8c',
                            500: '#4a5568',
                            600: '#3c4655',
                            700: '#2d3748',
                            800: '#1a202c',
                            900: '#0f141e',
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
    <!-- Primary Chart.js library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Fallback to ApexCharts if Chart.js fails -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        
        .card {
            @apply bg-white rounded-lg border border-neutral-200 shadow-sm;
        }
        
        .btn {
            @apply inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md focus:outline-none transition-colors duration-150 ease-in-out;
        }
        
        .btn-primary {
            @apply border-transparent text-white bg-primary-600 hover:bg-primary-700 focus:ring-2 focus:ring-offset-2 focus:ring-primary-500;
        }
        
        .btn-secondary {
            @apply border-secondary-300 text-secondary-700 bg-white hover:bg-secondary-50 focus:ring-2 focus:ring-offset-2 focus:ring-secondary-500;
        }
        
        .table-header {
            @apply px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider;
        }
        
        .table-cell {
            @apply px-6 py-4 whitespace-nowrap text-sm text-neutral-500;
        }
        
        .form-input {
            @apply block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm;
        }
        
        /* Badges */
        .badge {
            @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
        }
        
        .badge-success {
            @apply bg-green-100 text-green-800;
        }
        
        .badge-warning {
            @apply bg-yellow-100 text-yellow-800;
        }
        
        .badge-danger {
            @apply bg-red-100 text-red-800;
        }
        
        .badge-info {
            @apply bg-blue-100 text-blue-800;
        }
    </style>
    @yield('head')
</head>
<body class="bg-neutral-50 font-sans antialiased text-neutral-900">
    <div class="min-h-screen flex flex-col">
        <!-- Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <header class="z-10 py-4 bg-white border-b border-neutral-200 flex flex-col px-4 sm:px-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-xl font-semibold">Alexandria Educator</div>
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('api.profile.show') }}" class="text-sm text-neutral-700 hover:text-neutral-900">
                            <i class="fa-solid fa-user-circle mr-1"></i> My Profile
                        </a>
                        <form method="POST" action="{{ route('educator.logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-neutral-700 hover:text-neutral-900">
                                <i class="fa-solid fa-sign-out-alt mr-1"></i> Sign out
                            </button>
                        </form>
                    </div>
                </div>
                <div class="flex space-x-4 overflow-x-auto pb-2">
                    <a href="{{ route('educator.dashboard') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.dashboard') ? 'bg-primary-100 text-primary-700' : 'bg-white hover:bg-neutral-50 text-neutral-700' }} font-medium text-sm">
                        <i class="fa-solid fa-gauge-high mr-1"></i> Overview
                    </a>
                    <a href="{{ route('educator.courses.index') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.courses.*') ? 'bg-primary-100 text-primary-700' : 'bg-white hover:bg-neutral-50 text-neutral-700' }} font-medium text-sm">
                        <i class="fa-solid fa-book mr-1"></i> Courses
                    </a>
                    <a href="{{ route('educator.students') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.students') ? 'bg-primary-100 text-primary-700' : 'bg-white hover:bg-neutral-50 text-neutral-700' }} font-medium text-sm">
                        <i class="fa-solid fa-users mr-1"></i> Students
                    </a>
                    <a href="{{ route('educator.earnings') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.earnings') ? 'bg-primary-100 text-primary-700' : 'bg-white hover:bg-neutral-50 text-neutral-700' }} font-medium text-sm">
                        <i class="fa-solid fa-sack-dollar mr-1"></i> Earnings
                    </a>
                    <a href="{{ route('educator.settings') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.settings') ? 'bg-primary-100 text-primary-700' : 'bg-white hover:bg-neutral-50 text-neutral-700' }} font-medium text-sm">
                        <i class="fa-solid fa-gear mr-1"></i> Settings
                    </a>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-neutral-50">
                @if (session('success'))
                <div class="mb-4 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 shadow-sm animate-fade-in" role="alert">
                    <p class="font-medium">Success!</p>
                    <p>{{ session('success') }}</p>
                </div>
                @endif

                @if (session('error'))
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 shadow-sm animate-fade-in" role="alert">
                    <p class="font-medium">Error!</p>
                    <p>{{ session('error') }}</p>
                </div>
                @endif

                @if ($errors->any())
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 shadow-sm animate-fade-in" role="alert">
                    <p class="font-medium">Validation errors:</p>
                    <ul class="mt-1 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div>
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    @yield('scripts')
</body>
</html>