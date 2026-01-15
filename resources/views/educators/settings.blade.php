@extends('educators.dashboard-layout')

@section('title', 'Settings')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Educator Settings</h1>
    </div>
    
    <!-- Profile Information Form -->
    <div class="card" id="profile-information">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-medium text-gray-900">Profile Information</h2>
        </div>
        <form action="{{ route('educator.settings.update') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf
            
            <div class="flex items-start space-x-6">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                    <div class="relative">
                        <div class="h-24 w-24 rounded-full overflow-hidden bg-gray-100">
                            @if($user->avatar)
                                <img src="{{ $user->avatar }}" alt="{{ $user->username }}" class="h-full w-full object-cover">
                            @else
                                <div class="h-full w-full flex items-center justify-center bg-primary-100">
                                    <span class="text-primary-800 text-xl font-medium">{{ substr($user->first_name, 0, 1) }}{{ substr($user->last_name, 0, 1) }}</span>
                                </div>
                            @endif
                        </div>
                        <label for="avatar" class="absolute bottom-0 right-0 bg-white rounded-full p-1 shadow-sm border border-gray-200 cursor-pointer">
                            <i class="fa-solid fa-camera text-gray-500"></i>
                            <input type="file" id="avatar" name="avatar" accept="image/*" class="sr-only">
                        </label>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 text-center">Click to change</p>
                </div>
                
                <!-- Form Fields -->
                <div class="flex-1 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-600">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required
                                class="form-input" placeholder="Enter your first name">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-600">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required
                                class="form-input" placeholder="Enter your last name">
                        </div>
                    </div>
                    
                    <div>
                        <label for="bio" class="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                        <textarea id="bio" name="bio" rows="3" class="form-input" placeholder="Tell students about yourself, your expertise, and teaching style">{{ old('bio', $profile->bio ?? '') }}</textarea>
                    </div>
                </div>
            </div>
            
            <div class="pt-3 border-t border-gray-200">
                <button type="submit" class="btn btn-primary">
                    Save Profile Information
                </button>
            </div>
        </form>
    </div>
    
    <!-- Payout Information Form -->
    <div class="card" id="payout-information">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-medium text-gray-900">Payout Information</h2>
        </div>
        <form action="{{ route('educator.settings.update') }}" method="POST" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="form_type" value="payout">
            
            <div class="space-y-4">
                <div>
                    <label for="hire_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate for 1:1 Sessions</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">₦</span>
                        </div>
                        <input type="number" name="hire_rate" id="hire_rate" min="0" step="0.01"
                            class="form-input pl-7 pr-12" placeholder="0.00"
                            value="{{ old('hire_rate', $profile->hire_rate ?? '') }}">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">per hour</span>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Setting this enables students to book 1:1 sessions with you.</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" 
                            value="{{ old('bank_name', $profile->bank_name ?? '') }}"
                            class="form-input" placeholder="Enter your bank name">
                    </div>
                    <div>
                        <label for="account_name" class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                        <input type="text" id="account_name" name="account_name" 
                            value="{{ old('account_name', $profile->account_name ?? '') }}"
                            class="form-input" placeholder="Enter account holder name">
                    </div>
                    <div>
                        <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                        <input type="text" id="account_number" name="account_number" 
                            value="{{ old('account_number', $profile->account_number ?? '') }}"
                            class="form-input" placeholder="Enter your account number">
                    </div>
                </div>
            </div>
            
            <div class="pt-3 border-t border-gray-200">
                <button type="submit" class="btn btn-primary">
                    Save Payout Information
                </button>
            </div>
        </form>
    </div>
    
    <!-- Notification Preferences Form -->
    <div class="card" id="notification-preferences">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-medium text-gray-900">Notification Preferences</h2>
        </div>
        <form action="{{ route('educator.settings.update') }}" method="POST" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="form_type" value="notifications">
            
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">New Enrollments</h3>
                        <p class="text-xs text-gray-500">Receive notifications when a student enrolls in your course</p>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_enrollments" name="notify_enrollments" value="1" 
                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                            {{ $profile && $profile->notify_enrollments ? 'checked' : '' }}>
                        <label for="notify_enrollments" class="ml-2 block text-sm text-gray-700">
                            Enable
                        </label>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Course Comments</h3>
                        <p class="text-xs text-gray-500">Receive notifications when a student comments on your course</p>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_comments" name="notify_comments" value="1" 
                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                            {{ $profile && $profile->notify_comments ? 'checked' : '' }}>
                        <label for="notify_comments" class="ml-2 block text-sm text-gray-700">
                            Enable
                        </label>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Course Ratings</h3>
                        <p class="text-xs text-gray-500">Receive notifications when a student rates your course</p>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_ratings" name="notify_ratings" value="1" 
                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                            {{ $profile && $profile->notify_ratings ? 'checked' : '' }}>
                        <label for="notify_ratings" class="ml-2 block text-sm text-gray-700">
                            Enable
                        </label>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Payment Notifications</h3>
                        <p class="text-xs text-gray-500">Receive notifications when you receive a payment</p>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_payments" name="notify_payments" value="1" 
                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                            {{ $profile && $profile->notify_payments ? 'checked' : '' }}>
                        <label for="notify_payments" class="ml-2 block text-sm text-gray-700">
                            Enable
                        </label>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Marketing Updates</h3>
                        <p class="text-xs text-gray-500">Receive tips and recommendations to improve your courses</p>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="notify_marketing" name="notify_marketing" value="1" 
                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                            {{ $profile && $profile->notify_marketing ? 'checked' : '' }}>
                        <label for="notify_marketing" class="ml-2 block text-sm text-gray-700">
                            Enable
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="pt-3 border-t border-gray-200">
                <button type="submit" class="btn btn-primary">
                    Save Notification Preferences
                </button>
            </div>
        </form>
    </div>
</div>
@endsection