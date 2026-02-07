<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OpenLibrary;
use Illuminate\Support\Str;

class FixMissingLibraryShareKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libraries:fix-share-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate share keys for libraries that don\'t have them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for libraries without share keys...');
        
        // Check if share_key column exists
        if (!\Schema::hasColumn('open_libraries', 'share_key')) {
            $this->error('The share_key column does not exist in the open_libraries table.');
            $this->info('Please run the migration first: php artisan migrate');
            return 1;
        }
        
        // Find libraries without share keys
        $libraries = OpenLibrary::whereNull('share_key')->orWhere('share_key', '')->get();
        
        if ($libraries->isEmpty()) {
            $this->info('All libraries already have share keys!');
            return 0;
        }
        
        $this->info("Found {$libraries->count()} libraries without share keys.");
        
        $bar = $this->output->createProgressBar($libraries->count());
        $bar->start();
        
        foreach ($libraries as $library) {
            $library->share_key = Str::random(12);
            $library->save();
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('Successfully generated share keys for all libraries!');
        
        return 0;
    }
}
