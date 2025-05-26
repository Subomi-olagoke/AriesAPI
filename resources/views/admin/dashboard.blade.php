@extends('admin.dashboard-layout')

@section('styles')
<style>
    .chart-loading {
        position: relative;
    }
    
    .chart-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(255, 255, 255, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10;
    }
    
    .chart-loading-overlay i {
        font-size: 2rem;
        color: #3f5ae3;
    }
</style>
@endsection

@section('content')
<div class="bg-neutral-50 min-h-screen">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between mb-8">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold text-primary-800 sm:text-3xl leading-tight">
                    Dashboard Overview
                </h1>
                <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                    <div class="mt-2 flex items-center text-sm text-neutral-500">
                        <i class="fas fa-users flex-shrink-0 mr-1.5 h-5 w-5 text-neutral-400"></i>
                        <span>{{ $stats['users']['total'] }} total users</span>
                    </div>
                    <div class="mt-2 flex items-center text-sm text-neutral-500">
                        <i class="fas fa-file-lines flex-shrink-0 mr-1.5 h-5 w-5 text-neutral-400"></i>
                        <span>{{ $stats['content']['total_posts'] ?? 0 }} total posts</span>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
                <div class="relative" x-data="{ isOpen: false, selectedPeriod: 'last30' }" id="periodSelector">
                    <button @click="isOpen = !isOpen" class="inline-flex items-center px-4 py-2 border border-primary-300 rounded-md shadow-sm text-sm font-medium text-primary-700 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-calendar -ml-1 mr-2 h-5 w-5 text-primary-500"></i>
                        <span x-text="selectedPeriod === 'last30' ? 'Last 30 days' : 
                                  selectedPeriod === 'last90' ? 'Last 90 days' : 
                                  selectedPeriod === 'thisYear' ? 'This Year' : 
                                  selectedPeriod === 'allTime' ? 'All Time' : 'Last 30 days'"></span>
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    <div x-show="isOpen" @click.away="isOpen = false" class="absolute right-0 mt-1 bg-white shadow-lg rounded-md border border-neutral-200 z-10">
                        <div class="py-1">
                            <a href="#" @click.prevent="selectedPeriod = 'last30'; isOpen = false; window.updateChartData('last30')" class="block px-4 py-2 text-sm text-secondary-700 hover:bg-secondary-50">Last 30 days</a>
                            <a href="#" @click.prevent="selectedPeriod = 'last90'; isOpen = false; window.updateChartData('last90')" class="block px-4 py-2 text-sm text-secondary-700 hover:bg-secondary-50">Last 90 days</a>
                            <a href="#" @click.prevent="selectedPeriod = 'thisYear'; isOpen = false; window.updateChartData('thisYear')" class="block px-4 py-2 text-sm text-secondary-700 hover:bg-secondary-50">This Year</a>
                            <a href="#" @click.prevent="selectedPeriod = 'allTime'; isOpen = false; window.updateChartData('allTime')" class="block px-4 py-2 text-sm text-secondary-700 hover:bg-secondary-50">All Time</a>
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.export-stats') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-700 hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" id="exportBtn">
                    <i class="fas fa-file-export -ml-1 mr-2 h-5 w-5"></i>
                    Export Data
                </a>
            </div>
        </div>

        <!-- Debug Banner (will only show if there are chart errors) -->
        <div id="chart-debug-banner" class="mb-6 bg-orange-50 border border-orange-200 rounded-lg p-4 hidden">
            <div class="flex items-start">
                <div class="flex-shrink-0 mt-0.5">
                    <i class="fas fa-triangle-exclamation text-orange-500"></i>
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
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            <!-- Total Users -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg transition-all duration-300 hover:shadow-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-primary-100 rounded-md p-3">
                            <i class="fas fa-users text-primary-700 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">
                                    Total Users
                                </dt>
                                <dd>
                                    <div class="text-2xl font-semibold text-primary-900">
                                        {{ $stats['users']['total'] }}
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6">
                    <div class="text-sm">
                        <span class="font-medium text-green-600 flex items-center">
                            <i class="fas fa-arrow-up mr-1 text-xs"></i> {{ $stats['users']['new_this_week'] ?? 0 }}
                        </span>
                        <span class="text-neutral-500">new this week</span>
                    </div>
                </div>
            </div>

            <!-- Revenue -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg transition-all duration-300 hover:shadow-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                            <i class="fas fa-dollar-sign text-green-700 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">
                                    Total Revenue
                                </dt>
                                <dd>
                                    <div class="text-2xl font-semibold text-primary-900">
                                        ${{ number_format($stats['revenue']['total']) }}
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6">
                    <div class="text-sm">
                        <span class="font-medium text-green-600 flex items-center">
                            <i class="fas fa-arrow-up mr-1 text-xs"></i> ${{ number_format($stats['revenue']['this_month'] ?? 0) }}
                        </span>
                        <span class="text-neutral-500">this month</span>
                    </div>
                </div>
            </div>

            <!-- New Content -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg transition-all duration-300 hover:shadow-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                            <i class="fas fa-file-lines text-blue-700 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">
                                    New Content
                                </dt>
                                <dd>
                                    <div class="text-2xl font-semibold text-primary-900">
                                        {{ $stats['content']['posts_today'] }}
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6">
                    <div class="text-sm">
                        <span class="font-medium text-green-600 flex items-center">
                            <i class="fas fa-arrow-up mr-1 text-xs"></i> {{ $stats['content']['total_posts'] ?? 0 }}
                        </span>
                        <span class="text-neutral-500">total posts</span>
                    </div>
                </div>
            </div>

            <!-- Pending Verifications -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg transition-all duration-300 hover:shadow-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                            <i class="fas fa-user-check text-yellow-700 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">
                                    Pending Verifications
                                </dt>
                                <dd>
                                    <div class="text-2xl font-semibold text-primary-900">
                                        {{ $stats['content']['pending_libraries'] }}
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6">
                    <div class="text-sm">
                        <span class="font-medium text-yellow-600 flex items-center">
                            <i class="fas fa-clock mr-1 text-xs"></i> {{ $stats['content']['pending_libraries'] ?? 0 }}
                        </span>
                        <span class="text-neutral-500">awaiting review</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Apple App Store Status -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg transition-all duration-300 hover:shadow-md mb-8">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                            <i class="fab fa-apple text-blue-700 text-xl"></i>
                        </div>
                        <div class="ml-5">
                            <h3 class="text-lg font-medium text-neutral-900">Apple App Store</h3>
                            <div class="mt-1 flex items-center">
                                @if($appStore['status'] === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">
                                        <i class="fas fa-clock mr-1"></i> Pending Approval
                                    </span>
                                @elseif($appStore['status'] === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">
                                        <i class="fas fa-check mr-1"></i> Approved
                                    </span>
                                @elseif($appStore['status'] === 'rejected')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">
                                        <i class="fas fa-times mr-1"></i> Rejected
                                    </span>
                                @endif
                                <span class="text-sm text-neutral-500">Version {{ $appStore['version'] }} submitted on {{ $appStore['submitted_at'] }}</span>
                            </div>
                            @if($appStore['notes'])
                                <div class="mt-1 text-sm text-neutral-500">{{ $appStore['notes'] }}</div>
                            @endif
                        </div>
                    </div>
                    <button id="releaseButton" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-700 hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <i class="fas fa-rocket mr-2"></i> Release Update
                    </button>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- User Growth Chart -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="bg-white px-4 py-5 border-b border-neutral-200 sm:px-6">
                    <div class="-ml-4 -mt-2 flex items-center justify-between flex-wrap sm:flex-nowrap">
                        <div class="ml-4 mt-2">
                            <h3 class="text-lg leading-6 font-medium text-primary-800">
                                <i class="fas fa-chart-line mr-2 text-primary-600"></i>
                                User Growth
                            </h3>
                        </div>
                    </div>
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
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="bg-white px-4 py-5 border-b border-neutral-200 sm:px-6">
                    <div class="-ml-4 -mt-2 flex items-center justify-between flex-wrap sm:flex-nowrap">
                        <div class="ml-4 mt-2">
                            <h3 class="text-lg leading-6 font-medium text-primary-800">
                                <i class="fas fa-dollar-sign mr-2 text-primary-600"></i>
                                Revenue Trends
                            </h3>
                        </div>
                    </div>
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
        <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-8">
            <div class="bg-white px-4 py-5 border-b border-neutral-200 sm:px-6">
                <div class="-ml-4 -mt-2 flex items-center justify-between flex-wrap sm:flex-nowrap">
                    <div class="ml-4 mt-2">
                        <h3 class="text-lg leading-6 font-medium text-primary-800">
                            <i class="fas fa-history mr-2 text-primary-600"></i>
                            Recent Activity
                        </h3>
                    </div>
                    <div class="ml-4 mt-2 flex-shrink-0">
                        <button class="text-sm text-primary-600 hover:text-primary-900">View all</button>
                    </div>
                </div>
            </div>
            <div class="divide-y divide-neutral-200">
                @if(count($recentUsers) > 0)
                    @foreach($recentUsers->take(3) as $user)
                    <div class="p-5 flex items-center">
                        <div class="flex-shrink-0">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary-100">
                                <span class="text-xs font-medium leading-none text-primary-700">
                                    {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}{{ strtoupper(substr($user->last_name ?? 'U', 0, 1)) }}
                                </span>
                            </span>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="text-sm font-medium text-neutral-900">New user registered</div>
                            <div class="text-sm text-neutral-500">{{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }} ({{ $user->email ?? 'No email' }})</div>
                        </div>
                        <div class="text-sm text-neutral-500">{{ $user->created_at ? $user->created_at->diffForHumans() : 'Recently' }}</div>
                    </div>
                    @endforeach
                @else
                    <div class="py-8 text-center">
                        <i class="fas fa-history text-neutral-400 text-4xl mb-3"></i>
                        <h3 class="mt-2 text-sm font-medium text-neutral-900">No recent activity</h3>
                        <p class="mt-1 text-sm text-neutral-500">
                            New activities will appear here as they happen.
                        </p>
                    </div>
                @endif
            </div>
            <div class="bg-neutral-50 px-4 py-3 text-right border-t border-neutral-200">
                <a href="{{ route('admin.users.index') }}" class="text-sm text-primary-600 hover:text-primary-900">
                    View all activity
                </a>
            </div>
        </div>

        <!-- Libraries Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Pending Libraries -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="bg-white px-4 py-5 border-b border-neutral-200 sm:px-6">
                    <div class="-ml-4 -mt-2 flex items-center justify-between flex-wrap sm:flex-nowrap">
                        <div class="ml-4 mt-2">
                            <h3 class="text-lg leading-6 font-medium text-primary-800">
                                <i class="fas fa-book-open mr-2 text-primary-600"></i>
                                Pending Libraries
                            </h3>
                        </div>
                        <div class="ml-4 mt-2 flex-shrink-0">
                            <a href="{{ route('admin.libraries.index') }}" class="text-sm text-primary-600 hover:text-primary-900">
                                View all
                            </a>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    @if(count($pendingLibraries) > 0)
                        <table class="min-w-full divide-y divide-neutral-200">
                            <thead class="bg-neutral-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                        Created
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-neutral-200">
                                @foreach($pendingLibraries as $library)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-primary-100 rounded-md flex items-center justify-center">
                                                <i class="fas fa-book text-primary-600"></i>
                                            </div>
                                            <div class="ml-3">
                                                <div class="font-medium">{{ $library->title ?? 'Untitled Library' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                        {{ $library->created_at ? $library->created_at->format('M d, Y') : 'Unknown date' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            {{ ucfirst($library->approval_status ?? 'Pending') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <div class="flex justify-end space-x-2">
                                            <a href="{{ route('admin.libraries.view', [$library->id]) }}" class="text-primary-600 hover:text-primary-900">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form action="{{ route('admin.libraries.approve', [$library->id]) }}" method="POST" style="display:inline">
                                                @csrf
                                                <button type="submit" class="text-green-600 hover:text-green-900" title="Approve Library">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.libraries.reject', [$library->id]) }}" method="POST" style="display:inline">
                                                @csrf
                                                <input type="hidden" name="rejection_reason" value="Content does not meet guidelines">
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Reject Library">
                                                    <i class="fas fa-xmark"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="py-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-neutral-900">No pending libraries</h3>
                            <p class="mt-1 text-sm text-neutral-500">
                                There are no libraries waiting for approval.
                            </p>
                        </div>
                    @endif
                </div>
                @if(count($pendingLibraries) > 0)
                <div class="bg-neutral-50 px-4 py-3 text-right border-t border-neutral-200">
                    <a href="{{ route('admin.libraries.index') }}" class="text-sm text-primary-600 hover:text-primary-900">
                        View all pending libraries
                    </a>
                </div>
                @endif
            </div>

            <!-- Recent Users -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="bg-white px-4 py-5 border-b border-neutral-200 sm:px-6">
                    <div class="-ml-4 -mt-2 flex items-center justify-between flex-wrap sm:flex-nowrap">
                        <div class="ml-4 mt-2">
                            <h3 class="text-lg leading-6 font-medium text-primary-800">
                                <i class="fas fa-users mr-2 text-primary-600"></i>
                                Recently Registered Users
                            </h3>
                        </div>
                        <div class="ml-4 mt-2 flex-shrink-0">
                            <a href="{{ route('admin.users.index') }}" class="text-sm text-primary-600 hover:text-primary-900">
                                View all
                            </a>
                        </div>
                    </div>
                </div>
                <div class="divide-y divide-neutral-200">
                    @if(count($recentUsers) > 0)
                        @foreach($recentUsers as $user)
                        <div class="p-4 flex items-center">
                            <div class="flex-shrink-0">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-primary-100">
                                    <span class="font-medium text-primary-700">
                                        {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}{{ strtoupper(substr($user->last_name ?? 'U', 0, 1)) }}
                                    </span>
                                </span>
                            </div>
                            <div class="ml-3 flex-1">
                                <div class="flex items-center">
                                    <div class="text-sm font-medium text-neutral-900">{{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }}</div>
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $user->isAdmin ? 'bg-red-100 text-red-800' : 'bg-neutral-100 text-neutral-800' }}">
                                        {{ $user->isAdmin ? 'Admin' : 'User' }}
                                    </span>
                                </div>
                                <div class="text-xs text-neutral-500">{{ $user->email ?? 'No email' }}</div>
                            </div>
                            <div class="text-xs text-neutral-500">
                                <div>{{ $user->created_at ? $user->created_at->diffForHumans() : 'Recently' }}</div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="py-8 text-center">
                            <i class="fas fa-users text-neutral-400 text-4xl mb-3"></i>
                            <h3 class="mt-2 text-sm font-medium text-neutral-900">No users found</h3>
                            <p class="mt-1 text-sm text-neutral-500">
                                New users will appear here when they register.
                            </p>
                        </div>
                    @endif
                </div>
                <div class="bg-neutral-50 px-4 py-3 text-right border-t border-neutral-200">
                    <a href="{{ route('admin.users.index') }}" class="text-sm text-primary-600 hover:text-primary-900">
                        View all users
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
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
                setTimeout(fetchChartDataAndInitialize, 300);
            };
            chartScript.onerror = function() {
                logDebug('Failed to load Chart.js dynamically');
            };
            document.head.appendChild(chartScript);
        } else {
            logDebug('Chart.js is loaded, version: ' + Chart.version);
            // Fetch chart data
            fetchChartDataAndInitialize();
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
        
        // Function to fetch chart data from API
        function fetchChartDataAndInitialize() {
            const period = document.querySelector('[x-text]')?.getAttribute('x-text')?.match(/selectedPeriod === '([^']+)'/) 
                ? document.querySelector('[x-text]').getAttribute('x-text').match(/selectedPeriod === '([^']+)'/)[1] 
                : 'last30';
                
            logDebug(`Fetching chart data for period: ${period}`);
            
            fetch('{{ $statsApiUrl }}?period=' + period)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    logDebug('Successfully fetched chart data from API');
                    initializeChartsWithData(data);
                })
                .catch(error => {
                    logDebug('Error fetching chart data: ' + error.message);
                    // Fall back to dummy data if API fails
                    initializeAllCharts();
                });
        }
        
        // Initialize charts with API data
        function initializeChartsWithData(data) {
            logDebug('Initializing charts with API data...');
            
            // User Growth Chart
            initializeChart(
                'userGrowthChart', 
                'line', 
                data.userGrowthData.labels,
                [{
                    label: 'New Users',
                    data: data.userGrowthData.data,
                    borderColor: '#5a78ee',
                    backgroundColor: 'rgba(90, 120, 238, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            );
            
            // Revenue Chart
            initializeChart(
                'revenueChart', 
                'bar', 
                data.revenueData.labels,
                [{
                    label: 'Revenue',
                    data: data.revenueData.data,
                    backgroundColor: '#5a78ee',
                    borderRadius: 4
                }]
            );
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

        // Function to initialize all charts with fallback data when API is unavailable
        function initializeAllCharts() {
            logDebug('Initializing all charts with fallback data...');
            
            // Get last 12 months for labels
            const months = [];
            const currentDate = new Date();
            for (let i = 11; i >= 0; i--) {
                const date = new Date(currentDate);
                date.setMonth(currentDate.getMonth() - i);
                months.push(date.toLocaleString('default', { month: 'short' }));
            }
            
            // Create and initialize charts with generated data based on actual stats
            const userTotal = dashboardStats.users.total;
            const userGrowthData = months.map((_, index) => {
                // Generate a somewhat realistic growth curve based on total users
                return Math.floor(userTotal * (0.3 + (index / 12) * 0.7) / 12);
            });
            
            initializeChart(
                'userGrowthChart', 
                'line', 
                months,
                [{
                    label: 'New Users',
                    data: userGrowthData,
                    borderColor: '#5a78ee',
                    backgroundColor: 'rgba(90, 120, 238, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            );
            
            const revenueTotal = dashboardStats.revenue.total;
            const revenueData = months.map((_, index) => {
                // Generate a somewhat realistic revenue curve based on total revenue
                return Math.floor(revenueTotal * (0.4 + (index / 12) * 0.6) / 12);
            });
            
            initializeChart(
                'revenueChart', 
                'bar', 
                months,
                [{
                    label: 'Revenue',
                    data: revenueData,
                    backgroundColor: '#5a78ee',
                    borderRadius: 4
                }]
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
                            display: type !== 'doughnut',
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17, 24, 39, 0.9)',
                            padding: 12,
                            titleFont: {
                                size: 13
                            },
                            bodyFont: {
                                size: 12
                            },
                            cornerRadius: 6
                        }
                    },
                    scales: type !== 'doughnut' ? {
                        y: {
                            beginAtZero: true,
                            grid: { 
                                drawBorder: false,
                                color: 'rgba(229, 231, 235, 0.5)'
                            },
                            ticks: {
                                padding: 10
                            }
                        },
                        x: {
                            grid: { 
                                display: false, 
                                drawBorder: false 
                            },
                            ticks: {
                                padding: 10
                            }
                        }
                    } : undefined,
                    elements: type === 'line' ? {
                        point: {
                            radius: 2,
                            hoverRadius: 6,
                            borderWidth: 2,
                            backgroundColor: '#fff'
                        },
                        line: {
                            tension: 0.3
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
                                    toolbar: { show: false },
                                    fontFamily: 'Inter, sans-serif'
                                },
                                xaxis: {
                                    categories: labels
                                },
                                colors: datasets.map(d => d.borderColor || d.backgroundColor || '#5a78ee'),
                                theme: {
                                    mode: 'light'
                                },
                                tooltip: {
                                    theme: 'dark'
                                }
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
        
        // Set up the global updateChartData function
        window.updateChartData = function(period) {
            logDebug(`Chart period changed to: ${period}`);
            
            // Show loading state on charts
            const userChartContainer = document.getElementById('userGrowthChart').closest('.card');
            const revenueChartContainer = document.getElementById('revenueChart').closest('.card');
            
            if (userChartContainer) {
                userChartContainer.classList.add('chart-loading');
                userChartContainer.insertAdjacentHTML('beforeend', '<div class="chart-loading-overlay"><i class="fas fa-spinner fa-spin"></i></div>');
            }
            
            if (revenueChartContainer) {
                revenueChartContainer.classList.add('chart-loading');
                revenueChartContainer.insertAdjacentHTML('beforeend', '<div class="chart-loading-overlay"><i class="fas fa-spinner fa-spin"></i></div>');
            }
            
            // Fetch new data
            fetch('{{ $statsApiUrl }}?period=' + period)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    logDebug('Successfully fetched chart data from API');
                    
                    // Remove loading overlays
                    document.querySelectorAll('.chart-loading-overlay').forEach(el => el.remove());
                    document.querySelectorAll('.card').forEach(el => el.classList.remove('chart-loading'));
                    
                    // Update charts with new data
                    initializeChartsWithData(data);
                })
                .catch(error => {
                    logDebug('Error fetching chart data: ' + error.message);
                    
                    // Remove loading overlays
                    document.querySelectorAll('.chart-loading-overlay').forEach(el => el.remove());
                    document.querySelectorAll('.card').forEach(el => el.classList.remove('chart-loading'));
                    
                    // Fall back to dummy data if API fails
                    initializeAllCharts();
                });
        };
        
        // Apple App Store Release Button
        const releaseButton = document.getElementById('releaseButton');
        if (releaseButton) {
            releaseButton.addEventListener('click', function() {
                // Store original button content
                const originalContent = this.innerHTML;
                
                // Show spinner
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                this.disabled = true;
                
                // Simulate processing (remove in production and replace with actual API call)
                setTimeout(() => {
                    // Reset button after 3 seconds (for demo purposes)
                    this.innerHTML = originalContent;
                    this.disabled = false;
                    
                    // You would typically make an API call here
                    // fetch('/api/admin/release-app-update', {
                    //     method: 'POST',
                    //     headers: {
                    //         'Content-Type': 'application/json',
                    //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    //     }
                    // })
                    // .then(response => response.json())
                    // .then(data => {
                    //     // Handle response
                    //     this.innerHTML = originalContent;
                    //     this.disabled = false;
                    // })
                    // .catch(error => {
                    //     console.error('Error:', error);
                    //     this.innerHTML = originalContent;
                    //     this.disabled = false;
                    // });
                    
                }, 3000);
            });
        }
    });
</script>
@endsection