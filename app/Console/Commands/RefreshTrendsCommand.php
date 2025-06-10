<?php

namespace App\Console\Commands;

use App\Services\ExaTrendService;
use App\Models\Topic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshTrendsCommand extends Command
{
    protected $signature = 'trends:refresh';
    protected $description = 'Refresh trending topics data';
    
    protected $exaTrendService;
    
    public function __construct(ExaTrendService $exaTrendService)
    {
        parent::__construct();
        $this->exaTrendService = $exaTrendService;
    }
    
    public function handle()
    {
        $this->info('Starting trend data refresh...');
        
        // Clear existing caches for trending topics
        $this->info('Clearing existing caches...');
        $cacheKeys = [
            'trending_topics_day_*',
            'trending_topics_week_*',
            'trending_topics_month_*',
            'exa_discovered_trends_*'
        ];
        
        foreach ($cacheKeys as $pattern) {
            // Note: In a production environment, you might need a different approach
            // to clear pattern-based cache keys, depending on your cache driver
            Cache::forget($pattern);
        }
        
        // Refresh discovered trends from Exa.ai
        $this->info('Discovering new trending topics...');
        $topics = $this->exaTrendService->discoverTrendingTopics(20);
        $this->info('Found ' . count($topics['topics']) . ' trending topics.');
        
        // For each discovered topic that doesn't exist in our database,
        // we could potentially add it to the topics table automatically
        
        $this->info('Trend data refresh completed successfully.');
        
        return Command::SUCCESS;
    }
}