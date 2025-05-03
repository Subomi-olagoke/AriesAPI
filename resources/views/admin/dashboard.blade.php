<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alexeandria Admin</title>
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
    </style>
</head>
<body class="bg-neutral-50 font-sans antialiased text-neutral-900" x-data="{ sidebarOpen: true, currentTab: 'overview', showDropdown: false }" x-init="console.log('Alpine initialized')">
    <div class="min-h-screen flex">
        <!-- Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <header class="z-10 py-4 bg-white border-b border-neutral-200 flex flex-col px-4 sm:px-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-xl font-semibold">Aries Admin Dashboard</div>
                    <div class="flex items-center">
                        <a href="/admin/logout" class="text-sm text-neutral-700 hover:text-neutral-900">
                            <i class="fa-solid fa-sign-out-alt mr-1"></i> Sign out
                        </a>
                    </div>
                </div>
                <div class="flex space-x-4 overflow-x-auto pb-2">
                    <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 rounded-md bg-primary-100 text-primary-700 font-medium text-sm">
                        <i class="fa-solid fa-gauge-high mr-1"></i> Overview
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="px-3 py-2 rounded-md bg-white hover:bg-neutral-50 text-neutral-700 font-medium text-sm">
                        <i class="fa-solid fa-users mr-1"></i> Users
                    </a>
                    <a href="{{ route('admin.libraries.index') }}" class="px-3 py-2 rounded-md bg-white hover:bg-neutral-50 text-neutral-700 font-medium text-sm">
                        <i class="fa-solid fa-book-open mr-1"></i> Libraries
                    </a>
                    <a href="{{ route('admin.content') }}" class="px-3 py-2 rounded-md bg-white hover:bg-neutral-50 text-neutral-700 font-medium text-sm">
                        <i class="fa-solid fa-file-lines mr-1"></i> Content
                    </a>
                    <a href="{{ route('admin.reports') }}" class="px-3 py-2 rounded-md bg-white hover:bg-neutral-50 text-neutral-700 font-medium text-sm">
                        <i class="fa-solid fa-flag mr-1"></i> Reports
                    </a>
                    <a href="{{ route('admin.revenue') }}" class="px-3 py-2 rounded-md bg-white hover:bg-neutral-50 text-neutral-700 font-medium text-sm">
                        <i class="fa-solid fa-chart-line mr-1"></i> Revenue
                    </a>
                    <a href="{{ route('admin.verifications') }}" class="px-3 py-2 rounded-md bg-white hover:bg-neutral-50 text-neutral-700 font-medium text-sm">
                        <i class="fa-solid fa-user-check mr-1"></i> Verifications
                    </a>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-neutral-50">
                <!-- Overview Tab -->
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold text-neutral-900">Dashboard</h1>
                        <div class="flex space-x-2">
                            <div class="relative" x-data="{ isOpen: false, selectedPeriod: 'last30' }">
                                <button @click="isOpen = !isOpen" class="btn btn-secondary">
                                    <i class="fa-solid fa-calendar mr-2"></i> 
                                    <span x-text="selectedPeriod === 'last30' ? 'Last 30 days' : 
                                                 selectedPeriod === 'last90' ? 'Last 90 days' : 
                                                 selectedPeriod === 'thisYear' ? 'This Year' : 
                                                 selectedPeriod === 'allTime' ? 'All Time' : 'Last 30 days'"></span>
                                    <i class="fa-solid fa-chevron-down ml-2 text-xs"></i>
                                </button>
                                <div x-show="isOpen" @click.away="isOpen = false" class="absolute mt-1 bg-white shadow-lg rounded-md border border-neutral-200 z-10">
                                    <div class="py-1">
                                        <a href="#" @click.prevent="selectedPeriod = 'last30'; isOpen = false" class="block px-4 py-2 text-sm text-secondary-700 hover:bg-secondary-50">Last 30 days</a>
                                        <a href="#" @click.prevent="selectedPeriod = 'last90'; isOpen = false" class="block px-4 py-2 text-sm text-secondary-700 hover:bg-secondary-50">Last 90 days</a>
                                        <a href="#" @click.prevent="selectedPeriod = 'thisYear'; isOpen = false" class="block px-4 py-2 text-sm text-secondary-700 hover:bg-secondary-50">This Year</a>
                                        <a href="#" @click.prevent="selectedPeriod = 'allTime'; isOpen = false" class="block px-4 py-2 text-sm text-secondary-700 hover:bg-secondary-50">All Time</a>
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('admin.export-stats') }}" class="btn btn-secondary" id="exportBtn">
                                <i class="fa-solid fa-file-export mr-2"></i> Export
                            </a>
                        </div>
                    </div>

                    <!-- Debug Banner (will only show if there are chart errors) -->
                    <div id="chart-debug-banner" class="mb-6 bg-orange-50 border border-orange-200 rounded-lg p-4 hidden">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <i class="fa-solid fa-triangle-exclamation text-orange-500"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-orange-800">Chart Debug Information</h3>
                                <div class="mt-2 text-sm text-orange-700">
                                    <p>There were issues loading the charts. Check the developer console for more details.</p>
                                    <div id="chart-debug-details" class="mt-2 bg-white p-3 rounded text-xs font-mono overflow-auto max-h-40"></div>
                                    <div class="mt-3 flex space-x-3">
                                        <button id="retry-charts-btn" class="px-3 py-1 bg-orange-600 hover:bg-orange-700 text-white text-xs rounded">
                                            Retry Loading Charts
                                        </button>
                                        <button id="toggle-debug-btn" class="px-3 py-1 bg-gray-600 hover:bg-gray-700 text-white text-xs rounded">
                                            Toggle Debug Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Cards -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                        <!-- Total Users -->
                        <div class="card">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-neutral-500">Total Users</div>
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['users']['total'] }}</div>
                                    </div>
                                    <div class="w-12 h-12 bg-primary-50 rounded-md flex items-center justify-center">
                                        <i class="fa-solid fa-users text-primary-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-sm">
                                    <span class="text-green-600 font-medium flex items-center">
                                        <i class="fa-solid fa-arrow-up mr-1 text-xs"></i> 12%
                                    </span>
                                    <span class="ml-2 text-neutral-500">vs last month</span>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue -->
                        <div class="card">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-neutral-500">Total Revenue</div>
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900">${{ number_format($stats['revenue']['total']) }}</div>
                                    </div>
                                    <div class="w-12 h-12 bg-green-50 rounded-md flex items-center justify-center">
                                        <i class="fa-solid fa-dollar-sign text-green-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-sm">
                                    <span class="text-green-600 font-medium flex items-center">
                                        <i class="fa-solid fa-arrow-up mr-1 text-xs"></i> 8.2%
                                    </span>
                                    <span class="ml-2 text-neutral-500">vs last month</span>
                                </div>
                            </div>
                        </div>

                        <!-- New Content -->
                        <div class="card">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-neutral-500">New Content</div>
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['content']['posts_today'] }}</div>
                                    </div>
                                    <div class="w-12 h-12 bg-blue-50 rounded-md flex items-center justify-center">
                                        <i class="fa-solid fa-file-lines text-blue-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-sm">
                                    <span class="text-green-600 font-medium flex items-center">
                                        <i class="fa-solid fa-arrow-up mr-1 text-xs"></i> 5.4%
                                    </span>
                                    <span class="ml-2 text-neutral-500">vs last month</span>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Verifications -->
                        <div class="card">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-neutral-500">Pending Verifications</div>
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['content']['pending_libraries'] }}</div>
                                    </div>
                                    <div class="w-12 h-12 bg-yellow-50 rounded-md flex items-center justify-center">
                                        <i class="fa-solid fa-user-check text-yellow-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-sm">
                                    <span class="text-red-600 font-medium flex items-center">
                                        <i class="fa-solid fa-arrow-up mr-1 text-xs"></i> 12.5%
                                    </span>
                                    <span class="ml-2 text-neutral-500">vs last month</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- User Growth Chart -->
                        <div class="card">
                            <div class="p-5 border-b border-neutral-200">
                                <h3 class="text-lg font-medium text-neutral-900">User Growth</h3>
                            </div>
                            <div class="p-5">
                                <!-- Chart debug message will appear here if there's an issue -->
                                <div id="userGrowthChartError" class="hidden p-3 bg-red-50 text-red-700 text-sm rounded mb-3">
                                    Chart failed to load. Please check console for details.
                                </div>
                                <canvas id="userGrowthChart" height="260"></canvas>
                            </div>
                        </div>

                        <!-- Revenue Chart -->
                        <div class="card">
                            <div class="p-5 border-b border-neutral-200">
                                <h3 class="text-lg font-medium text-neutral-900">Revenue Trends</h3>
                            </div>
                            <div class="p-5">
                                <!-- Chart debug message will appear here if there's an issue -->
                                <div id="revenueChartError" class="hidden p-3 bg-red-50 text-red-700 text-sm rounded mb-3">
                                    Chart failed to load. Please check console for details.
                                </div>
                                <canvas id="revenueChart" height="260"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card mb-6">
                        <div class="p-5 border-b border-neutral-200 flex justify-between items-center">
                            <h3 class="text-lg font-medium text-neutral-900">Recent Activity</h3>
                            <button class="text-sm text-secondary-700 hover:text-secondary-900">View all</button>
                        </div>
                        <div class="divide-y divide-neutral-200">
                            <div class="p-5 flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary-100">
                                        <i class="fa-solid fa-user-plus text-primary-600"></i>
                                    </span>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="text-sm font-medium text-neutral-900">New user registered</div>
                                    <div class="text-sm text-neutral-500">John Smith created an account</div>
                                </div>
                                <div class="text-sm text-neutral-500">2 hours ago</div>
                            </div>
                            <div class="p-5 flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                                        <i class="fa-solid fa-dollar-sign text-green-600"></i>
                                    </span>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="text-sm font-medium text-neutral-900">New payment received</div>
                                    <div class="text-sm text-neutral-500">Sarah Johnson purchased "Advanced JavaScript Course"</div>
                                </div>
                                <div class="text-sm text-neutral-500">4 hours ago</div>
                            </div>
                            <div class="p-5 flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-yellow-100">
                                        <i class="fa-solid fa-file-circle-plus text-yellow-600"></i>
                                    </span>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="text-sm font-medium text-neutral-900">New course added</div>
                                    <div class="text-sm text-neutral-500">Alex Williams added "Python for Data Science"</div>
                                </div>
                                <div class="text-sm text-neutral-500">6 hours ago</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Libraries Tab -->
                <div x-show="currentTab === 'libraries'" x-cloak>
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold text-neutral-900">Content Libraries</h1>
                        <div class="flex space-x-2">
                            <div class="relative">
                                <input type="text" placeholder="Search libraries..." class="form-input pl-9 py-2">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-search text-neutral-400"></i>
                                </div>
                            </div>
                            <select class="form-input py-2">
                                <option>All Status</option>
                                <option>Pending</option>
                                <option>Approved</option>
                                <option>Rejected</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Summary -->
                    <div class="bg-white px-4 py-3 border border-neutral-200 rounded-md mb-4 flex items-center">
                        <span class="text-sm text-neutral-600">Filters:</span>
                        <div class="ml-2 flex flex-wrap gap-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-neutral-100 text-neutral-800">
                                Status: Pending
                                <button class="ml-1 text-neutral-500 hover:text-neutral-700">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </span>
                        </div>
                        <button class="ml-auto text-sm text-primary-600 hover:text-primary-700">Clear all</button>
                    </div>

                    <!-- Libraries Table -->
                    <div class="card overflow-hidden">
                        <table class="min-w-full divide-y divide-neutral-200">
                            <thead class="bg-neutral-50">
                                <tr>
                                    <th scope="col" class="table-header">Name</th>
                                    <th scope="col" class="table-header">Type</th>
                                    <th scope="col" class="table-header">Created</th>
                                    <th scope="col" class="table-header">Items</th>
                                    <th scope="col" class="table-header">Status</th>
                                    <th scope="col" class="table-header text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-neutral-200">
                                @forelse($pendingLibraries as $library)
                                <tr>
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0 rounded bg-primary-100 flex items-center justify-center">
                                                <i class="fa-solid fa-book text-primary-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-neutral-900">{{ $library->title ?? 'Untitled Library' }}</div>
                                                <div class="text-xs text-neutral-500 truncate max-w-xs">{{ $library->description ?? 'No description available' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="table-cell">{{ $library->type ?? 'Auto' }}</td>
                                    <td class="table-cell">{{ $library->created_at ? $library->created_at->format('M d, Y') : 'Unknown date' }}</td>
                                    <td class="table-cell">{{ $library->items_count ?? 0 }}</td>
                                    <td class="table-cell">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ ucfirst($library->approval_status ?? 'Pending') }}
                                        </span>
                                    </td>
                                    <td class="table-cell text-right">
                                        <div class="flex justify-end space-x-2">
                                            <a href="{{ route('admin.libraries.view', [$library->id]) }}" class="text-primary-600 hover:text-primary-900">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <form action="{{ route('admin.libraries.approve', [$library->id]) }}" method="POST" style="display:inline">
                                                @csrf
                                                <button type="submit" class="text-green-600 hover:text-green-900" title="Approve Library">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.libraries.reject', [$library->id]) }}" method="POST" style="display:inline">
                                                @csrf
                                                <input type="hidden" name="rejection_reason" value="Content does not meet guidelines">
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Reject Library">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="table-cell text-center py-4">
                                        <div class="text-sm text-neutral-500">No pending libraries found</div>
                                    </td>
                                </tr>
                                @endforelse
                                
                                @if(count($pendingLibraries) > 0 && count($pendingLibraries) < 3)
                                <!-- Add some sample approved libraries to show contrast -->
                                <tr>
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0 rounded bg-primary-100 flex items-center justify-center">
                                                <i class="fa-solid fa-book text-primary-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-neutral-900">Art & Literature</div>
                                                <div class="text-xs text-neutral-500 truncate max-w-xs">Exploring artistic expressions and literary works</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="table-cell">Auto</td>
                                    <td class="table-cell">Apr 28, 2025</td>
                                    <td class="table-cell">30</td>
                                    <td class="table-cell">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Approved
                                        </span>
                                    </td>
                                    <td class="table-cell text-right">
                                        <div class="flex justify-end space-x-2">
                                            <button class="text-primary-600 hover:text-primary-900">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="text-neutral-500 hover:text-neutral-700">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-neutral-200">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <button class="btn btn-secondary">Previous</button>
                                <button class="btn btn-secondary ml-3">Next</button>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-neutral-700">
                                        Showing <span class="font-medium">1</span> to <span class="font-medium">{{ count($pendingLibraries) }}</span> of <span class="font-medium">{{ $stats['content']['pending_libraries'] }}</span> pending libraries
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        <button class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-neutral-300 bg-white text-sm font-medium text-neutral-500 hover:bg-neutral-50">
                                            <i class="fa-solid fa-chevron-left text-xs"></i>
                                        </button>
                                        <button class="relative inline-flex items-center px-4 py-2 border border-neutral-300 bg-white text-sm font-medium text-neutral-700 hover:bg-neutral-50">1</button>
                                        <button class="relative inline-flex items-center px-4 py-2 border border-neutral-300 bg-primary-50 text-sm font-medium text-primary-600 hover:bg-primary-100">2</button>
                                        <button class="relative inline-flex items-center px-4 py-2 border border-neutral-300 bg-white text-sm font-medium text-neutral-700 hover:bg-neutral-50">3</button>
                                        <button class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-neutral-300 bg-white text-sm font-medium text-neutral-500 hover:bg-neutral-50">
                                            <i class="fa-solid fa-chevron-right text-xs"></i>
                                        </button>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Tab -->
                <div x-show="currentTab === 'users'" x-cloak>
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold text-neutral-900">User Management</h1>
                        <div class="flex space-x-2">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-primary">
                                <i class="fa-solid fa-user-group mr-2"></i> View All Users
                            </a>
                            <a href="{{ route('admin.users.banned') }}" class="btn btn-secondary">
                                <i class="fa-solid fa-user-slash mr-2"></i> Banned Users
                            </a>
                        </div>
                    </div>
                    
                    <!-- User Stats Cards -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                        <!-- Total Users -->
                        <div class="card">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-neutral-500">Total Users</div>
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900">{{ $stats['users']['total'] }}</div>
                                    </div>
                                    <div class="w-12 h-12 bg-primary-50 rounded-md flex items-center justify-center">
                                        <i class="fa-solid fa-users text-primary-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-sm">
                                    <span class="text-green-600 font-medium flex items-center">
                                        <i class="fa-solid fa-arrow-up mr-1 text-xs"></i> 12%
                                    </span>
                                    <span class="ml-2 text-neutral-500">vs last month</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Users -->
                        <div class="card">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-neutral-500">Active Users</div>
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900">1,625</div>
                                    </div>
                                    <div class="w-12 h-12 bg-green-50 rounded-md flex items-center justify-center">
                                        <i class="fa-solid fa-user-check text-green-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-sm">
                                    <span class="text-green-600 font-medium flex items-center">
                                        <i class="fa-solid fa-arrow-up mr-1 text-xs"></i> 8%
                                    </span>
                                    <span class="ml-2 text-neutral-500">vs last month</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- New Users Today -->
                        <div class="card">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-neutral-500">New Users Today</div>
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900">48</div>
                                    </div>
                                    <div class="w-12 h-12 bg-blue-50 rounded-md flex items-center justify-center">
                                        <i class="fa-solid fa-user-plus text-blue-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-sm">
                                    <span class="text-green-600 font-medium flex items-center">
                                        <i class="fa-solid fa-arrow-up mr-1 text-xs"></i> 15%
                                    </span>
                                    <span class="ml-2 text-neutral-500">vs yesterday</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Banned Users -->
                        <div class="card">
                            <div class="p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-neutral-500">Banned Users</div>
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900">12</div>
                                    </div>
                                    <div class="w-12 h-12 bg-red-50 rounded-md flex items-center justify-center">
                                        <i class="fa-solid fa-user-slash text-red-600 text-xl"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center text-sm">
                                    <span class="text-neutral-600 font-medium flex items-center">
                                        <i class="fa-solid fa-minus mr-1 text-xs"></i> No change
                                    </span>
                                    <span class="ml-2 text-neutral-500">vs last month</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Overview -->
                    <div class="card mb-6">
                        <div class="p-5 border-b border-neutral-200">
                            <h3 class="text-lg font-medium text-neutral-900">Recently Registered Users</h3>
                        </div>
                        <div class="divide-y divide-neutral-200">
                            <!-- Sample user rows - would be dynamically populated -->
                            <div class="p-5 flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-neutral-500">
                                        <span class="font-medium leading-none text-white">JS</span>
                                    </span>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="flex items-center">
                                        <div class="text-sm font-medium text-neutral-900">John Smith</div>
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-neutral-100 text-neutral-800">
                                            User
                                        </span>
                                    </div>
                                    <div class="text-sm text-neutral-500">john.smith@example.com</div>
                                </div>
                                <div class="text-sm text-neutral-500">
                                    <div>Registered 2 hours ago</div>
                                    <div class="text-right mt-1">
                                        <a href="#" class="text-primary-600 hover:text-primary-700">View Profile</a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-5 flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-neutral-500">
                                        <span class="font-medium leading-none text-white">SJ</span>
                                    </span>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="flex items-center">
                                        <div class="text-sm font-medium text-neutral-900">Sarah Johnson</div>
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Educator
                                        </span>
                                    </div>
                                    <div class="text-sm text-neutral-500">sarah.johnson@example.com</div>
                                </div>
                                <div class="text-sm text-neutral-500">
                                    <div>Registered 5 hours ago</div>
                                    <div class="text-right mt-1">
                                        <a href="#" class="text-primary-600 hover:text-primary-700">View Profile</a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-5 flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-neutral-500">
                                        <span class="font-medium leading-none text-white">AW</span>
                                    </span>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="flex items-center">
                                        <div class="text-sm font-medium text-neutral-900">Alex Williams</div>
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-neutral-100 text-neutral-800">
                                            User
                                        </span>
                                    </div>
                                    <div class="text-sm text-neutral-500">alex.williams@example.com</div>
                                </div>
                                <div class="text-sm text-neutral-500">
                                    <div>Registered 8 hours ago</div>
                                    <div class="text-right mt-1">
                                        <a href="#" class="text-primary-600 hover:text-primary-700">View Profile</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-neutral-50 px-5 py-3 text-right border-t border-neutral-200">
                            <a href="{{ route('admin.users.index') }}" class="text-sm text-primary-600 hover:text-primary-700">
                                View all users
                            </a>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- User Growth Chart -->
                        <div class="card">
                            <div class="p-5 border-b border-neutral-200">
                                <h3 class="text-lg font-medium text-neutral-900">User Registration Trends</h3>
                            </div>
                            <div class="p-5">
                                <!-- Chart debug message will appear here if there's an issue -->
                                <div id="userRegistrationChartError" class="hidden p-3 bg-red-50 text-red-700 text-sm rounded mb-3">
                                    Chart failed to load. Please check console for details.
                                </div>
                                <canvas id="userRegistrationChart" height="260"></canvas>
                            </div>
                        </div>
                        
                        <!-- User Roles Distribution -->
                        <div class="card">
                            <div class="p-5 border-b border-neutral-200">
                                <h3 class="text-lg font-medium text-neutral-900">User Roles Distribution</h3>
                            </div>
                            <div class="p-5">
                                <!-- Chart debug message will appear here if there's an issue -->
                                <div id="userRolesChartError" class="hidden p-3 bg-red-50 text-red-700 text-sm rounded mb-3">
                                    Chart failed to load. Please check console for details.
                                </div>
                                <canvas id="userRolesChart" height="260"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Other content tabs will be added here -->
            </main>
        </div>
    </div>

    <script>
        console.log('Script execution started');
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded - Charts initialization should start now');

            // Initialize debug UI
            const debugBanner = document.getElementById('chart-debug-banner');
            const debugDetails = document.getElementById('chart-debug-details');
            
            function logDebug(message) {
                console.log(message);
                if (debugDetails) {
                    debugBanner.classList.remove('hidden');
                    const timestamp = new Date().toLocaleTimeString();
                    debugDetails.innerHTML += `<div>[${timestamp}] ${message}</div>`;
                }
            }
            
            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                logDebug('ERROR: Chart.js is not loaded. Adding it again...');
                // Try to load Chart.js again
                const chartScript = document.createElement('script');
                chartScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
                chartScript.onload = function() {
                    logDebug('Chart.js loaded successfully, initializing charts...');
                    setTimeout(initializeAllCharts, 300);
                };
                chartScript.onerror = function() {
                    logDebug('Failed to load Chart.js dynamically');
                };
                document.head.appendChild(chartScript);
            } else {
                logDebug('Chart.js is loaded, version: ' + Chart.version);
            }
            
            // Parse dashboard stats safely
            let dashboardStats;
            try {
                dashboardStats = {
                    users: {
                        total: {{ $stats['users']['total'] ?? 0 }},
                        newToday: {{ $stats['users']['new_today'] ?? 0 }},
                        newThisWeek: {{ $stats['users']['new_this_week'] ?? 0 }},
                        banned: {{ $stats['users']['banned'] ?? 0 }}
                    },
                    content: {
                        totalPosts: {{ $stats['content']['total_posts'] ?? 0 }},
                        postsToday: {{ $stats['content']['posts_today'] ?? 0 }},
                        totalCourses: {{ $stats['content']['total_courses'] ?? 0 }},
                        totalLibraries: {{ $stats['content']['total_libraries'] ?? 0 }},
                        pendingLibraries: {{ $stats['content']['pending_libraries'] ?? 0 }}
                    },
                    revenue: {
                        total: {{ $stats['revenue']['total'] ?? 0 }},
                        thisMonth: {{ $stats['revenue']['this_month'] ?? 0 }}
                    }
                };
                logDebug('Dashboard stats loaded successfully');
            } catch (e) {
                logDebug('Error parsing dashboard stats: ' + e.message);
                // Provide fallback values if there was an error
                dashboardStats = {
                    users: { total: 0, newToday: 0, newThisWeek: 0, banned: 0 },
                    content: { totalPosts: 0, postsToday: 0, totalCourses: 0, totalLibraries: 0, pendingLibraries: 0 },
                    revenue: { total: 0, thisMonth: 0 }
                };
            }

            // Function to handle CSV export
            function exportToCSV(data, filename) {
                // Create CSV content
                const csvRows = [];
                
                // Add headers
                const headers = Object.keys(data);
                csvRows.push(headers.join(','));
                
                // Add values
                const values = headers.map(header => {
                    if (typeof data[header] === 'object') {
                        return JSON.stringify(data[header]);
                    }
                    return data[header];
                });
                csvRows.push(values.join(','));
                
                // Create blob and download
                const csvContent = csvRows.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            // Set up export functionality
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    logDebug('Export button clicked, generating CSV...');
                    exportToCSV(dashboardStats, 'dashboard-stats-' + new Date().toISOString().split('T')[0] + '.csv');
                });
            } else {
                logDebug('Export button not found on page');
            }

            // Function to initialize all charts
            function initializeAllCharts() {
                logDebug('Initializing all charts...');
                
                // Create and initialize charts
                initializeChart(
                    'userGrowthChart', 
                    'line', 
                    ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    [{
                        label: 'New Users',
                        data: [65, 78, 90, 105, 125, 138, 156, 170, 186, 199, 210, 225],
                        borderColor: '#5a78ee',
                        backgroundColor: 'rgba(90, 120, 238, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                );
                
                initializeChart(
                    'revenueChart', 
                    'bar', 
                    ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    [{
                        label: 'Revenue',
                        data: [1500, 1700, 1900, 2100, 2300, 2500, 2700, 2900, 3100, 3300, 3500, 3700],
                        backgroundColor: '#1a202c',
                        borderRadius: 4
                    }]
                );
                
                initializeChart(
                    'userRegistrationChart', 
                    'line', 
                    ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    [{
                        label: 'New Registrations',
                        data: [120, 135, 155, 180, 210, 235, 245, 260, 285, 295, 310, 325],
                        borderColor: '#5a78ee',
                        backgroundColor: 'rgba(90, 120, 238, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                );
                
                initializeChart(
                    'userRolesChart', 
                    'doughnut', 
                    ['Regular Users', 'Educators', 'Admins'],
                    [{
                        data: [2250, 540, 24],
                        backgroundColor: [
                            'rgba(90, 120, 238, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(107, 114, 128, 0.8)'
                        ],
                        borderWidth: 0
                    }],
                    {
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { usePointStyle: true, padding: 20 }
                            }
                        },
                        cutout: '65%'
                    }
                );
            }
            
            // Function to initialize a single chart
            function initializeChart(elementId, type, labels, datasets, additionalOptions = {}) {
                try {
                    const chartElement = document.getElementById(elementId);
                    if (!chartElement) {
                        logDebug(`Chart element '${elementId}' not found in DOM`);
                        // Show error message
                        showChartError(elementId);
                        return;
                    }
                    
                    logDebug(`Initializing ${type} chart for ${elementId}...`);
                    
                    // Setup default options
                    const defaultOptions = {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: type !== 'doughnut'
                            }
                        },
                        scales: type !== 'doughnut' ? {
                            y: {
                                beginAtZero: true,
                                grid: { drawBorder: false }
                            },
                            x: {
                                grid: { display: false, drawBorder: false }
                            }
                        } : undefined,
                        elements: type === 'line' ? {
                            point: {
                                radius: 0,
                                hoverRadius: 4
                            }
                        } : undefined
                    };
                    
                    // Merge options
                    const options = {...defaultOptions, ...additionalOptions};
                    
                    // Create chart
                    const ctx = chartElement.getContext('2d');
                    if (!ctx) {
                        logDebug(`Could not get 2D context for ${elementId}`);
                        showChartError(elementId);
                        return;
                    }
                    
                    // Create chart with unique ID to prevent conflicts
                    if (chartElement.chart) {
                        logDebug(`Destroying existing chart for ${elementId}`);
                        chartElement.chart.destroy();
                    }
                    
                    // Try to create chart with Chart.js
                    try {
                        chartElement.chart = new Chart(ctx, {
                            type: type,
                            data: {
                                labels: labels,
                                datasets: datasets
                            },
                            options: options
                        });
                        
                        // Hide any error message if chart created successfully
                        hideChartError(elementId);
                        logDebug(`Successfully created ${type} chart for ${elementId}`);
                    } catch (err) {
                        logDebug(`Error during Chart constructor for ${elementId}: ${err.message}`);
                        
                        // Try ApexCharts as fallback if Chart.js fails
                        try {
                            if (typeof ApexCharts !== 'undefined') {
                                logDebug(`Attempting to render ${elementId} with ApexCharts instead...`);
                                
                                // Create a div container for ApexCharts
                                const apexContainer = document.createElement('div');
                                apexContainer.id = `${elementId}-apex`;
                                chartElement.parentNode.insertBefore(apexContainer, chartElement);
                                chartElement.style.display = 'none';
                                
                                // Convert Chart.js data to ApexCharts format
                                const series = datasets.map(dataset => {
                                    return {
                                        name: dataset.label || 'Series',
                                        data: dataset.data
                                    };
                                });
                                
                                // Create ApexChart
                                let chartType = 'line';
                                if (type === 'bar') chartType = 'bar';
                                if (type === 'doughnut') chartType = 'donut';
                                
                                const apexOptions = {
                                    series: series,
                                    chart: {
                                        type: chartType,
                                        height: 260,
                                        toolbar: { show: false }
                                    },
                                    xaxis: {
                                        categories: labels
                                    },
                                    colors: datasets.map(d => d.borderColor || d.backgroundColor || '#5a78ee')
                                };
                                
                                const apexChart = new ApexCharts(document.getElementById(`${elementId}-apex`), apexOptions);
                                apexChart.render();
                                chartElement.apexChart = apexChart;
                                
                                hideChartError(elementId);
                                logDebug(`Successfully created fallback ApexChart for ${elementId}`);
                            } else {
                                throw new Error('ApexCharts not available');
                            }
                        } catch (apexErr) {
                            logDebug(`Fallback to ApexCharts also failed: ${apexErr.message}`);
                            showChartError(elementId);
                        }
                    }
                } catch (e) {
                    logDebug(`Error creating chart ${elementId}: ${e.message}`);
                    console.error('Chart initialization error:', e);
                    showChartError(elementId);
                }
            }
            
            // Helper functions to show/hide chart errors
            function showChartError(chartId) {
                const errorEl = document.getElementById(chartId + 'Error');
                if (errorEl) {
                    errorEl.classList.remove('hidden');
                }
            }
            
            function hideChartError(chartId) {
                const errorEl = document.getElementById(chartId + 'Error');
                if (errorEl) {
                    errorEl.classList.add('hidden');
                }
            }
            
            // Initialize charts after a short delay to ensure DOM is fully loaded
            setTimeout(initializeAllCharts, 300);
            
            // Set up debug UI functionality
            const retryButton = document.getElementById('retry-charts-btn');
            if (retryButton) {
                retryButton.addEventListener('click', function() {
                    logDebug('Manual chart rendering requested...');
                    initializeAllCharts();
                });
            }
            
            const toggleDebugButton = document.getElementById('toggle-debug-btn');
            if (toggleDebugButton) {
                toggleDebugButton.addEventListener('click', function() {
                    const details = document.getElementById('chart-debug-details');
                    if (details) {
                        details.classList.toggle('hidden');
                        this.textContent = details.classList.contains('hidden') 
                            ? 'Show Debug Details' 
                            : 'Hide Debug Details';
                    }
                });
            }
        });
    </script>
</body>
</html>