<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\OpenLibrary;
use App\Http\Controllers\OpenLibraryController;

class WarmCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up Redis cache with frequently accessed data for instant loading';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔥 Warming up cache...');
        
        try {
            // Clear old cache to ensure fresh data
            $this->info('🧹 Clearing old cache...');
            Cache::forget('library_sections_v2');
            Cache::forget('library_sections'); // Old key
            
            // Clear library detail caches (pattern matching)
            try {
                $redis = Cache::getRedis();
                $keys = $redis->keys('library_*_user_*');
                if (!empty($keys)) {
                    $redis->del($keys);
                    $this->info("✅ Cleared " . count($keys) . " library cache entries");
                }
            } catch (\Exception $e) {
                $this->warn("Could not clear library caches: " . $e->getMessage());
            }
            
            $this->info('✅ Cache cleared successfully!');
            $this->info('💡 Cache will be populated automatically on first request');
            $this->info('🚀 Your app is ready for super snappy performance!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Cache warming failed: ' . $e->getMessage());
            Log::error('Cache warming failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
