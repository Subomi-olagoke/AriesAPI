<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OpenLibrary;
use App\Models\LibraryContent;
use App\Models\LibraryUrl;
use App\Services\UrlMetadataService;
use Illuminate\Support\Facades\Log;

class UpdateLibraryMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-library-metadata {library_id : ID of the library to update} {--force : Force update even if thumbnail exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and update rich metadata for all URLs in a specific library';

    /**
     * Execute the console command.
     */
    public function handle(UrlMetadataService $metadataService)
    {
        $libraryId = $this->argument('library_id');
        $force = $this->option('force');

        $library = OpenLibrary::find($libraryId);

        if (!$library) {
            $this->error("Library with ID {$libraryId} not found.");
            return 1;
        }

        $this->info("Found library: {$library->name}");
        $this->info("Fetching content IDs...");

        // Find URLs in this library
        $contentIds = LibraryContent::where('library_id', $libraryId)
            ->where('content_type', 'App\Models\LibraryUrl')
            ->pluck('content_id');

        if ($contentIds->isEmpty()) {
            $this->info("No URLs found in this library.");
            return 0;
        }

        $query = LibraryUrl::whereIn('id', $contentIds);
        if (!$force) {
            $query->whereNull('thumbnail_url');
        }

        $urls = $query->get();
        $count = $urls->count();

        if ($count === 0) {
            $this->info("All URLs in this library already have metadata.");
            return 0;
        }

        $this->info("Updating metadata for {$count} URLs...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

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

                usleep(300000); // 0.3s pause

            } catch (\Exception $e) {
                Log::error("Failed to update metadata for URL ID {$urlRecord->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Library metadata update completed!');
        return 0;
    }
}
