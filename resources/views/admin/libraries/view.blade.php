@extends('admin.dashboard-layout')

@section('title', 'Library Details')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">Library Details</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.libraries.index') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Libraries
        </a>
        
        @if($library->approval_status === 'pending')
            <button type="button" class="btn btn-primary" onclick="openApproveModal()">
                <i class="fa-solid fa-check mr-2"></i> Approve
            </button>
            <button type="button" class="btn btn-danger" onclick="openRejectModal()">
                <i class="fa-solid fa-xmark mr-2"></i> Reject
            </button>
        @endif
        
        @if(!$library->has_ai_cover)
            <button type="button" class="btn btn-secondary" onclick="generateCover()">
                <i class="fa-solid fa-image mr-2"></i> Generate Cover
            </button>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Library details -->
    <div class="lg:col-span-1">
        <div class="card p-6">
            <div class="flex flex-col items-center mb-4">
                @if($library->cover_image_url)
                    <img src="{{ $library->cover_image_url }}" alt="{{ $library->name }}" class="w-full h-48 object-cover rounded-md mb-3">
                @elseif($library->thumbnail_url)
                    <img src="{{ $library->thumbnail_url }}" alt="{{ $library->name }}" class="w-full h-48 object-cover rounded-md mb-3">
                @else
                    <div class="w-full h-48 bg-primary-100 rounded-md flex items-center justify-center mb-3">
                        <i class="fa-solid fa-book-open text-primary-600 text-4xl"></i>
                    </div>
                @endif
                
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                    @if($library->approval_status === 'approved') bg-green-100 text-green-800
                    @elseif($library->approval_status === 'pending') bg-yellow-100 text-yellow-800
                    @else bg-red-100 text-red-800 @endif">
                    {{ ucfirst($library->approval_status) }}
                </span>
            </div>
            
            <div class="mb-4">
                <h2 class="text-lg font-medium text-neutral-900">{{ $library->name }}</h2>
                <p class="text-sm text-neutral-500">{{ $library->description }}</p>
            </div>
            
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600">Type</span>
                    <span class="text-sm font-medium">{{ ucfirst($library->type) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600">Content Items</span>
                    <span class="text-sm font-medium">{{ count($contentItems) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600">Created</span>
                    <span class="text-sm font-medium">{{ $library->created_at->format('M d, Y') }}</span>
                </div>
                
                @if($library->approval_status === 'approved')
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600">Approved by</span>
                    <span class="text-sm font-medium">{{ $library->approver ? $library->approver->name : 'Unknown' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-neutral-600">Approved on</span>
                    <span class="text-sm font-medium">{{ $library->approval_date->format('M d, Y') }}</span>
                </div>
                @endif
                
                @if($library->approval_status === 'rejected' && $library->rejection_reason)
                <div>
                    <span class="text-sm text-neutral-600">Rejection Reason</span>
                    <p class="text-sm text-red-600 mt-1">{{ $library->rejection_reason }}</p>
                </div>
                @endif
                
                @if($library->has_ai_cover && $library->cover_prompt)
                <div>
                    <span class="text-sm text-neutral-600">Cover Image Prompt</span>
                    <p class="text-sm text-neutral-500 mt-1">{{ $library->cover_prompt }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Content items -->
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-neutral-200 bg-neutral-50 flex justify-between items-center">
                <h3 class="text-lg font-medium text-neutral-900">Content Items</h3>
                <div class="flex items-center space-x-2">
                    <input type="text" placeholder="Search items..." class="form-input py-2 text-sm">
                    <a href="{{ route('admin.libraries.add-content-form', $library->id) }}" class="btn btn-primary">
                        <i class="fa-solid fa-plus mr-1"></i> Add Content
                    </a>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th scope="col" class="table-header">Title</th>
                            <th scope="col" class="table-header">Type</th>
                            <th scope="col" class="table-header">Relevance</th>
                            <th scope="col" class="table-header">Added</th>
                            <th scope="col" class="table-header text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-neutral-200">
                        @foreach($contentItems as $item)
                        <tr>
                            <td class="table-cell">
                                <div class="flex items-center">
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-neutral-900">{{ $item['title'] }}</div>
                                        <div class="text-xs text-neutral-500">By {{ $item['user'] ?? 'Unknown' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="table-cell">{{ $item['type'] }}</td>
                            <td class="table-cell">
                                <div class="relative pt-1">
                                    <div class="overflow-hidden h-2 text-xs flex rounded bg-neutral-200">
                                        <div style="width:{{ $item['relevance_score'] * 100 }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-primary-500"></div>
                                    </div>
                                </div>
                                <div class="text-xs text-center mt-1">{{ round($item['relevance_score'] * 100) }}%</div>
                            </td>
                            <td class="table-cell">{{ \Carbon\Carbon::parse($item['created_at'])->format('M d, Y') }}</td>
                            <td class="table-cell text-right">
                                <a href="{{ $item['url'] }}" target="_blank" class="text-primary-600 hover:text-primary-900 mr-3">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                
                                <form action="{{ route('admin.libraries.remove-content', $library->id) }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="content_id" value="{{ $item['id'] }}">
                                    <button type="submit" onclick="return confirm('Are you sure you want to remove this item from the library?')" 
                                        class="text-red-600 hover:text-red-900">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed inset-0 bg-neutral-900 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-neutral-900 mb-4">Approve Library</h3>
        <p class="text-neutral-600 mb-4">Are you sure you want to approve this library? Once approved, it will be visible to all users.</p>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" id="generateCoverOnApprove" class="form-checkbox h-4 w-4 text-primary-600">
                <span class="ml-2 text-sm text-neutral-700">Generate AI cover image</span>
            </label>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button type="button" class="btn btn-secondary" onclick="closeApproveModal()">Cancel</button>
            <form action="{{ route('admin.libraries.approve', $library->id) }}" method="POST">
                @csrf
                <input type="hidden" name="generate_cover" id="generateCoverInput" value="0">
                <button type="submit" class="btn btn-primary">Approve Library</button>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-neutral-900 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-neutral-900 mb-4">Reject Library</h3>
        <p class="text-neutral-600 mb-4">Please provide a reason for rejecting this library:</p>
        
        <form action="{{ route('admin.libraries.reject', $library->id) }}" method="POST">
            @csrf
            <div class="mb-4">
                <textarea name="rejection_reason" rows="3" class="form-input w-full" placeholder="Rejection reason..." required></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Library</button>
            </div>
        </form>
    </div>
</div>

<!-- Cover Generation Modal -->
<div id="coverModal" class="hidden fixed inset-0 bg-neutral-900 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-neutral-900 mb-4">Generate Cover Image</h3>
        <p class="text-neutral-600 mb-4">Generate an AI cover image for this library using GPT-4o's DALL-E model?</p>
        
        <div class="mt-2 mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md text-sm text-yellow-700">
            <p><i class="fa-solid fa-triangle-exclamation mr-2"></i>This will use OpenAI API credits and may take a few moments to generate.</p>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button type="button" class="btn btn-secondary" onclick="closeCoverModal()">Cancel</button>
            <form action="{{ route('admin.libraries.generate-cover', $library->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">Generate Cover</button>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Approve Modal
    function openApproveModal() {
        document.getElementById('approveModal').classList.remove('hidden');
    }
    
    function closeApproveModal() {
        document.getElementById('approveModal').classList.add('hidden');
    }
    
    // Set generate cover input value based on checkbox
    document.getElementById('generateCoverOnApprove').addEventListener('change', function() {
        document.getElementById('generateCoverInput').value = this.checked ? '1' : '0';
    });
    
    // Reject Modal
    function openRejectModal() {
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
    
    // Cover Generation Modal
    function generateCover() {
        document.getElementById('coverModal').classList.remove('hidden');
    }
    
    function closeCoverModal() {
        document.getElementById('coverModal').classList.add('hidden');
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target.id === 'approveModal') {
            closeApproveModal();
        } else if (e.target.id === 'rejectModal') {
            closeRejectModal();
        } else if (e.target.id === 'coverModal') {
            closeCoverModal();
        }
    });
</script>
@endsection