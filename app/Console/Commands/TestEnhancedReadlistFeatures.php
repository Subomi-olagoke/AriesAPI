<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Readlist;
use App\Services\EnhancedTopicExtractionService;
use App\Services\AIReadlistImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestEnhancedReadlistFeatures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:enhanced-readlist-features 
                           {--user-id= : User ID to test with}
                           {--topic= : Topic to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the enhanced readlist features including topic extraction and AI image generation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Enhanced Readlist Features...');
        
        // Get test user
        $userId = $this->option('user-id');
        $user = $userId ? User::find($userId) : User::first();
        
        if (!$user) {
            $this->error('No user found for testing');
            return 1;
        }
        
        $this->info("Testing with user: {$user->username} (ID: {$user->id})");
        
        // Test topic extraction
        $this->testTopicExtraction();
        
        // Test AI image generation
        $this->testAIImageGeneration($user);
        
        $this->info('Enhanced readlist features test completed!');
        return 0;
    }
    
    /**
     * Test enhanced topic extraction
     */
    private function testTopicExtraction()
    {
        $this->info('Testing Enhanced Topic Extraction...');
        
        $topicService = app(EnhancedTopicExtractionService::class);
        
        $testInputs = [
            'create a readlist about machine learning',
            'I want to learn about web development',
            'show me resources about artificial intelligence',
            'build me a reading list on data science'
        ];
        
        foreach ($testInputs as $input) {
            $this->line("Testing input: '{$input}'");
            
            try {
                $result = $topicService->extractAndEnhanceTopic($input);
                
                $this->line("  Primary Topic: " . ($result['primary_topic'] ?? 'N/A'));
                $this->line("  Category: " . ($result['category'] ?? 'N/A'));
                $this->line("  Learning Level: " . ($result['learning_level'] ?? 'N/A'));
                $this->line("  Search Keywords: " . implode(', ', $result['search_keywords'] ?? []));
                $this->line("  Extraction Method: " . ($result['extraction_method'] ?? 'N/A'));
                $this->line('');
                
            } catch (\Exception $e) {
                $this->error("  Error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Test AI image generation
     */
    private function testAIImageGeneration(User $user)
    {
        $this->info('Testing AI Image Generation...');
        
        // Create a test readlist
        $readlist = new Readlist([
            'user_id' => $user->id,
            'title' => 'Test Readlist: Machine Learning',
            'description' => 'A collection of resources about machine learning, artificial intelligence, and data science.',
            'is_public' => true,
        ]);
        
        $readlist->save();
        
        $this->line("Created test readlist: {$readlist->title} (ID: {$readlist->id})");
        
        // Test AI image generation
        $aiImageService = app(AIReadlistImageService::class);
        
        try {
            $this->line('Generating AI image...');
            $imageUrl = $aiImageService->generateReadlistImage($readlist, 'machine learning', [
                'search_keywords' => ['machine learning', 'artificial intelligence', 'data science', 'algorithms']
            ]);
            
            if ($imageUrl) {
                $this->info("Successfully generated AI image: {$imageUrl}");
                
                // Update the readlist with the generated image
                $readlist->image_url = $imageUrl;
                $readlist->save();
                
                $this->info('Readlist updated with AI-generated image!');
            } else {
                $this->error('Failed to generate AI image');
            }
            
        } catch (\Exception $e) {
            $this->error("Error generating AI image: " . $e->getMessage());
        }
        
        // Clean up test readlist
        $this->line('Cleaning up test readlist...');
        $readlist->delete();
        $this->info('Test readlist deleted');
    }
} 