<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Readlist;
use App\Services\AIReadlistImageService;

class GenerateReadlistImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $readlistId;
    protected $topic;
    protected $enhancedTopicInfo;

    /**
     * Create a new job instance.
     */
    public function __construct($readlistId, $topic, $enhancedTopicInfo = [])
    {
        $this->readlistId = $readlistId;
        $this->topic = $topic;
        $this->enhancedTopicInfo = $enhancedTopicInfo;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $readlist = Readlist::find($this->readlistId);
        if (!$readlist) {
            \Log::warning('GenerateReadlistImage job: Readlist not found', ['readlist_id' => $this->readlistId]);
            return;
        }
        $aiImageService = app(AIReadlistImageService::class);
        $keywords = $aiImageService->extractKeywordsFromReadlist($readlist);
        if (!empty($this->enhancedTopicInfo['search_keywords'])) {
            $keywords = array_merge($keywords, $this->enhancedTopicInfo['search_keywords']);
        }
        $keywords = array_unique($keywords);
        $keywords = array_slice($keywords, 0, 8);
        \Log::info('GenerateReadlistImage job: Starting image generation', [
            'readlist_id' => $readlist->id,
            'topic' => $this->topic,
            'keywords' => $keywords
        ]);
        $imageUrl = $aiImageService->generateReadlistImage($readlist, $this->topic, $keywords);
        if ($imageUrl) {
            \Log::info('GenerateReadlistImage job: Image generated', [
                'readlist_id' => $readlist->id,
                'image_url' => $imageUrl
            ]);
        } else {
            \Log::warning('GenerateReadlistImage job: Failed to generate image', [
                'readlist_id' => $readlist->id,
                'topic' => $this->topic
            ]);
        }
    }
}
