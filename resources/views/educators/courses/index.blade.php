@extends('educators.dashboard-layout')

@section('title', 'Manage Courses')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Manage Your Courses</h1>
        <a href="{{ route('educator.courses.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus mr-2"></i> Create New Course
        </a>
    </div>

    <!-- Filters -->
    <div class="card p-4">
        <form action="{{ route('educator.courses.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" 
                    class="form-input" placeholder="Search by title...">
            </div>
            <div>
                <label for="topic" class="block text-sm font-medium text-gray-700 mb-1">Topic</label>
                <select name="topic_id" id="topic" class="form-input">
                    <option value="">All Topics</option>
                    @foreach($topics ?? [] as $topic)
                        <option value="{{ $topic->id }}" {{ request('topic_id') == $topic->id ? 'selected' : '' }}>
                            {{ $topic->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select name="sort" id="sort" class="form-input">
                    <option value="newest" {{ request('sort', 'newest') == 'newest' ? 'selected' : '' }}>
                        Newest First
                    </option>
                    <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>
                        Oldest First
                    </option>
                    <option value="enrollments" {{ request('sort') == 'enrollments' ? 'selected' : '' }}>
                        Most Enrollments
                    </option>
                    <option value="revenue" {{ request('sort') == 'revenue' ? 'selected' : '' }}>
                        Highest Revenue
                    </option>
                </select>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="btn btn-secondary">
                    <i class="fa-solid fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Courses Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($courses as $course)
            <div class="card overflow-hidden flex flex-col">
                <!-- Course Image -->
                <div class="relative h-40 bg-gray-200">
                    @if($course->thumbnail_url)
                        <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-primary-100">
                            <i class="fa-solid fa-book text-4xl text-primary-500"></i>
                        </div>
                    @endif
                    @if($course->is_featured)
                        <div class="absolute top-2 right-2 badge badge-warning">
                            <i class="fa-solid fa-star mr-1"></i> Featured
                        </div>
                    @endif
                </div>
                
                <!-- Course Info -->
                <div class="p-4 flex-grow flex flex-col">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ $course->title }}</h3>
                    <div class="text-sm text-gray-500 mb-2">
                        <span class="badge {{ $course->difficulty_level ? 'badge-info' : 'badge-secondary' }} mr-2">
                            {{ ucfirst($course->difficulty_level ?? 'No Level') }}
                        </span>
                        @if($course->topic)
                            <span class="badge badge-secondary">{{ $course->topic->name }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-600 line-clamp-2 mb-3">
                        {{ \Illuminate\Support\Str::limit($course->description, 120) }}
                    </p>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-2 my-2 text-sm">
                        <div>
                            <span class="text-gray-600">Price:</span>
                            <span class="font-medium">
                                {{ $course->price > 0 ? '₦'.number_format($course->price, 2) : 'Free' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600">Lessons:</span>
                            <span class="font-medium">{{ $course->lessons_count }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Students:</span>
                            <span class="font-medium">{{ $course->enrollments_count }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Revenue:</span>
                            <span class="font-medium">₦{{ number_format($course->total_revenue, 2) }}</span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mt-auto pt-3 flex space-x-2 border-t border-gray-100">
                        <a href="{{ route('educator.courses.show', $course->id) }}" class="btn btn-secondary flex-1 justify-center py-1">
                            <i class="fa-solid fa-eye mr-1"></i> View
                        </a>
                        <a href="{{ route('educator.courses.edit', $course->id) }}" class="btn btn-secondary flex-1 justify-center py-1">
                            <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                        </a>
                        <form action="{{ route('educator.courses.toggle-featured', $course->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="btn {{ $course->is_featured ? 'btn-secondary' : 'border border-yellow-300 text-yellow-600 bg-yellow-50 hover:bg-yellow-100' }} py-1 px-2">
                                <i class="fa-solid fa-star"></i>
                            </button>
                        </form>
                        <form action="{{ route('educator.courses.destroy', $course->id) }}" method="POST" class="inline"
                            onsubmit="return confirm('Are you sure you want to delete this course? This action cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn border border-red-300 text-red-600 bg-red-50 hover:bg-red-100 py-1 px-2">
                                <i class="fa-solid fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 card p-8 text-center">
                <div class="text-gray-500 mb-4">
                    <i class="fa-solid fa-book-open text-5xl mb-4"></i>
                    <h3 class="text-xl font-medium">No courses found</h3>
                </div>
                <p class="text-gray-600 mb-6">You haven't created any courses yet. Get started by creating your first course.</p>
                <a href="{{ route('educator.courses.create') }}" class="btn btn-primary inline-block">
                    <i class="fa-solid fa-plus mr-2"></i> Create Your First Course
                </a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($courses->hasPages())
        <div class="flex justify-center mt-6">
            {{ $courses->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection