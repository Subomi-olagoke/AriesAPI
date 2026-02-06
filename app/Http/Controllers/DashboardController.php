<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\User;
use App\Models\Readlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get aggregate statistics for the admin dashboard.
     */
    public function index()
    {
        // User Stats
        $totalUsers = User::count();

        // Library Stats
        $totalLibraries = OpenLibrary::count();
        $pendingLibraries = OpenLibrary::where('approval_status', 'pending')->count();
        $approvedLibraries = OpenLibrary::where('approval_status', 'approved')->count();

        // Readlist Stats
        $totalReadlists = Readlist::count();

        // Recent Activity (Simple implementation: latest 5 libraries)
        $recentLibraries = OpenLibrary::orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'name', 'type', 'created_at', 'approval_status']);

        return response()->json([
            'stats' => [
                'users' => [
                    'total' => $totalUsers
                ],
                'libraries' => [
                    'total' => $totalLibraries,
                    'pending' => $pendingLibraries,
                    'approved' => $approvedLibraries
                ],
                'readlists' => [
                    'total' => $totalReadlists
                ]
            ],
        ]);
    }

    /**
     * Get list of users for admin dashboard.
     */
    public function getUsers()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);
        return response()->json($users);
    }

    /**
     * Get list of readlists for admin dashboard.
     */
    public function getReadlists()
    {
        $readlists = Readlist::with('user:id,name,username')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return response()->json($readlists);
    }
}
