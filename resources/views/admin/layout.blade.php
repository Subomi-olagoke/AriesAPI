<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aries Admin @yield('title')</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        
        .sidebar-item {
            @apply flex items-center px-4 py-2 text-sm font-medium rounded-md transition-colors duration-150 ease-in-out;
        }
        
        .sidebar-item.active {
            @apply bg-primary-50 text-primary-700;
        }
        
        .sidebar-item:not(.active) {
            @apply text-neutral-600 hover:bg-neutral-50;
        }
        
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
            @apply border-neutral-300 text-neutral-700 bg-white hover:bg-neutral-50 focus:ring-2 focus:ring-offset-2 focus:ring-primary-500;
        }
        
        .btn-danger {
            @apply border-transparent text-white bg-red-600 hover:bg-red-700 focus:ring-2 focus:ring-offset-2 focus:ring-red-500;
        }
        
        .btn-success {
            @apply border-transparent text-white bg-green-600 hover:bg-green-700 focus:ring-2 focus:ring-offset-2 focus:ring-green-500;
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
    </style>
    @yield('head')
</head>
<body class="bg-neutral-50 font-sans antialiased text-neutral-900" x-data="{ sidebarOpen: true, showDropdown: false }">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-30 w-64 transition-all duration-300 transform bg-white border-r border-neutral-200" :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">
            <div class="flex items-center justify-between h-16 px-4 border-b border-neutral-200">
                <div class="flex items-center">
                    <div class="text-xl font-semibold text-neutral-900">Aries Admin</div>
                </div>
            </div>
            <div class="flex flex-col flex-1 h-0 overflow-y-auto">
                <nav class="flex-1 px-2 py-4 space-y-1">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="fa-solid fa-gauge-high w-5 h-5 mr-3"></i>
                        <span>Overview</span>
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="sidebar-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-users w-5 h-5 mr-3"></i>
                        <span>Users</span>
                    </a>
                    <a href="{{ route('admin.content') }}" class="sidebar-item {{ request()->routeIs('admin.content.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-lines w-5 h-5 mr-3"></i>
                        <span>Content</span>
                    </a>
                    <a href="{{ route('admin.libraries.index') }}" class="sidebar-item {{ request()->routeIs('admin.libraries.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-book-open w-5 h-5 mr-3"></i>
                        <span>Libraries</span>
                    </a>
                    <a href="{{ route('admin.reports') }}" class="sidebar-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-flag w-5 h-5 mr-3"></i>
                        <span>Reports</span>
                    </a>
                    <a href="{{ route('admin.revenue') }}" class="sidebar-item {{ request()->routeIs('admin.revenue.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-chart-line w-5 h-5 mr-3"></i>
                        <span>Revenue</span>
                    </a>
                    <a href="{{ route('admin.verifications') }}" class="sidebar-item {{ request()->routeIs('admin.verifications.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-user-check w-5 h-5 mr-3"></i>
                        <span>Verifications</span>
                    </a>
                    <a href="{{ route('admin.settings') }}" class="sidebar-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-gear w-5 h-5 mr-3"></i>
                        <span>Settings</span>
                    </a>
                </nav>
                <div class="p-4 border-t border-neutral-200">
                    <div class="flex items-center">
                        <div @click="showDropdown = !showDropdown" class="relative flex items-center w-full cursor-pointer">
                            <div class="flex-shrink-0">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-neutral-500">
                                    <span class="text-xs font-medium leading-none text-white">{{ substr(auth()->user()->first_name, 0, 1) }}{{ substr(auth()->user()->last_name, 0, 1) }}</span>
                                </span>
                            </div>
                            <div class="ml-3 flex-1 overflow-hidden">
                                <div class="text-sm font-medium text-neutral-900 truncate">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
                                <div class="text-xs text-neutral-500 truncate">{{ auth()->user()->email }}</div>
                            </div>
                            <i class="fa-solid fa-chevron-down text-neutral-400 ml-1"></i>
                        </div>
                    </div>
                    <div x-show="showDropdown" x-cloak @click.away="showDropdown = false" class="absolute bottom-14 left-3 right-3 z-10 mt-1 bg-white rounded-md shadow-lg border border-neutral-200">
                        <div class="py-1">
                            <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">Profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 flex flex-col" :class="{'ml-64': sidebarOpen, 'ml-0': !sidebarOpen}">
            <!-- Header -->
            <header class="z-10 py-4 bg-white border-b border-neutral-200 flex items-center justify-between px-4 sm:px-6">
                <button @click="sidebarOpen = !sidebarOpen" class="text-neutral-500 focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="flex-1 px-4 flex justify-end">
                    <div class="ml-4 flex items-center md:ml-6">
                        <button class="p-1 rounded-full text-neutral-400 hover:text-neutral-500 focus:outline-none">
                            <i class="fa-solid fa-bell"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-neutral-50">
                @if (session('success'))
                <div class="mb-4 bg-green-50 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p class="font-medium">Success!</p>
                    <p>{{ session('success') }}</p>
                </div>
                @endif

                @if (session('error'))
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-medium">Error!</p>
                    <p>{{ session('error') }}</p>
                </div>
                @endif

                @if ($errors->any())
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-medium">Validation errors:</p>
                    <ul class="mt-1 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
    @yield('scripts')
</body>
</html>