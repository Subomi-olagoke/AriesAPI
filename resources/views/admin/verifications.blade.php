@extends('admin.dashboard-layout')

@section('title', 'Verifications Dashboard')

@section('head')
<style>
    .verification-card {
        @apply p-4 rounded-lg shadow-sm flex flex-col;
    }
    .verification-card-title {
        @apply text-neutral-500 text-sm font-medium mb-1;
    }
    .verification-card-value {
        @apply text-2xl font-bold;
    }
    .verification-card-subtitle {
        @apply text-neutral-500 text-xs mt-1;
    }
</style>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-neutral-900">Verifications Dashboard</h1>
        <div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Overview
            </a>
        </div>
    </div>

    <!-- Stats Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="verification-card bg-yellow-50 border border-yellow-100">
            <span class="verification-card-title">Pending Verifications</span>
            <span class="verification-card-value text-yellow-700">{{ number_format($stats['pending']) }}</span>
            <span class="verification-card-subtitle">Verification requests awaiting review</span>
        </div>
        
        <div class="verification-card bg-green-50 border border-green-100">
            <span class="verification-card-title">Approved Verifications</span>
            <span class="verification-card-value text-green-700">{{ number_format($stats['approved']) }}</span>
            <span class="verification-card-subtitle">Successfully verified accounts</span>
        </div>
        
        <div class="verification-card bg-red-50 border border-red-100">
            <span class="verification-card-title">Rejected Verifications</span>
            <span class="verification-card-value text-red-700">{{ number_format($stats['rejected']) }}</span>
            <span class="verification-card-subtitle">Verification requests that failed</span>
        </div>
        
        <div class="verification-card bg-blue-50 border border-blue-100">
            <span class="verification-card-title">Total Verifications</span>
            <span class="verification-card-value text-blue-700">{{ number_format($stats['total']) }}</span>
            <span class="verification-card-subtitle">All verification requests received</span>
        </div>
    </div>

    <!-- Verification Types -->
    <div class="card p-6">
        <h2 class="text-lg font-semibold mb-4">Verification Types</h2>
        <div class="w-full h-60">
            <canvas id="verificationTypesChart"></canvas>
        </div>
    </div>

    <!-- Verification Trends Chart -->
    <div class="card p-6">
        <h2 class="text-lg font-semibold mb-4">Verification Trends</h2>
        <div class="w-full h-80">
            <canvas id="verificationTrendsChart"></canvas>
        </div>
    </div>

    <!-- Recent Verification Requests -->
    <div class="card p-6">
        <h2 class="text-lg font-semibold mb-4">Recent Verification Requests</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200">
                <thead class="bg-neutral-50">
                    <tr>
                        <th scope="col" class="table-header">Date</th>
                        <th scope="col" class="table-header">User</th>
                        <th scope="col" class="table-header">Type</th>
                        <th scope="col" class="table-header">Status</th>
                        <th scope="col" class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-neutral-200">
                    @foreach($recent_verifications as $verification)
                    <tr>
                        <td class="table-cell">{{ date('M d, Y H:i', strtotime($verification->created_at)) }}</td>
                        <td class="table-cell">
                            <div class="font-medium text-neutral-800">{{ $verification->first_name }} {{ $verification->last_name }}</div>
                            <div class="text-sm text-neutral-500">{{ $verification->email }}</div>
                        </td>
                        <td class="table-cell">
                            <span class="badge {{ $verification->verification_type == 'educator' ? 'badge-info' : 'badge-warning' }}">
                                {{ ucfirst($verification->verification_type) }}
                            </span>
                        </td>
                        <td class="table-cell">
                            <span class="badge {{ 
                                $verification->status == 'pending' ? 'badge-warning' : 
                                ($verification->status == 'approved' ? 'badge-success' : 'badge-danger') 
                            }}">
                                {{ ucfirst($verification->status) }}
                            </span>
                        </td>
                        <td class="table-cell">
                            <div class="flex space-x-2">
                                <a href="#" class="text-blue-600 hover:text-blue-800">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                @if($verification->status == 'pending')
                                <a href="#" class="text-green-600 hover:text-green-800">
                                    <i class="fa-solid fa-check"></i>
                                </a>
                                <a href="#" class="text-red-600 hover:text-red-800">
                                    <i class="fa-solid fa-times"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 flex justify-center">
            <a href="#" class="btn btn-secondary">
                View All Verification Requests
            </a>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Verification Types Chart
    const verificationTypesCtx = document.getElementById('verificationTypesChart').getContext('2d');
    
    // Extract data for the chart
    const verificationTypes = {!! json_encode($verifications_by_type->pluck('verification_type')) !!};
    const verificationCounts = {!! json_encode($verifications_by_type->pluck('count')) !!};
    
    // Chart colors
    const backgroundColors = [
        'rgba(59, 130, 246, 0.6)', 
        'rgba(16, 185, 129, 0.6)',
        'rgba(245, 158, 11, 0.6)',
        'rgba(239, 68, 68, 0.6)',
        'rgba(139, 92, 246, 0.6)'
    ];
    
    const verificationTypesChart = new Chart(verificationTypesCtx, {
        type: 'pie',
        data: {
            labels: verificationTypes.map(type => type.charAt(0).toUpperCase() + type.slice(1)),
            datasets: [{
                data: verificationCounts,
                backgroundColor: backgroundColors.slice(0, verificationTypes.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    
    // Verification Trends Chart
    const verificationTrendsCtx = document.getElementById('verificationTrendsChart').getContext('2d');
    
    const verificationTrendsData = {
        labels: {!! json_encode($verifications_over_time->pluck('month')) !!},
        datasets: [{
            label: 'Verification Requests',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            borderColor: 'rgba(99, 102, 241, 0.8)',
            borderWidth: 2,
            data: {!! json_encode($verifications_over_time->pluck('count')) !!},
            tension: 0.3,
            fill: true
        }]
    };
    
    const verificationTrendsChart = new Chart(verificationTrendsCtx, {
        type: 'line',
        data: verificationTrendsData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>
@endsection