<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alexandria Educator @yield('title', 'Dashboard')</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        
        .card {
            @apply bg-white rounded-lg border border-neutral-200 shadow-sm hover:shadow-md transition-shadow;
        }
        
        .btn {
            @apply inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md focus:outline-none transition-all duration-150 ease-in-out;
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
        
        /* Nav element styles */
        .nav-item {
            @apply px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150;
        }
        
        .nav-item-active {
            @apply bg-primary-100 text-primary-700;
        }
        
        .nav-item-inactive {
            @apply bg-white hover:bg-neutral-50 text-neutral-700 hover:text-neutral-900;
        }
        
        /* Alerts */
        .alert {
            @apply p-4 rounded-md shadow-sm mb-4 border-l-4 animate-fade-in;
        }
        
        .alert-success {
            @apply bg-green-50 border-green-500 text-green-700;
        }
        
        .alert-error {
            @apply bg-red-50 border-red-500 text-red-700;
        }
        
        /* Custom animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
    @yield('head')
</head>
<body class="bg-neutral-50 font-sans antialiased text-neutral-900">
    <div x-data="{ sidebarOpen: false }" class="min-h-screen flex flex-col">
        <!-- Mobile Header & Nav (small screens) -->
        <header class="lg:hidden z-10 py-4 bg-white border-b border-neutral-200 px-4 sm:px-6 flex items-center justify-between">
            <div class="flex items-center">
                <button @click="sidebarOpen = !sidebarOpen" class="text-neutral-500 focus:outline-none focus:text-neutral-700 mr-3">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div class="text-xl font-semibold text-primary-700">Alexandria Educator</div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center focus:outline-none">
                        <img src="{{ Auth::user()->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode(Auth::user()->first_name . ' ' . Auth::user()->last_name) . '&color=7F9CF5&background=EBF4FF' }}" 
                             alt="Profile" class="h-8 w-8 rounded-full object-cover border border-neutral-200">
                    </button>
                    <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <a href="{{ route('profile.deep-link', Auth::user()->username) }}" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">My Profile</a>
                        <a href="{{ route('educator.settings') }}" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">Settings</a>
                        <form method="POST" action="{{ route('educator.logout') }}" class="inline w-full">
                            @csrf
                            <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Sidebar (small screens) -->
        <div x-show="sidebarOpen" @click.away="sidebarOpen = false" class="fixed inset-0 flex z-40 lg:hidden">
            <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-neutral-600 bg-opacity-75"></div>
            
            <div x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform"
                x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in-out duration-300 transform"
                x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                class="relative flex-1 flex flex-col max-w-xs w-full bg-white">
                
                <div class="px-6 pt-6 pb-4 flex items-center justify-between">
                    <div class="text-xl font-bold text-primary-700">Alexandria</div>
                    <button @click="sidebarOpen = false" class="text-neutral-500 focus:outline-none focus:text-neutral-700">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>
                
                <div class="flex-1 px-6 py-4 overflow-y-auto">
                    <div class="flex flex-col space-y-2">
                        <span class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-2">Main</span>
                        <a href="{{ route('educator.dashboard') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.dashboard') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                            <i class="fa-solid fa-gauge-high w-5 mr-2"></i> Dashboard
                        </a>
                        <a href="{{ route('educator.courses.index') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.courses.*') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                            <i class="fa-solid fa-book-open w-5 mr-2"></i> Courses
                        </a>
                        <a href="{{ route('educator.students') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.students') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                            <i class="fa-solid fa-users w-5 mr-2"></i> Students
                        </a>
                        <a href="{{ route('educator.earnings') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.earnings') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                            <i class="fa-solid fa-sack-dollar w-5 mr-2"></i> Earnings
                        </a>

                        <span class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mt-6 mb-2">Account</span>
                        <a href="{{ route('educator.settings') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.settings') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                            <i class="fa-solid fa-gear w-5 mr-2"></i> Settings
                        </a>
                        <form method="POST" action="{{ route('educator.logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="w-full text-left px-3 py-2 rounded-md text-neutral-700 hover:bg-neutral-100 font-medium text-sm flex items-center">
                                <i class="fa-solid fa-sign-out-alt w-5 mr-2"></i> Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 flex lg:flex-row flex-col">
            <!-- Sidebar for large screens -->
            <div class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-white border-r border-neutral-200">
                <div class="flex flex-col h-full">
                    <div class="px-6 pt-6 pb-4">
                        <div class="text-xl font-bold text-primary-700">Alexandria</div>
                        <div class="text-sm text-neutral-500">Educator Dashboard</div>
                    </div>
                    
                    <div class="flex-1 px-6 py-4 overflow-y-auto">
                        <div class="flex flex-col space-y-2">
                            <span class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-2">Main</span>
                            <a href="{{ route('educator.dashboard') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.dashboard') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                                <i class="fa-solid fa-gauge-high w-5 mr-2"></i> Dashboard
                            </a>
                            <a href="{{ route('educator.courses.index') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.courses.*') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                                <i class="fa-solid fa-book-open w-5 mr-2"></i> Courses
                            </a>
                            <a href="{{ route('educator.students') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.students') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                                <i class="fa-solid fa-users w-5 mr-2"></i> Students
                            </a>
                            <a href="{{ route('educator.earnings') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.earnings') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                                <i class="fa-solid fa-sack-dollar w-5 mr-2"></i> Earnings
                            </a>

                            <span class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mt-6 mb-2">Account</span>
                            <a href="{{ route('educator.settings') }}" class="px-3 py-2 rounded-md {{ request()->routeIs('educator.settings') ? 'bg-primary-100 text-primary-700' : 'text-neutral-700 hover:bg-neutral-100' }} font-medium text-sm flex items-center">
                                <i class="fa-solid fa-gear w-5 mr-2"></i> Settings
                            </a>
                            <form method="POST" action="{{ route('educator.logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-2 rounded-md text-neutral-700 hover:bg-neutral-100 font-medium text-sm flex items-center">
                                    <i class="fa-solid fa-sign-out-alt w-5 mr-2"></i> Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="p-4 border-t border-neutral-200">
                        <div class="flex items-center">
                            <img src="{{ Auth::user()->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode(Auth::user()->first_name . ' ' . Auth::user()->last_name) . '&color=7F9CF5&background=EBF4FF' }}" 
                                 alt="Profile" class="h-10 w-10 rounded-full object-cover border border-neutral-200">
                            <div class="ml-3">
                                <p class="text-sm font-medium text-neutral-700">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</p>
                                <p class="text-xs text-neutral-500">{{ Auth::user()->email }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="lg:pl-64 flex-1">
                <!-- Alerts -->
                <div class="p-4 sm:p-6">
                    @if (session('success'))
                    <div class="alert alert-success" role="alert" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                        <div class="flex">
                            <i class="fa-solid fa-check-circle text-green-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="font-medium">Success!</p>
                                <p>{{ session('success') }}</p>
                            </div>
                            <button @click="show = false" class="ml-auto text-green-500 hover:text-green-700">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                    @endif

                    @if (session('error'))
                    <div class="alert alert-error" role="alert" x-data="{ show: true }" x-show="show">
                        <div class="flex">
                            <i class="fa-solid fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="font-medium">Error!</p>
                                <p>{{ session('error') }}</p>
                            </div>
                            <button @click="show = false" class="ml-auto text-red-500 hover:text-red-700">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                    @endif

                    @if ($errors->any())
                    <div class="alert alert-error" role="alert" x-data="{ show: true }" x-show="show">
                        <div class="flex">
                            <i class="fa-solid fa-exclamation-triangle text-red-500 mt-0.5 mr-3"></i>
                            <div>
                                <p class="font-medium">Validation errors:</p>
                                <ul class="mt-1 list-disc list-inside">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <button @click="show = false" class="ml-auto text-red-500 hover:text-red-700">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                    @endif

                    <!-- Page Header -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-neutral-800">@yield('page-title', 'Dashboard')</h1>
                        <p class="text-sm text-neutral-500">@yield('page-subtitle', 'Welcome to your educator dashboard')</p>
                    </div>

                    <!-- Page Content -->
                    <div>
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>
    @yield('scripts')
</body>
</html>