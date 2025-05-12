@extends('educators.dashboard-layout')

@section('title', $course->title)

@section('content')
<div class="space-y-6">
    <!-- Header and Actions -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-3 sm:space-y-0">
        <h1 class="text-2xl font-bold text-gray-900">{{ $course->title }}</h1>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('educator.courses.edit', $course->id) }}" class="btn btn-secondary">
                <i class="fa-solid fa-edit mr-2"></i> Edit Course
            </a>
            <form action="{{ route('educator.courses.toggle-featured', $course->id) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn {{ $course->is_featured ? 'btn-secondary' : 'border border-yellow-300 text-yellow-600 bg-yellow-50 hover:bg-yellow-100' }}">
                    <i class="fa-solid fa-star mr-2"></i> {{ $course->is_featured ? 'Unfeature' : 'Feature' }}
                </button>
            </form>
            <a href="{{ route('educator.courses.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-2"></i> Back to Courses
            </a>
        </div>
    </div>

    <!-- Course Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Course Details -->
        <div class="lg:col-span-2 card">
            <div class="p-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
                    <!-- Course Thumbnail -->
                    <div class="w-full sm:w-1/3 aspect-video bg-gray-200 rounded-lg overflow-hidden">
                        @if($course->thumbnail_url)
                            <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-primary-100">
                                <i class="fa-solid fa-book text-4xl text-primary-500"></i>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Course Information -->
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 mb-2">
                            <span class="badge {{ $course->difficulty_level ? 'badge-info' : 'badge-secondary' }}">
                                {{ ucfirst($course->difficulty_level ?? 'No Level') }}
                            </span>
                            @if($course->topic)
                                <span class="badge badge-secondary">{{ $course->topic->name }}</span>
                            @endif
                            @if($course->is_featured)
                                <span class="badge badge-warning">
                                    <i class="fa-solid fa-star mr-1"></i> Featured
                                </span>
                            @endif
                        </div>
                        <div class="space-y-1 text-sm">
                            <div><span class="font-medium">Price:</span> {{ $course->price > 0 ? '₦'.number_format($course->price, 2) : 'Free' }}</div>
                            <div><span class="font-medium">Created:</span> {{ $course->created_at->format('M d, Y') }}</div>
                            <div><span class="font-medium">Duration:</span> {{ $course->duration_minutes ?? '0' }} minutes</div>
                            <div><span class="font-medium">Enrollments:</span> {{ $course->enrollments_count }}</div>
                            <div><span class="font-medium">Completion Rate:</span> {{ number_format($completionRate, 1) }}%</div>
                            <div><span class="font-medium">Revenue:</span> ₦{{ number_format($totalRevenue, 2) }}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-2">Description</h2>
                    <div class="text-gray-700 whitespace-pre-line">{{ $course->description }}</div>
                </div>
                
                <!-- Metadata Lists -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Learning Outcomes -->
                    @if($course->learning_outcomes && count($course->learning_outcomes) > 0)
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Learning Outcomes</h3>
                        <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                            @foreach($course->learning_outcomes as $outcome)
                                <li>{{ $outcome }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    
                    <!-- Prerequisites -->
                    @if($course->prerequisites && count($course->prerequisites) > 0)
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Prerequisites</h3>
                        <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                            @foreach($course->prerequisites as $prerequisite)
                                <li>{{ $prerequisite }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    
                    <!-- Completion Criteria -->
                    @if($course->completion_criteria && count($course->completion_criteria) > 0)
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Completion Criteria</h3>
                        <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                            @foreach($course->completion_criteria as $criterion)
                                <li>{{ $criterion }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Stats Card -->
        <div class="card p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Course Statistics</h2>
            <div class="space-y-4">
                <!-- Enrollment Chart -->
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Recent Enrollments</h3>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <!-- Chart would go here -->
                        <p class="text-sm text-gray-500 text-center py-3">Enrollment data visualization</p>
                    </div>
                </div>
                
                <!-- Recent Students -->
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Recent Students</h3>
                    <div class="space-y-2">
                        @forelse($recentEnrollments as $enrollment)
                            <div class="flex items-center p-2 bg-gray-50 rounded-lg">
                                <div class="w-8 h-8 rounded-full bg-primary-100 overflow-hidden mr-3">
                                    @if($enrollment->user->avatar)
                                        <img src="{{ $enrollment->user->avatar }}" alt="{{ $enrollment->user->username }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="fa-solid fa-user text-primary-500"></i>
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $enrollment->user->first_name }} {{ $enrollment->user->last_name }}</p>
                                    <p class="text-xs text-gray-500">Enrolled {{ $enrollment->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-2">No recent enrollments</p>
                        @endforelse
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Quick Links</h3>
                    <div class="space-y-2">
                        <a href="{{ route('educator.courses.edit', $course->id) }}" class="flex items-center p-2 bg-gray-50 rounded-lg hover:bg-gray-100">
                            <i class="fa-solid fa-edit text-primary-500 mr-3"></i>
                            <span class="text-sm">Edit Course Details</span>
                        </a>
                        <a href="{{ route('educator.students') }}?course={{ $course->id }}" class="flex items-center p-2 bg-gray-50 rounded-lg hover:bg-gray-100">
                            <i class="fa-solid fa-users text-primary-500 mr-3"></i>
                            <span class="text-sm">View All Students</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Content Management -->
    <div class="card">
        <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-900">Course Content</h2>
            <a href="{{ route('educator.courses.sections.create', $course->id) }}" class="btn btn-primary">
                <i class="fa-solid fa-plus mr-2"></i> Add Section
            </a>
        </div>
        
        <div class="p-6">
            @if($course->sections->count() > 0)
                <div class="space-y-6">
                    @foreach($course->sections as $sectionIndex => $section)
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <!-- Section Header -->
                            <div class="bg-gray-50 px-4 py-3 flex justify-between items-center">
                                <div>
                                    <span class="text-lg font-medium text-gray-900">Section {{ $sectionIndex + 1 }}: {{ $section->title }}</span>
                                    <span class="ml-2 text-xs text-gray-500">{{ $section->lessons->count() }} lessons</span>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="{{ route('educator.courses.sections.edit', ['courseId' => $course->id, 'sectionId' => $section->id]) }}" class="text-gray-500 hover:text-primary-600">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>
                                    <form action="{{ route('educator.courses.sections.destroy', ['courseId' => $course->id, 'sectionId' => $section->id]) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to delete this section and all its lessons? This action cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700">
                                            <i class="fa-solid fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Section Description -->
                            @if($section->description)
                                <div class="px-4 py-2 text-sm text-gray-700 border-b border-gray-200">
                                    {{ $section->description }}
                                </div>
                            @endif
                            
                            <!-- Lessons List -->
                            <div>
                                @if($section->lessons->count() > 0)
                                    <ul class="divide-y divide-gray-200">
                                        @foreach($section->lessons as $lessonIndex => $lesson)
                                            <li class="px-4 py-3 flex items-center justify-between hover:bg-gray-50">
                                                <div class="flex items-center space-x-3 flex-1">
                                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                                        {{ $lessonIndex + 1 }}
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $lesson->title }}</p>
                                                        <div class="flex items-center space-x-2 text-xs text-gray-500">
                                                            @if($lesson->content_type)
                                                                <span>
                                                                    @if($lesson->content_type == 'video')
                                                                        <i class="fa-solid fa-video mr-1"></i> Video
                                                                    @elseif($lesson->content_type == 'document')
                                                                        <i class="fa-solid fa-file-alt mr-1"></i> Document
                                                                    @elseif($lesson->content_type == 'quiz')
                                                                        <i class="fa-solid fa-question-circle mr-1"></i> Quiz
                                                                    @elseif($lesson->content_type == 'assignment')
                                                                        <i class="fa-solid fa-tasks mr-1"></i> Assignment
                                                                    @endif
                                                                </span>
                                                            @endif
                                                            @if($lesson->duration_minutes)
                                                                <span><i class="fa-regular fa-clock mr-1"></i> {{ $lesson->duration_minutes }} min</span>
                                                            @endif
                                                            @if($lesson->is_preview)
                                                                <span class="badge badge-info text-xs">Preview</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <a href="{{ route('educator.courses.lessons.edit', ['courseId' => $course->id, 'lessonId' => $lesson->id]) }}" class="text-gray-500 hover:text-primary-600">
                                                        <i class="fa-solid fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('educator.courses.lessons.destroy', ['courseId' => $course->id, 'lessonId' => $lesson->id]) }}" method="POST" class="inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this lesson? This action cannot be undone.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-500 hover:text-red-700">
                                                            <i class="fa-solid fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <div class="px-4 py-6 text-center text-sm text-gray-500">
                                        <p>No lessons in this section yet.</p>
                                    </div>
                                @endif
                                
                                <!-- Add Lesson Button -->
                                <div class="px-4 py-3 bg-gray-50 text-right">
                                    <a href="{{ route('educator.courses.lessons.create', ['courseId' => $course->id, 'sectionId' => $section->id]) }}" class="btn btn-secondary text-sm py-1">
                                        <i class="fa-solid fa-plus mr-1"></i> Add Lesson
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <div class="text-primary-500 mb-4">
                        <i class="fa-solid fa-book-open text-5xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Content Yet</h3>
                    <p class="text-gray-500 mb-6">Your course needs content to engage students. Start by adding sections and lessons.</p>
                    <a href="{{ route('educator.courses.sections.create', $course->id) }}" class="btn btn-primary">
                        <i class="fa-solid fa-plus mr-2"></i> Add Your First Section
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection