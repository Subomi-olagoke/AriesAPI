@extends('admin.dashboard-layout')

@section('title', 'Simplified Dashboard')


@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">Simplified Dashboard</h1>
    <a href="{{ route('admin.export-stats') }}" class="btn btn-secondary">
        <i class="fa-solid fa-file-export mr-2"></i> Export Data
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
    <!-- User Stats Card -->
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
                                {{ number_format($stats['users']['total'] ?? 0) }}
                            </div>
                            <div class="flex items-center text-sm text-green-600">
                                <i class="fas fa-arrow-up mr-1"></i>
                                {{ $stats['users']['new_this_week'] ?? 0 }} new this week
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Stats Card -->
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
                                ${{ number_format($stats['revenue']['total'] ?? 0) }}
                            </div>
                            <div class="flex items-center text-sm text-green-600">
                                <i class="fas fa-arrow-up mr-1"></i>
                                ${{ number_format($stats['revenue']['this_month'] ?? 0) }} this month
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Posts Stats Card -->
    <div class="bg-white overflow-hidden shadow-sm rounded-lg transition-all duration-300 hover:shadow-md">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                    <i class="fas fa-file-lines text-purple-700 text-xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-neutral-500 truncate">
                            Content Posts
                        </dt>
                        <dd>
                            <div class="text-2xl font-semibold text-primary-900">
                                {{ number_format($stats['content']['total_posts'] ?? 0) }}
                            </div>
                            <div class="flex items-center text-sm text-green-600">
                                <i class="fas fa-arrow-up mr-1"></i>
                                {{ $stats['content']['posts_today'] ?? 0 }} new today
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Libraries Stats Card -->
    <div class="bg-white overflow-hidden shadow-sm rounded-lg transition-all duration-300 hover:shadow-md">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                    <i class="fas fa-book text-yellow-700 text-xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-neutral-500 truncate">
                            Libraries
                        </dt>
                        <dd>
                            <div class="text-2xl font-semibold text-primary-900">
                                {{ number_format($stats['content']['total_libraries'] ?? 0) }}
                            </div>
                            <div class="flex items-center text-sm text-yellow-600">
                                {{ $stats['content']['pending_libraries'] ?? 0 }} pending approval
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Apple App Store Status -->
<div class="bg-white overflow-hidden shadow-sm rounded-lg transition-all duration-300 hover:shadow-md mb-6">
    <div class="px-4 py-5 sm:p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                    <i class="fab fa-apple text-blue-700 text-xl"></i>
                </div>
                <div class="ml-5">
                    <h3 class="text-lg font-medium text-neutral-900">Apple App Store</h3>
                    <div class="mt-1 flex items-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">
                            <i class="fas fa-clock mr-1"></i> Pending Approval
                        </span>
                        <span class="text-sm text-neutral-500">Version 1.2.0 submitted on May 24, 2025</span>
                    </div>
                </div>
            </div>
            <button id="releaseButton" class="btn btn-primary">
                <i class="fas fa-rocket mr-2"></i> Release Update
            </button>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- User Growth Chart -->
    <div class="card">
        <div class="p-5 border-b border-neutral-200">
            <h3 class="text-lg font-medium text-neutral-900">User Growth</h3>
        </div>
        <div class="p-5" style="height: 300px">
            <canvas id="userGrowthChart"></canvas>
        </div>
    </div>

    <!-- Revenue Trends Chart -->
    <div class="card">
        <div class="p-5 border-b border-neutral-200">
            <h3 class="text-lg font-medium text-neutral-900">Revenue Trends</h3>
        </div>
        <div class="p-5" style="height: 300px">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Users -->
<div class="card overflow-hidden">
    <div class="p-5 border-b border-neutral-200">
        <h3 class="text-lg font-medium text-neutral-900">Recent Users</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
                <tr>
                    <th scope="col" class="table-header">User</th>
                    <th scope="col" class="table-header">Email</th>
                    <th scope="col" class="table-header">Registration Date</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
                @foreach($recentUsers as $user)
                <tr>
                    <td class="table-cell">
                        <div class="flex items-center">
                            <div class="h-10 w-10 flex-shrink-0">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-neutral-500">
                                    <span class="text-xs font-medium leading-none text-white">{{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}{{ strtoupper(substr($user->last_name ?? 'U', 0, 1)) }}</span>
                                </span>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-neutral-900">{{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }}</div>
                                <div class="text-sm text-neutral-500">{{ '@' . ($user->username ?? 'unknown') }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="table-cell">{{ $user->email ?? 'No email' }}</td>
                    <td class="table-cell">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'Unknown' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // User Growth Chart
        const userGrowthData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'New Users',
                data: [65, 78, 90, 105, 125, 138, 156, 170, 186, 199, 210, 225],
                borderColor: '#3f5ae3',
                backgroundColor: 'rgba(90, 120, 238, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        };
        
        const userCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userCtx, {
            type: 'line',
            data: userGrowthData,
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
        
        // Revenue Chart
        const revenueData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Revenue',
                data: [1500, 1700, 1900, 2100, 2300, 2500, 2700, 2900, 3100, 3300, 3500, 3700],
                backgroundColor: '#10b981',
                borderRadius: 4
            }]
        };
        
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: revenueData,
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