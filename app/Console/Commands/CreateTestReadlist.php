<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Readlist;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTestReadlist extends Command
{
    protected $signature = 'readlist:create-test {user_id?}';
    protected $description = 'Create a test readlist for a specific user or the first admin user';

    public function handle()
    {
        // Get user ID from argument or find first admin
        $userId = $this->argument('user_id');
        
        if (!$userId) {
            $user = User::where('isAdmin', true)->first();
            if (!$user) {
                $user = User::first();
            }
        } else {
            $user = User::find($userId);
        }
        
        if (!$user) {
            $this->error('No user found!');
            return 1;
        }
        
        $username = isset($user->username) ? $user->username : $user->email;
        $this->info("Creating test readlist for user: {$user->id} ({$username})");
        
        // Create a test readlist
        $readlist = new Readlist();
        $readlist->user_id = $user->id;
        $readlist->title = 'Test Readlist ' . date('Y-m-d H:i:s');
        $readlist->description = 'This is a test readlist created for API testing';
        $readlist->is_public = true;
        $readlist->is_system = false;
        $readlist->share_key = Str::random(10);
        $readlist->save();
        
        $this->info("Created readlist with ID: {$readlist->id}");
        
        // Find a post to add to the readlist
        $post = Post::first();
        if ($post) {
            $this->info("Found post with ID: {$post->id}");
            
            // Simulate adding item to readlist
            $this->info("To add this post to the readlist, use:");
            $this->line("POST /api/readlists/{$readlist->id}/items");
            $this->line("With body: { \"item_type\": \"post\", \"item_id\": {$post->id} }");
            
            // Actually add the item to demonstrate it works
            try {
                $readlist->items()->create([
                    'item_id' => $post->id,
                    'item_type' => 'App\\Models\\Post',
                    'order' => 1
                ]);
                $this->info("Added post {$post->id} to readlist automatically!");
            } catch (\Exception $e) {
                $this->error("Failed to add post: " . $e->getMessage());
            }
        } else {
            $this->warn("No posts found to add to the readlist.");
        }
        
        return 0;
    }
}