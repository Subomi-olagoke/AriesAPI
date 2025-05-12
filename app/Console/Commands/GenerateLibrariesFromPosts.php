<?php

namespace App\Console\Commands;

use App\Services\OpenLibraryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateLibrariesFromPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libraries:generate-from-posts 
                           {--days=7 : Number of days to look back for posts}
                           {--min-posts=10 : Minimum number of posts required}
                           {--auto-approve : Automatically approve created libraries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate libraries from recent popular posts using AI categorization';

    /**
     * The OpenLibraryService instance.
     *
     * @var OpenLibraryService
     */
    protected $libraryService;

    /**
     * Create a new command instance.
     *
     * @param OpenLibraryService $libraryService
     * @return void
     */
    public function __construct(OpenLibraryService $libraryService)
    {
        parent::__construct();
        $this->libraryService = $libraryService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = $this->option('days');
        $minPosts = $this->option('min-posts');
        $autoApprove = $this->option('auto-approve');

        $this->info("Checking for posts from the last {$days} days to create libraries...");
        $this->info("Minimum posts required: {$minPosts}");
        $this->info("Auto-approve: " . ($autoApprove ? 'Yes' : 'No'));

        try {
            $result = $this->libraryService->checkAndCreateLibrariesFromRecentPosts(
                $days,
                $minPosts,
                $autoApprove
            );

            if ($result['success']) {
                $count = $result['count'] ?? 0;
                $this->info("Successfully created {$count} libraries from recent posts!");
                
                if (isset($result['libraries']) && is_array($result['libraries'])) {
                    $this->line("\nCreated libraries:");
                    foreach ($result['libraries'] as $index => $library) {
                        $index++;
                        $this->line("{$index}. {$library->name} - {$library->description}");
                    }
                }
                
                return 0;
            } else {
                $this->warn($result['message']);
                
                if (isset($result['count']) && isset($result['min_required'])) {
                    $this->line("Found {$result['count']} posts, but {$result['min_required']} are required.");
                }
                
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error generating libraries: " . $e->getMessage());
            Log::error('Command libraries:generate-from-posts failed: ' . $e->getMessage());
            return 1;
        }
    }
}