@extends('admin.dashboard-layout')

@section('title', 'Add Content to Library')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">Add Content to Library</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.libraries.view', $library->id) }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Library
        </a>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow mb-6">
    <div class="flex items-center mb-4">
        <div class="mr-4 flex-shrink-0">
            @if($library->cover_image_url)
                <img src="{{ $library->cover_image_url }}" alt="{{ $library->name }}" class="h-24 w-24 object-cover rounded-md">
            @elseif($library->thumbnail_url)
                <img src="{{ $library->thumbnail_url }}" alt="{{ $library->name }}" class="h-24 w-24 object-cover rounded-md">
            @else
                <div class="h-24 w-24 bg-primary-100 rounded-md flex items-center justify-center">
                    <i class="fa-solid fa-book-open text-primary-600 text-3xl"></i>
                </div>
            @endif
        </div>
        <div>
            <h2 class="text-xl font-medium text-neutral-900">{{ $library->name }}</h2>
            <p class="text-neutral-500">{{ $library->description }}</p>
            <div class="flex items-center mt-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                    @if($library->approval_status === 'approved') bg-green-100 text-green-800
                    @elseif($library->approval_status === 'pending') bg-yellow-100 text-yellow-800
                    @else bg-red-100 text-red-800 @endif">
                    {{ ucfirst($library->approval_status) }}
                </span>
                <span class="ml-4 text-sm text-neutral-600">{{ $library->contents->count() }} items</span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="p-4 border-b border-neutral-200">
        <div x-data="{ activeTab: 'courses' }">
            <!-- Content Type Tabs -->
            <div class="border-b border-neutral-200">
                <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                    <button @click="activeTab = 'courses'" 
                        :class="{'border-primary-600 text-primary-600': activeTab === 'courses', 'border-transparent text-neutral-600 hover:text-neutral-800 hover:border-neutral-300': activeTab !== 'courses'}"
                        class="pb-3 px-1 border-b-2 font-medium text-sm">
                        Courses
                    </button>
                    <button @click="activeTab = 'posts'" 
                        :class="{'border-primary-600 text-primary-600': activeTab === 'posts', 'border-transparent text-neutral-600 hover:text-neutral-800 hover:border-neutral-300': activeTab !== 'posts'}"
                        class="pb-3 px-1 border-b-2 font-medium text-sm">
                        Posts
                    </button>
                </nav>
            </div>
            
            <!-- Search and Filter -->
            <div class="py-4">
                <div class="flex items-center">
                    <div class="relative flex-grow max-w-sm">
                        <input type="text" placeholder="Search..." id="content-search" class="form-input pr-10 w-full">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <i class="fa-solid fa-search text-neutral-400"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Courses Tab Panel -->
            <div x-show="activeTab === 'courses'" class="py-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @forelse($availableContent['courses'] as $course)
                    <div class="border border-neutral-200 rounded-md p-4 hover:bg-neutral-50 transition-colors">
                        <h3 class="text-lg font-medium text-neutral-900">{{ $course['title'] }}</h3>
                        <p class="text-sm text-neutral-500 my-2">{{ $course['description'] }}</p>
                        <div class="flex justify-between items-center mt-4">
                            <span class="text-xs text-neutral-500">Created: {{ \Carbon\Carbon::parse($course['created_at'])->format('M d, Y') }}</span>
                            <form action="{{ route('admin.libraries.add-content', $library->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="content_id" value="{{ $course['id'] }}">
                                <input type="hidden" name="content_type" value="{{ $course['type'] }}">
                                <div class="flex items-center">
                                    <div class="mr-3">
                                        <label for="relevance-{{ $course['id'] }}" class="text-xs text-neutral-600">Relevance:</label>
                                        <select id="relevance-{{ $course['id'] }}" name="relevance_score" class="form-input py-1 text-xs">
                                            <option value="1">100%</option>
                                            <option value="0.9">90%</option>
                                            <option value="0.8">80%</option>
                                            <option value="0.7" selected>70%</option>
                                            <option value="0.6">60%</option>
                                            <option value="0.5">50%</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary py-1 text-sm">
                                        <i class="fa-solid fa-plus mr-1"></i> Add
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div class="col-span-full p-8 text-center">
                        <p class="text-neutral-500">No courses found</p>
                    </div>
                    @endforelse
                </div>
            </div>
            
            <!-- Posts Tab Panel -->
            <div x-show="activeTab === 'posts'" class="py-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @forelse($availableContent['posts'] as $post)
                    <div class="border border-neutral-200 rounded-md p-4 hover:bg-neutral-50 transition-colors">
                        <h3 class="text-lg font-medium text-neutral-900">{{ $post['title'] }}</h3>
                        <p class="text-sm text-neutral-500 my-2">{{ $post['description'] }}...</p>
                        <div class="flex justify-between items-center mt-4">
                            <span class="text-xs text-neutral-500">Created: {{ \Carbon\Carbon::parse($post['created_at'])->format('M d, Y') }}</span>
                            <form action="{{ route('admin.libraries.add-content', $library->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="content_id" value="{{ $post['id'] }}">
                                <input type="hidden" name="content_type" value="{{ $post['type'] }}">
                                <div class="flex items-center">
                                    <div class="mr-3">
                                        <label for="relevance-{{ $post['id'] }}" class="text-xs text-neutral-600">Relevance:</label>
                                        <select id="relevance-{{ $post['id'] }}" name="relevance_score" class="form-input py-1 text-xs">
                                            <option value="1">100%</option>
                                            <option value="0.9">90%</option>
                                            <option value="0.8">80%</option>
                                            <option value="0.7" selected>70%</option>
                                            <option value="0.6">60%</option>
                                            <option value="0.5">50%</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary py-1 text-sm">
                                        <i class="fa-solid fa-plus mr-1"></i> Add
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div class="col-span-full p-8 text-center">
                        <p class="text-neutral-500">No posts found</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Filter content items based on search input
    document.getElementById('content-search').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        
        // Filter courses
        document.querySelectorAll('[x-show="activeTab === \'courses\'"] .border').forEach(item => {
            const title = item.querySelector('h3').textContent.toLowerCase();
            const description = item.querySelector('p').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Filter posts
        document.querySelectorAll('[x-show="activeTab === \'posts\'"] .border').forEach(item => {
            const title = item.querySelector('h3').textContent.toLowerCase();
            const description = item.querySelector('p').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
</script>
@endsection