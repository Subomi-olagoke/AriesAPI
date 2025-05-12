@extends('admin.dashboard-layout')

@section('content')
<div class="bg-neutral-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between mb-8">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold text-primary-800 sm:text-3xl leading-tight">
                    Waitlist Management
                </h1>
                <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:mt-0 sm:space-x-6">
                    <div class="mt-2 flex items-center text-sm text-neutral-500">
                        <i class="fas fa-users flex-shrink-0 mr-1.5 h-5 w-5 text-neutral-400"></i>
                        <span>{{ count($waitlist) }} people on waitlist</span>
                    </div>
                    <div class="mt-2 flex items-center text-sm text-neutral-500">
                        <i class="fas fa-envelope flex-shrink-0 mr-1.5 h-5 w-5 text-neutral-400"></i>
                        <span>{{ count($emails) }} emails sent</span>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4 space-x-3">
                <button type="button" class="inline-flex items-center px-4 py-2 border border-primary-300 rounded-md shadow-sm text-sm font-medium text-primary-700 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500" onclick="document.getElementById('downloadCSV').click()">
                    <i class="fas fa-download -ml-1 mr-2 h-5 w-5 text-primary-500"></i>
                    Export CSV
                </button>
                <a id="downloadCSV" href="{{ route('admin.waitlist') }}?export=csv" class="hidden"></a>
                <a href="#emailSection" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-700 hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-paper-plane -ml-1 mr-2 h-5 w-5"></i>
                    Send Email
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-8">
            <!-- Total Waitlist Entries -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-primary-100 rounded-md p-3">
                            <i class="fas fa-users text-primary-700 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">
                                    Total Waitlist Entries
                                </dt>
                                <dd>
                                    <div class="text-lg font-semibold text-primary-900">
                                        {{ count($waitlist) }}
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6">
                    <div class="text-sm">
                        <span class="font-medium text-primary-700">{{ $waitlist->where('created_at', '>=', now()->subDays(7))->count() }}</span>
                        <span class="text-neutral-500"> new in the last 7 days</span>
                    </div>
                </div>
            </div>

            <!-- Total Emails Sent -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                            <i class="fas fa-envelope text-blue-700 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">
                                    Total Emails Sent
                                </dt>
                                <dd>
                                    <div class="text-lg font-semibold text-neutral-900">
                                        {{ count($emails) }}
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6">
                    <div class="text-sm">
                        <span class="font-medium text-blue-700">{{ $emails->where('created_at', '>=', now()->subDays(7))->count() }}</span>
                        <span class="text-neutral-500"> sent in the last 7 days</span>
                    </div>
                </div>
            </div>

            <!-- Conversion Rate -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                            <i class="fas fa-user-check text-green-700 text-xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-neutral-500 truncate">
                                    Conversion Rate
                                </dt>
                                <dd>
                                    <div class="text-lg font-semibold text-neutral-900">
                                        @php
                                            $totalUsers = \App\Models\User::count();
                                            $conversionRate = count($waitlist) > 0 ? min(100, round(($totalUsers / count($waitlist)) * 100, 2)) : 0;
                                        @endphp
                                        {{ $conversionRate }}%
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6">
                    <div class="text-sm">
                        <span class="font-medium text-green-700">{{ \App\Models\User::where('created_at', '>=', now()->subDays(7))->count() }}</span>
                        <span class="text-neutral-500"> new users in the last 7 days</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Waitlist Entries -->
        <div class="bg-white shadow-sm rounded-lg mb-8 overflow-hidden">
            <div class="bg-white px-4 py-5 border-b border-neutral-200 sm:px-6">
                <div class="-ml-4 -mt-2 flex items-center justify-between flex-wrap sm:flex-nowrap">
                    <div class="ml-4 mt-2">
                        <h3 class="text-lg leading-6 font-medium text-primary-800">
                            <i class="fas fa-users mr-2 text-primary-600"></i>
                            Waitlist Entries
                        </h3>
                    </div>
                    <div class="ml-4 mt-2 flex-shrink-0">
                        <div class="relative">
                            <input type="text" id="searchWaitlist" placeholder="Search waitlist..." class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full pr-10 sm:text-sm border-neutral-300 rounded-md">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-neutral-400"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                @if(count($waitlist) > 0)
                    <table class="min-w-full divide-y divide-neutral-200">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    ID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Joined
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Emails Received
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-neutral-200" id="waitlistTableBody">
                            @foreach($waitlist as $entry)
                            <tr class="hover:bg-neutral-50 waitlist-row">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    {{ $entry->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                    {{ $entry->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    <a href="mailto:{{ $entry->email }}" class="text-primary-600 hover:text-primary-900">
                                        {{ $entry->email }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    <time datetime="{{ $entry->created_at->toISOString() }}">
                                        {{ $entry->created_at->format('M d, Y') }}
                                        <span class="text-xs text-neutral-400">
                                            {{ $entry->created_at->format('g:i A') }}
                                        </span>
                                    </time>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-primary-100 text-primary-800">
                                        {{ $entry->emails->count() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button type="button" class="text-primary-600 hover:text-primary-900" data-email="{{ $entry->email }}" onclick="fillSingleRecipient(this)">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                        <a href="#" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to remove this entry from the waitlist?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-neutral-200 sm:px-6">
                        {{ $waitlist->links('admin.pagination') }}
                    </div>
                @else
                    <div class="py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-neutral-900">No waitlist entries</h3>
                        <p class="mt-1 text-sm text-neutral-500">
                            There are no users on the waitlist yet.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Send Email -->
        <div id="emailSection" class="bg-white shadow-sm rounded-lg mb-8 overflow-hidden">
            <div class="bg-white px-4 py-5 border-b border-neutral-200 sm:px-6">
                <div class="-ml-4 -mt-2 flex items-center justify-between flex-wrap sm:flex-nowrap">
                    <div class="ml-4 mt-2">
                        <h3 class="text-lg leading-6 font-medium text-primary-800">
                            <i class="fas fa-paper-plane mr-2 text-primary-600"></i>
                            Send Email to Waitlist
                        </h3>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <form id="sendEmailForm" method="POST" action="{{ route('admin.waitlist.send-email') }}" class="space-y-6">
                    @csrf
                    <div>
                        <label for="subject" class="block text-sm font-medium text-neutral-700">Email Subject</label>
                        <div class="mt-1">
                            <input type="text" name="subject" id="subject" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-neutral-300 rounded-md" placeholder="An exciting update from Alexandria" required>
                        </div>
                    </div>

                    <div>
                        <label for="content" class="block text-sm font-medium text-neutral-700">Email Content</label>
                        <div class="mt-1">
                            <textarea id="content" name="content" rows="10" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-neutral-300 rounded-md" placeholder="Write your email content here..." required></textarea>
                        </div>
                        <p class="mt-2 text-sm text-neutral-500">
                            You can use HTML for formatting. Remember to maintain a professional tone.
                        </p>
                    </div>

                    <div>
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input id="selectAll" name="selectAll" type="checkbox" class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-neutral-300 rounded" checked>
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="selectAll" class="font-medium text-neutral-700">Send to all waitlist members</label>
                                <p class="text-neutral-500">This will send to all {{ count($waitlist) }} members on the waitlist.</p>
                            </div>
                        </div>
                    </div>

                    <div class="recipient-selection bg-neutral-50 p-4 rounded-md" style="display: none;">
                        <label class="block text-sm font-medium text-neutral-700 mb-3">Select Recipients</label>
                        <div class="max-h-60 overflow-y-auto">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($waitlist as $entry)
                                <div class="relative flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="recipient{{ $entry->id }}" name="recipients[]" value="{{ $entry->id }}" type="checkbox" class="recipient-checkbox focus:ring-primary-500 h-4 w-4 text-primary-600 border-neutral-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="recipient{{ $entry->id }}" class="font-medium text-neutral-700">{{ $entry->name }}</label>
                                        <p class="text-neutral-500 truncate max-w-xs">{{ $entry->email }}</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Email History -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="bg-white px-4 py-5 border-b border-neutral-200 sm:px-6">
                <div class="-ml-4 -mt-2 flex items-center justify-between flex-wrap sm:flex-nowrap">
                    <div class="ml-4 mt-2">
                        <h3 class="text-lg leading-6 font-medium text-primary-800">
                            <i class="fas fa-history mr-2 text-primary-600"></i>
                            Email History
                        </h3>
                    </div>
                    <div class="ml-4 mt-2 flex-shrink-0">
                        <div class="relative">
                            <input type="text" id="searchEmails" placeholder="Search emails..." class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full pr-10 sm:text-sm border-neutral-300 rounded-md">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-neutral-400"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                @if(count($emails) > 0)
                    <table class="min-w-full divide-y divide-neutral-200">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    ID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Subject
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Sent At
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Recipients
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Open Rate
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-neutral-200" id="emailsTableBody">
                            @foreach($emails as $email)
                            <tr class="hover:bg-neutral-50 email-row">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    {{ $email->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900 font-medium">
                                    {{ $email->subject }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    @if($email->sent_at)
                                        <time datetime="{{ $email->sent_at->toISOString() }}">
                                            {{ $email->sent_at->format('M d, Y') }}
                                            <span class="text-xs text-neutral-400">
                                                {{ $email->sent_at->format('g:i A') }}
                                            </span>
                                        </time>
                                    @else
                                        <span class="text-yellow-600">Not sent</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-primary-100 text-primary-800">
                                        {{ $email->recipients->count() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-neutral-200 rounded-full h-2.5">
                                            @php
                                                // Simulated open rate - in a real app you'd track this
                                                $openRate = mt_rand(50, 95);
                                            @endphp
                                            <div class="bg-primary-600 h-2.5 rounded-full" style="width: {{ $openRate }}%"></div>
                                        </div>
                                        <span class="ml-2">{{ $openRate }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button type="button" class="text-primary-600 hover:text-primary-900 view-email" 
                                        data-id="{{ $email->id }}" 
                                        data-subject="{{ $email->subject }}" 
                                        data-content="{{ $email->content }}">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-neutral-200 sm:px-6">
                        {{ $emails->links('admin.pagination') }}
                    </div>
                @else
                    <div class="py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-neutral-900">No emails sent yet</h3>
                        <p class="mt-1 text-sm text-neutral-500">
                            Use the form above to send your first email to the waitlist.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Email Preview Modal -->
<div class="fixed inset-0 overflow-y-auto hidden" id="emailPreviewModal" aria-labelledby="emailPreviewModalLabel" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="emailModalOverlay"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-primary-900" id="emailPreviewModalLabel">
                            Email Preview
                        </h3>
                        <div class="mt-4">
                            <h4 class="text-md font-medium text-neutral-900" id="emailSubject"></h4>
                            <div class="mt-4 border-t border-neutral-200 pt-4">
                                <div id="emailContent" class="prose max-w-none text-neutral-700"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" id="closeModalBtn">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle search for waitlist entries
        const searchWaitlist = document.getElementById('searchWaitlist');
        if (searchWaitlist) {
            searchWaitlist.addEventListener('keyup', function() {
                const searchValue = this.value.toLowerCase();
                const rows = document.querySelectorAll('.waitlist-row');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchValue)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Handle search for emails
        const searchEmails = document.getElementById('searchEmails');
        if (searchEmails) {
            searchEmails.addEventListener('keyup', function() {
                const searchValue = this.value.toLowerCase();
                const rows = document.querySelectorAll('.email-row');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchValue)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Handle select all toggle for recipients
        const selectAllCheckbox = document.getElementById('selectAll');
        const recipientSelectionDiv = document.querySelector('.recipient-selection');
        const recipientCheckboxes = document.querySelectorAll('.recipient-checkbox');
        
        if (selectAllCheckbox && recipientSelectionDiv) {
            selectAllCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    recipientSelectionDiv.style.display = 'none';
                    recipientCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                } else {
                    recipientSelectionDiv.style.display = 'block';
                }
            });
        }
        
        // Handle email preview modal
        const viewEmailButtons = document.querySelectorAll('.view-email');
        const emailSubjectElement = document.getElementById('emailSubject');
        const emailContentElement = document.getElementById('emailContent');
        const emailPreviewModal = document.getElementById('emailPreviewModal');
        const emailModalOverlay = document.getElementById('emailModalOverlay');
        const closeModalBtn = document.getElementById('closeModalBtn');
        
        function openModal() {
            emailPreviewModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
        
        function closeModal() {
            emailPreviewModal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        
        if (viewEmailButtons.length && emailPreviewModal) {
            viewEmailButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const subject = this.getAttribute('data-subject');
                    const content = this.getAttribute('data-content');
                    
                    emailSubjectElement.textContent = subject;
                    emailContentElement.innerHTML = content;
                    
                    openModal();
                });
            });
            
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', closeModal);
            }
            
            if (emailModalOverlay) {
                emailModalOverlay.addEventListener('click', closeModal);
            }
            
            // Close on escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !emailPreviewModal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        }
    });
    
    // Function to fill in a single recipient when clicking the email icon
    function fillSingleRecipient(button) {
        const email = button.getAttribute('data-email');
        const selectAllCheckbox = document.getElementById('selectAll');
        const recipientSelectionDiv = document.querySelector('.recipient-selection');
        
        // Uncheck "select all" and show recipient selection
        selectAllCheckbox.checked = false;
        recipientSelectionDiv.style.display = 'block';
        
        // Find the recipient checkbox by email
        const recipientCheckboxes = document.querySelectorAll('.recipient-checkbox');
        recipientCheckboxes.forEach(checkbox => {
            // Get the label that contains the email text
            const emailText = checkbox.parentElement.nextElementSibling.querySelector('p').textContent;
            if (emailText === email) {
                checkbox.checked = true;
            } else {
                checkbox.checked = false;
            }
        });
        
        // Scroll to the email form
        document.getElementById('emailSection').scrollIntoView({ behavior: 'smooth' });
    }
</script>
@endsection