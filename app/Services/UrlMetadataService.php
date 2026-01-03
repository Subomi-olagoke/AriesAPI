<?php

namespace App\Services;


use Embed\Embed;
use Illuminate\Support\Facades\Log;

class UrlMetadataService
{
    /**
     * Fetch metadata for a given URL using embed/embed.
     * Returns title, description, and thumbnail image URL.
     *
     * @param string $url
     * @return array
     */
    public function fetchMetadata(string $url): array
    {
        try {
            $embed = new Embed();
            $info = $embed->get($url);

            // Get the best available image
            $image = $info->image;

            return [
                'title' => $info->title,
                'description' => $info->description,
                'thumbnail_url' => $image ? (string) $image : null,
            ];

        } catch (\Exception $e) {
            Log::warning('URL metadata fetch failed with embed/embed: ' . $e->getMessage(), ['url' => $url]);
            return $this->getFallbackMetadata($url);
        }
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
