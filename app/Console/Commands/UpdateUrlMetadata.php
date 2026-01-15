<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LibraryUrl;
use App\Services\UrlMetadataService;
use Illuminate\Support\Facades\Log;

class UpdateUrlMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-url-metadata {--limit=50 : Number of URLs to process} {--force : Force update even if thumbnail exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and update rich metadata (thumbnails) for existing URLs';

    /**
     * Execute the console command.
     */
    public function handle(UrlMetadataService $metadataService)
    {
        $force = $this->option('force');
        $limit = $this->option('limit');

        $query = LibraryUrl::query();
        
        if (!$force) {
            $query->whereNull('thumbnail_url');
        }

        // Just get the count first
        $total = $query->count();
        
        if ($limit && $limit < $total) {
            $total = $limit;
        }

        $this->info("Starting metadata update for {$total} URLs...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;

        // Use chunkById to efficiently process large datasets
        // If we are modifying the result set (adding thumbnail_url), simple chunk might skip items
        // But since we are likely ordering by ID in chunkById, it's safer.
        // However, if we process 'whereNull', once updated, it's no longer 'null', so the offset shifts?
        // Actually chunkById is safe against that if we don't change the ID.
        // But 'whereNull' + chunkById is tricky if the condition becomes false.
        // Better: Use standard chunking but re-query or just limit/offset carefully.
        // Safest for 'whereNull': Just iterate. 
        // Or simpler: Load in chunks of 50, process, repeat until none left (if not forcing)
        
        // Let's stick to a simpler approach: process in batches of 50
        $batchSize = 50;
        
        $query->orderBy('id', 'desc'); // Newest first is usually good, but for full backfill order doesn't matter much.
            
        if ($limit) {
            // If limit is set, we just do a simple get
             $urls = $query->limit($limit)->get();
             // Process $urls... (refactor the loop into a method?)
             $this->processUrls($urls, $metadataService, $bar);
        } else {
             // Process ALL
             $query->chunkById($batchSize, function ($urls) use ($metadataService, $bar) {
                 $this->processUrls($urls, $metadataService, $bar);
                 // Check if we should stop? No, we want all.
             });
        }

        $bar->finish();
        $this->newLine();
        $this->info('Metadata update completed!');
    }

    private function processUrls($urls, $metadataService, $bar) 
    {
        foreach ($urls as $urlRecord) {
            try {
                // Fetch metadata
                $metadata = $metadataService->fetchMetadata($urlRecord->url);

                $updates = [];
                $hasUpdates = false;

                // Update thumbnail
                if (!empty($metadata['thumbnail_url'])) {
                    $updates['thumbnail_url'] = $metadata['thumbnail_url'];
                    $hasUpdates = true;
                }

                // Update title if missing or looks like a URL
                if (empty($urlRecord->title) || $urlRecord->title === $urlRecord->url || str_contains($urlRecord->title, 'http')) {
                    if (!empty($metadata['title'])) {
                        $updates['title'] = $metadata['title'];
                        $hasUpdates = true;
                    }
                }

                // Update summary if missing/URL-like
                if (empty($urlRecord->summary) || $urlRecord->summary === $urlRecord->url) {
                    if (!empty($metadata['description'])) {
                        $updates['summary'] = $metadata['description'];
                        $hasUpdates = true;
                    }
                }

                if ($hasUpdates) {
                    $urlRecord->update($updates);
                }

                // Optimized sleep: 0.1s
                usleep(100000); 

            } catch (\Exception $e) {
                Log::error("Failed to update metadata for URL ID {$urlRecord->id}: " . $e->getMessage());
                // Don't output error to console to keep progress bar clean, just log
            }

            $bar->advance();
        }
    }
}
