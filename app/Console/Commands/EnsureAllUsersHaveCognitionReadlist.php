<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Readlist;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EnsureAllUsersHaveCognitionReadlist extends Command
{
    protected $signature = 'readlists:ensure-cognition';
    protected $description = 'Ensure all users have a Cognition readlist';

    public function handle()
    {
        $this->info('Starting to create Cognition readlists for users who don\'t have one...');
        
        // Get count of users without Cognition readlist
        $userCount = User::whereDoesntHave('readlists', function($query) {
            $query->where('is_system', true)
                  ->where('title', 'Cognition');
        })->count();
        
        $this->info("Found {$userCount} users without a Cognition readlist");
        
        // Process in batches of 100 to avoid memory issues
        User::whereDoesntHave('readlists', function($query) {
            $query->where('is_system', true)
                  ->where('title', 'Cognition');
        })->chunkById(100, function($users) {
            foreach ($users as $user) {
                // Create Cognition readlist for user
                $readlist = new Readlist();
                $readlist->user_id = $user->id;
                $readlist->title = 'Cognition';
                $readlist->description = 'Your personalized learning feed powered by Cogni AI';
                $readlist->is_public = true;
                $readlist->is_system = true;
                $readlist->share_key = Str::random(10);
                $readlist->save();
                
                $this->line("Created Cognition readlist for user ID: {$user->id}");
                
                // Optional: Queue job to populate the readlist
                if (class_exists('\\App\\Jobs\\PopulateCognitionReadlist')) {
                    \App\Jobs\PopulateCognitionReadlist::dispatch($user)->delay(now()->addMinutes(5));
                }
            }
        });
        
        $this->info('Completed creating Cognition readlists!');
        
        return 0;
    }
}