@extends('admin.dashboard-layout')

@section('title', 'Content Management')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Content Management</h1>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.content.export') }}" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
                Export Stats
            </a>
            <a href="{{ route('admin.libraries.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                </svg>
                Manage Libraries
            </a>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Posts Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-xl font-semibold mb-2">Posts</h3>
                    <p class="text-4xl font-bold text-blue-600">{{ number_format($stats['posts']['total']) }}</p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                    </svg>
                </div>
            </div>
            <div class="flex justify-between items-center mt-4 text-sm">
                <div>
                    <p class="text-gray-500">Today</p>
                    <p class="font-semibold">{{ number_format($stats['posts']['today']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">This Week</p>
                    <p class="font-semibold">{{ number_format($stats['posts']['this_week']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">This Month</p>
                    <p class="font-semibold">{{ number_format($stats['posts']['this_month']) }}</p>
                </div>
            </div>
        </div>
        
        <!-- Courses Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-xl font-semibold mb-2">Courses</h3>
                    <p class="text-4xl font-bold text-purple-600">{{ number_format($stats['courses']['total']) }}</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
            </div>
            <div class="flex justify-between items-center mt-4 text-sm">
                <div>
                    <p class="text-gray-500">This Month</p>
                    <p class="font-semibold">{{ number_format($stats['courses']['this_month']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Enrollments</p>
                    <p class="font-semibold">{{ number_format($stats['enrollments']['total']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">New Enrollments</p>
                    <p class="font-semibold">{{ number_format($stats['enrollments']['this_month']) }}</p>
                </div>
            </div>
        </div>
        
        <!-- Libraries Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-xl font-semibold mb-2">Libraries</h3>
                    <p class="text-4xl font-bold text-indigo-600">{{ number_format($stats['libraries']['total']) }}</p>
                </div>
                <div class="p-3 bg-indigo-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                    </svg>
                </div>
            </div>
            <div class="flex justify-between items-center mt-4 text-sm">
                <div>
                    <p class="text-gray-500">Pending</p>
                    <p class="font-semibold text-yellow-600">{{ number_format($stats['libraries']['pending']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Approved</p>
                    <p class="font-semibold text-green-600">{{ number_format($stats['libraries']['approved']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Rejected</p>
                    <p class="font-semibold text-red-600">{{ number_format($stats['libraries']['rejected']) }}</p>
                </div>
            </div>
        </div>
        
        <!-- Topics Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="text-xl font-semibold mb-2">Topic Coverage</h3>
                    <p class="text-4xl font-bold text-teal-600">{{ $contentByTopic->count() }}</p>
                </div>
                <div class="p-3 bg-teal-100 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 text-sm">
                <p class="text-gray-500 mb-1">Top Topics</p>
                @foreach($contentByTopic->take(3) as $topic)
                <div class="flex justify-between mt-1">
                    <p class="font-semibold truncate">{{ $topic->name }}</p>
                    <p>{{ $topic->courses_count + $topic->posts_count }} items</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Content Growth Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-semibold mb-4">Content Growth (12 Months)</h3>
            <canvas id="contentGrowthChart" height="300"></canvas>
        </div>
        
        <!-- Content by Topics -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-semibold mb-4">Content by Topics</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Topic</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Courses</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Posts</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($contentByTopic as $topic)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">{{ $topic->name }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-500">{{ $topic->courses_count }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-500">{{ $topic->posts_count }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center font-medium text-blue-600">{{ $topic->courses_count + $topic->posts_count }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Posts -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h3 class="text-xl font-semibold">Top Posts by Likes</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Likes</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($topPosts as $post)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">{{ $post->title ?? 'Untitled Post' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-500">{{ $post->likes_count }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-500">{{ $post->created_at->format('M d, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Top Courses -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h3 class="text-xl font-semibold">Top Courses by Enrollments</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Enrollments</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($topCourses as $course)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">{{ $course->title }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-500">{{ $course->enrollments_count }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-500">{{ $course->created_at->format('M d, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Libraries -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h3 class="text-xl font-semibold">Recent Libraries</h3>
            <a href="{{ route('admin.libraries.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($recentLibraries as $library)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">{{ $library->name }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500 max-w-xs truncate">{{ $library->description }}</td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-500">{{ ucfirst($library->type) }}</td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-center">
                            @if($library->approval_status === 'approved')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Approved
                                </span>
                            @elseif($library->approval_status === 'pending')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Pending
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Rejected
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-500">{{ $library->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-center">
                            <a href="{{ route('admin.libraries.view', $library->id) }}" class="text-blue-600 hover:text-blue-900">
                                <span class="sr-only">View</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No libraries found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format data for the Content Growth chart
    const months = [];
    const postCounts = [];
    const courseCounts = [];
    const libraryCounts = [];
    
    // Create a map of all months in the last year
    const allMonthsMap = {};
    const today = new Date();
    for (let i = 11; i >= 0; i--) {
        const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
        const month = d.toISOString().substring(0, 7); // YYYY-MM format
        allMonthsMap[month] = 0;
    }
    
    // Process post growth data
    const postData = @json($postGrowth);
    const postMonthMap = { ...allMonthsMap };
    postData.forEach(item => {
        postMonthMap[item.month] = parseInt(item.count);
    });
    
    // Process course growth data
    const courseData = @json($courseGrowth);
    const courseMonthMap = { ...allMonthsMap };
    courseData.forEach(item => {
        courseMonthMap[item.month] = parseInt(item.count);
    });
    
    // Process library growth data
    const libraryData = @json($libraryGrowth);
    const libraryMonthMap = { ...allMonthsMap };
    if (libraryData && libraryData.length) {
        libraryData.forEach(item => {
            libraryMonthMap[item.month] = parseInt(item.count);
        });
    }
    
    // Sort the months and populate the arrays
    Object.keys(allMonthsMap).sort().forEach(month => {
        // Format the month for display (e.g., "Jan 2023")
        const date = new Date(month + '-01');
        const formattedMonth = date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        
        months.push(formattedMonth);
        postCounts.push(postMonthMap[month] || 0);
        courseCounts.push(courseMonthMap[month] || 0);
        libraryCounts.push(libraryMonthMap[month] || 0);
    });
    
    // Create content growth chart
    const contentGrowthCtx = document.getElementById('contentGrowthChart').getContext('2d');
    new Chart(contentGrowthCtx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Posts',
                    data: postCounts,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Courses',
                    data: courseCounts,
                    borderColor: 'rgb(147, 51, 234)',
                    backgroundColor: 'rgba(147, 51, 234, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Libraries',
                    data: libraryCounts,
                    borderColor: 'rgb(79, 70, 229)',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
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
});
</script>
@endsection