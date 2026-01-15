@extends('admin.dashboard-layout')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div class="flex items-center">
        <a href="{{ route('admin.users.show', $user->id) }}" class="text-primary-600 mr-3">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h1 class="text-2xl font-semibold text-neutral-900">Edit User</h1>
    </div>
</div>

<div class="card">
    <div class="p-5 border-b border-neutral-200">
        <h3 class="text-lg font-medium text-neutral-900">User Information</h3>
    </div>
    <div class="p-5">
        <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-neutral-700 mb-1">Username</label>
                        <input type="text" name="username" id="username" value="{{ old('username', $user->username) }}" 
                            class="form-input w-full @error('username') border-red-300 @enderror">
                        @error('username')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-neutral-700 mb-1">Email Address</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" 
                            class="form-input w-full @error('email') border-red-300 @enderror">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-neutral-700 mb-1">First Name</label>
                        <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $user->first_name) }}" 
                            class="form-input w-full @error('first_name') border-red-300 @enderror">
                        @error('first_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-neutral-700 mb-1">Last Name</label>
                        <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $user->last_name) }}" 
                            class="form-input w-full @error('last_name') border-red-300 @enderror">
                        @error('last_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="space-y-6">
                    <div>
                        <label for="role" class="block text-sm font-medium text-neutral-700 mb-1">Role</label>
                        <select name="role" id="role" class="form-input w-full @error('role') border-red-300 @enderror">
                            <option value="user" {{ (old('role', $user->role) == 'user') ? 'selected' : '' }}>User</option>
                            <option value="educator" {{ (old('role', $user->role) == 'educator') ? 'selected' : '' }}>Educator</option>
                            <option value="admin" {{ (old('role', $user->role) == 'admin') ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="is_admin" class="flex items-center space-x-2">
                            <input type="checkbox" name="is_admin" id="is_admin" value="1" 
                                {{ (old('is_admin', $user->is_admin) == 1) ? 'checked' : '' }}
                                class="h-4 w-4 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm font-medium text-neutral-700">Administrator Access</span>
                        </label>
                        <p class="mt-1 text-xs text-neutral-500 pl-6">
                            Grants full access to all admin functions. Use with caution.
                        </p>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-neutral-700 mb-1">New Password</label>
                        <input type="password" name="password" id="password" 
                            class="form-input w-full @error('password') border-red-300 @enderror">
                        <p class="mt-1 text-xs text-neutral-500">
                            Leave blank to keep the current password.
                        </p>
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-neutral-700 mb-1">Confirm New Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-input w-full">
                    </div>
                </div>
            </div>
            
            <!-- Profile Information -->
            <div class="mt-8">
                <h4 class="text-base font-medium text-neutral-900 mb-4">Profile Information</h4>
                
                <div>
                    <label for="bio" class="block text-sm font-medium text-neutral-700 mb-1">Bio</label>
                    <textarea name="bio" id="bio" rows="4" 
                        class="form-input w-full @error('bio') border-red-300 @enderror">{{ old('bio', $user->profile->bio ?? '') }}</textarea>
                    @error('bio')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="mt-8 flex items-center justify-end space-x-3">
                <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection