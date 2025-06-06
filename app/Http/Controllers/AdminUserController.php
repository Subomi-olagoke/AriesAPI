<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    /**
     * Display a listing of users with advanced filtering
     */
    public function index(Request $request)
    {
        $query = User::query();
        
        // Join with profiles if needed for filtering
        if ($request->has('bio') || $request->has('has_profile')) {
            $query->leftJoin('profiles', 'users.id', '=', 'profiles.user_id');
            $query->select('users.*'); // Ensure we only get user columns
        }
        
        // Apply search filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }
        
        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        // Filter by status
        if ($request->has('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('is_banned', false);
                    break;
                case 'banned':
                    $query->where('is_banned', true);
                    break;
                case 'verified':
                    $query->where('email_verified_at', '!=', null);
                    break;
                case 'unverified':
                    $query->where('email_verified_at', null);
                    break;
            }
        }
        
        // Filter by admin status
        if ($request->has('isAdmin')) {
            $query->where('isAdmin', $request->isAdmin == 'true');
        }
        
        // Filter by profile existence
        if ($request->has('has_profile')) {
            if ($request->has_profile == 'true') {
                $query->whereNotNull('profiles.id');
            } else {
                $query->whereNull('profiles.id');
            }
        }
        
        // Filter by creation date
        if ($request->has('created_after')) {
            $query->where('users.created_at', '>=', $request->created_after);
        }
        
        if ($request->has('created_before')) {
            $query->where('users.created_at', '<=', $request->created_before);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        
        // Validate sortable fields to prevent SQL injection
        $allowedSortFields = ['id', 'username', 'email', 'first_name', 'last_name', 'created_at', 'role'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        // Paginate results
        $users = $query->paginate($request->input('per_page', 15));
        
        // If this is an API request, return JSON
        if ($request->expectsJson()) {
            return response()->json([
                'users' => $users->items(),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage()
                ]
            ]);
        }
        
        // For web request, return the view with data
        return view('admin.users.index', [
            'users' => $users,
            'filters' => [
                'search' => $request->search,
                'role' => $request->role,
                'status' => $request->status,
                'isAdmin' => $request->isAdmin,
                'has_profile' => $request->has_profile,
                'created_after' => $request->created_after,
                'created_before' => $request->created_before,
                'sort_by' => $sortField,
                'sort_dir' => $sortDir
            ]
        ]);
    }
    
    /**
     * Display details for a specific user
     */
    public function show($id)
    {
        $user = User::with(['profile', 'posts', 'courses', 'followers', 'following'])->findOrFail($id);
        
        // Get additional stats
        $stats = [
            'posts_count' => $user->posts()->count(),
            'courses_count' => $user->courses()->count(),
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'comments_count' => $user->comments()->count(),
            'likes_count' => $user->likes()->count()
        ];
        
        // Get user's activities (recent actions)
        $activities = $this->getUserActivities($user);
        
        return view('admin.users.show', [
            'user' => $user,
            'stats' => $stats,
            'activities' => $activities
        ]);
    }
    
    /**
     * Edit user form
     */
    public function edit($id)
    {
        $user = User::with('profile')->findOrFail($id);
        
        return view('admin.users.edit', [
            'user' => $user
        ]);
    }
    
    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'role' => 'required|in:user,educator,admin',
            'isAdmin' => 'boolean',
            'password' => 'nullable|string|min:8|confirmed',
            'bio' => 'nullable|string',
        ]);
        
        // Update basic user info
        $user->username = $request->username;
        $user->email = $request->email;
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->role = $request->role;
        $user->isAdmin = $request->isAdmin ? true : false;
        
        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        
        $user->save();
        
        // Update profile if it exists or create if it doesn't
        if ($user->profile) {
            $profile = $user->profile;
        } else {
            $profile = new Profile();
            $profile->user_id = $user->id;
        }
        
        if ($request->filled('bio')) {
            $profile->bio = $request->bio;
        }
        
        // Update other profile fields as needed
        $profile->save();
        
        // Log this action
        // $this->logAdminAction('Updated user: ' . $user->username, $user);
        
        return redirect()->route('admin.users.show', $user->id)
            ->with('success', 'User updated successfully');
    }
    
    /**
     * Ban a user
     */
    public function ban(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        
        // Don't allow banning other admins
        if ($user->isAdmin) {
            return back()->with('error', 'Cannot ban an administrator');
        }
        
        $user->is_banned = true;
        $user->banned_at = now();
        $user->ban_reason = $request->reason;
        $user->save();
        
        // Revoke all tokens to force logout
        $user->tokens()->delete();
        
        // Log this action
        // $this->logAdminAction('Banned user: ' . $user->username, $user);
        
        return back()->with('success', 'User has been banned');
    }
    
    /**
     * Unban a user
     */
    public function unban($id)
    {
        $user = User::findOrFail($id);
        
        $user->is_banned = false;
        $user->banned_at = null;
        $user->ban_reason = null;
        $user->save();
        
        // Log this action
        // $this->logAdminAction('Unbanned user: ' . $user->username, $user);
        
        return back()->with('success', 'User has been unbanned');
    }
    
    /**
     * Make a user an admin
     */
    public function makeAdmin($id)
    {
        $user = User::findOrFail($id);
        
        $user->isAdmin = true;
        $user->save();
        
        // Log this action
        // $this->logAdminAction('Made admin: ' . $user->username, $user);
        
        return back()->with('success', 'User is now an administrator');
    }
    
    /**
     * Remove admin privileges
     */
    public function removeAdmin($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent removing the last admin
        $adminCount = User::where('isAdmin', true)->count();
        if ($adminCount <= 1 && $user->isAdmin) {
            return back()->with('error', 'Cannot remove the last administrator');
        }
        
        $user->isAdmin = false;
        $user->save();
        
        // Log this action
        // $this->logAdminAction('Removed admin: ' . $user->username, $user);
        
        return back()->with('success', 'Administrator privileges removed');
    }
    
    /**
     * Get a list of banned users
     */
    public function bannedUsers(Request $request)
    {
        $query = User::where('is_banned', true)
            ->orderBy('banned_at', 'desc');
            
        // Apply search if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }
        
        $bannedUsers = $query->paginate($request->per_page ?? 15);
        
        return view('admin.users.banned', [
            'users' => $bannedUsers,
            'search' => $request->search
        ]);
    }
    
    /**
     * Send a notification to a user
     */
    public function notify(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,warning,important'
        ]);
        
        // Create a notification record
        $notification = new \App\Models\Notification();
        $notification->notifiable_id = $user->id;
        $notification->notifiable_type = User::class;
        $notification->type = 'App\\Notifications\\AdminNotification';
        $notification->data = json_encode([
            'subject' => $request->subject,
            'message' => $request->message,
            'type' => $request->type,
        ]);
        $notification->save();
        
        // Log this action
        // $this->logAdminAction('Sent notification to user: ' . $user->username, $user);
        
        return back()->with('success', 'Notification sent successfully');
    }
    
    /**
     * Export users based on filter criteria
     */
    public function export(Request $request)
    {
        $query = User::query();
        
        // Join with profiles if needed for filtering
        if ($request->has('bio') || $request->has('has_profile')) {
            $query->leftJoin('profiles', 'users.id', '=', 'profiles.user_id');
            $query->select('users.*'); // Ensure we only get user columns
        }
        
        // Apply the same filters as in the index method
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }
        
        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        // Filter by status
        if ($request->has('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('is_banned', false);
                    break;
                case 'banned':
                    $query->where('is_banned', true);
                    break;
                case 'verified':
                    $query->where('email_verified_at', '!=', null);
                    break;
                case 'unverified':
                    $query->where('email_verified_at', null);
                    break;
            }
        }
        
        // Filter by admin status
        if ($request->has('isAdmin')) {
            $query->where('isAdmin', $request->isAdmin == 'true');
        }
        
        // Filter by profile existence
        if ($request->has('has_profile')) {
            if ($request->has_profile == 'true') {
                $query->whereNotNull('profiles.id');
            } else {
                $query->whereNull('profiles.id');
            }
        }
        
        // Filter by creation date - use export-specific date fields if provided
        if ($request->has('export_date_from') && !empty($request->export_date_from)) {
            $query->where('users.created_at', '>=', $request->export_date_from);
        } elseif ($request->has('created_after') && !empty($request->created_after)) {
            $query->where('users.created_at', '>=', $request->created_after);
        }
        
        if ($request->has('export_date_to') && !empty($request->export_date_to)) {
            // Add one day to include the entire day
            $dateTo = date('Y-m-d', strtotime($request->export_date_to . ' +1 day'));
            $query->where('users.created_at', '<', $dateTo);
        } elseif ($request->has('created_before') && !empty($request->created_before)) {
            $query->where('users.created_at', '<=', $request->created_before);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        
        // Validate sortable fields to prevent SQL injection
        $allowedSortFields = ['id', 'username', 'email', 'first_name', 'last_name', 'created_at', 'role'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        // Get all users matching the criteria
        $users = $query->get();
        
        // Determine export format
        $exportFormat = $request->input('export_format', 'csv');
        
        // Handle Excel export
        if ($exportFormat === 'xlsx') {
            return $this->exportToExcel($users, $request);
        }
        
        // Get selected fields or use defaults
        $selectedFields = $request->input('export_fields', [
            'id', 'username', 'email', 'first_name', 'last_name', 'role',
            'isAdmin', 'is_banned', 'created_at', 'last_login_at', 'email_verified_at'
        ]);
        
        // Map field names to display names
        $fieldMappings = [
            'id' => 'ID',
            'username' => 'Username',
            'email' => 'Email',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'role' => 'Role',
            'isAdmin' => 'Admin',
            'is_banned' => 'Banned',
            'created_at' => 'Registered Date',
            'last_login_at' => 'Last Login',
            'email_verified_at' => 'Verified'
        ];
        
        // Generate a filename for the export
        $filename = 'users_export_' . date('Y-m-d_His') . '.csv';
        
        // Open output stream
        $handle = fopen('php://temp', 'r+');
        
        // Add CSV header with only selected fields
        $headerRow = [];
        foreach ($selectedFields as $field) {
            $headerRow[] = $fieldMappings[$field] ?? ucwords(str_replace('_', ' ', $field));
        }
        fputcsv($handle, $headerRow);
        
        // Add user data with only selected fields
        foreach ($users as $user) {
            $row = [];
            foreach ($selectedFields as $field) {
                switch ($field) {
                    case 'isAdmin':
                        $row[] = $user->isAdmin ? 'Yes' : 'No';
                        break;
                    case 'is_banned':
                        $row[] = $user->is_banned ? 'Yes' : 'No';
                        break;
                    case 'created_at':
                        $row[] = $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '';
                        break;
                    case 'last_login_at':
                        $row[] = $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never';
                        break;
                    case 'email_verified_at':
                        $row[] = $user->email_verified_at ? 'Yes' : 'No';
                        break;
                    default:
                        $row[] = $user->{$field};
                }
            }
            fputcsv($handle, $row);
        }
        
        // Reset pointer to the beginning
        rewind($handle);
        
        // Get the content
        $content = stream_get_contents($handle);
        
        // Close the handle
        fclose($handle);
        
        // Create the response with CSV content
        $response = response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
        
        return $response;
    }
    
    /**
     * Export users to Excel format
     * 
     * @param Collection $users
     * @param Request $request
     * @return Response
     */
    private function exportToExcel($users, $request)
    {
        // Since we don't have PHPSpreadsheet installed, we'll return a JSON response
        // instructing the admin to install the necessary package
        
        return response()->json([
            'error' => 'Excel export requires PhpSpreadsheet. Please install it using: composer require phpoffice/phpspreadsheet',
            'message' => 'Please run the command: composer require phpoffice/phpspreadsheet to enable Excel exports'
        ], 501);
        
        /*
        // This is the implementation you would use with PhpSpreadsheet installed
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Get selected fields or use defaults
        $selectedFields = $request->input('export_fields', [
            'id', 'username', 'email', 'first_name', 'last_name', 
            'role', 'isAdmin', 'is_banned', 'created_at', 'last_login_at', 'email_verified_at'
        ]);
        
        // Field mappings (same as in the CSV export)
        $fieldMappings = [
            'id' => 'ID',
            'username' => 'Username',
            'email' => 'Email',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'role' => 'Role',
            'isAdmin' => 'Admin', 
            'is_banned' => 'Banned',
            'created_at' => 'Registered Date',
            'last_login_at' => 'Last Login',
            'email_verified_at' => 'Verified'
        ];
        
        // Add headers
        $column = 1;
        foreach ($selectedFields as $field) {
            $sheet->setCellValueByColumnAndRow(
                $column++, 
                1, 
                $fieldMappings[$field] ?? ucwords(str_replace('_', ' ', $field))
            );
        }
        
        // Add data
        $row = 2;
        foreach ($users as $user) {
            $column = 1;
            foreach ($selectedFields as $field) {
                $value = '';
                switch ($field) {
                    case 'isAdmin':
                        $value = $user->isAdmin ? 'Yes' : 'No';
                        break;
                    case 'is_banned':
                        $value = $user->is_banned ? 'Yes' : 'No';
                        break;
                    case 'created_at':
                        $value = $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '';
                        break;
                    case 'last_login_at':
                        $value = $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never';
                        break;
                    case 'email_verified_at':
                        $value = $user->email_verified_at ? 'Yes' : 'No';
                        break;
                    default:
                        $value = $user->{$field};
                }
                $sheet->setCellValueByColumnAndRow($column++, $row, $value);
            }
            $row++;
        }
        
        // Style the header row
        $headerRow = $sheet->getRowDimension(1);
        $headerRow->setRowHeight(20);
        
        // Auto-size columns
        foreach (range(1, count($selectedFields)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        
        // Generate filename
        $filename = 'users_export_' . date('Y-m-d_His') . '.xlsx';
        
        // Create Excel file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $temp_file = tempnam(sys_get_temp_dir(), 'excel_');
        $writer->save($temp_file);
        
        return response()->download($temp_file, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
        */
    }

    /**
     * Get user activities for the admin dashboard
     */
    private function getUserActivities($user)
    {
        // This is a simplistic implementation - in a real app, you'd likely
        // have a dedicated activities or audit log table
        
        $activities = collect();
        
        // Recent posts
        $posts = $user->posts()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($post) {
                return [
                    'type' => 'post',
                    'action' => 'created',
                    'title' => $post->title ?? substr($post->body, 0, 30) . '...',
                    'date' => $post->created_at,
                    'url' => route('post.deep-link', $post->id)
                ];
            });
        
        // Recent comments
        $comments = $user->comments()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($comment) {
                return [
                    'type' => 'comment',
                    'action' => 'commented on',
                    'title' => 'Post #' . $comment->post_id,
                    'date' => $comment->created_at,
                    'url' => route('post.deep-link', $comment->post_id)
                ];
            });
        
        // Recent likes
        $likes = $user->likes()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($like) {
                return [
                    'type' => 'like',
                    'action' => 'liked',
                    'title' => 'Post #' . $like->post_id,
                    'date' => $like->created_at,
                    'url' => route('post.deep-link', $like->post_id)
                ];
            });
        
        // Combine and sort activities
        return $activities->concat($posts)
            ->concat($comments)
            ->concat($likes)
            ->sortByDesc('date')
            ->take(10)
            ->values()
            ->all();
    }
}