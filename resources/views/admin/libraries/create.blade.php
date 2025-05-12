@extends('admin.dashboard-layout')

@section('title', 'Create Library')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">Create New Library</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.libraries.index') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Libraries
        </a>
    </div>
</div>

<div class="card p-6 max-w-4xl mx-auto">
    <form action="{{ route('admin.libraries.store') }}" method="POST" class="space-y-6">
        @csrf
        
        <div class="grid grid-cols-1 gap-6">
            <!-- Library Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-neutral-700 mb-1">Library Name *</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                    class="form-input w-full" placeholder="Enter a descriptive name for the library">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            <!-- Library Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-neutral-700 mb-1">Description *</label>
                <textarea name="description" id="description" rows="4" required
                    class="form-input w-full" placeholder="Describe what this library is about and what type of content it will contain">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            <!-- Library Type -->
            <div>
                <label for="type" class="block text-sm font-medium text-neutral-700 mb-1">Library Type *</label>
                <select name="type" id="type" required class="form-input w-full" onchange="toggleCourseField()">
                    <option value="">Select a library type</option>
                    <option value="curated" {{ old('type') == 'curated' ? 'selected' : '' }}>Curated (Manually created)</option>
                    <option value="dynamic" {{ old('type') == 'dynamic' ? 'selected' : '' }}>Dynamic (AI-generated)</option>
                    <option value="course" {{ old('type') == 'course' ? 'selected' : '' }}>Course (Linked to a course)</option>
                </select>
                @error('type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                
                <div class="mt-2 p-3 bg-primary-50 border border-primary-200 rounded-md text-sm text-primary-700">
                    <p class="curated-help {{ old('type') == 'curated' ? 'block' : 'hidden' }}">
                        <i class="fa-solid fa-info-circle mr-2"></i>
                        Curated libraries allow you to manually select and organize content.
                    </p>
                    <p class="dynamic-help {{ old('type') == 'dynamic' ? 'block' : 'hidden' }}">
                        <i class="fa-solid fa-info-circle mr-2"></i>
                        Dynamic libraries are automatically populated based on relevance criteria and AI.
                    </p>
                    <p class="course-help {{ old('type') == 'course' ? 'block' : 'hidden' }}">
                        <i class="fa-solid fa-info-circle mr-2"></i>
                        Course libraries are automatically associated with a specific course.
                    </p>
                </div>
            </div>
            
            <!-- Course Selection (conditional) -->
            <div id="course-field" class="{{ old('type') == 'course' ? 'block' : 'hidden' }}">
                <label for="course_id" class="block text-sm font-medium text-neutral-700 mb-1">Associated Course</label>
                <select name="course_id" id="course_id" class="form-input w-full">
                    <option value="">Select a course</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" {{ old('course_id') == $course->id ? 'selected' : '' }}>
                            {{ $course->title }}
                        </option>
                    @endforeach
                </select>
                @error('course_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            <!-- Thumbnail URL -->
            <div>
                <label for="thumbnail_url" class="block text-sm font-medium text-neutral-700 mb-1">Thumbnail URL (Optional)</label>
                <input type="url" name="thumbnail_url" id="thumbnail_url" value="{{ old('thumbnail_url') }}"
                    class="form-input w-full" placeholder="https://example.com/image.jpg">
                <p class="mt-1 text-xs text-neutral-500">Add a URL to an image that represents this library. If left empty, a default or AI-generated image will be used.</p>
                @error('thumbnail_url')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            <!-- Submit Button -->
            <div class="pt-4">
                <button type="submit" class="btn btn-primary w-full sm:w-auto">
                    <i class="fa-solid fa-save mr-2"></i> Create Library
                </button>
            </div>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>
    function toggleCourseField() {
        const type = document.getElementById('type').value;
        const courseField = document.getElementById('course-field');
        
        // Show/hide the course field based on library type
        if (type === 'course') {
            courseField.classList.remove('hidden');
            document.getElementById('course_id').setAttribute('required', 'required');
        } else {
            courseField.classList.add('hidden');
            document.getElementById('course_id').removeAttribute('required');
        }
        
        // Show/hide help text
        document.querySelector('.curated-help').classList.toggle('hidden', type !== 'curated');
        document.querySelector('.dynamic-help').classList.toggle('hidden', type !== 'dynamic');
        document.querySelector('.course-help').classList.toggle('hidden', type !== 'course');
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleCourseField();
    });
</script>
@endsection