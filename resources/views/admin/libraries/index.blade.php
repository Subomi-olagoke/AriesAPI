@extends('admin.dashboard-layout')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">Content Libraries</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.libraries.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus mr-2"></i> Create Library
        </a>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Search and Filters -->
<div class="card mb-6">
    <div class="p-5">
        <form action="{{ route('admin.libraries.index') }}" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-neutral-700 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" name="search" id="search" value="{{ $filters['search'] ?? '' }}" 
                            placeholder="Search by name or description..." 
                            class="form-input pl-9 w-full">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-search text-neutral-400"></i>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-neutral-700 mb-1">Status</label>
                    <select name="status" id="status" class="form-input w-full">
                        <option value="">All Status</option>
                        <option value="pending" {{ ($filters['status'] ?? '') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ ($filters['status'] ?? '') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ ($filters['status'] ?? '') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-neutral-700 mb-1">Sort By</label>
                    <select name="sort_by" id="sort_by" class="form-input w-full">
                        <option value="created_at" {{ ($filters['sort_by'] ?? 'created_at') == 'created_at' ? 'selected' : '' }}>Created Date</option>
                        <option value="name" {{ ($filters['sort_by'] ?? '') == 'name' ? 'selected' : '' }}>Name</option>
                        <option value="approval_status" {{ ($filters['sort_by'] ?? '') == 'approval_status' ? 'selected' : '' }}>Status</option>
                        <option value="contents_count" {{ ($filters['sort_by'] ?? '') == 'contents_count' ? 'selected' : '' }}>Content Count</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between">
                <a href="{{ route('admin.libraries.index') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Reset Filters</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Active Filters Summary -->
@if(array_filter($filters))
<div class="bg-white px-4 py-3 border border-neutral-200 rounded-md mb-4 flex items-center flex-wrap">
    <span class="text-sm text-neutral-600 mr-2 mb-2">Active Filters:</span>
    @if(!empty($filters['search']))
    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-neutral-100 text-neutral-800 mr-2 mb-2">
        Search: {{ $filters['search'] }}
        <a href="{{ route('admin.libraries.index', array_merge($filters, ['search' => ''])) }}" class="ml-1 text-neutral-500 hover:text-neutral-700">
            <i class="fa-solid fa-xmark"></i>
        </a>
    </span>
    @endif
    
    @if(!empty($filters['status']))
    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-neutral-100 text-neutral-800 mr-2 mb-2">
        Status: {{ ucfirst($filters['status']) }}
        <a href="{{ route('admin.libraries.index', array_merge($filters, ['status' => ''])) }}" class="ml-1 text-neutral-500 hover:text-neutral-700">
            <i class="fa-solid fa-xmark"></i>
        </a>
    </span>
    @endif
    
    <a href="{{ route('admin.libraries.index') }}" class="ml-auto text-sm text-primary-600 hover:text-primary-700">Clear all</a>
</div>
@endif

<!-- Libraries Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
    @forelse($libraries as $library)
    <div class="card overflow-hidden hover:shadow-md transition-shadow duration-200">
        <div class="h-40 bg-primary-50 relative">
            @if($library->cover_image_url)
                <img src="{{ $library->cover_image_url }}" alt="{{ $library->name }}" class="w-full h-full object-cover">
            @elseif($library->thumbnail_url)
                <img src="{{ $library->thumbnail_url }}" alt="{{ $library->name }}" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center">
                    <i class="fa-solid fa-book-open text-primary-600 text-4xl"></i>
                </div>
            @endif
            
            <div class="absolute top-2 right-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                    @if($library->approval_status === 'approved') bg-green-100 text-green-800
                    @elseif($library->approval_status === 'pending') bg-yellow-100 text-yellow-800
                    @else bg-red-100 text-red-800 @endif">
                    {{ ucfirst($library->approval_status) }}
                </span>
            </div>
        </div>
        <div class="p-4">
            <h3 class="text-lg font-medium text-neutral-900 truncate">{{ $library->name ?? 'Untitled Library' }}</h3>
            <p class="text-sm text-neutral-500 h-10 overflow-hidden">{{ $library->description ?? 'No description available' }}</p>
            
            <div class="mt-3 flex justify-between items-center">
                <div class="text-xs text-neutral-500">
                    <span>{{ $library->contents_count ?? 0 }} items</span>
                    <span class="mx-1">•</span>
                    <span>{{ $library->created_at ? $library->created_at->format('M d, Y') : 'Unknown date' }}</span>
                </div>
                <div class="flex space-x-1">
                    <a href="{{ route('admin.libraries.view', $library->id) }}" class="p-1 text-primary-600 hover:text-primary-800 rounded-full hover:bg-primary-50">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    
                    @if($library->approval_status === 'pending')
                    <form action="{{ route('admin.libraries.approve', $library->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="p-1 text-green-600 hover:text-green-800 rounded-full hover:bg-green-50" title="Approve Library">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </form>
                    
                    <button onclick="openRejectModal('{{ $library->id }}', '{{ $library->name }}')" class="p-1 text-red-600 hover:text-red-800 rounded-full hover:bg-red-50" title="Reject Library">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-full py-10 text-center">
        <div class="flex flex-col items-center">
            <i class="fa-solid fa-books text-4xl mb-3 text-neutral-300"></i>
            <p class="text-neutral-500">No libraries found matching your criteria.</p>
            @if(array_filter($filters))
            <a href="{{ route('admin.libraries.index') }}" class="mt-2 text-primary-600 hover:text-primary-700">Clear filters and try again</a>
            @endif
        </div>
    </div>
    @endforelse
</div>

<!-- Pagination -->
@if($libraries->count() > 0)
<div class="flex items-center justify-between">
    <div class="flex-1 flex justify-between sm:hidden">
        @if($libraries->onFirstPage())
        <span class="btn btn-secondary opacity-50 cursor-not-allowed">Previous</span>
        @else
        <a href="{{ $libraries->previousPageUrl() }}" class="btn btn-secondary">Previous</a>
        @endif
        
        @if($libraries->hasMorePages())
        <a href="{{ $libraries->nextPageUrl() }}" class="btn btn-secondary ml-3">Next</a>
        @else
        <span class="btn btn-secondary opacity-50 cursor-not-allowed ml-3">Next</span>
        @endif
    </div>
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-neutral-700">
                Showing <span class="font-medium">{{ $libraries->firstItem() ?? 0 }}</span> to 
                <span class="font-medium">{{ $libraries->lastItem() ?? 0 }}</span> of 
                <span class="font-medium">{{ $libraries->total() }}</span> libraries
            </p>
        </div>
        <div>
            {{ $libraries->appends(request()->query())->links('admin.pagination', ['alignment' => 'right']) }}
        </div>
    </div>
</div>
@endif

<!-- Reject Modal -->
<div id="rejectModal" x-data="{ show: false, libraryId: '', libraryName: '' }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="show = false">
            <div class="absolute inset-0 bg-neutral-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form :action="'{{ route('admin.libraries.reject', '') }}/' + libraryId" method="POST">
                @csrf
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fa-solid fa-xmark text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900" id="modal-title">
                                Reject Library
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-neutral-500" x-text="'Are you sure you want to reject the library \"' + libraryName + '\"?'"></p>
                                <div class="mt-4">
                                    <label for="rejection_reason" class="block text-sm font-medium text-neutral-700 mb-1">Reason for Rejection</label>
                                    <textarea id="rejection_reason" name="rejection_reason" rows="3" required class="form-input w-full" placeholder="Provide a reason for rejecting this library..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Reject
                    </button>
                    <button type="button" @click="show = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openRejectModal(libraryId, libraryName) {
        const modal = document.getElementById('rejectModal').__x.$data;
        modal.libraryId = libraryId;
        modal.libraryName = libraryName;
        modal.show = true;
    }
</script>
@endsection