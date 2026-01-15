@extends('educators.dashboard-layout')

@section('title', 'Earnings')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Your Earnings</h1>
    </div>
    
    <!-- Earnings Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="card p-6">
            <h2 class="text-sm font-medium text-gray-500 mb-1">Total Earnings</h2>
            <div class="text-3xl font-bold text-gray-900">₦{{ number_format($totalEarnings, 2) }}</div>
            <div class="mt-2 text-sm text-gray-500">Lifetime earnings from all courses</div>
        </div>
        
        <div class="card p-6">
            <h2 class="text-sm font-medium text-gray-500 mb-1">Current Month</h2>
            <div class="text-3xl font-bold text-gray-900">₦{{ number_format($currentMonthEarnings, 2) }}</div>
            <div class="mt-2 flex items-center text-sm">
                @if($earningsGrowth > 0)
                    <span class="text-green-500 font-medium"><i class="fa-solid fa-arrow-up mr-1"></i> {{ number_format($earningsGrowth, 1) }}%</span>
                    <span class="text-gray-500 ml-1">from last month</span>
                @elseif($earningsGrowth < 0)
                    <span class="text-red-500 font-medium"><i class="fa-solid fa-arrow-down mr-1"></i> {{ number_format(abs($earningsGrowth), 1) }}%</span>
                    <span class="text-gray-500 ml-1">from last month</span>
                @else
                    <span class="text-gray-500">No change from last month</span>
                @endif
            </div>
        </div>
        
        <div class="card p-6">
            <h2 class="text-sm font-medium text-gray-500 mb-1">Available Balance</h2>
            <div class="text-3xl font-bold text-gray-900">₦{{ number_format($totalEarnings * 0.7, 2) }}</div>
            <div class="mt-2 text-sm text-gray-500">After platform fees (30%)</div>
        </div>
    </div>
    
    <!-- Monthly Earnings Chart -->
    <div class="card p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Monthly Earnings</h2>
        <div class="h-80">
            <canvas id="earningsChart"></canvas>
        </div>
    </div>
    
    <!-- Earnings by Course -->
    <div class="card overflow-hidden">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-medium text-gray-900">Earnings by Course</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="table-header">Course</th>
                        <th scope="col" class="table-header">Price</th>
                        <th scope="col" class="table-header">Enrollments</th>
                        <th scope="col" class="table-header">Total Revenue</th>
                        <th scope="col" class="table-header">Your Earnings</th>
                        <th scope="col" class="table-header">Last Enrollment</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($earningsByCourse as $course)
                        <tr>
                            <td class="table-cell">
                                <a href="{{ route('educator.courses.show', $course->id) }}" class="text-primary-600 hover:text-primary-900 font-medium">
                                    {{ $course->title }}
                                </a>
                            </td>
                            <td class="table-cell">₦{{ number_format($course->price, 2) }}</td>
                            <td class="table-cell">{{ $course->enrollment_count }}</td>
                            <td class="table-cell">₦{{ number_format($course->total, 2) }}</td>
                            <td class="table-cell">₦{{ number_format($course->total * 0.7, 2) }}</td>
                            <td class="table-cell">
                                {{ $course->latest_enrollment ? \Carbon\Carbon::parse($course->latest_enrollment)->format('M d, Y') : 'N/A' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fa-solid fa-coins text-4xl mb-4 text-gray-400"></i>
                                    <p class="text-base font-medium text-gray-900 mb-1">No earnings yet</p>
                                    <p>You haven't had any enrollments in your courses yet.</p>
                                    <a href="{{ route('educator.courses.index') }}" class="mt-4 btn btn-primary">
                                        Manage Your Courses
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Bank Information -->
    <div class="card p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Payout Information</h2>
        
        @if($bankInfo)
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Current Bank Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Bank Name</p>
                        <p class="text-sm font-medium">{{ $bankInfo->bank_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Account Name</p>
                        <p class="text-sm font-medium">{{ $bankInfo->account_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Account Number</p>
                        <p class="text-sm font-medium">{{ $bankInfo->account_number }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fa-solid fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            You haven't set up your bank information for payouts yet.
                        </p>
                    </div>
                </div>
            </div>
        @endif
        
        <a href="{{ route('educator.settings') }}#payout-information" class="btn btn-primary">
            {{ $bankInfo ? 'Update' : 'Set Up' }} Payout Information
        </a>
    </div>
</div>

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('earningsChart').getContext('2d');
        
        // Parse the monthly earnings data
        const monthlyEarnings = @json($monthlyEarnings);
        
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyEarnings.map(item => item.month_year),
                datasets: [{
                    label: 'Earnings (₦)',
                    data: monthlyEarnings.map(item => item.total),
                    backgroundColor: '#5a78ee',
                    borderColor: '#3f5ae3',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Earnings: ₦' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@endsection
@endsection