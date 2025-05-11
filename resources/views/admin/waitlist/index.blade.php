@extends('admin.dashboard-layout')

@section('content')
<div class="container-fluid px-4">
    <h1 class="mt-4">Waitlist Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active">Waitlist</li>
    </ol>

    @if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Waitlist Entries
        </div>
        <div class="card-body">
            @if(count($waitlist) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered" id="waitlistTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Joined</th>
                                <th>Emails Sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waitlist as $entry)
                            <tr>
                                <td>{{ $entry->id }}</td>
                                <td>{{ $entry->name }}</td>
                                <td>{{ $entry->email }}</td>
                                <td>{{ $entry->created_at->format('M d, Y') }}</td>
                                <td>{{ $entry->emails->count() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $waitlist->links('admin.pagination') }}
                </div>
            @else
                <p>No waitlist entries found.</p>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-envelope me-1"></i>
            Send Email to Waitlist
        </div>
        <div class="card-body">
            <form id="sendEmailForm" method="POST" action="{{ route('admin.waitlist.send-email') }}">
                @csrf
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" class="form-control" id="subject" name="subject" required>
                </div>
                <div class="mb-3">
                    <label for="content" class="form-label">Email Content</label>
                    <textarea class="form-control" id="content" name="content" rows="10" required></textarea>
                    <small class="text-muted">You can use HTML for formatting.</small>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll" checked>
                        <label class="form-check-label" for="selectAll">
                            Send to all waitlist members
                        </label>
                    </div>
                </div>
                <div class="mb-3 recipient-selection" style="display: none;">
                    <label class="form-label">Select Recipients</label>
                    <div class="recipient-list">
                        @foreach($waitlist as $entry)
                        <div class="form-check">
                            <input class="form-check-input recipient-checkbox" type="checkbox" name="recipients[]" value="{{ $entry->id }}" id="recipient{{ $entry->id }}">
                            <label class="form-check-label" for="recipient{{ $entry->id }}">
                                {{ $entry->name }} ({{ $entry->email }})
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Send Email</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-history me-1"></i>
            Email History
        </div>
        <div class="card-body">
            @if(count($emails) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered" id="emailsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject</th>
                                <th>Sent At</th>
                                <th>Recipients</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($emails as $email)
                            <tr>
                                <td>{{ $email->id }}</td>
                                <td>{{ $email->subject }}</td>
                                <td>{{ $email->sent_at ? $email->sent_at->format('M d, Y H:i') : 'Not sent' }}</td>
                                <td>{{ $email->recipients->count() }}</td>
                                <td>
                                    <button class="btn btn-sm btn-info view-email" data-id="{{ $email->id }}" data-subject="{{ $email->subject }}" data-content="{{ $email->content }}">View</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $emails->links('admin.pagination') }}
                </div>
            @else
                <p>No emails have been sent yet.</p>
            @endif
        </div>
    </div>
</div>

<!-- Email Preview Modal -->
<div class="modal fade" id="emailPreviewModal" tabindex="-1" aria-labelledby="emailPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailPreviewModalLabel">Email Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="emailSubject"></h6>
                <hr>
                <div id="emailContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle select all toggle
        const selectAllCheckbox = document.getElementById('selectAll');
        const recipientSelectionDiv = document.querySelector('.recipient-selection');
        const recipientCheckboxes = document.querySelectorAll('.recipient-checkbox');
        
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
        
        // Handle email preview
        const viewEmailButtons = document.querySelectorAll('.view-email');
        const emailSubjectElement = document.getElementById('emailSubject');
        const emailContentElement = document.getElementById('emailContent');
        
        viewEmailButtons.forEach(button => {
            button.addEventListener('click', function() {
                const subject = this.getAttribute('data-subject');
                const content = this.getAttribute('data-content');
                
                emailSubjectElement.textContent = subject;
                emailContentElement.innerHTML = content;
                
                const emailPreviewModal = new bootstrap.Modal(document.getElementById('emailPreviewModal'));
                emailPreviewModal.show();
            });
        });
    });
</script>
@endsection