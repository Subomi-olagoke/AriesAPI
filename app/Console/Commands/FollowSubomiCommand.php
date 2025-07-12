<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Follow;

class FollowSubomiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aries:follow-subomi';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make all users follow the account with username subomi olagoke';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetUsername = 'subomi';
        $targetUser = User::where('username', $targetUsername)->first();

        if (!$targetUser) {
            $this->error("Target user with username '$targetUsername' not found.");
            return 1;
        }

        $targetId = $targetUser->id;
        $users = User::where('id', '!=', $targetId)->get();
        $count = 0;
        foreach ($users as $user) {
            $already = Follow::where('user_id', $user->id)
                ->where('followeduser', $targetId)
                ->exists();
            if ($already) {
                $this->line("User {$user->username} already follows {$targetUsername}");
                continue;
            }
            Follow::create([
                'user_id' => $user->id,
                'followeduser' => $targetId,
            ]);
            $this->info("User {$user->username} now follows {$targetUsername}");
            $count++;
        }
        $this->info("Done. $count users now follow $targetUsername.");
        return 0;
    }
} 