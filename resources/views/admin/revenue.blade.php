@extends('admin.dashboard-layout')

@section('title', 'Revenue Dashboard')

@section('head')
<style>
    .revenue-card {
        @apply p-4 rounded-lg shadow-sm flex flex-col;
    }
    .revenue-card-title {
        @apply text-neutral-500 text-sm font-medium mb-1;
    }
    .revenue-card-value {
        @apply text-2xl font-bold;
    }
    .revenue-card-subtitle {
        @apply text-neutral-500 text-xs mt-1;
    }
</style>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-neutral-900">Revenue Dashboard</h1>
        <div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Overview
            </a>
        </div>
    </div>

    <!-- Revenue Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="revenue-card bg-green-50 border border-green-100">
            <span class="revenue-card-title">Total Revenue</span>
            <span class="revenue-card-value text-green-700">₦{{ number_format($stats['total_revenue']) }}</span>
            <span class="revenue-card-subtitle">Lifetime revenue from all sources</span>
        </div>
        
        <div class="revenue-card bg-blue-50 border border-blue-100">
            <span class="revenue-card-title">Revenue This Month</span>
            <span class="revenue-card-value text-blue-700">₦{{ number_format($stats['revenue_this_month']) }}</span>
            <span class="revenue-card-subtitle">Month to date: {{ now()->format('F Y') }}</span>
        </div>
        
        <div class="revenue-card bg-purple-50 border border-purple-100">
            <span class="revenue-card-title">Total Transactions</span>
            <span class="revenue-card-value text-purple-700">{{ number_format($stats['total_transactions']) }}</span>
            <span class="revenue-card-subtitle">Successful payments processed</span>
        </div>
        
        <div class="revenue-card bg-yellow-50 border border-yellow-100">
            <span class="revenue-card-title">Average Transaction</span>
            <span class="revenue-card-value text-yellow-700">₦{{ number_format($stats['average_transaction']) }}</span>
            <span class="revenue-card-subtitle">Average payment amount</span>
        </div>
    </div>

    <!-- Revenue by Type -->
    <div class="card p-6">
        <h2 class="text-lg font-semibold mb-4">Revenue by Type</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-sm text-neutral-500 mb-1">Course Enrollments</div>
                <div class="text-xl font-bold">₦{{ number_format($revenue_by_type['course']) }}</div>
                <div class="text-xs text-neutral-500 mt-1">{{ $stats['total_revenue'] > 0 ? round(($revenue_by_type['course'] / $stats['total_revenue']) * 100) : 0 }}% of total revenue</div>
            </div>
            
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-sm text-neutral-500 mb-1">Subscriptions</div>
                <div class="text-xl font-bold">₦{{ number_format($revenue_by_type['subscription']) }}</div>
                <div class="text-xs text-neutral-500 mt-1">{{ $stats['total_revenue'] > 0 ? round(($revenue_by_type['subscription'] / $stats['total_revenue']) * 100) : 0 }}% of total revenue</div>
            </div>
            
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="text-sm text-neutral-500 mb-1">Tutoring Sessions</div>
                <div class="text-xl font-bold">₦{{ number_format($revenue_by_type['tutoring']) }}</div>
                <div class="text-xs text-neutral-500 mt-1">{{ $stats['total_revenue'] > 0 ? round(($revenue_by_type['tutoring'] / $stats['total_revenue']) * 100) : 0 }}% of total revenue</div>
            </div>
        </div>
    </div>

    <!-- Revenue Growth Chart -->
    <div class="card p-6">
        <h2 class="text-lg font-semibold mb-4">Revenue Growth</h2>
        <div class="w-full h-80">
            <canvas id="revenueGrowthChart"></canvas>
        </div>
    </div>

    <!-- Revenue by Type Chart -->
    <div class="card p-6">
        <h2 class="text-lg font-semibold mb-4">Revenue by Type (Monthly)</h2>
        <div class="w-full h-80">
            <canvas id="revenueByTypeChart"></canvas>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card p-6">
        <h2 class="text-lg font-semibold mb-4">Recent Transactions</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200">
                <thead class="bg-neutral-50">
                    <tr>
                        <th scope="col" class="table-header">Date</th>
                        <th scope="col" class="table-header">User</th>
                        <th scope="col" class="table-header">Type</th>
                        <th scope="col" class="table-header">Amount</th>
                        <th scope="col" class="table-header">Status</th>
                        <th scope="col" class="table-header">Reference</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-neutral-200">
                    @foreach($recent_transactions as $transaction)
                    <tr>
                        <td class="table-cell">{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                        <td class="table-cell">
                            @if($transaction->user)
                            {{ $transaction->user->first_name }} {{ $transaction->user->last_name }}
                            @else
                            Unknown User
                            @endif
                        </td>
                        <td class="table-cell">
                            <span class="badge {{ $transaction->payment_type == 'course' ? 'badge-info' : ($transaction->payment_type == 'subscription' ? 'badge-success' : 'badge-warning') }}">
                                {{ ucfirst($transaction->payment_type) }}
                            </span>
                        </td>
                        <td class="table-cell font-medium">₦{{ number_format($transaction->amount) }}</td>
                        <td class="table-cell">
                            <span class="badge badge-success">{{ ucfirst($transaction->status) }}</span>
                        </td>
                        <td class="table-cell text-xs text-neutral-400">{{ $transaction->transaction_reference }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Revenue Growth Chart
    const revenueGrowthCtx = document.getElementById('revenueGrowthChart').getContext('2d');
    const revenueGrowthData = {
        labels: {!! json_encode($revenue_growth->pluck('month')) !!},
        datasets: [{
            label: 'Revenue (₦)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderColor: 'rgba(59, 130, 246, 0.8)',
            borderWidth: 2,
            data: {!! json_encode($revenue_growth->pluck('total')) !!},
            tension: 0.3,
            fill: true
        }]
    };
    
    const revenueGrowthChart = new Chart(revenueGrowthCtx, {
        type: 'line',
        data: revenueGrowthData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₦' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₦' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Revenue by Type Chart
    const revenueByTypeCtx = document.getElementById('revenueByTypeChart').getContext('2d');
    
    // Process chart data
    const months = [...new Set([
        ...{!! json_encode($course_revenue_by_month->pluck('month')) !!},
        ...{!! json_encode($subscription_revenue_by_month->pluck('month')) !!},
        ...{!! json_encode($tutoring_revenue_by_month->pluck('month')) !!}
    ])].sort();
    
    // Map the data for each month
    const courseData = months.map(month => {
        const entry = {!! json_encode($course_revenue_by_month) !!}.find(e => e.month === month);
        return entry ? entry.total : 0;
    });
    
    const subscriptionData = months.map(month => {
        const entry = {!! json_encode($subscription_revenue_by_month) !!}.find(e => e.month === month);
        return entry ? entry.total : 0;
    });
    
    const tutoringData = months.map(month => {
        const entry = {!! json_encode($tutoring_revenue_by_month) !!}.find(e => e.month === month);
        return entry ? entry.total : 0;
    });
    
    const revenueByTypeData = {
        labels: months,
        datasets: [
            {
                label: 'Course Enrollments',
                backgroundColor: 'rgba(96, 165, 250, 0.5)',
                borderColor: 'rgba(96, 165, 250, 1)',
                borderWidth: 1,
                data: courseData
            },
            {
                label: 'Subscriptions',
                backgroundColor: 'rgba(52, 211, 153, 0.5)',
                borderColor: 'rgba(52, 211, 153, 1)',
                borderWidth: 1,
                data: subscriptionData
            },
            {
                label: 'Tutoring Sessions',
                backgroundColor: 'rgba(251, 146, 60, 0.5)',
                borderColor: 'rgba(251, 146, 60, 1)',
                borderWidth: 1,
                data: tutoringData
            }
        ]
    };
    
    const revenueByTypeChart = new Chart(revenueByTypeCtx, {
        type: 'bar',
        data: revenueByTypeData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ₦' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₦' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
</script>
@endsection