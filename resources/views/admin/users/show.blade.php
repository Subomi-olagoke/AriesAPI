@extends('admin.dashboard-layout')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div class="flex items-center">
        <a href="{{ route('admin.users.index') }}" class="text-primary-600 mr-3">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h1 class="text-2xl font-semibold text-neutral-900">User Profile</h1>
    </div>
    <div class="flex space-x-2">
        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-secondary">
            <i class="fa-solid fa-pencil mr-2"></i> Edit User
        </a>
        
        @if(!$user->is_banned && !$user->is_admin)
        <button class="btn btn-danger" onclick="showBanModal('{{ $user->id }}', '{{ $user->first_name }} {{ $user->last_name }}')">
            <i class="fa-solid fa-ban mr-2"></i> Ban User
        </button>
        @elseif($user->is_banned)
        <form action="{{ route('admin.users.unban', $user->id) }}" method="POST">
            @csrf
            @method('POST')
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-check mr-2"></i> Unban User
            </button>
        </form>
        @endif
        
        <button class="btn btn-primary" onclick="showNotifyModal('{{ $user->id }}', '{{ $user->first_name }} {{ $user->last_name }}')">
            <i class="fa-solid fa-bell mr-2"></i> Send Notification
        </button>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- User Profile Card -->
    <div class="card">
        <div class="p-5 border-b border-neutral-200">
            <h3 class="text-lg font-medium text-neutral-900">User Information</h3>
        </div>
        <div class="p-5">
            <div class="flex flex-col items-center pb-5">
                @if($user->avatar)
                <img class="h-32 w-32 rounded-full mb-3" src="{{ $user->avatar }}" alt="{{ $user->username }}">
                @else
                <div class="h-32 w-32 rounded-full bg-neutral-500 flex items-center justify-center mb-3">
                    <span class="text-3xl font-medium text-white">{{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}</span>
                </div>
                @endif
                <h2 class="text-xl font-semibold text-neutral-900">{{ $user->first_name }} {{ $user->last_name }}</h2>
                <p class="text-neutral-500">{{ '@' . $user->username }}</p>
                
                <div class="flex mt-3 space-x-2">
                    @if($user->is_admin)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                        Admin
                    </span>
                    @endif
                    
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->role === 'educator' ? 'bg-blue-100 text-blue-800' : 'bg-neutral-100 text-neutral-800' }}">
                        {{ ucfirst($user->role) }}
                    </span>
                    
                    @if($user->is_banned)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        Banned
                    </span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Active
                    </span>
                    @endif
                </div>
            </div>
            
            <div class="space-y-3 border-t border-neutral-200 pt-5">
                <div>
                    <label class="text-sm font-medium text-neutral-500">Email</label>
                    <div class="flex items-center mt-1">
                        <p class="text-neutral-900">{{ $user->email }}</p>
                        @if($user->email_verified_at)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                            <i class="fa-solid fa-check mr-1"></i> Verified
                        </span>
                        @else
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                            <i class="fa-solid fa-exclamation-triangle mr-1"></i> Unverified
                        </span>
                        @endif
                    </div>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-neutral-500">Member Since</label>
                    <p class="text-neutral-900 mt-1">{{ $user->created_at->format('F j, Y') }}</p>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-neutral-500">Last Login</label>
                    <p class="text-neutral-900 mt-1">{{ $user->last_login_at ? $user->last_login_at->format('F j, Y g:i A') : 'Never' }}</p>
                </div>
                
                @if($user->is_banned)
                <div>
                    <label class="text-sm font-medium text-neutral-500">Banned On</label>
                    <p class="text-neutral-900 mt-1">{{ $user->banned_at->format('F j, Y g:i A') }}</p>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-neutral-500">Ban Reason</label>
                    <p class="text-neutral-900 mt-1">{{ $user->ban_reason }}</p>
                </div>
                @endif
                
                <div>
                    <label class="text-sm font-medium text-neutral-500">Bio</label>
                    <p class="text-neutral-900 mt-1">{{ $user->profile->bio ?? 'No bio available' }}</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Stats Card -->
    <div class="card md:col-span-2">
        <div class="p-5 border-b border-neutral-200">
            <h3 class="text-lg font-medium text-neutral-900">User Statistics</h3>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-5">
                <div class="text-center p-4 bg-neutral-50 rounded-lg">
                    <div class="text-2xl font-semibold text-neutral-900">{{ $stats['posts_count'] }}</div>
                    <div class="text-sm text-neutral-600">Posts</div>
                </div>
                
                <div class="text-center p-4 bg-neutral-50 rounded-lg">
                    <div class="text-2xl font-semibold text-neutral-900">{{ $stats['courses_count'] }}</div>
                    <div class="text-sm text-neutral-600">Courses</div>
                </div>
                
                <div class="text-center p-4 bg-neutral-50 rounded-lg">
                    <div class="text-2xl font-semibold text-neutral-900">{{ $stats['comments_count'] }}</div>
                    <div class="text-sm text-neutral-600">Comments</div>
                </div>
                
                <div class="text-center p-4 bg-neutral-50 rounded-lg">
                    <div class="text-2xl font-semibold text-neutral-900">{{ $stats['followers_count'] }}</div>
                    <div class="text-sm text-neutral-600">Followers</div>
                </div>
                
                <div class="text-center p-4 bg-neutral-50 rounded-lg">
                    <div class="text-2xl font-semibold text-neutral-900">{{ $stats['following_count'] }}</div>
                    <div class="text-sm text-neutral-600">Following</div>
                </div>
                
                <div class="text-center p-4 bg-neutral-50 rounded-lg">
                    <div class="text-2xl font-semibold text-neutral-900">{{ $stats['likes_count'] }}</div>
                    <div class="text-sm text-neutral-600">Likes</div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="mt-6">
                <h4 class="text-base font-medium text-neutral-900 mb-4">Recent Activity</h4>
                <div class="space-y-3">
                    @forelse($activities as $activity)
                    <div class="flex items-center p-3 bg-white border border-neutral-200 rounded-lg">
                        <div class="flex-shrink-0">
                            @if($activity['type'] == 'post')
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                                <i class="fa-solid fa-file-lines text-blue-600"></i>
                            </span>
                            @elseif($activity['type'] == 'comment')
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
                                <i class="fa-solid fa-comment text-green-600"></i>
                            </span>
                            @elseif($activity['type'] == 'like')
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-100">
                                <i class="fa-solid fa-heart text-red-600"></i>
                            </span>
                            @endif
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="text-sm font-medium text-neutral-900">
                                {{ ucfirst($activity['action']) }} {{ $activity['title'] }}
                            </div>
                            <div class="text-xs text-neutral-500">
                                {{ $activity['date']->diffForHumans() }}
                            </div>
                        </div>
                        <a href="{{ $activity['url'] }}" target="_blank" class="text-primary-600 hover:text-primary-700">
                            <i class="fa-solid fa-external-link"></i>
                        </a>
                    </div>
                    @empty
                    <div class="text-center py-6 text-neutral-500">
                        <i class="fa-regular fa-calendar-xmark text-3xl mb-2"></i>
                        <p>No recent activity found for this user.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Posts -->
    <div class="card md:col-span-3">
        <div class="p-5 border-b border-neutral-200">
            <h3 class="text-lg font-medium text-neutral-900">Recent Posts</h3>
        </div>
        <div>
            @forelse($user->posts()->latest()->take(5)->get() as $post)
            <div class="p-5 border-b border-neutral-200">
                <div class="flex items-start">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <h4 class="text-base font-medium text-neutral-900 mr-2">{{ $post->title ?? 'Post #' . $post->id }}</h4>
                            <span class="text-xs text-neutral-500">{{ $post->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="mt-1 text-neutral-600 line-clamp-2">
                            {{ $post->body }}
                        </div>
                        <div class="mt-2 flex items-center text-xs text-neutral-500">
                            <span class="flex items-center mr-3">
                                <i class="fa-solid fa-heart mr-1"></i> {{ $post->likes_count ?? 0 }} likes
                            </span>
                            <span class="flex items-center">
                                <i class="fa-solid fa-comment mr-1"></i> {{ $post->comments_count ?? 0 }} comments
                            </span>
                        </div>
                    </div>
                    <a href="{{ route('post.deep-link', $post->id) }}" target="_blank" class="text-primary-600 hover:text-primary-700 ml-4">
                        <i class="fa-solid fa-external-link"></i>
                    </a>
                </div>
            </div>
            @empty
            <div class="p-8 text-center text-neutral-500">
                <i class="fa-regular fa-file-lines text-3xl mb-2"></i>
                <p>This user hasn't created any posts yet.</p>
            </div>
            @endforelse
            
            @if($user->posts()->count() > 5)
            <div class="p-4 text-center">
                <a href="#" class="text-primary-600 hover:text-primary-700">View all posts</a>
            </div>
            @endif
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

<!-- Send Notification Modal -->
<div id="notifyUserModal" x-data="{ show: false, userId: '', userName: '' }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="show = false">
            <div class="absolute inset-0 bg-neutral-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form :action="'{{ route('admin.users.notify', '') }}/' + userId" method="POST">
                @csrf
                @method('POST')
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-primary-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fa-solid fa-bell text-primary-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900" id="modal-title">
                                Send Notification
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-neutral-500" x-text="'Send a notification to ' + userName"></p>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="subject" class="block text-sm font-medium text-neutral-700 mb-1">Subject</label>
                                        <input type="text" id="subject" name="subject" required class="form-input w-full" placeholder="Notification subject...">
                                    </div>
                                    
                                    <div>
                                        <label for="message" class="block text-sm font-medium text-neutral-700 mb-1">Message</label>
                                        <textarea id="message" name="message" rows="3" required class="form-input w-full" placeholder="Notification message..."></textarea>
                                    </div>
                                    
                                    <div>
                                        <label for="type" class="block text-sm font-medium text-neutral-700 mb-1">Type</label>
                                        <select id="type" name="type" class="form-input w-full">
                                            <option value="info">Information</option>
                                            <option value="warning">Warning</option>
                                            <option value="important">Important</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Send Notification
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
    function showBanModal(userId, userName) {
        const modal = document.getElementById('banUserModal').__x.$data;
        modal.userId = userId;
        modal.userName = userName;
        modal.show = true;
    }
    
    function showNotifyModal(userId, userName) {
        const modal = document.getElementById('notifyUserModal').__x.$data;
        modal.userId = userId;
        modal.userName = userName;
        modal.show = true;
    }
</script>
@endsection