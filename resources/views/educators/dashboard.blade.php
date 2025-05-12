@extends('educators.dashboard-layout')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Welcome card -->
    <div class="card p-6">
        <h2 class="text-xl font-semibold mb-4">Welcome to your Educator Dashboard</h2>
        <p class="text-neutral-600">Manage your courses, students, and earnings from this central hub.</p>
        <div class="mt-4">
            <a href="{{ route('educator.courses.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus mr-2"></i> Create New Course
            </a>
        </div>
    </div>

    <!-- Stats overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-neutral-700">Total Courses</h3>
                <span class="text-primary-600">
                    <i class="fa-solid fa-book fa-2x"></i>
                </span>
            </div>
            <div class="mt-4">
                <p class="text-3xl font-bold">{{ $courseCount ?? 0 }}</p>
            </div>
        </div>
        
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-neutral-700">Total Students</h3>
                <span class="text-primary-600">
                    <i class="fa-solid fa-users fa-2x"></i>
                </span>
            </div>
            <div class="mt-4">
                <p class="text-3xl font-bold">{{ $studentCount ?? 0 }}</p>
            </div>
        </div>
        
        <div class="card p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-neutral-700">Total Earnings</h3>
                <span class="text-primary-600">
                    <i class="fa-solid fa-sack-dollar fa-2x"></i>
                </span>
            </div>
            <div class="mt-4">
                <p class="text-3xl font-bold">₦{{ number_format($totalEarnings ?? 0, 2) }}</p>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="card">
        <div class="border-b border-neutral-200 px-6 py-4">
            <h3 class="text-lg font-medium text-neutral-700">Recent Activity</h3>
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
                        <tr>
                            <td class="table-cell">{{ $activity->type }}</td>
                            <td class="table-cell">{{ $activity->course_title }}</td>
                            <td class="table-cell">{{ $activity->created_at->format('M d, Y H:i') }}</td>
                            <td class="table-cell">
                                <a href="{{ $activity->link }}" class="text-primary-600 hover:text-primary-900">View</a>
                            </td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" class="table-cell text-center py-8 text-neutral-500">No recent activity</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection