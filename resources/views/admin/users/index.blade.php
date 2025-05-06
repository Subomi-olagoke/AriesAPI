@extends('admin.dashboard-layout')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-neutral-900">User Management</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
        <button class="btn btn-primary" onclick="document.getElementById('exportForm').submit();">
            <i class="fa-solid fa-file-export mr-2"></i> Export
        </button>
    </div>
</div>

<!-- Search and Filters -->
<div class="card mb-6">
    <div class="p-5">
        <form action="{{ route('admin.users.index') }}" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-neutral-700 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" name="search" id="search" value="{{ $filters['search'] ?? '' }}" 
                            placeholder="Search by name, email, username..." 
                            class="form-input pl-9 w-full">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-search text-neutral-400"></i>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-neutral-700 mb-1">Role</label>
                    <select name="role" id="role" class="form-input w-full">
                        <option value="">All Roles</option>
                        <option value="user" {{ ($filters['role'] ?? '') == 'user' ? 'selected' : '' }}>User</option>
                        <option value="educator" {{ ($filters['role'] ?? '') == 'educator' ? 'selected' : '' }}>Educator</option>
                        <option value="admin" {{ ($filters['role'] ?? '') == 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-neutral-700 mb-1">Status</label>
                    <select name="status" id="status" class="form-input w-full">
                        <option value="">All Status</option>
                        <option value="active" {{ ($filters['status'] ?? '') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="banned" {{ ($filters['status'] ?? '') == 'banned' ? 'selected' : '' }}>Banned</option>
                        <option value="verified" {{ ($filters['status'] ?? '') == 'verified' ? 'selected' : '' }}>Verified</option>
                        <option value="unverified" {{ ($filters['status'] ?? '') == 'unverified' ? 'selected' : '' }}>Unverified</option>
                    </select>
                </div>
            </div>
            
            <!-- Advanced Filters (Collapsible) -->
            <div x-data="{ showAdvanced: false }">
                <button type="button" @click="showAdvanced = !showAdvanced" class="text-sm text-primary-600 hover:text-primary-700 flex items-center">
                    <i class="fa-solid" :class="showAdvanced ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                    <span class="ml-2">Advanced Filters</span>
                </button>
                
                <div x-show="showAdvanced" x-cloak class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="is_admin" class="block text-sm font-medium text-neutral-700 mb-1">Admin Status</label>
                        <select name="is_admin" id="is_admin" class="form-input w-full">
                            <option value="">All Users</option>
                            <option value="true" {{ ($filters['is_admin'] ?? '') == 'true' ? 'selected' : '' }}>Admins Only</option>
                            <option value="false" {{ ($filters['is_admin'] ?? '') == 'false' ? 'selected' : '' }}>Non-Admins Only</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="has_profile" class="block text-sm font-medium text-neutral-700 mb-1">Profile Status</label>
                        <select name="has_profile" id="has_profile" class="form-input w-full">
                            <option value="">All Users</option>
                            <option value="true" {{ ($filters['has_profile'] ?? '') == 'true' ? 'selected' : '' }}>With Profile</option>
                            <option value="false" {{ ($filters['has_profile'] ?? '') == 'false' ? 'selected' : '' }}>Without Profile</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-neutral-700 mb-1">Sort By</label>
                        <select name="sort_by" id="sort_by" class="form-input w-full">
                            <option value="created_at" {{ ($filters['sort_by'] ?? 'created_at') == 'created_at' ? 'selected' : '' }}>Registration Date</option>
                            <option value="username" {{ ($filters['sort_by'] ?? '') == 'username' ? 'selected' : '' }}>Username</option>
                            <option value="email" {{ ($filters['sort_by'] ?? '') == 'email' ? 'selected' : '' }}>Email</option>
                            <option value="first_name" {{ ($filters['sort_by'] ?? '') == 'first_name' ? 'selected' : '' }}>First Name</option>
                            <option value="last_name" {{ ($filters['sort_by'] ?? '') == 'last_name' ? 'selected' : '' }}>Last Name</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="sort_dir" class="block text-sm font-medium text-neutral-700 mb-1">Sort Direction</label>
                        <select name="sort_dir" id="sort_dir" class="form-input w-full">
                            <option value="desc" {{ ($filters['sort_dir'] ?? 'desc') == 'desc' ? 'selected' : '' }}>Descending</option>
                            <option value="asc" {{ ($filters['sort_dir'] ?? '') == 'asc' ? 'selected' : '' }}>Ascending</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="created_after" class="block text-sm font-medium text-neutral-700 mb-1">Created After</label>
                        <input type="date" name="created_after" id="created_after" value="{{ $filters['created_after'] ?? '' }}" class="form-input w-full">
                    </div>
                    
                    <div>
                        <label for="created_before" class="block text-sm font-medium text-neutral-700 mb-1">Created Before</label>
                        <input type="date" name="created_before" id="created_before" value="{{ $filters['created_before'] ?? '' }}" class="form-input w-full">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between">
                <a href="{{ route('admin.users.index') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Reset Filters</a>
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
        <a href="{{ route('admin.users.index', array_merge($filters, ['search' => ''])) }}" class="ml-1 text-neutral-500 hover:text-neutral-700">
            <i class="fa-solid fa-xmark"></i>
        </a>
    </span>
    @endif
    
    @if(!empty($filters['role']))
    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-neutral-100 text-neutral-800 mr-2 mb-2">
        Role: {{ ucfirst($filters['role']) }}
        <a href="{{ route('admin.users.index', array_merge($filters, ['role' => ''])) }}" class="ml-1 text-neutral-500 hover:text-neutral-700">
            <i class="fa-solid fa-xmark"></i>
        </a>
    </span>
    @endif
    
    @if(!empty($filters['status']))
    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-neutral-100 text-neutral-800 mr-2 mb-2">
        Status: {{ ucfirst($filters['status']) }}
        <a href="{{ route('admin.users.index', array_merge($filters, ['status' => ''])) }}" class="ml-1 text-neutral-500 hover:text-neutral-700">
            <i class="fa-solid fa-xmark"></i>
        </a>
    </span>
    @endif
    
    <!-- Add other active filters here -->
    
    <a href="{{ route('admin.users.index') }}" class="ml-auto text-sm text-primary-600 hover:text-primary-700">Clear all</a>
</div>
@endif

<!-- Users Table -->
<div class="card overflow-hidden">
    <table class="min-w-full divide-y divide-neutral-200">
        <thead class="bg-neutral-50">
            <tr>
                <th scope="col" class="table-header">User</th>
                <th scope="col" class="table-header">Email</th>
                <th scope="col" class="table-header">Role</th>
                <th scope="col" class="table-header">Registered</th>
                <th scope="col" class="table-header">Status</th>
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
                                @if($user->is_admin)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 ml-1">
                                    Admin
                                </span>
                                @endif
                            </div>
                            <div class="text-sm text-neutral-500">{{ '@' . $user->username }}</div>
                        </div>
                    </div>
                </td>
                <td class="table-cell">
                    <div class="text-sm text-neutral-900">{{ $user->email }}</div>
                    @if($user->email_verified_at)
                    <div class="text-xs text-green-600">Verified</div>
                    @else
                    <div class="text-xs text-yellow-600">Unverified</div>
                    @endif
                </td>
                <td class="table-cell">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->role === 'educator' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800' }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td class="table-cell">
                    <div class="text-sm text-neutral-900">{{ $user->created_at->format('M d, Y') }}</div>
                    <div class="text-xs text-neutral-500">{{ $user->created_at->diffForHumans() }}</div>
                </td>
                <td class="table-cell">
                    @if($user->is_banned)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        Banned
                    </span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Active
                    </span>
                    @endif
                </td>
                <td class="table-cell text-right">
                    <div class="flex justify-end space-x-2">
                        <a href="{{ route('admin.users.show', $user->id) }}" class="text-primary-600 hover:text-primary-900" title="View Details">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.users.edit', $user->id) }}" class="text-blue-600 hover:text-blue-900" title="Edit User">
                            <i class="fa-solid fa-pencil"></i>
                        </a>
                        @if(!$user->is_banned && !$user->is_admin)
                        <button onclick="showBanModal('{{ $user->id }}', '{{ $user->first_name }} {{ $user->last_name }}')" class="text-red-600 hover:text-red-900" title="Ban User">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                        @elseif($user->is_banned)
                        <form action="{{ route('admin.users.unban', $user->id) }}" method="POST" class="inline">
                            @csrf
                            @method('POST')
                            <button type="submit" class="text-green-600 hover:text-green-900" title="Unban User">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </form>
                        @endif
                        
                        @if(!$user->is_admin)
                        <form action="{{ route('admin.users.make-admin', $user->id) }}" method="POST" class="inline">
                            @csrf
                            @method('POST')
                            <button type="submit" class="text-purple-600 hover:text-purple-900" title="Make Admin">
                                <i class="fa-solid fa-crown"></i>
                            </button>
                        </form>
                        @else
                        <form action="{{ route('admin.users.remove-admin', $user->id) }}" method="POST" class="inline">
                            @csrf
                            @method('POST')
                            <button type="submit" class="text-neutral-600 hover:text-neutral-900" title="Remove Admin">
                                <i class="fa-solid fa-user-minus"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-10 text-center text-neutral-500">
                    <div class="flex flex-col items-center">
                        <i class="fa-solid fa-users-slash text-4xl mb-3 text-neutral-300"></i>
                        <p>No users found matching your criteria.</p>
                        @if(array_filter($filters))
                        <a href="{{ route('admin.users.index') }}" class="mt-2 text-primary-600 hover:text-primary-700">Clear filters and try again</a>
                        @endif
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
                    <span class="font-medium">{{ $users->total() }}</span> users
                </p>
            </div>
            <div>
                {{ $users->appends(request()->query())->links('admin.pagination', ['alignment' => 'right']) }}
            </div>
        </div>
    </div>
</div>

<!-- Ban User Modal -->
<div id="banUserModal" x-data="{ show: false, userId: '', userName: '' }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="show = false">
            <div class="absolute inset-0 bg-neutral-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form :action="'{{ route('admin.users.ban', '') }}/' + userId" method="POST">
                @csrf
                @method('POST')
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fa-solid fa-ban text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900" id="modal-title">
                                Ban User
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-neutral-500" x-text="'Are you sure you want to ban ' + userName + '? This will prevent the user from accessing their account.'"></p>
                                <div class="mt-4">
                                    <label for="ban_reason" class="block text-sm font-medium text-neutral-700 mb-1">Reason for Ban</label>
                                    <textarea id="ban_reason" name="reason" rows="3" required class="form-input w-full" placeholder="Provide a reason for banning this user..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Ban User
                    </button>
                    <button type="button" @click="show = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Export Form -->
<form id="exportForm" action="{{ route('admin.users.export') }}" method="POST" class="hidden">
    @csrf
    @foreach($filters as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>

<script>
    function showBanModal(userId, userName) {
        const modal = document.getElementById('banUserModal').__x.$data;
        modal.userId = userId;
        modal.userName = userName;
        modal.show = true;
    }
</script>
@endsection