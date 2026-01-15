@extends('admin.dashboard-layout')

@section('title', 'View Report')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">Report Details</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.reports.all') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Reports
        </a>
    </div>
</div>

<!-- Status Banner -->
<div class="mb-6">
    @if($report->status === 'pending')
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fa-solid fa-clock text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        This report is pending. Take action to resolve or reject it.
                    </p>
                </div>
            </div>
        </div>
    @elseif($report->status === 'resolved')
        <div class="bg-green-50 border-l-4 border-green-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fa-solid fa-check text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        This report has been resolved.
                    </p>
                </div>
            </div>
        </div>
    @elseif($report->status === 'rejected')
        <div class="bg-red-50 border-l-4 border-red-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fa-solid fa-xmark text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        This report has been rejected.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Report Information -->
    <div class="md:col-span-2">
        <div class="card">
            <div class="border-b border-neutral-200 p-4 bg-neutral-50">
                <h2 class="text-lg font-medium">Report Information</h2>
            </div>
            <div class="p-4">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500">Report ID</h3>
                        <p class="mt-1 text-neutral-900">#{{ $report->id }}</p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500">Reported Content</h3>
                        <div class="mt-1 p-3 bg-neutral-50 rounded-md border border-neutral-200">
                            @php
                                $typeParts = explode('\\', $report->reportable_type);
                                $typeName = end($typeParts);
                            @endphp
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-neutral-100 text-neutral-800 mb-2 inline-block">
                                {{ $typeName }}
                            </span>
                            
                            @if($typeName === 'Post')
                                <div class="mt-2">
                                    <h4 class="font-medium">{{ $report->reportable->title ?? 'No Title' }}</h4>
                                    <p class="text-neutral-600 mt-1">{{ $report->reportable->content ?? '' }}</p>
                                    
                                    @if($report->reportable->media)
                                        <div class="mt-2">
                                            <p class="text-sm text-neutral-500">Has media attachments</p>
                                        </div>
                                    @endif
                                </div>
                            @elseif($typeName === 'User')
                                <div class="mt-2 flex items-center">
                                    @if($report->reportable->avatar)
                                        <img src="{{ $report->reportable->avatar }}" alt="User avatar" class="h-10 w-10 rounded-full mr-3">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-primary-100 flex items-center justify-center mr-3">
                                            <span class="text-primary-600 font-medium">{{ substr($report->reportable->first_name ?? 'U', 0, 1) }}</span>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="font-medium">{{ $report->reportable->first_name ?? '' }} {{ $report->reportable->last_name ?? '' }}</p>
                                        <p class="text-sm text-neutral-500">{{ $report->reportable->email ?? '' }}</p>
                                    </div>
                                </div>
                            @elseif($typeName === 'Comment')
                                <div class="mt-2">
                                    <p class="text-neutral-600">{{ $report->reportable->content ?? 'No content available' }}</p>
                                </div>
                            @else
                                <div class="mt-2">
                                    <p class="text-neutral-600">Content details not available</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500">Reason for Report</h3>
                        <p class="mt-1 text-neutral-900 p-3 bg-neutral-50 rounded-md border border-neutral-200">{{ $report->reason }}</p>
                    </div>
                    
                    @if($report->notes)
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500">Additional Notes</h3>
                        <p class="mt-1 text-neutral-900 p-3 bg-neutral-50 rounded-md border border-neutral-200">{{ $report->notes }}</p>
                    </div>
                    @endif
                    
                    @if($report->status !== 'pending')
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500">Admin Notes</h3>
                        <p class="mt-1 text-neutral-900 p-3 bg-neutral-50 rounded-md border border-neutral-200">{{ $report->admin_notes ?? 'No admin notes' }}</p>
                    </div>
                    @endif
                    
                    <div>
                        <h3 class="text-sm font-medium text-neutral-500">Reported At</h3>
                        <p class="mt-1 text-neutral-900">{{ $report->created_at->format('F j, Y \a\t g:i a') }}</p>
                    </div>
                </div>
            </div>
        </div>
        
        @if($report->status === 'pending')
        <!-- Actions Form -->
        <div class="card mt-6">
            <div class="border-b border-neutral-200 p-4 bg-neutral-50">
                <h2 class="text-lg font-medium">Take Action</h2>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.reports.status', $report->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-4">
                        <label for="notes" class="block text-sm font-medium text-neutral-700 mb-1">Admin Notes</label>
                        <textarea name="notes" id="notes" rows="4" class="form-textarea" placeholder="Add notes about this report and any actions taken..."></textarea>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" name="status" value="resolved" class="btn btn-success flex-1">
                            <i class="fa-solid fa-check mr-2"></i> Resolve Report
                        </button>
                        
                        <button type="submit" name="status" value="rejected" class="btn btn-danger flex-1">
                            <i class="fa-solid fa-xmark mr-2"></i> Reject Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
    
    <!-- Reporter Information -->
    <div class="md:col-span-1">
        <div class="card">
            <div class="border-b border-neutral-200 p-4 bg-neutral-50">
                <h2 class="text-lg font-medium">Reporter Information</h2>
            </div>
            <div class="p-4">
                <div class="flex items-center mb-4">
                    @if($report->reporter->avatar)
                        <img src="{{ $report->reporter->avatar }}" alt="Reporter avatar" class="h-12 w-12 rounded-full mr-4">
                    @else
                        <div class="h-12 w-12 rounded-full bg-primary-100 flex items-center justify-center mr-4">
                            <span class="text-primary-600 font-medium">{{ substr($report->reporter->first_name ?? 'U', 0, 1) }}</span>
                        </div>
                    @endif
                    <div>
                        <h3 class="font-medium text-neutral-900">{{ $report->reporter->first_name ?? '' }} {{ $report->reporter->last_name ?? '' }}</h3>
                        <p class="text-sm text-neutral-500">{{ $report->reporter->email ?? '' }}</p>
                    </div>
                </div>
                
                <div class="border-t border-neutral-200 pt-4 mt-4">
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-medium text-neutral-500">User ID</h3>
                            <p class="mt-1 text-neutral-900">#{{ $report->reporter->id }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-neutral-500">Joined</h3>
                            <p class="mt-1 text-neutral-900">{{ $report->reporter->created_at->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-neutral-500">Reports Submitted</h3>
                            <p class="mt-1 text-neutral-900">
                                {{ App\Models\Report::where('reporter_id', $report->reporter->id)->count() }}
                            </p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-neutral-500">Status</h3>
                            <p class="mt-1">
                                @if($report->reporter->is_banned)
                                    <span class="badge badge-danger">Banned</span>
                                @else
                                    <span class="badge badge-success">Active</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <a href="{{ route('admin.users.show', $report->reporter->id) }}" class="btn btn-secondary w-full">
                        <i class="fa-solid fa-user mr-2"></i> View User Profile
                    </a>
                </div>
            </div>
        </div>
        
        @if($typeName === 'User')
        <!-- Actions for Reported User -->
        <div class="card mt-6">
            <div class="border-b border-neutral-200 p-4 bg-neutral-50">
                <h2 class="text-lg font-medium">Reported User Actions</h2>
            </div>
            <div class="p-4">
                <div class="space-y-4">
                    @if(!$report->reportable->is_banned)
                    <form action="{{ route('admin.users.ban', $report->reportable->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="reason" value="Banned due to report #{{ $report->id }}">
                        <button type="submit" class="btn btn-danger w-full">
                            <i class="fa-solid fa-ban mr-2"></i> Ban This User
                        </button>
                    </form>
                    @else
                    <form action="{{ route('admin.users.unban', $report->reportable->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success w-full">
                            <i class="fa-solid fa-check mr-2"></i> Unban This User
                        </button>
                    </form>
                    @endif
                    
                    <a href="{{ route('admin.users.show', $report->reportable->id) }}" class="btn btn-secondary w-full">
                        <i class="fa-solid fa-user mr-2"></i> View User Profile
                    </a>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@endsection