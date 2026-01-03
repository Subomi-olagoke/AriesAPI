<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UrlMetadataService
{
    /**
     * Fetch metadata for a given URL.
     * Returns title, description, and thumbnail image URL.
     *
     * @param string $url
     * @return array
     */
    public function fetchMetadata(string $url): array
    {
        try {
            // Use a simple HTTP request to fetch the page
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; AlexandriaBot/1.0; +https://alexandria.app)'
                ])
                ->get($url);

            if (!$response->successful()) {
                return $this->getFallbackMetadata($url);
            }

            $html = $response->body();
            
            // Extract metadata using regex and DOMDocument
            $metadata = [
                'title' => $this->extractTitle($html, $url),
                'description' => $this->extractDescription($html),
                'thumbnail_url' => $this->extractImage($html, $url),
            ];

            return $metadata;

        } catch (\Exception $e) {
            Log::warning('URL metadata fetch failed: ' . $e->getMessage(), ['url' => $url]);
            return $this->getFallbackMetadata($url);
        }
    }

    /**
     * Extract title from HTML.
     */
    private function extractTitle(string $html, string $url): string
    {
        // Try Open Graph title first
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try Twitter title
        if (preg_match('/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try standard title tag
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Fallback to domain
        $parsed = parse_url($url);
        $domain = $parsed['host'] ?? 'Unknown';
        return preg_replace('/^www\./', '', ucfirst($domain));
    }

    /**
     * Extract description from HTML.
     */
    private function extractDescription(string $html): ?string
    {
        // Try Open Graph description
        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try Twitter description
        if (preg_match('/<meta\s+name=["\']twitter:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Try standard meta description
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    /**
     * Extract image from HTML.
     */
    private function extractImage(string $html, string $url): ?string
    {
        // Try Open Graph image
        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return $this->normalizeUrl($matches[1], $url);
        }

        // Try Twitter image
        if (preg_match('/<meta\s+name=["\']twitter:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return $this->normalizeUrl($matches[1], $url);
        }

        // Try to find any image in meta tags
        if (preg_match('/<meta\s+(?:name|property)=["\'](?:image|thumbnail)["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            return $this->normalizeUrl($matches[1], $url);
        }

        return null;
    }

    /**
     * Normalize relative URLs to absolute URLs.
     */
    private function normalizeUrl(string $imageUrl, string $baseUrl): string
    {
        // If already absolute, return as is
        if (preg_match('/^https?:\/\//i', $imageUrl)) {
            return $imageUrl;
        }

        // Parse base URL
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        // Handle protocol-relative URLs (//example.com/image.jpg)
        if (strpos($imageUrl, '//') === 0) {
            return $scheme . ':' . $imageUrl;
        }

        // Handle absolute paths (/image.jpg)
        if (strpos($imageUrl, '/') === 0) {
            return $scheme . '://' . $host . $imageUrl;
        }

        // Handle relative paths (image.jpg)
        $path = $parsed['path'] ?? '/';
        $directory = dirname($path);
        return $scheme . '://' . $host . $directory . '/' . $imageUrl;
    }

    /**
     * Get fallback metadata when fetching fails.
     */
    private function getFallbackMetadata(string $url): array
    {
        $parsed = parse_url($url);
        $domain = $parsed['host'] ?? 'Unknown';
        $title = preg_replace('/^www\./', '', ucfirst($domain));

        return [
            'title' => $title,
            'description' => $url,
            'thumbnail_url' => null,
        ];
    }
}
