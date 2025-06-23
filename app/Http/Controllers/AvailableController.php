<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\LiveClass;

class AvailableController extends Controller
{
    /**
     * Fetch available channels (regular and hive) and live classes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $channels = collect([]);
        $liveClasses = collect([]);

        // Fetch hive channels
        if (Schema::hasTable('hive_channels')) {
            $hiveChannels = DB::table('hive_channels')
                ->where('status', 'active')
                ->get()
                ->map(function ($channel) use ($user) {
                    $isJoined = false;
                    if (Schema::hasTable('hive_channel_members')) {
                        $isJoined = DB::table('hive_channel_members')
                            ->where('channel_id', $channel->id)
                            ->where('user_id', $user->id)
                            ->exists();
                    }
                    return [
                        'id' => $channel->id,
                        'name' => $channel->name,
                        'description' => $channel->description,
                        'color' => $channel->color,
                        'member_count' => 0, // Optionally fetch count
                        'is_joined' => $isJoined,
                        'created_at' => $channel->created_at,
                        'updated_at' => $channel->updated_at,
                        'source' => 'hive'
                    ];
                });
            $channels = $channels->concat($hiveChannels);
        }

        // Fetch regular channels
        if (Schema::hasTable('channels')) {
            $regularChannels = DB::table('channels')
                ->where('is_active', true)
                ->get()
                ->map(function ($channel) use ($user) {
                    $isJoined = false;
                    $memberCount = 0;
                    if (Schema::hasTable('channel_members')) {
                        $isJoined = DB::table('channel_members')
                            ->where('channel_id', $channel->id)
                            ->where('user_id', $user->id)
                            ->where('status', 'approved')
                            ->exists();
                        $memberCount = DB::table('channel_members')
                            ->where('channel_id', $channel->id)
                            ->where('status', 'approved')
                            ->count();
                    }
                    return [
                        'id' => $channel->id,
                        'name' => $channel->title,
                        'description' => $channel->description,
                        'color' => '#007AFF',
                        'member_count' => $memberCount,
                        'is_joined' => $isJoined,
                        'created_at' => $channel->created_at,
                        'updated_at' => $channel->updated_at,
                        'source' => 'regular',
                        'picture' => $channel->picture
                    ];
                });
            $channels = $channels->concat($regularChannels);
        }

        // Fetch live classes
        $liveClasses = LiveClass::with('teacher')
            ->where('status', '!=', 'ended')
            ->orderBy('scheduled_at', 'asc')
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'title' => $class->title,
                    'description' => $class->description,
                    'scheduled_at' => $class->scheduled_at,
                    'status' => $class->status,
                    'teacher' => $class->teacher ? [
                        'id' => $class->teacher->id,
                        'username' => $class->teacher->username
                    ] : null,
                    'participant_count' => $class->activeParticipants()->count(),
                    'type' => $class->class_type,
                ];
            });

        return response()->json([
            'channels' => $channels->sortByDesc('created_at')->values(),
            'live_classes' => $liveClasses,
        ]);
    }
} 