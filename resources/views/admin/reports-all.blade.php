@extends('admin.dashboard-layout')

@section('title', 'All Reports')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">All Reports</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.reports') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card p-5 mb-6">
    <form action="{{ route('admin.reports.all') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label for="status" class="block text-sm font-medium text-neutral-700 mb-1">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="pending" {{ $filters['status'] === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="resolved" {{ $filters['status'] === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="rejected" {{ $filters['status'] === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>
        <div>
            <label for="type" class="block text-sm font-medium text-neutral-700 mb-1">Type</label>
            <select name="type" id="type" class="form-select">
                <option value="">All Types</option>
                <option value="Post" {{ $filters['type'] === 'Post' ? 'selected' : '' }}>Posts</option>
                <option value="User" {{ $filters['type'] === 'User' ? 'selected' : '' }}>Users</option>
                <option value="Comment" {{ $filters['type'] === 'Comment' ? 'selected' : '' }}>Comments</option>
            </select>
        </div>
        <div>
            <label for="search" class="block text-sm font-medium text-neutral-700 mb-1">Search</label>
            <input type="text" name="search" id="search" class="form-input" placeholder="Search by reporter..." value="{{ $filters['search'] }}">
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn btn-primary w-full">
                <i class="fa-solid fa-filter mr-2"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Status Tabs -->
<div class="mb-6 border-b border-neutral-200">
    <nav class="flex -mb-px">
        <a href="{{ route('admin.reports.all') }}" class="py-4 px-6 border-b-2 {{ !$filters['status'] ? 'border-primary-600 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700' }} font-medium text-sm">
            All Reports <span class="ml-1 bg-neutral-100 text-neutral-700 py-0.5 px-2 rounded-full text-xs">{{ $reports->total() }}</span>
        </a>
        <a href="{{ route('admin.reports.all', ['status' => 'pending']) }}" class="py-4 px-6 border-b-2 {{ $filters['status'] === 'pending' ? 'border-primary-600 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700' }} font-medium text-sm">
            Pending <span class="ml-1 bg-yellow-100 text-yellow-700 py-0.5 px-2 rounded-full text-xs">{{ $pendingCount }}</span>
        </a>
        <a href="{{ route('admin.reports.all', ['status' => 'resolved']) }}" class="py-4 px-6 border-b-2 {{ $filters['status'] === 'resolved' ? 'border-primary-600 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700' }} font-medium text-sm">
            Resolved <span class="ml-1 bg-green-100 text-green-700 py-0.5 px-2 rounded-full text-xs">{{ $resolvedCount }}</span>
        </a>
        <a href="{{ route('admin.reports.all', ['status' => 'rejected']) }}" class="py-4 px-6 border-b-2 {{ $filters['status'] === 'rejected' ? 'border-primary-600 text-primary-600' : 'border-transparent text-neutral-500 hover:text-neutral-700' }} font-medium text-sm">
            Rejected <span class="ml-1 bg-red-100 text-red-700 py-0.5 px-2 rounded-full text-xs">{{ $rejectedCount }}</span>
        </a>
    </nav>
</div>

<!-- Reports Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
                <tr>
                    <th class="table-header">ID</th>
                    <th class="table-header">Reported Item</th>
                    <th class="table-header">Reporter</th>
                    <th class="table-header">Reason</th>
                    <th class="table-header">Date</th>
                    <th class="table-header">Status</th>
                    <th class="table-header text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
                @forelse($reports as $report)
                <tr>
                    <td class="table-cell">
                        #{{ $report->id }}
                    </td>
                    <td class="table-cell">
                        <div class="truncate max-w-xs">
                            @php
                                $typeParts = explode('\\', $report->reportable_type);
                                $typeName = end($typeParts);
                            @endphp
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-neutral-100 text-neutral-800">
                                {{ $typeName }}
                            </span>
                            {{ $report->reportable->title ?? $report->reportable->content ?? 'Item #' . $report->reportable_id }}
                        </div>
                    </td>
                    <td class="table-cell">
                        {{ $report->reporter->first_name ?? '' }} {{ $report->reporter->last_name ?? '' }}
                    </td>
                    <td class="table-cell">
                        <div class="truncate max-w-xs">{{ $report->reason }}</div>
                    </td>
                    <td class="table-cell">
                        {{ $report->created_at->format('M d, Y') }}
                    </td>
                    <td class="table-cell">
                        @if($report->status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                        @elseif($report->status === 'resolved')
                            <span class="badge badge-success">Resolved</span>
                        @elseif($report->status === 'rejected')
                            <span class="badge badge-danger">Rejected</span>
                        @endif
                    </td>
                    <td class="table-cell text-right">
                        <div class="flex justify-end space-x-2">
                            <a href="{{ route('admin.reports.view', $report->id) }}" class="text-primary-600 hover:text-primary-800">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            
                            @if($report->status === 'pending')
                                <form action="{{ route('admin.reports.status', $report->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="resolved">
                                    <button type="submit" class="text-green-600 hover:text-green-800" title="Resolve Report">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                                
                                <form action="{{ route('admin.reports.status', $report->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="text-red-600 hover:text-red-800" title="Reject Report">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="table-cell text-center py-8 text-neutral-500">
                        No reports found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="p-4 border-t border-neutral-200">
        {{ $reports->links() }}
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Add any additional scripts here
</script>
@endsection