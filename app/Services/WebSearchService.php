<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WebSearchService
{
    private string $apiKey;
    private string $baseUrl = 'https://google.serper.dev';

    public function __construct()
    {
        $this->apiKey = config('services.serper.api_key', 'f53f322261f16468c3d40726f7ca2d9effad76f9');
    }

    /**
     * Search the web using Serper.dev
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search(string $query, int $limit = 10): array
    {
        $cacheKey = 'web_search_' . md5($query . '_' . $limit);
        
        // Cache results for 1 hour
        return Cache::remember($cacheKey, 3600, function() use ($query, $limit) {
            try {
                Log::info("🔍 Performing web search", ['query' => $query, 'limit' => $limit]);

                $response = Http::withHeaders([
                    'X-API-KEY' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl . '/search', [
                    'q' => $query,
                    'num' => min($limit, 15),
                ]);

                if (!$response->successful()) {
                    Log::error("❌ Serper.dev API error", [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return [];
                }

                $data = $response->json();
                $results = [];

                // Process organic search results
                if (isset($data['organic'])) {
                    foreach ($data['organic'] as $index => $result) {
                        $results[] = [
                            'id' => 'web_' . md5($result['link'] ?? $index),
                            'title' => $result['title'] ?? 'Untitled',
                            'url' => $result['link'] ?? '',
                            'snippet' => $result['snippet'] ?? '',
                            'thumbnail' => $result['imageUrl'] ?? null,
                            'source' => $this->extractDomain($result['link'] ?? ''),
                            'type' => 'web',
                            'position' => $index + 1,
                            'date' => $result['date'] ?? null,
                        ];
                    }
                }

                // Add knowledge graph if available
                if (isset($data['knowledgeGraph'])) {
                    $kg = $data['knowledgeGraph'];
                    array_unshift($results, [
                        'id' => 'web_kg_' . md5($kg['title'] ?? ''),
                        'title' => $kg['title'] ?? 'Knowledge Graph',
                        'url' => $kg['website'] ?? '',
                        'snippet' => $kg['description'] ?? '',
                        'thumbnail' => $kg['imageUrl'] ?? null,
                        'source' => 'Knowledge Graph',
                        'type' => 'web_knowledge',
                        'position' => 0,
                        'date' => null,
                    ]);
                }

                Log::info("✅ Web search completed", ['results_count' => count($results)]);
                return $results;

            } catch (\Exception $e) {
                Log::error("❌ Web search failed", [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);
                return [];
            }
        });
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return 'Web';
        }
        
        // Remove www. prefix
        $host = preg_replace('/^www\./', '', $host);
        
        // Capitalize first letter of each word
        return ucfirst($host);
    }
}
