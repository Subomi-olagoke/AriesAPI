<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Aries Admin</title>
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
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-30 w-64 transition-all duration-300 transform bg-white border-r border-neutral-200" :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}">
            <div class="flex items-center justify-between h-16 px-4 border-b border-neutral-200">
                <div class="flex items-center">
                    <div class="text-xl font-semibold text-neutral-900">Aries Admin</div>
                </div>
            </div>
            <div class="flex flex-col flex-1 h-0 overflow-y-auto">
                <nav class="flex-1 px-2 py-4 space-y-1">
                    <a href="#" @click.prevent="currentTab = 'overview'" class="sidebar-item" :class="{'active': currentTab === 'overview'}">
                        <i class="fa-solid fa-gauge-high w-5 h-5 mr-3"></i>
                        <span>Overview</span>
                    </a>
                    <a href="#" @click.prevent="currentTab = 'users'" class="sidebar-item" :class="{'active': currentTab === 'users'}">
                        <i class="fa-solid fa-users w-5 h-5 mr-3"></i>
                        <span>Users</span>
                    </a>
                    <a href="#" @click.prevent="currentTab = 'content'" class="sidebar-item" :class="{'active': currentTab === 'content'}">
                        <i class="fa-solid fa-file-lines w-5 h-5 mr-3"></i>
                        <span>Content</span>
                    </a>
                    <a href="#" @click.prevent="currentTab = 'libraries'" class="sidebar-item" :class="{'active': currentTab === 'libraries'}">
                        <i class="fa-solid fa-book-open w-5 h-5 mr-3"></i>
                        <span>Libraries</span>
                    </a>
                    <a href="#" @click.prevent="currentTab = 'reports'" class="sidebar-item" :class="{'active': currentTab === 'reports'}">
                        <i class="fa-solid fa-flag w-5 h-5 mr-3"></i>
                        <span>Reports</span>
                    </a>
                    <a href="#" @click.prevent="currentTab = 'revenue'" class="sidebar-item" :class="{'active': currentTab === 'revenue'}">
                        <i class="fa-solid fa-chart-line w-5 h-5 mr-3"></i>
                        <span>Revenue</span>
                    </a>
                    <a href="#" @click.prevent="currentTab = 'verifications'" class="sidebar-item" :class="{'active': currentTab === 'verifications'}">
                        <i class="fa-solid fa-user-check w-5 h-5 mr-3"></i>
                        <span>Verifications</span>
                    </a>
                    <a href="#" @click.prevent="currentTab = 'settings'" class="sidebar-item" :class="{'active': currentTab === 'settings'}">
                        <i class="fa-solid fa-gear w-5 h-5 mr-3"></i>
                        <span>Settings</span>
                    </a>
                </nav>
                <div class="p-4 border-t border-neutral-200">
                    <div class="flex items-center">
                        <div @click="showDropdown = !showDropdown" class="relative flex items-center w-full cursor-pointer">
                            <div class="flex-shrink-0">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-neutral-500">
                                    <span class="text-xs font-medium leading-none text-white">AU</span>
                                </span>
                            </div>
                            <div class="ml-3 flex-1 overflow-hidden">
                                <div class="text-sm font-medium text-neutral-900 truncate">Admin User</div>
                                <div class="text-xs text-neutral-500 truncate">admin@aries.com</div>
                            </div>
                            <i class="fa-solid fa-chevron-down text-neutral-400 ml-1"></i>
                        </div>
                    </div>
                    <div x-show="showDropdown" x-cloak @click.away="showDropdown = false" class="absolute bottom-14 left-3 right-3 z-10 mt-1 bg-white rounded-md shadow-lg border border-neutral-200">
                        <div class="py-1">
                            <a href="#" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">Profile</a>
                            <a href="/admin/logout" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">Sign out</a>
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
                <!-- Overview Tab -->
                <div x-show="currentTab === 'overview'" x-cloak>
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold text-neutral-900">Dashboard</h1>
                        <div class="flex space-x-2">
                            <button class="btn btn-secondary">
                                <i class="fa-solid fa-calendar mr-2"></i> Last 30 days
                            </button>
                            <a href="{{ route('admin.export-stats') }}" class="btn btn-secondary">
                                <i class="fa-solid fa-file-export mr-2"></i> Export
                            </a>
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
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900" data-stat="users.total">2,814</div>
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
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900" data-stat="revenue.total" data-format="currency">$18,230</div>
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
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900" data-stat="content.postsToday">142</div>
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
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900" data-stat="content.pendingLibraries">23</div>
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
                                <canvas id="userGrowthChart" height="260"></canvas>
                            </div>
                        </div>

                        <!-- Revenue Chart -->
                        <div class="card">
                            <div class="p-5 border-b border-neutral-200">
                                <h3 class="text-lg font-medium text-neutral-900">Revenue Trends</h3>
                            </div>
                            <div class="p-5">
                                <canvas id="revenueChart" height="260"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card mb-6">
                        <div class="p-5 border-b border-neutral-200 flex justify-between items-center">
                            <h3 class="text-lg font-medium text-neutral-900">Recent Activity</h3>
                            <button class="text-sm text-primary-600 hover:text-primary-700">View all</button>
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
                                <tr>
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0 rounded bg-primary-100 flex items-center justify-center">
                                                <i class="fa-solid fa-book text-primary-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-neutral-900">Science & Technology</div>
                                                <div class="text-xs text-neutral-500 truncate max-w-xs">A collection of the latest science and technology articles</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="table-cell">Auto</td>
                                    <td class="table-cell">May 2, 2025</td>
                                    <td class="table-cell">24</td>
                                    <td class="table-cell">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    </td>
                                    <td class="table-cell text-right">
                                        <div class="flex justify-end space-x-2">
                                            <button class="text-primary-600 hover:text-primary-900">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="text-green-600 hover:text-green-900">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button class="text-red-600 hover:text-red-900">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0 rounded bg-primary-100 flex items-center justify-center">
                                                <i class="fa-solid fa-book text-primary-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-neutral-900">Business & Economics</div>
                                                <div class="text-xs text-neutral-500 truncate max-w-xs">Business strategies and economic trends</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="table-cell">Auto</td>
                                    <td class="table-cell">May 1, 2025</td>
                                    <td class="table-cell">18</td>
                                    <td class="table-cell">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    </td>
                                    <td class="table-cell text-right">
                                        <div class="flex justify-end space-x-2">
                                            <button class="text-primary-600 hover:text-primary-900">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="text-green-600 hover:text-green-900">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button class="text-red-600 hover:text-red-900">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
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
                                        Showing <span class="font-medium">1</span> to <span class="font-medium">3</span> of <span class="font-medium">12</span> results
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
                                        <div class="mt-1 text-3xl font-semibold text-neutral-900" data-stat="users.total">2,814</div>
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
                                <canvas id="userRegistrationChart" height="260"></canvas>
                            </div>
                        </div>
                        
                        <!-- User Roles Distribution -->
                        <div class="card">
                            <div class="p-5 border-b border-neutral-200">
                                <h3 class="text-lg font-medium text-neutral-900">User Roles Distribution</h3>
                            </div>
                            <div class="p-5">
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
        document.addEventListener('DOMContentLoaded', function() {
            // Add the dashboard stats from the server
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
            } catch (e) {
                console.error('Error parsing dashboard stats:', e);
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

            // Update dashboard stats with real data
            document.querySelectorAll('[data-stat]').forEach(el => {
                const path = el.getAttribute('data-stat').split('.');
                let value = dashboardStats;
                
                for (const key of path) {
                    if (value && value[key] !== undefined) {
                        value = value[key];
                    } else {
                        value = 0;
                        break;
                    }
                }
                
                if (el.getAttribute('data-format') === 'currency') {
                    el.textContent = '$' + value.toLocaleString();
                } else {
                    el.textContent = value.toLocaleString();
                }
            });
            
            // Set up export functionality
            document.getElementById('exportBtn').addEventListener('click', function() {
                exportToCSV(dashboardStats, 'dashboard-stats-' + new Date().toISOString().split('T')[0] + '.csv');
            });
            $' + value.toLocaleString();
                } else {
                    el.textContent = value.toLocaleString();
                }
            });
            
            // Set up export functionality
            document.getElementById('exportBtn').addEventListener('click', function() {
                exportToCSV(dashboardStats, 'dashboard-stats-' + new Date().toISOString().split('T')[0] + '.csv');
            });
            // User Growth Chart
            const userCtx = document.getElementById('userGrowthChart').getContext('2d');
            new Chart(userCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'New Users',
                        data: [65, 78, 90, 105, 125, 138, 156, 170, 186, 199, 210, 225],
                        borderColor: '#5a78ee',
                        backgroundColor: 'rgba(90, 120, 238, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            }
                        }
                    },
                    elements: {
                        point: {
                            radius: 0,
                            hoverRadius: 4
                        }
                    }
                }
            });

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Revenue',
                        data: [1500, 1700, 1900, 2100, 2300, 2500, 2700, 2900, 3100, 3300, 3500, 3700],
                        backgroundColor: 'rgba(90, 120, 238, 0.8)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            }
                        }
                    }
                }
            });
            
            // User Registration Chart
            const userRegCtx = document.getElementById('userRegistrationChart');
            if (userRegCtx) {
                new Chart(userRegCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [{
                            label: 'New Registrations',
                            data: [120, 135, 155, 180, 210, 235, 245, 260, 285, 295, 310, 325],
                            borderColor: '#5a78ee',
                            backgroundColor: 'rgba(90, 120, 238, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    drawBorder: false
                                }
                            },
                            x: {
                                grid: {
                                    display: false,
                                    drawBorder: false
                                }
                            }
                        },
                        elements: {
                            point: {
                                radius: 0,
                                hoverRadius: 4
                            }
                        }
                    }
                });
            }
            
            // User Roles Distribution Chart
            const userRolesCtx = document.getElementById('userRolesChart');
            if (userRolesCtx) {
                new Chart(userRolesCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Regular Users', 'Educators', 'Admins'],
                        datasets: [{
                            data: [2250, 540, 24],
                            backgroundColor: [
                                'rgba(90, 120, 238, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(107, 114, 128, 0.8)'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20
                                }
                            }
                        },
                        cutout: '65%'
                    }
                });
            }
        });
    </script>
</body>
</html>