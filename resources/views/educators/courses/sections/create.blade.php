@extends('educators.dashboard-layout')

@section('title', 'Add Section')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Add Section to "{{ $course->title }}"</h1>
        <a href="{{ route('educator.courses.show', $course->id) }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Course
        </a>
    </div>

    <!-- Form -->
    <div class="card">
        <form action="{{ route('educator.courses.sections.store', $course->id) }}" method="POST" class="p-6 space-y-6">
            @csrf
            
            <div class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Section Title <span class="text-red-600">*</span></label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" required
                        class="form-input" placeholder="Enter section title">
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3"
                        class="form-input" placeholder="Enter section description (optional)">{{ old('description') }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">A short description of what students will learn in this section.</p>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-3">
                <a href="{{ route('educator.courses.show', $course->id) }}" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save mr-2"></i> Create Section
                </button>
            </div>
        </form>
    </div>
</div>
@endsection