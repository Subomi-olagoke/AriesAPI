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
    <div x-data="{ step: 1, libraryType: '{{ old('type') }}' }">
        <!-- Progress indicator -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div class="w-full flex items-center">
                    <!-- Step 1: Library Details -->
                    <div class="relative flex flex-col items-center text-teal-600" x-bind:class="{ 'text-neutral-600': step > 1 }">
                        <div class="rounded-full h-10 w-10 flex items-center justify-center bg-teal-100 border-2 border-teal-600" x-bind:class="{ 'bg-neutral-100 border-neutral-600': step > 1 }">
                            <span class="text-sm font-medium">1</span>
                        </div>
                        <div class="text-xs mt-1">Details</div>
                    </div>
                    
                    <!-- Connecting Line -->
                    <div class="flex-auto border-t-2 mx-2 border-teal-600" x-bind:class="{ 'border-neutral-300': step < 2, 'border-neutral-600': step >= 2 }"></div>
                    
                    <!-- Step 2: Add Content -->
                    <div class="relative flex flex-col items-center" x-bind:class="{ 'text-neutral-400': step < 2, 'text-teal-600': step === 2, 'text-neutral-600': step > 2 }">
                        <div class="rounded-full h-10 w-10 flex items-center justify-center bg-white border-2 border-neutral-300" x-bind:class="{ 'bg-teal-100 border-teal-600': step === 2, 'bg-neutral-100 border-neutral-600': step > 2 }">
                            <span class="text-sm font-medium">2</span>
                        </div>
                        <div class="text-xs mt-1">Add Content</div>
                    </div>
                    
                    <!-- Connecting Line -->
                    <div class="flex-auto border-t-2 mx-2 border-neutral-300" x-bind:class="{ 'border-neutral-600': step === 3 }"></div>
                    
                    <!-- Step 3: Review & Create -->
                    <div class="relative flex flex-col items-center text-neutral-400" x-bind:class="{ 'text-teal-600': step === 3 }">
                        <div class="rounded-full h-10 w-10 flex items-center justify-center bg-white border-2 border-neutral-300" x-bind:class="{ 'bg-teal-100 border-teal-600': step === 3 }">
                            <span class="text-sm font-medium">3</span>
                        </div>
                        <div class="text-xs mt-1">Review</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form with steps -->
        <form action="{{ route('admin.libraries.store') }}" method="POST" id="createLibraryForm" class="space-y-6">
            @csrf
            
            <!-- Step 1: Library Details -->
            <div x-show="step === 1">
                <h2 class="text-xl font-medium text-neutral-900 mb-4">Library Details</h2>
                
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
                        <select name="type" id="type" required class="form-input w-full" x-model="libraryType" @change="toggleCourseField()">
                            <option value="">Select a library type</option>
                            <option value="curated" {{ old('type') == 'curated' ? 'selected' : '' }}>Curated (Manually created)</option>
                            <option value="dynamic" {{ old('type') == 'dynamic' ? 'selected' : '' }}>Dynamic (AI-generated)</option>
                            <option value="course" {{ old('type') == 'course' ? 'selected' : '' }}>Course (Linked to a course)</option>
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        
                        <div class="mt-2 p-3 bg-primary-50 border border-primary-200 rounded-md text-sm text-primary-700">
                            <p x-show="libraryType === 'curated'">
                                <i class="fa-solid fa-info-circle mr-2"></i>
                                Curated libraries allow you to manually select and organize content.
                            </p>
                            <p x-show="libraryType === 'dynamic'">
                                <i class="fa-solid fa-info-circle mr-2"></i>
                                Dynamic libraries are automatically populated based on relevance criteria and AI.
                            </p>
                            <p x-show="libraryType === 'course'">
                                <i class="fa-solid fa-info-circle mr-2"></i>
                                Course libraries are automatically associated with a specific course.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Course Selection (conditional) -->
                    <div x-show="libraryType === 'course'">
                        <label for="course_id" class="block text-sm font-medium text-neutral-700 mb-1">Associated Course</label>
                        <select name="course_id" id="course_id" class="form-input w-full" x-bind:required="libraryType === 'course'">
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

                    <!-- AI Cover Option -->
                    <div>
                        <div class="flex items-center">
                            <input id="generate_cover" name="generate_cover" type="checkbox" class="form-checkbox h-4 w-4 text-primary-600">
                            <label for="generate_cover" class="ml-2 block text-sm text-neutral-700">
                                Generate AI cover image for this library
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-neutral-500 ml-6">Uses GPT-4o to create a custom cover image based on the library content and description.</p>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <div class="pt-6 flex justify-between">
                    <div></div> <!-- Empty div for spacing -->
                    <button type="button" class="btn btn-primary" @click="validateStep1()">
                        Continue to Add Content <i class="fa-solid fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Add Initial Content -->
            <div x-show="step === 2">
                <h2 class="text-xl font-medium text-neutral-900 mb-4">Add Content to Library</h2>
                
                <div x-show="libraryType === 'course'" class="p-4 bg-primary-50 rounded-md mb-4">
                    <p class="text-sm text-primary-800"><i class="fa-solid fa-info-circle mr-2"></i> Course libraries are automatically populated with content from the selected course. You can add additional content after creating the library.</p>
                </div>
                
                <div x-show="libraryType === 'dynamic'" class="p-4 bg-primary-50 rounded-md mb-4">
                    <p class="text-sm text-primary-800"><i class="fa-solid fa-info-circle mr-2"></i> Dynamic libraries are populated automatically by AI. You can review and modify the content after creating the library.</p>
                </div>
                
                <!-- Content selection for curated libraries -->
                <div x-show="libraryType === 'curated'" x-data="{ activeTab: 'courses', selectedContent: [] }" class="border border-neutral-200 rounded-md">
                    <!-- Content Type Tabs -->
                    <div class="border-b border-neutral-200 bg-neutral-50 p-4">
                        <nav class="flex space-x-4" aria-label="Tabs">
                            <button type="button" @click="activeTab = 'courses'" 
                                :class="{'text-primary-600 border-b-2 border-primary-600': activeTab === 'courses', 'text-neutral-500 hover:text-neutral-700': activeTab !== 'courses'}"
                                class="px-3 py-2 font-medium text-sm">
                                Courses
                            </button>
                            <button type="button" @click="activeTab = 'posts'" 
                                :class="{'text-primary-600 border-b-2 border-primary-600': activeTab === 'posts', 'text-neutral-500 hover:text-neutral-700': activeTab !== 'posts'}"
                                class="px-3 py-2 font-medium text-sm">
                                Posts
                            </button>
                        </nav>
                        
                        <!-- Search and Filter -->
                        <div class="mt-3">
                            <input type="text" placeholder="Search..." id="content-search" class="form-input w-full max-w-sm">
                        </div>
                    </div>
                    
                    <!-- Content Selection Area -->
                    <div class="p-4">
                        <!-- Courses Tab -->
                        <div x-show="activeTab === 'courses'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($courses as $course)
                            <div class="border border-neutral-200 rounded-md p-4 hover:bg-neutral-50 transition-colors content-item" data-title="{{ $course->title }}" data-id="{{ $course->id }}" data-type="Course">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 pt-1">
                                        <input type="checkbox" class="form-checkbox h-5 w-5 text-primary-600 content-checkbox" 
                                               onchange="toggleContentSelection(this, '{{ $course->id }}', 'Course', '{{ $course->title }}')">
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <h3 class="text-lg font-medium text-neutral-900">{{ $course->title }}</h3>
                                        <p class="text-sm text-neutral-500 mt-1">{{ substr($course->description ?? 'No description available', 0, 100) }}{{ strlen($course->description ?? '') > 100 ? '...' : '' }}</p>
                                        
                                        <div class="flex justify-between items-center mt-4">
                                            <span class="text-xs text-neutral-500">Created: {{ $course->created_at->format('M d, Y') }}</span>
                                            <div>
                                                <label for="relevance-{{ $course->id }}" class="text-xs text-neutral-600">Relevance:</label>
                                                <select id="relevance-{{ $course->id }}" class="form-input py-1 text-xs relevance-select" 
                                                        data-id="{{ $course->id }}" data-type="Course">
                                                    <option value="1">100%</option>
                                                    <option value="0.9">90%</option>
                                                    <option value="0.8">80%</option>
                                                    <option value="0.7" selected>70%</option>
                                                    <option value="0.6">60%</option>
                                                    <option value="0.5">50%</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        
                        <!-- Posts Tab -->
                        <div x-show="activeTab === 'posts'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($posts as $post)
                            <div class="border border-neutral-200 rounded-md p-4 hover:bg-neutral-50 transition-colors content-item" data-title="{{ $post->title }}" data-id="{{ $post->id }}" data-type="Post">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 pt-1">
                                        <input type="checkbox" class="form-checkbox h-5 w-5 text-primary-600 content-checkbox" 
                                               onchange="toggleContentSelection(this, '{{ $post->id }}', 'Post', '{{ $post->title }}')">
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <h3 class="text-lg font-medium text-neutral-900">{{ $post->title ?? 'Untitled Post' }}</h3>
                                        <p class="text-sm text-neutral-500 mt-1">{{ substr($post->body ?? 'No content available', 0, 100) }}{{ strlen($post->body ?? '') > 100 ? '...' : '' }}</p>
                                        
                                        <div class="flex justify-between items-center mt-4">
                                            <span class="text-xs text-neutral-500">Created: {{ $post->created_at->format('M d, Y') }}</span>
                                            <div>
                                                <label for="relevance-{{ $post->id }}" class="text-xs text-neutral-600">Relevance:</label>
                                                <select id="relevance-{{ $post->id }}" class="form-input py-1 text-xs relevance-select" 
                                                        data-id="{{ $post->id }}" data-type="Post">
                                                    <option value="1">100%</option>
                                                    <option value="0.9">90%</option>
                                                    <option value="0.8">80%</option>
                                                    <option value="0.7" selected>70%</option>
                                                    <option value="0.6">60%</option>
                                                    <option value="0.5">50%</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Selected Content Counter -->
                    <div class="p-4 border-t border-neutral-200 bg-neutral-50">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-sm font-medium text-neutral-700">Selected:</span>
                                <span id="selected-counter" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">0 items</span>
                            </div>
                            <div class="text-sm text-neutral-500">
                                <span>Select at least 5 items for best results</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden inputs to store selected content -->
                <div id="selected-content-inputs"></div>
                
                <!-- Navigation Buttons -->
                <div class="pt-6 flex justify-between">
                    <button type="button" class="btn btn-secondary" @click="step = 1">
                        <i class="fa-solid fa-arrow-left mr-2"></i> Back to Details
                    </button>
                    <button type="button" class="btn btn-primary" @click="step = 3">
                        Continue to Review <i class="fa-solid fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Review & Submit -->
            <div x-show="step === 3">
                <h2 class="text-xl font-medium text-neutral-900 mb-4">Review Library</h2>
                
                <div class="border border-neutral-200 rounded-md overflow-hidden">
                    <div class="p-4 bg-neutral-50 border-b border-neutral-200">
                        <h3 class="text-lg font-medium text-neutral-900">Library Details</h3>
                    </div>
                    
                    <div class="p-4">
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Name</dt>
                                <dd class="mt-1 text-sm text-neutral-900" id="review-name"></dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-neutral-500">Type</dt>
                                <dd class="mt-1 text-sm text-neutral-900" id="review-type"></dd>
                            </div>
                            
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-neutral-500">Description</dt>
                                <dd class="mt-1 text-sm text-neutral-900" id="review-description"></dd>
                            </div>
                            
                            <div x-show="libraryType === 'course'">
                                <dt class="text-sm font-medium text-neutral-500">Associated Course</dt>
                                <dd class="mt-1 text-sm text-neutral-900" id="review-course"></dd>
                            </div>
                        </dl>
                    </div>
                </div>
                
                <!-- Selected Content Summary for curated libraries -->
                <div x-show="libraryType === 'curated'" class="mt-6 border border-neutral-200 rounded-md overflow-hidden">
                    <div class="p-4 bg-neutral-50 border-b border-neutral-200 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-neutral-900">Selected Content</h3>
                        <span id="review-count" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">0 items</span>
                    </div>
                    
                    <div class="p-4">
                        <div id="review-content-list" class="space-y-3">
                            <!-- Content items will be inserted here via JS -->
                        </div>
                        
                        <div id="empty-content-message" class="text-center py-4 text-neutral-500">
                            <p>No content items selected. You can add content after creating the library.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <div class="pt-6 flex justify-between">
                    <button type="button" class="btn btn-secondary" @click="step = 2">
                        <i class="fa-solid fa-arrow-left mr-2"></i> Back to Content Selection
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save mr-2"></i> Create Library
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Global selected content array
    let selectedContent = [];
    
    function toggleCourseField() {
        const type = document.getElementById('type').value;
        
        // Show/hide help text
        document.querySelector('.curated-help').classList.toggle('hidden', type !== 'curated');
        document.querySelector('.dynamic-help').classList.toggle('hidden', type !== 'dynamic');
        document.querySelector('.course-help').classList.toggle('hidden', type !== 'course');
    }
    
    function toggleContentSelection(checkbox, id, type, title) {
        if (checkbox.checked) {
            // Get relevance score
            const relevanceSelect = document.getElementById(`relevance-${id}`);
            const relevanceScore = relevanceSelect.value;
            
            // Add to selected content
            selectedContent.push({
                id: id,
                type: type,
                title: title,
                relevance_score: relevanceScore
            });
        } else {
            // Remove from selected content
            selectedContent = selectedContent.filter(item => !(item.id === id && item.type === type));
        }
        
        // Update counter
        document.getElementById('selected-counter').textContent = `${selectedContent.length} items`;
        
        // Update hidden inputs
        updateSelectedContentInputs();
    }
    
    function updateSelectedContentInputs() {
        const container = document.getElementById('selected-content-inputs');
        container.innerHTML = '';
        
        selectedContent.forEach((item, index) => {
            // Create hidden inputs for each selected item
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = `selected_content[${index}][id]`;
            idInput.value = item.id;
            
            const typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = `selected_content[${index}][type]`;
            typeInput.value = item.type;
            
            const relevanceInput = document.createElement('input');
            relevanceInput.type = 'hidden';
            relevanceInput.name = `selected_content[${index}][relevance_score]`;
            relevanceInput.value = item.relevance_score;
            
            container.appendChild(idInput);
            container.appendChild(typeInput);
            container.appendChild(relevanceInput);
        });
    }
    
    // Update review screen with form data
    function updateReviewScreen() {
        // Update library details
        document.getElementById('review-name').textContent = document.getElementById('name').value;
        
        const type = document.getElementById('type').value;
        document.getElementById('review-type').textContent = type.charAt(0).toUpperCase() + type.slice(1);
        
        document.getElementById('review-description').textContent = document.getElementById('description').value;
        
        // Update course info if applicable
        if (type === 'course') {
            const courseSelect = document.getElementById('course_id');
            const courseTitle = courseSelect.options[courseSelect.selectedIndex].text;
            document.getElementById('review-course').textContent = courseTitle;
        }
        
        // Update selected content list
        updateSelectedContentReview();
    }
    
    function updateSelectedContentReview() {
        const contentList = document.getElementById('review-content-list');
        const emptyMessage = document.getElementById('empty-content-message');
        const countElement = document.getElementById('review-count');
        
        // Update count
        countElement.textContent = `${selectedContent.length} items`;
        
        // Clear list
        contentList.innerHTML = '';
        
        // Show/hide empty message
        if (selectedContent.length === 0) {
            emptyMessage.style.display = 'block';
            contentList.style.display = 'none';
            return;
        } else {
            emptyMessage.style.display = 'none';
            contentList.style.display = 'block';
        }
        
        // Add items to list
        selectedContent.forEach(item => {
            const itemElement = document.createElement('div');
            itemElement.className = 'flex justify-between items-center p-2 border border-neutral-100 rounded';
            
            const contentInfo = document.createElement('div');
            contentInfo.innerHTML = `
                <p class="text-sm font-medium">${item.title}</p>
                <p class="text-xs text-neutral-500">${item.type}</p>
            `;
            
            const relevanceIndicator = document.createElement('div');
            relevanceIndicator.className = 'text-xs bg-primary-100 text-primary-800 px-2 py-1 rounded';
            relevanceIndicator.textContent = `${Math.round(item.relevance_score * 100)}% relevance`;
            
            itemElement.appendChild(contentInfo);
            itemElement.appendChild(relevanceIndicator);
            contentList.appendChild(itemElement);
        });
    }
    
    // Filter content items based on search input
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('content-search');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const contentItems = document.querySelectorAll('.content-item');
                
                contentItems.forEach(item => {
                    const title = item.getAttribute('data-title').toLowerCase();
                    if (title.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        
        // Handle relevance score changes
        document.querySelectorAll('.relevance-select').forEach(select => {
            select.addEventListener('change', function() {
                const id = this.getAttribute('data-id');
                const type = this.getAttribute('data-type');
                
                // Update relevance score in selectedContent array
                const contentItem = selectedContent.find(item => item.id === id && item.type === type);
                if (contentItem) {
                    contentItem.relevance_score = this.value;
                    updateSelectedContentInputs();
                }
            });
        });
    });
    
    // AlpineJS functions
    function validateStep1() {
        const form = document.getElementById('createLibraryForm');
        const nameInput = document.getElementById('name');
        const descInput = document.getElementById('description');
        const typeInput = document.getElementById('type');
        const courseInput = document.getElementById('course_id');
        
        // Check required fields
        if (!nameInput.value) {
            alert('Please enter a library name');
            nameInput.focus();
            return;
        }
        
        if (!descInput.value) {
            alert('Please enter a library description');
            descInput.focus();
            return;
        }
        
        if (!typeInput.value) {
            alert('Please select a library type');
            typeInput.focus();
            return;
        }
        
        // Check course selection for course libraries
        if (typeInput.value === 'course' && !courseInput.value) {
            alert('Please select a course for this library');
            courseInput.focus();
            return;
        }
        
        // If all validations pass, move to step 2
        const step = Alpine.store('step');
        Alpine.store('step', 2);
    }
    
    // Initialize Alpine.js data
    document.addEventListener('alpine:init', () => {
        Alpine.data('libraryForm', () => ({
            step: 1,
            libraryType: '',
            
            next() {
                if (this.step === 1) {
                    this.validateStep1();
                } else if (this.step === 2) {
                    updateReviewScreen();
                    this.step = 3;
                }
            },
            
            back() {
                if (this.step > 1) {
                    this.step--;
                }
            },
            
            validateStep1() {
                // Validation logic here
                this.step = 2;
            }
        }));
    });
    
    // Update review screen when proceeding to step 3
    document.addEventListener('DOMContentLoaded', function() {
        const createLibraryForm = document.getElementById('createLibraryForm');
        const buttons = createLibraryForm.querySelectorAll('button[type="button"]');
        
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                if (this.textContent.includes('Continue to Review')) {
                    updateReviewScreen();
                }
            });
        });
    });
</script>
@endsection