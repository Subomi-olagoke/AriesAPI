<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BackfillPostShareKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:backfill-share-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill share keys for existing posts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Backfilling share keys for posts...');
        
        $posts = Post::whereNull('share_key')->get();
        $count = $posts->count();
        
        if ($count === 0) {
            $this->info('No posts found without share keys.');
            return;
        }
        
        $this->info("Found {$count} posts without share keys. Processing...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        foreach ($posts as $post) {
            DB::beginTransaction();
            try {
                $post->share_key = Str::random(10);
                $post->save();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error processing post ID {$post->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('Share keys have been generated for all posts!');
    }
}
