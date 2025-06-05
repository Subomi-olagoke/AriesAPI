<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use DOMDocument;
use Exception;

class UrlFetchService
{
    protected $aiService;
    protected $exaSearchService;
    protected $cacheExpiration = 86400; // 24 hours in seconds

    public function __construct(AIService $aiService, ExaSearchService $exaSearchService)
    {
        $this->aiService = $aiService;
        $this->exaSearchService = $exaSearchService;
    }

    /**
     * Fetch and summarize content from a URL
     *
     * @param string $url The URL to fetch content from
     * @return array The fetched URL data with title, content, and summary
     */
    public function fetchAndSummarize(string $url)
    {
        $cacheKey = 'url_summary_' . md5($url);

        // Check cache first to avoid redundant processing
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Try using Exa service first if configured
            if ($this->exaSearchService->isConfigured()) {
                $result = $this->fetchWithExaService($url);
                if ($result['success']) {
                    Cache::put($cacheKey, $result, $this->cacheExpiration);
                    return $result;
                }
            }

            // Fallback to direct fetching and AI summarization
            $result = $this->fetchAndSummarizeDirectly($url);
            Cache::put($cacheKey, $result, $this->cacheExpiration);
            return $result;
            
        } catch (Exception $e) {
            Log::error('URL fetch and summarize error: ' . $e->getMessage(), [
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'url' => $url,
                'title' => 'Failed to fetch content',
                'content' => '',
                'summary' => 'Could not retrieve content from this URL.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch URL content using the ExaSearchService
     *
     * @param string $url The URL to fetch
     * @return array The processed URL data
     */
    protected function fetchWithExaService(string $url)
    {
        $query = "Get summary and key information from: {$url}";
        
        $searchParams = [
            'includeDomains' => [parse_url($url, PHP_URL_HOST)],
            'numResults' => 1,
            'contentsOptions' => [
                'highlights' => true,
                'text' => true,
                'summary' => true
            ]
        ];
        
        $result = $this->exaSearchService->search($query, 1, $searchParams['includeDomains']);
        
        if ($result['success'] && !empty($result['results'])) {
            $content = $result['results'][0];
            
            return [
                'success' => true,
                'url' => $url,
                'title' => $content['title'] ?? 'No title available',
                'content' => $content['text'] ?? '',
                'summary' => $content['summary'] ?? ($content['highlights'][0] ?? 'No summary available'),
                'source' => 'exa'
            ];
        }
        
        return [
            'success' => false,
            'url' => $url,
            'error' => 'Exa service could not fetch content'
        ];
    }

    /**
     * Fetch URL directly and summarize with AI
     *
     * @param string $url The URL to fetch
     * @return array The processed URL data
     */
    protected function fetchAndSummarizeDirectly(string $url)
    {
        // Fetch the URL content
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; AreisAPI/1.0; +https://aries.com)'
            ])
            ->get($url);
        
        if (!$response->successful()) {
            throw new Exception('Failed to fetch URL: ' . $response->status());
        }
        
        $html = $response->body();
        
        // Extract content from HTML
        $data = $this->extractContentFromHtml($html, $url);
        
        // Generate summary using AI if content is available
        if (!empty($data['content'])) {
            $summary = $this->generateSummary($data['content'], $data['title']);
            $data['summary'] = $summary;
        } else {
            $data['summary'] = 'No content available to summarize.';
        }
        
        $data['success'] = true;
        $data['source'] = 'direct';
        
        return $data;
    }

    /**
     * Extract useful content from HTML
     *
     * @param string $html The HTML content
     * @param string $url The source URL
     * @return array Extracted data (title, content)
     */
    protected function extractContentFromHtml(string $html, string $url)
    {
        $result = [
            'url' => $url,
            'title' => '',
            'content' => ''
        ];
        
        // Use DOMDocument for basic HTML parsing
        libxml_use_internal_errors(true); // Suppress HTML5 parsing errors
        $doc = new DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        // Extract title
        $titleTags = $doc->getElementsByTagName('title');
        if ($titleTags->length > 0) {
            $result['title'] = trim($titleTags->item(0)->textContent);
        }
        
        // Try to get meta description
        $metaTags = $doc->getElementsByTagName('meta');
        foreach ($metaTags as $metaTag) {
            if ($metaTag->getAttribute('name') === 'description') {
                $result['description'] = $metaTag->getAttribute('content');
            }
        }
        
        // Extract main content - this is a basic approach
        // Could be improved with more sophisticated content extraction
        $contentBlocks = [];
        
        // Get content from paragraphs
        $paragraphs = $doc->getElementsByTagName('p');
        foreach ($paragraphs as $paragraph) {
            $text = trim($paragraph->textContent);
            if (strlen($text) > 50) { // Filter out short paragraphs
                $contentBlocks[] = $text;
            }
        }
        
        // Get content from article tags
        $articles = $doc->getElementsByTagName('article');
        foreach ($articles as $article) {
            $text = trim($article->textContent);
            if (strlen($text) > 100) {
                $contentBlocks[] = $text;
            }
        }
        
        // Fallback to divs with substantial content
        if (empty($contentBlocks)) {
            $divs = $doc->getElementsByTagName('div');
            foreach ($divs as $div) {
                $text = trim($div->textContent);
                if (strlen($text) > 200 && strpos($text, '{') === false) { // Avoid JS objects
                    $contentBlocks[] = $text;
                }
            }
        }
        
        // Combine extracted content blocks
        $result['content'] = implode("\n\n", array_slice($contentBlocks, 0, 5));
        
        return $result;
    }

    /**
     * Generate a summary of the content using AI
     *
     * @param string $content The content to summarize
     * @param string $title The title of the content
     * @return string The generated summary
     */
    protected function generateSummary(string $content, string $title = '')
    {
        // Truncate content if too long for API
        $maxLength = 4000;
        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength) . '...';
        }
        
        $titlePrefix = !empty($title) ? "Title: {$title}\n\n" : '';
        $prompt = "{$titlePrefix}Please provide a concise summary (2-3 sentences) of the following content, highlighting the key points and main takeaways:\n\n{$content}";
        
        $summary = $this->aiService->generateResponse($prompt, config('ai.openai.model', 'gpt-3.5-turbo'), 0.5, 300);
        
        if (empty($summary)) {
            // Fallback if AI fails
            $words = str_word_count($content, 1);
            $summaryWords = array_slice($words, 0, 50);
            return implode(' ', $summaryWords) . '...';
        }
        
        return trim($summary);
    }
}