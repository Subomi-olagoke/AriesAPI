<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alexandria Admin @yield('title')</title>
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
                            50: '#f5f5f5',
                            100: '#ebebeb',
                            200: '#d6d6d6',
                            300: '#b3b3b3',
                            400: '#8c8c8c',
                            500: '#666666',
                            600: '#4d4d4d',
                            700: '#333333',
                            800: '#1a1a1a',
                            900: '#0a0a0a',
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
                        'accent': {
                            500: '#000000',
                            600: '#000000',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-in': 'slideIn 0.5s ease-out',
                        'pulse-subtle': 'pulseSubtle 2s infinite',
                        'float': 'float 3s ease-in-out infinite',
                        'slide-up': 'slideUp 0.4s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideIn: {
                            '0%': { transform: 'translateX(-10px)', opacity: '0' },
                            '100%': { transform: 'translateX(0)', opacity: '1' },
                        },
                        pulseSubtle: {
                            '0%, 100%': { opacity: '1' },
                            '50%': { opacity: '0.85' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        
        /* Base animations and transitions */
        .animate-fade-in {
            @apply animate-fade-in;
        }
        
        .animate-slide-in {
            @apply animate-slide-in;
        }
        
        .animate-slide-up {
            @apply animate-slide-up;
        }
        
        .animate-pulse-subtle {
            @apply animate-pulse-subtle;
        }
        
        .animate-float {
            @apply animate-float;
        }
        
        /* Staggered animations */
        .stagger-1 { animation-delay: 0.05s; }
        .stagger-2 { animation-delay: 0.1s; }
        .stagger-3 { animation-delay: 0.15s; }
        .stagger-4 { animation-delay: 0.2s; }
        .stagger-5 { animation-delay: 0.25s; }
        
        /* Component styles */
        .sidebar-item {
            @apply flex items-center px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 ease-in-out;
        }
        
        .sidebar-item.active {
            @apply bg-primary-800 text-white;
        }
        
        .sidebar-item:not(.active) {
            @apply text-neutral-600 hover:bg-primary-100 hover:text-primary-700;
        }
        
        .card {
            @apply bg-white rounded-lg border border-neutral-200 shadow-sm transition-all duration-300 hover:shadow-md;
        }
        
        .btn {
            @apply inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md focus:outline-none transition-all duration-200 ease-in-out;
        }
        
        .btn-primary {
            @apply border-transparent text-white bg-primary-800 hover:bg-primary-900 focus:ring-2 focus:ring-offset-2 focus:ring-primary-700;
        }
        
        .btn-secondary {
            @apply border-primary-300 text-primary-700 bg-white hover:bg-primary-50 focus:ring-2 focus:ring-offset-2 focus:ring-primary-500;
        }
        
        .btn-danger {
            @apply border-transparent text-white bg-neutral-800 hover:bg-neutral-900 focus:ring-2 focus:ring-offset-2 focus:ring-neutral-700;
        }
        
        .btn-success {
            @apply border-transparent text-white bg-primary-600 hover:bg-primary-700 focus:ring-2 focus:ring-offset-2 focus:ring-primary-500;
        }
        
        .table-header {
            @apply px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider;
        }
        
        .table-cell {
            @apply px-6 py-4 whitespace-nowrap text-sm text-neutral-600;
        }
        
        .form-input {
            @apply block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-800 focus:ring-primary-700 sm:text-sm transition-all duration-200;
        }
        
        /* Hover effects */
        .hover-lift {
            @apply transition-transform duration-300 hover:-translate-y-1;
        }
        
        .hover-scale {
            @apply transition-transform duration-300 hover:scale-105;
        }
        
        /* Status badges */
        .badge {
            @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
        }
        
        .badge-success {
            @apply bg-primary-100 text-primary-800;
        }
        
        .badge-warning {
            @apply bg-neutral-100 text-neutral-800;
        }
        
        .badge-danger {
            @apply bg-neutral-800 text-white;
        }
        
    </style>
    @yield('head')
</head>
<body class="bg-neutral-50 font-sans antialiased text-neutral-900" x-data="{ sidebarOpen: true, showDropdown: false }">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-30 w-64 transition-all duration-300 transform bg-white border-r border-neutral-200 shadow-md" :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">
            <div class="flex items-center justify-between h-16 px-4 border-b border-neutral-200 bg-primary-800 text-white">
                <div class="flex items-center">
                    <div class="text-xl font-semibold">Alexandria Admin</div>
                </div>
            </div>
            <div class="flex flex-col flex-1 h-0 overflow-y-auto">
                <nav class="flex-1 px-2 py-4 space-y-1">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }} animate-slide-in stagger-1">
                        <i class="fa-solid fa-gauge-high w-5 h-5 mr-3"></i>
                        <span>Overview</span>
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="sidebar-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }} animate-slide-in stagger-2">
                        <i class="fa-solid fa-users w-5 h-5 mr-3"></i>
                        <span>Users</span>
                    </a>
                    <a href="{{ route('admin.content') }}" class="sidebar-item {{ request()->routeIs('admin.content.*') ? 'active' : '' }} animate-slide-in stagger-3">
                        <i class="fa-solid fa-file-lines w-5 h-5 mr-3"></i>
                        <span>Content</span>
                    </a>
                    <a href="{{ route('admin.libraries.index') }}" class="sidebar-item {{ request()->routeIs('admin.libraries.*') ? 'active' : '' }} animate-slide-in stagger-4">
                        <i class="fa-solid fa-book-open w-5 h-5 mr-3"></i>
                        <span>Libraries</span>
                    </a>
                    <a href="{{ route('admin.reports') }}" class="sidebar-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }} animate-slide-in stagger-5">
                        <i class="fa-solid fa-flag w-5 h-5 mr-3"></i>
                        <span>Reports</span>
                    </a>
                    <div class="border-t border-neutral-200 my-2"></div>
                    <a href="{{ route('admin.revenue') }}" class="sidebar-item {{ request()->routeIs('admin.revenue.*') ? 'active' : '' }} animate-slide-in stagger-1">
                        <i class="fa-solid fa-chart-line w-5 h-5 mr-3"></i>
                        <span>Revenue</span>
                    </a>
                    <a href="{{ route('admin.verifications') }}" class="sidebar-item {{ request()->routeIs('admin.verifications.*') ? 'active' : '' }} animate-slide-in stagger-2">
                        <i class="fa-solid fa-user-check w-5 h-5 mr-3"></i>
                        <span>Verifications</span>
                    </a>
                    <a href="{{ route('admin.settings') }}" class="sidebar-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }} animate-slide-in stagger-3">
                        <i class="fa-solid fa-gear w-5 h-5 mr-3 animate-pulse-subtle"></i>
                        <span>Settings</span>
                    </a>
                </nav>
                <div class="p-4 border-t border-neutral-200 animate-fade-in">
                    <div class="flex items-center">
                        <div @click="showDropdown = !showDropdown" class="relative flex items-center w-full cursor-pointer hover-lift">
                            <div class="flex-shrink-0">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary-700">
                                    <span class="text-xs font-medium leading-none text-white">{{ substr(auth()->user()->first_name, 0, 1) }}{{ substr(auth()->user()->last_name, 0, 1) }}</span>
                                </span>
                            </div>
                            <div class="ml-3 flex-1 overflow-hidden">
                                <div class="text-sm font-medium text-neutral-900 truncate">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
                                <div class="text-xs text-neutral-500 truncate">{{ auth()->user()->email }}</div>
                            </div>
                            <i class="fa-solid fa-chevron-down text-neutral-400 ml-1 transition-transform duration-200" :class="{'rotate-180': showDropdown}"></i>
                        </div>
                    </div>
                    <div x-show="showDropdown" x-cloak @click.away="showDropdown = false" 
                        x-transition:enter="transition ease-out duration-200" 
                        x-transition:enter-start="opacity-0 scale-95" 
                        x-transition:enter-end="opacity-100 scale-100" 
                        x-transition:leave="transition ease-in duration-150" 
                        x-transition:leave-start="opacity-100 scale-100" 
                        x-transition:leave-end="opacity-0 scale-95" 
                        class="absolute bottom-14 left-3 right-3 z-10 mt-1 bg-white rounded-md shadow-lg border border-neutral-200">
                        <div class="py-1">
                            <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-primary-50 transition-colors duration-150">Profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-primary-50 transition-colors duration-150">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 flex flex-col transition-all duration-300" :class="{'ml-64': sidebarOpen, 'ml-0': !sidebarOpen}">
            <!-- Header -->
            <header class="z-10 py-4 bg-white border-b border-neutral-200 shadow-sm flex items-center justify-between px-4 sm:px-6 animate-fade-in">
                <button @click="sidebarOpen = !sidebarOpen" class="text-primary-600 hover:text-primary-800 focus:outline-none transition-all duration-200 hover:scale-110 p-1">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-center">
                    <h1 class="text-xl font-semibold text-primary-800 hidden md:block">
                        @if(request()->routeIs('admin.dashboard'))
                            Dashboard Overview
                        @elseif(request()->routeIs('admin.users.*'))
                            User Management
                        @elseif(request()->routeIs('admin.content.*'))
                            Content Management
                        @elseif(request()->routeIs('admin.libraries.*'))
                            Libraries
                        @elseif(request()->routeIs('admin.reports.*'))
                            Reports
                        @elseif(request()->routeIs('admin.revenue.*'))
                            Revenue Analytics
                        @elseif(request()->routeIs('admin.verifications.*'))
                            Verifications
                        @elseif(request()->routeIs('admin.settings.*'))
                            System Settings
                        @else
                            Alexandria Admin
                        @endif
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="p-1 rounded-full text-primary-600 hover:text-primary-800 focus:outline-none hover-scale">
                        <i class="fa-solid fa-bell"></i>
                    </button>
                    <button class="p-1 rounded-full text-primary-600 hover:text-primary-800 focus:outline-none hover-scale">
                        <i class="fa-solid fa-gear"></i>
                    </button>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-neutral-50 animate-fade-in">
                @if (session('success'))
                <div class="mb-4 bg-primary-50 border-l-4 border-primary-500 text-primary-700 p-4 shadow-sm animate-slide-up" role="alert">
                    <p class="font-medium">Success!</p>
                    <p>{{ session('success') }}</p>
                </div>
                @endif

                @if (session('error'))
                <div class="mb-4 bg-neutral-100 border-l-4 border-neutral-800 text-neutral-800 p-4 shadow-sm animate-slide-up" role="alert">
                    <p class="font-medium">Error!</p>
                    <p>{{ session('error') }}</p>
                </div>
                @endif

                @if ($errors->any())
                <div class="mb-4 bg-neutral-100 border-l-4 border-neutral-800 text-neutral-800 p-4 shadow-sm animate-slide-up" role="alert">
                    <p class="font-medium">Validation errors:</p>
                    <ul class="mt-1 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="animate-fade-in">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    @yield('scripts')
</body>
</html>