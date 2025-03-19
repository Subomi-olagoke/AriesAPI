<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Topic;
use App\Services\OpenLibraryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateOpenLibraries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'libraries:generate 
                            {--courses : Generate libraries for all courses}
                            {--topics : Generate libraries for all topics}
                            {--force : Force regeneration of existing libraries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate open libraries for courses and topics';

    /**
     * The library service.
     *
     * @var OpenLibraryService
     */
    protected $libraryService;

    /**
     * Create a new command instance.
     */
    public function __construct(OpenLibraryService $libraryService)
    {
        parent::__construct();
        $this->libraryService = $libraryService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $generateCourses = $this->option('courses');
        $generateTopics = $this->option('topics');
        $force = $this->option('force');
        
        // If no specific option is provided, generate both
        if (!$generateCourses && !$generateTopics) {
            $generateCourses = true;
            $generateTopics = true;
        }
        
        if ($generateCourses) {
            $this->generateCourseLibraries($force);
        }
        
        if ($generateTopics) {
            $this->generateTopicLibraries($force);
        }
        
        $this->info('Library generation complete.');
    }
    
    /**
     * Generate libraries for all courses.
     */
    protected function generateCourseLibraries($force = false)
    {
        $courses = Course::all();
        $total = $courses->count();
        
        $this->info("Generating libraries for {$total} courses...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        $created = 0;
        $errors = 0;
        
        foreach ($courses as $course) {
            try {
                // Check if a library already exists for this course
                $existingLibrary = \App\Models\OpenLibrary::where('course_id', $course->id)
                    ->where('type', 'course')
                    ->first();
                    
                if ($existingLibrary && !$force) {
                    $bar->advance();
                    continue;
                }
                
                if ($existingLibrary && $force) {
                    // Delete existing library
                    \App\Models\LibraryContent::where('library_id', $existingLibrary->id)->delete();
                    $existingLibrary->delete();
                }
                
                // Create a new library
                $this->libraryService->createCourseLibrary($course);
                $created++;
                
            } catch (\Exception $e) {
                Log::error("Error creating library for course #{$course->id}: " . $e->getMessage());
                $errors++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("Created {$created} course libraries with {$errors} errors.");
    }
    
    /**
     * Generate libraries for all topics.
     */
    protected function generateTopicLibraries($force = false)
    {
        $topics = Topic::all();
        $total = $topics->count();
        
        $this->info("Generating libraries for {$total} topics...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        $created = 0;
        $errors = 0;
        
        foreach ($topics as $topic) {
            try {
                // Check if a library already exists for this topic
                $existingLibrary = \App\Models\OpenLibrary::whereJsonContains('criteria->topic_id', $topic->id)
                    ->where('type', 'auto')
                    ->first();
                    
                if ($existingLibrary && !$force) {
                    $bar->advance();
                    continue;
                }
                
                if ($existingLibrary && $force) {
                    // Delete existing library
                    \App\Models\LibraryContent::where('library_id', $existingLibrary->id)->delete();
                    $existingLibrary->delete();
                }
                
                // Create a new library
                $this->libraryService->createTopicLibrary($topic);
                $created++;
                
            } catch (\Exception $e) {
                Log::error("Error creating library for topic #{$topic->id}: " . $e->getMessage());
                $errors++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("Created {$created} topic libraries with {$errors} errors.");
    }
}