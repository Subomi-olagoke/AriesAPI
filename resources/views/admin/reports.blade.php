@extends('admin.dashboard-layout')

@section('title', 'Reports Management')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">Reports Management</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="card p-5">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                <i class="fa-solid fa-clock text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-neutral-500">Pending Reports</p>
                <p class="text-2xl font-semibold">{{ $pendingReports }}</p>
            </div>
        </div>
    </div>
    
    <div class="card p-5">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                <i class="fa-solid fa-check text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-neutral-500">Resolved Reports</p>
                <p class="text-2xl font-semibold">{{ $resolvedReports }}</p>
            </div>
        </div>
    </div>
    
    <div class="card p-5">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                <i class="fa-solid fa-xmark text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-neutral-500">Rejected Reports</p>
                <p class="text-2xl font-semibold">{{ $rejectedReports }}</p>
            </div>
        </div>
    </div>
    
    <div class="card p-5">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                <i class="fa-solid fa-flag text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-neutral-500">Total Reports</p>
                <p class="text-2xl font-semibold">{{ $totalReports }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Reports by Type -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Reports Distribution -->
    <div class="card p-5 lg:col-span-1">
        <h2 class="text-lg font-medium mb-4">Reports by Type</h2>
        
        @if($reportsByType->count() > 0)
            <div class="space-y-4">
                @foreach($reportsByType as $type)
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-neutral-700">{{ $type->type_name }}</span>
                            <span class="text-sm text-neutral-500">{{ $type->count }}</span>
                        </div>
                        <div class="w-full bg-neutral-200 rounded-full h-2">
                            <div class="bg-primary-600 h-2 rounded-full" style="width: {{ ($type->count / $totalReports) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-6 text-neutral-500">
                No reports data available
            </div>
        @endif
    </div>

    <!-- Recent Reports List -->
    <div class="card lg:col-span-2">
        <div class="border-b border-neutral-200 p-4 bg-neutral-50">
            <h2 class="text-lg font-medium">Recent Reports</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200">
                <thead class="bg-neutral-50">
                    <tr>
                        <th class="table-header">Reported Item</th>
                        <th class="table-header">Reporter</th>
                        <th class="table-header">Reason</th>
                        <th class="table-header">Date</th>
                        <th class="table-header">Status</th>
                        <th class="table-header text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-neutral-200">
                    @forelse($recentReports as $report)
                    <tr>
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
                        <td colspan="6" class="table-cell text-center py-8 text-neutral-500">
                            No reports found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($recentReports->count() > 0)
        <div class="p-4 border-t border-neutral-200 bg-neutral-50 text-center">
            <a href="{{ route('admin.reports.all') }}" class="text-primary-600 hover:text-primary-800 text-sm font-medium">
                View All Reports <i class="fa-solid fa-arrow-right ml-1"></i>
            </a>
        </div>
        @endif
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Add any additional scripts here
</script>
@endsection