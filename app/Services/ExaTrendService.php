<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExaTrendService
{
    /**
     * We're not using this method in the simplified approach, but keeping it
     * as it might be useful in the future.
     */
    public function getTrendingTopicsData($topicNames, $timeframe)
    {
        // Just return an empty array since we're not using this in the simplified approach
        return [];
    }
    
    /**
     * Ask Exa.ai to identify currently trending topics in education/technology
     * 
     * @param int $limit Number of trending topics to return
     * @return array Array of trending topics
     */
    public function discoverTrendingTopics($limit = 10)
    {
        // Create cache key
        $cacheKey = 'exa_discovered_trends_' . $limit;
        
        // Return cached data if available (cached for 12 hours)
        return Cache::remember($cacheKey, 43200, function () use ($limit) {
            try {
                Log::info('Starting discovery of trending topics with Exa.ai');
                $exaService = app(ExaSearchService::class);
                
                // Use Exa's search capability to find trending topics
                $searchResults = $exaService->search(
                    "trending topics in education technology programming 2025", 
                    20, // More results for better diversity
                    [
                        'medium.com', 'techcrunch.com', 'wired.com', 'forbes.com',
                        'dev.to', 'github.com', 'coursera.org', 'edx.org',
                        'freecodecamp.org', 'pluralsight.com', 'udacity.com',
                        'stackoverflow.com', 'linkedin.com', 'kaggle.com'
                    ], // includeDomains - wider set of sources
                    true, // safeSearch
                    [], // excludeDomains
                    'neural', // search type
                    '', // category
                    [
                        'start' => now()->subDays(30)->format('Y-m-d'), // Last 30 days for more current trends
                        'end' => now()->format('Y-m-d')
                    ] // dateRange
                );
                
                // Extract topic mentions from search results
                $topicMentions = $this->extractTopicMentionsFromResults($searchResults['results'] ?? []);
                
                // Sort by frequency and take top results
                arsort($topicMentions);
                $trendingTopics = array_slice($topicMentions, 0, $limit * 2, true); // Get more than needed for filtering
                
                // Format results
                $formattedTopics = [];
                foreach ($trendingTopics as $topic => $count) {
                    // Skip generic or vague topics
                    if (in_array(strtolower($topic), ['topics', 'technology', 'education', 'programming', 'learning', 'engineering'])) {
                        continue;
                    }
                    
                    $formattedTopics[] = [
                        'name' => $topic,
                        'description' => $this->generateTopicDescription($topic, $searchResults['results'] ?? []),
                        'mention_count' => $count
                    ];
                    
                    // Break once we have enough
                    if (count($formattedTopics) >= $limit) {
                        break;
                    }
                }
                
                Log::info('Found ' . count($formattedTopics) . ' trending topics via Exa.ai');
                
                return [
                    'topics' => $formattedTopics,
                    'sources' => $this->extractSourcesFromResults($searchResults['results'] ?? [])
                ];
                
            } catch (\Exception $e) {
                Log::error("Error discovering topics with Exa.ai: " . $e->getMessage());
                return [
                    'topics' => [],
                    'sources' => []
                ];
            }
        });
    }
    
    /**
     * Calculate trend score based on search results
     */
    private function calculateTrendScore($searchResults)
    {
        // Base score on result count, freshness, and prominence
        $resultCount = count($searchResults['results'] ?? []);
        
        if ($resultCount === 0) {
            return 0;
        }
        
        // Calculate average freshness (days since publication)
        $totalFreshness = 0;
        $freshnessCount = 0;
        
        foreach ($searchResults['results'] as $result) {
            if (isset($result['published_date'])) {
                $publishedDate = strtotime($result['published_date']);
                if ($publishedDate) {
                    $daysSincePublished = (time() - $publishedDate) / 86400; // 86400 seconds in a day
                    $totalFreshness += max(0, 30 - $daysSincePublished); // Higher score for newer content
                    $freshnessCount++;
                }
            }
        }
        
        $freshnessScore = $freshnessCount > 0 ? $totalFreshness / $freshnessCount : 0;
        
        // Calculate prominence based on domain authority
        $prominenceScore = 0;
        foreach ($searchResults['results'] as $result) {
            if (isset($result['domain'])) {
                $domain = $result['domain'];
                if (strpos($domain, 'medium.com') !== false) $prominenceScore += 3;
                if (strpos($domain, 'github.com') !== false) $prominenceScore += 4;
                if (strpos($domain, 'techcrunch.com') !== false) $prominenceScore += 5;
                if (strpos($domain, 'wired.com') !== false) $prominenceScore += 4;
                if (strpos($domain, 'coursera.org') !== false) $prominenceScore += 4;
                if (strpos($domain, 'udemy.com') !== false) $prominenceScore += 3;
                if (strpos($domain, 'stackoverflow.com') !== false) $prominenceScore += 4;
            }
        }
        
        // Combine factors for final score (0-100)
        $finalScore = min(100, ($resultCount * 5) + ($freshnessScore * 2) + $prominenceScore);
        
        return $finalScore;
    }
    
    /**
     * Extract related concepts from search results
     */
    private function extractRelatedConcepts($searchResults, $mainTopic)
    {
        $concepts = [];
        $mainTopicLower = strtolower($mainTopic);
        
        // Process highlights and extract key phrases
        foreach ($searchResults['results'] ?? [] as $result) {
            if (isset($result['highlights'])) {
                foreach ($result['highlights'] as $highlight) {
                    // Use regex to extract likely technical terms and concepts
                    preg_match_all('/\b([A-Z][a-z]+(?:\s[A-Z][a-z]+)*|[a-z]+\s(?:learning|networks|framework|language|model|algorithm|library))\b/', $highlight, $matches);
                    
                    if (!empty($matches[0])) {
                        foreach ($matches[0] as $match) {
                            // Don't include the main topic itself
                            if (strtolower($match) !== $mainTopicLower) {
                                $concepts[] = $match;
                            }
                        }
                    }
                }
            }
        }
        
        // Count occurrences and take top 5
        $conceptCounts = array_count_values($concepts);
        arsort($conceptCounts);
        
        return array_slice(array_keys($conceptCounts), 0, 5);
    }
    
    /**
     * Extract trending resources from search results
     */
    private function extractTrendingResources($searchResults)
    {
        $resources = [];
        
        foreach ($searchResults['results'] ?? [] as $result) {
            $resources[] = [
                'title' => $result['title'] ?? 'Untitled',
                'url' => $result['url'] ?? '',
                'relevance_score' => $result['score'] ?? 0,
                'published_date' => $result['published_date'] ?? null
            ];
        }
        
        // Sort by relevance and take top 3
        usort($resources, function($a, $b) {
            return ($b['relevance_score'] ?? 0) <=> ($a['relevance_score'] ?? 0);
        });
        
        return array_slice($resources, 0, 3);
    }
    
    /**
     * Extract topic mentions from search results
     */
    private function extractTopicMentionsFromResults($results)
    {
        $stopWords = ['the', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'about', 'like', 'through', 'over', 'before', 'between', 'after', 'from', 'up', 'down', 'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'shall', 'should', 'may', 'might', 'must', 'can', 'could', 'of', 'a', 'an', 'this', 'that', 'these', 'those', 'my', 'your', 'his', 'her', 'its', 'our', 'their', 'which', 'who', 'whom', 'whose', 'what', 'where', 'when', 'why', 'how'];
        
        $topicMentions = [];
        
        foreach ($results as $result) {
            if (isset($result['text'])) {
                // Extract potential topics using NLP-inspired pattern matching
                preg_match_all('/\b(?:[A-Z][a-z]+\s)*[A-Z][a-z]+\b|\b[A-Za-z]+\s(?:Programming|Learning|Development|Intelligence|Science|Engineering|Technology|Languages?|Framework|Platform)\b/i', $result['text'], $matches);
                
                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        $topic = trim($match);
                        
                        // Skip if too short or is a stop word
                        if (strlen($topic) < 4 || in_array(strtolower($topic), $stopWords)) {
                            continue;
                        }
                        
                        // Count mentions
                        if (!isset($topicMentions[$topic])) {
                            $topicMentions[$topic] = 1;
                        } else {
                            $topicMentions[$topic]++;
                        }
                    }
                }
            }
            
            // Also check the title for topic mentions
            if (isset($result['title'])) {
                preg_match_all('/\b(?:[A-Z][a-z]+\s)*[A-Z][a-z]+\b|\b[A-Za-z]+\s(?:Programming|Learning|Development|Intelligence|Science|Engineering|Technology|Languages?|Framework|Platform)\b/i', $result['title'], $matches);
                
                if (!empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        $topic = trim($match);
                        
                        // Skip if too short or is a stop word
                        if (strlen($topic) < 4 || in_array(strtolower($topic), $stopWords)) {
                            continue;
                        }
                        
                        // Give more weight to title mentions
                        if (!isset($topicMentions[$topic])) {
                            $topicMentions[$topic] = 2; // Title mentions count double
                        } else {
                            $topicMentions[$topic] += 2;
                        }
                    }
                }
            }
        }
        
        return $topicMentions;
    }
    
    /**
     * Generate a description for a discovered topic
     */
    private function generateTopicDescription($topic, $results)
    {
        // Find mentions of the topic in the results
        $relevantSnippets = [];
        
        foreach ($results as $result) {
            if (isset($result['text'])) {
                $text = $result['text'];
                
                // Check if topic is mentioned in the text
                if (stripos($text, $topic) !== false) {
                    // Extract a sentence or two around the mention
                    $pattern = '/[^.!?]*\b' . preg_quote($topic, '/') . '\b[^.!?]*[.!?]/i';
                    preg_match_all($pattern, $text, $matches);
                    
                    if (!empty($matches[0])) {
                        foreach ($matches[0] as $match) {
                            $relevantSnippets[] = trim($match);
                        }
                    }
                }
            }
        }
        
        if (!empty($relevantSnippets)) {
            // Use the most informative snippet as the description
            usort($relevantSnippets, function($a, $b) {
                return strlen($b) <=> strlen($a); // Longer snippets first
            });
            
            return $relevantSnippets[0];
        }
        
        // Default description if no good snippets found
        return "A trending topic in technology and education.";
    }
    
    /**
     * Extract source information from results
     */
    private function extractSourcesFromResults($results)
    {
        $sources = [];
        
        foreach ($results as $result) {
            if (isset($result['url']) && isset($result['title'])) {
                $sources[] = [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'domain' => $result['domain'] ?? parse_url($result['url'], PHP_URL_HOST)
                ];
            }
        }
        
        return array_slice($sources, 0, 5); // Return top 5 sources
    }
}