@extends('educators.dashboard-layout')

@section('title', 'Edit Course')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Edit Course</h1>
        <div class="flex space-x-3">
            <a href="{{ route('educator.courses.show', $course->id) }}" class="btn btn-secondary">
                <i class="fa-solid fa-eye mr-2"></i> View Course
            </a>
            <a href="{{ route('educator.courses.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-2"></i> Back to Courses
            </a>
        </div>
    </div>

    <!-- Form -->
    <div class="card">
        <form action="{{ route('educator.courses.update', $course->id) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf
            @method('PUT')
            
            <!-- Basic Information -->
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Course Title <span class="text-red-600">*</span></label>
                        <input type="text" id="title" name="title" value="{{ old('title', $course->title) }}" required
                            class="form-input" placeholder="Enter course title">
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-red-600">*</span></label>
                        <textarea id="description" name="description" rows="4" required
                            class="form-input" placeholder="Enter course description">{{ old('description', $course->description) }}</textarea>
                    </div>
                    <div>
                        <label for="topic_id" class="block text-sm font-medium text-gray-700 mb-1">Topic <span class="text-red-600">*</span></label>
                        <select id="topic_id" name="topic_id" required class="form-input">
                            <option value="">Select a topic</option>
                            @foreach($topics as $topic)
                                <option value="{{ $topic->id }}" {{ old('topic_id', $course->topic_id) == $topic->id ? 'selected' : '' }}>
                                    {{ $topic->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price (₦) <span class="text-red-600">*</span></label>
                        <input type="number" id="price" name="price" value="{{ old('price', $course->price) }}" min="0" step="0.01" required
                            class="form-input" placeholder="Enter course price">
                        <p class="mt-1 text-xs text-gray-500">Set to 0 for free courses</p>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Additional Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="difficulty_level" class="block text-sm font-medium text-gray-700 mb-1">Difficulty Level</label>
                        <select id="difficulty_level" name="difficulty_level" class="form-input">
                            <option value="">Select difficulty</option>
                            <option value="beginner" {{ old('difficulty_level', $course->difficulty_level) == 'beginner' ? 'selected' : '' }}>Beginner</option>
                            <option value="intermediate" {{ old('difficulty_level', $course->difficulty_level) == 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                            <option value="advanced" {{ old('difficulty_level', $course->difficulty_level) == 'advanced' ? 'selected' : '' }}>Advanced</option>
                        </select>
                    </div>
                    <div>
                        <label for="duration_minutes" class="block text-sm font-medium text-gray-700 mb-1">Estimated Duration (minutes)</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes', $course->duration_minutes) }}" min="1"
                            class="form-input" placeholder="Enter estimated duration in minutes">
                    </div>
                </div>
            </div>

            <!-- Media -->
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Course Media</h2>
                <div>
                    <label for="thumbnail" class="block text-sm font-medium text-gray-700 mb-1">Course Thumbnail</label>
                    @if($course->thumbnail_url)
                        <div class="mb-3">
                            <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" class="h-40 object-cover rounded-md">
                            <p class="mt-1 text-xs text-gray-500">Current thumbnail</p>
                        </div>
                    @endif
                    <input type="file" id="thumbnail" name="thumbnail" accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-100 file:text-primary-700 hover:file:bg-primary-200">
                    <p class="mt-1 text-xs text-gray-500">Upload a new thumbnail to replace the current one. Recommended size: 800x450px (16:9). Max 5MB.</p>
                </div>
            </div>

            <!-- Learning Outcomes, Prerequisites, and Completion Criteria -->
            <div x-data="{ 
                learning_outcomes: {{ $course->learning_outcomes ? json_encode($course->learning_outcomes) : '[]' }},
                prerequisites: {{ $course->prerequisites ? json_encode($course->prerequisites) : '[]' }},
                completion_criteria: {{ $course->completion_criteria ? json_encode($course->completion_criteria) : '[]' }},
                newLearningOutcome: '',
                newPrerequisite: '',
                newCompletionCriterion: '',
                
                addLearningOutcome() {
                    if (this.newLearningOutcome.trim() !== '') {
                        this.learning_outcomes.push(this.newLearningOutcome.trim());
                        this.newLearningOutcome = '';
                    }
                },
                removeLearningOutcome(index) {
                    this.learning_outcomes.splice(index, 1);
                },
                
                addPrerequisite() {
                    if (this.newPrerequisite.trim() !== '') {
                        this.prerequisites.push(this.newPrerequisite.trim());
                        this.newPrerequisite = '';
                    }
                },
                removePrerequisite(index) {
                    this.prerequisites.splice(index, 1);
                },
                
                addCompletionCriterion() {
                    if (this.newCompletionCriterion.trim() !== '') {
                        this.completion_criteria.push(this.newCompletionCriterion.trim());
                        this.newCompletionCriterion = '';
                    }
                },
                removeCompletionCriterion(index) {
                    this.completion_criteria.splice(index, 1);
                }
            }">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Learning Outcomes -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Learning Outcomes</h3>
                        <div class="flex space-x-2 mb-2">
                            <input type="text" x-model="newLearningOutcome" 
                                class="form-input" placeholder="Add a learning outcome"
                                @keydown.enter.prevent="addLearningOutcome()">
                            <button type="button" @click="addLearningOutcome()" 
                                class="btn btn-secondary py-1">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <ul class="space-y-2 mt-2">
                            <template x-for="(item, index) in learning_outcomes" :key="index">
                                <li class="flex justify-between items-center bg-gray-50 p-2 rounded text-sm">
                                    <span x-text="item"></span>
                                    <input type="hidden" :name="'learning_outcomes['+index+']'" x-model="item">
                                    <button type="button" @click="removeLearningOutcome(index)" 
                                        class="text-red-600 hover:text-red-800">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                    
                    <!-- Prerequisites -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Prerequisites</h3>
                        <div class="flex space-x-2 mb-2">
                            <input type="text" x-model="newPrerequisite" 
                                class="form-input" placeholder="Add a prerequisite"
                                @keydown.enter.prevent="addPrerequisite()">
                            <button type="button" @click="addPrerequisite()" 
                                class="btn btn-secondary py-1">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <ul class="space-y-2 mt-2">
                            <template x-for="(item, index) in prerequisites" :key="index">
                                <li class="flex justify-between items-center bg-gray-50 p-2 rounded text-sm">
                                    <span x-text="item"></span>
                                    <input type="hidden" :name="'prerequisites['+index+']'" x-model="item">
                                    <button type="button" @click="removePrerequisite(index)" 
                                        class="text-red-600 hover:text-red-800">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                    
                    <!-- Completion Criteria -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-900 mb-2">Completion Criteria</h3>
                        <div class="flex space-x-2 mb-2">
                            <input type="text" x-model="newCompletionCriterion" 
                                class="form-input" placeholder="Add completion criteria"
                                @keydown.enter.prevent="addCompletionCriterion()">
                            <button type="button" @click="addCompletionCriterion()" 
                                class="btn btn-secondary py-1">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <ul class="space-y-2 mt-2">
                            <template x-for="(item, index) in completion_criteria" :key="index">
                                <li class="flex justify-between items-center bg-gray-50 p-2 rounded text-sm">
                                    <span x-text="item"></span>
                                    <input type="hidden" :name="'completion_criteria['+index+']'" x-model="item">
                                    <button type="button" @click="removeCompletionCriterion(index)" 
                                        class="text-red-600 hover:text-red-800">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3 pt-3">
                <a href="{{ route('educator.courses.show', $course->id) }}" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save mr-2"></i> Update Course
                </button>
            </div>
        </form>
    </div>
</div>
@endsection