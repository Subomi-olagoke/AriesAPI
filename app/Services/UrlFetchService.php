<?php

namespace App\Services;

use App\Services\AIService;
use App\Services\ExaSearchService;
use Illuminate\Support\Facades\Log;

/**
 * URL Fetch Service
 *
 * This service is currently not in use but is injected into some controllers.
 * The iOS app handles URL metadata fetching client-side using ArticleExtractor.
 * Keeping this as a stub to prevent dependency injection errors.
 */
class UrlFetchService
{
    protected $aiService;
    protected $exaSearchService;

    /**
     * Create a new UrlFetchService instance.
     *
     * @param AIService $aiService
     * @param ExaSearchService $exaSearchService
     */
    public function __construct(AIService $aiService, ExaSearchService $exaSearchService)
    {
        $this->aiService = $aiService;
        $this->exaSearchService = $exaSearchService;
    }

    /**
     * Fetch metadata for a given URL
     * 
     * NOTE: This method is not currently used. The iOS app handles metadata
     * fetching client-side using ArticleExtractor.swift
     *
     * @param string $url
     * @return array
     */
    public function fetchUrlMetadata($url)
    {
        Log::info("UrlFetchService::fetchUrlMetadata called for: {$url}");

        // Stub implementation - returns basic metadata
        return [
            'url' => $url,
            'title' => null,
            'description' => null,
            'image' => null,
        ];
    }

    /**
     * Fetch and parse content from a URL
     * 
     * NOTE: This method is not currently used.
     *
     * @param string $url
     * @return array
     */
    public function fetchContent($url)
    {
        Log::info("UrlFetchService::fetchContent called for: {$url}");

        // Stub implementation
        return [
            'url' => $url,
            'content' => null,
            'metadata' => [],
        ];
    }
}
