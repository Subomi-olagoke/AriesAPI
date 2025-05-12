@extends('educators.dashboard-layout')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Overview of your courses, students, and earnings')

@section('content')
<div class="space-y-6">
    <!-- Welcome card -->
    <div class="card p-6 border-l-4 border-primary-500">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-neutral-800 mb-2">Welcome, {{ Auth::user()->first_name }}!</h2>
                <p class="text-neutral-600">Manage your courses, students, and earnings from this central hub.</p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('educator.courses.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-plus mr-2"></i> Create New Course
                </a>
            </div>
        </div>
    </div>

    <!-- Stats overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-neutral-700">Total Courses</h3>
                <span class="text-primary-600 bg-primary-100 p-3 rounded-full">
                    <i class="fa-solid fa-book-open"></i>
                </span>
            </div>
            <div class="mt-4">
                <p class="text-3xl font-bold text-neutral-800">{{ $courseCount ?? 0 }}</p>
                <p class="text-sm text-neutral-500 mt-1">
                    <a href="{{ route('educator.courses.index') }}" class="text-primary-600 hover:text-primary-700">
                        View all courses <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
                    </a>
                </p>
            </div>
        </div>
        
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-neutral-700">Total Students</h3>
                <span class="text-primary-600 bg-primary-100 p-3 rounded-full">
                    <i class="fa-solid fa-users"></i>
                </span>
            </div>
            <div class="mt-4">
                <p class="text-3xl font-bold text-neutral-800">{{ $studentCount ?? 0 }}</p>
                <p class="text-sm text-neutral-500 mt-1">
                    <a href="{{ route('educator.students') }}" class="text-primary-600 hover:text-primary-700">
                        Manage students <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
                    </a>
                </p>
            </div>
        </div>
        
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-neutral-700">Total Earnings</h3>
                <span class="text-primary-600 bg-primary-100 p-3 rounded-full">
                    <i class="fa-solid fa-sack-dollar"></i>
                </span>
            </div>
            <div class="mt-4">
                <p class="text-3xl font-bold text-neutral-800">₦{{ number_format($totalEarnings ?? 0, 2) }}</p>
                <p class="text-sm text-neutral-500 mt-1">
                    <a href="{{ route('educator.earnings') }}" class="text-primary-600 hover:text-primary-700">
                        View earnings details <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
                    </a>
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent activity -->
        <div class="card">
            <div class="border-b border-neutral-200 px-6 py-4">
                <h3 class="text-lg font-medium text-neutral-800">Recent Activity</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th scope="col" class="table-header">Event</th>
                            <th scope="col" class="table-header">Course</th>
                            <th scope="col" class="table-header">Date</th>
                            <th scope="col" class="table-header">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-neutral-200">
                        @if(isset($recentActivities) && count($recentActivities) > 0)
                            @foreach($recentActivities as $activity)
                            <tr class="hover:bg-neutral-50">
                                <td class="table-cell">
                                    @if($activity->type == 'New Enrollment')
                                    <span class="badge badge-success">
                                        <i class="fa-solid fa-user-plus mr-1"></i> {{ $activity->type }}
                                    </span>
                                    @else
                                    <span class="badge badge-info">
                                        <i class="fa-solid fa-book mr-1"></i> {{ $activity->type }}
                                    </span>
                                    @endif
                                </td>
                                <td class="table-cell font-medium text-neutral-700">{{ $activity->course_title }}</td>
                                <td class="table-cell">{{ $activity->created_at->format('M d, Y H:i') }}</td>
                                <td class="table-cell">
                                    <a href="{{ $activity->link }}" class="text-primary-600 hover:text-primary-800 hover:underline">
                                        View <i class="fa-solid fa-external-link text-xs ml-1"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="4" class="table-cell text-center py-8 text-neutral-500">
                                    <i class="fa-solid fa-inbox text-neutral-300 text-4xl mb-3"></i>
                                    <p>No recent activity</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            @if(isset($recentActivities) && count($recentActivities) > 0)
            <div class="px-6 py-4 border-t border-neutral-200 bg-neutral-50">
                <a href="{{ route('educator.students') }}" class="text-sm text-primary-600 hover:text-primary-800">
                    View all activity <i class="fa-solid fa-arrow-right ml-1"></i>
                </a>
            </div>
            @endif
        </div>

        <!-- Quick actions -->
        <div class="card">
            <div class="border-b border-neutral-200 px-6 py-4">
                <h3 class="text-lg font-medium text-neutral-800">Quick Actions</h3>
            </div>
            <div class="p-6 space-y-6">
                <div class="flex flex-col space-y-4">
                    <a href="{{ route('educator.courses.create') }}" class="flex items-center p-4 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition-colors">
                        <div class="flex-shrink-0 p-3 bg-primary-100 rounded-md text-primary-600">
                            <i class="fa-solid fa-plus"></i>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-base font-medium text-neutral-800">Create New Course</h4>
                            <p class="text-sm text-neutral-500">Start creating a new educational course</p>
                        </div>
                        <i class="fa-solid fa-chevron-right ml-auto text-neutral-400"></i>
                    </a>
                    
                    <a href="{{ route('educator.students') }}" class="flex items-center p-4 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition-colors">
                        <div class="flex-shrink-0 p-3 bg-primary-100 rounded-md text-primary-600">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-base font-medium text-neutral-800">Manage Students</h4>
                            <p class="text-sm text-neutral-500">View and manage your enrolled students</p>
                        </div>
                        <i class="fa-solid fa-chevron-right ml-auto text-neutral-400"></i>
                    </a>
                    
                    <a href="{{ route('educator.earnings') }}" class="flex items-center p-4 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition-colors">
                        <div class="flex-shrink-0 p-3 bg-primary-100 rounded-md text-primary-600">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-base font-medium text-neutral-800">View Earnings</h4>
                            <p class="text-sm text-neutral-500">Check your earnings and financial reports</p>
                        </div>
                        <i class="fa-solid fa-chevron-right ml-auto text-neutral-400"></i>
                    </a>
                    
                    <a href="{{ route('educator.settings') }}" class="flex items-center p-4 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition-colors">
                        <div class="flex-shrink-0 p-3 bg-primary-100 rounded-md text-primary-600">
                            <i class="fa-solid fa-gear"></i>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-base font-medium text-neutral-800">Account Settings</h4>
                            <p class="text-sm text-neutral-500">Update your profile and preferences</p>
                        </div>
                        <i class="fa-solid fa-chevron-right ml-auto text-neutral-400"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Available for future dashboard analytics or interactive elements
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize any dashboard widgets or charts here if needed
    });
</script>
@endsection