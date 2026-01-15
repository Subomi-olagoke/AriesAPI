@extends('educators.dashboard-layout')

@section('title', 'Add Lesson')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Add Lesson</h1>
            <p class="text-gray-600">Section: {{ $section->title }}</p>
        </div>
        <a href="{{ route('educator.courses.show', $course->id) }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Course
        </a>
    </div>

    <!-- Form -->
    <div class="card">
        <form action="{{ route('educator.courses.lessons.store', ['courseId' => $course->id, 'sectionId' => $section->id]) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf
            
            <!-- Basic Information -->
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Lesson Title <span class="text-red-600">*</span></label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" required
                            class="form-input" placeholder="Enter lesson title">
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                            class="form-input" placeholder="Enter lesson description (optional)">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label for="content_type" class="block text-sm font-medium text-gray-700 mb-1">Content Type <span class="text-red-600">*</span></label>
                        <select id="content_type" name="content_type" required class="form-input" onchange="showContentFields()">
                            <option value="">Select content type</option>
                            <option value="video" {{ old('content_type') == 'video' ? 'selected' : '' }}>Video</option>
                            <option value="document" {{ old('content_type') == 'document' ? 'selected' : '' }}>Document</option>
                            <option value="quiz" {{ old('content_type') == 'quiz' ? 'selected' : '' }}>Quiz</option>
                            <option value="assignment" {{ old('content_type') == 'assignment' ? 'selected' : '' }}>Assignment</option>
                        </select>
                    </div>
                    <div>
                        <label for="duration_minutes" class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes') }}" min="1"
                            class="form-input" placeholder="Enter estimated duration">
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex items-center">
                            <input type="checkbox" id="is_preview" name="is_preview" value="1" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                                {{ old('is_preview') ? 'checked' : '' }}>
                            <label for="is_preview" class="ml-2 block text-sm text-gray-700">
                                Preview Lesson (available to non-enrolled students)
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Making this lesson available as a preview allows potential students to sample your course content.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Content Fields (conditionally displayed based on content_type) -->
            <div id="video_fields" class="border-b border-gray-200 pb-6 hidden">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Video Content</h2>
                <div class="space-y-4">
                    <div>
                        <label for="video" class="block text-sm font-medium text-gray-700 mb-1">Video File <span class="text-red-600">*</span></label>
                        <input type="file" id="video" name="video" accept="video/*"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-100 file:text-primary-700 hover:file:bg-primary-200">
                        <p class="mt-1 text-xs text-gray-500">Supported formats: MP4, MOV, AVI, WEBM. Maximum file size: 5GB.</p>
                    </div>
                    <div>
                        <label for="thumbnail" class="block text-sm font-medium text-gray-700 mb-1">Video Thumbnail</label>
                        <input type="file" id="thumbnail" name="thumbnail" accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-100 file:text-primary-700 hover:file:bg-primary-200">
                        <p class="mt-1 text-xs text-gray-500">Recommended size: 640x360px (16:9). Maximum file size: 10MB.</p>
                    </div>
                </div>
            </div>

            <div id="document_fields" class="border-b border-gray-200 pb-6 hidden">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Document Content</h2>
                <div>
                    <label for="file" class="block text-sm font-medium text-gray-700 mb-1">Document File <span class="text-red-600">*</span></label>
                    <input type="file" id="file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-100 file:text-primary-700 hover:file:bg-primary-200">
                    <p class="mt-1 text-xs text-gray-500">Supported formats: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, ZIP. Maximum file size: 200MB.</p>
                </div>
            </div>

            <div id="quiz_fields" class="border-b border-gray-200 pb-6 hidden">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Quiz Content</h2>
                <p class="text-sm text-gray-500 mb-4">Quiz functionality will be implemented in a future update. Please use document uploads for quizzes for now.</p>
            </div>

            <div id="assignment_fields" class="border-b border-gray-200 pb-6 hidden">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Assignment Content</h2>
                <p class="text-sm text-gray-500 mb-4">Assignment functionality will be implemented in a future update. Please use document uploads for assignments for now.</p>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-3">
                <a href="{{ route('educator.courses.show', $course->id) }}" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save mr-2"></i> Create Lesson
                </button>
            </div>
        </form>
    </div>
</div>

@section('scripts')
<script>
    function showContentFields() {
        // Hide all content type fields
        document.getElementById('video_fields').classList.add('hidden');
        document.getElementById('document_fields').classList.add('hidden');
        document.getElementById('quiz_fields').classList.add('hidden');
        document.getElementById('assignment_fields').classList.add('hidden');
        
        // Show the selected content type fields
        const contentType = document.getElementById('content_type').value;
        if (contentType) {
            document.getElementById(contentType + '_fields').classList.remove('hidden');
        }
    }
    
    // Initialize form state
    document.addEventListener('DOMContentLoaded', function() {
        showContentFields();
    });
</script>
@endsection
@endsection