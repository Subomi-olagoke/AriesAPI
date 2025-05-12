@extends('admin.dashboard-layout')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">Banned Users</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Users
        </a>
    </div>
</div>

<!-- Search -->
<div class="card mb-6">
    <div class="p-5">
        <form action="{{ route('admin.users.banned') }}" method="GET" class="flex">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ $search ?? '' }}" 
                    placeholder="Search banned users by name, email, username..." 
                    class="form-input pl-9 w-full">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fa-solid fa-search text-neutral-400"></i>
                </div>
            </div>
            <button type="submit" class="btn btn-primary ml-2">
                Search
            </button>
            @if($search)
            <a href="{{ route('admin.users.banned') }}" class="btn btn-secondary ml-2">
                Clear
            </a>
            @endif
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card overflow-hidden">
    <table class="min-w-full divide-y divide-neutral-200">
        <thead class="bg-neutral-50">
            <tr>
                <th scope="col" class="table-header">User</th>
                <th scope="col" class="table-header">Email</th>
                <th scope="col" class="table-header">Role</th>
                <th scope="col" class="table-header">Banned On</th>
                <th scope="col" class="table-header">Reason</th>
                <th scope="col" class="table-header text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-neutral-200">
            @forelse($users as $user)
            <tr>
                <td class="table-cell">
                    <div class="flex items-center">
                        <div class="h-10 w-10 flex-shrink-0">
                            @if($user->avatar)
                            <img class="h-10 w-10 rounded-full" src="{{ $user->avatar }}" alt="{{ $user->username }}">
                            @else
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-neutral-500">
                                <span class="text-xs font-medium leading-none text-white">{{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}</span>
                            </span>
                            @endif
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-neutral-900">
                                {{ $user->first_name }} {{ $user->last_name }}
                            </div>
                            <div class="text-sm text-neutral-500">{{ '@' . $user->username }}</div>
                        </div>
                    </div>
                </td>
                <td class="table-cell">{{ $user->email }}</td>
                <td class="table-cell">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->role === 'educator' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800' }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td class="table-cell">
                    <div class="text-sm text-neutral-900">{{ $user->banned_at->format('M d, Y') }}</div>
                    <div class="text-xs text-neutral-500">{{ $user->banned_at->diffForHumans() }}</div>
                </td>
                <td class="table-cell">
                    <div class="text-sm text-neutral-900 max-w-xs truncate" title="{{ $user->ban_reason }}">
                        {{ $user->ban_reason }}
                    </div>
                </td>
                <td class="table-cell text-right">
                    <div class="flex justify-end space-x-2">
                        <a href="{{ route('admin.users.show', $user->id) }}" class="text-primary-600 hover:text-primary-900">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <form action="{{ route('admin.users.unban', $user->id) }}" method="POST" class="inline">
                            @csrf
                            @method('POST')
                            <button type="submit" class="text-green-600 hover:text-green-900">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-10 text-center text-neutral-500">
                    <div class="flex flex-col items-center">
                        <i class="fa-solid fa-user-shield text-4xl mb-3 text-neutral-300"></i>
                        <p>No banned users found.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-neutral-200">
        <div class="flex-1 flex justify-between sm:hidden">
            @if($users->onFirstPage())
            <span class="btn btn-secondary opacity-50 cursor-not-allowed">Previous</span>
            @else
            <a href="{{ $users->previousPageUrl() }}" class="btn btn-secondary">Previous</a>
            @endif
            
            @if($users->hasMorePages())
            <a href="{{ $users->nextPageUrl() }}" class="btn btn-secondary ml-3">Next</a>
            @else
            <span class="btn btn-secondary opacity-50 cursor-not-allowed ml-3">Next</span>
            @endif
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-neutral-700">
                    Showing <span class="font-medium">{{ $users->firstItem() ?? 0 }}</span> to 
                    <span class="font-medium">{{ $users->lastItem() ?? 0 }}</span> of 
                    <span class="font-medium">{{ $users->total() }}</span> banned users
                </p>
            </div>
            <div>
                {{ $users->appends(request()->query())->links('admin.pagination', ['alignment' => 'right']) }}
            </div>
        </div>
    </div>
</div>
@endsection