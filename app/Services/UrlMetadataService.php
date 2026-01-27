<?php

namespace App\Services;


use Embed\Embed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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
        // Check for special platform handling first
        if ($this->isTikTokUrl($url)) {
            return $this->fetchTikTokMetadata($url);
        }

        if ($this->isInstagramUrl($url)) {
            return $this->fetchInstagramMetadata($url);
        }

        if ($this->isSpotifyUrl($url)) {
            return $this->fetchSpotifyMetadata($url);
        }

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
     * Check if URL is a TikTok URL
     */
    private function isTikTokUrl(string $url): bool
    {
        return (bool) preg_match('/tiktok\.com|vm\.tiktok\.com/i', $url);
    }

    /**
     * Check if URL is an Instagram URL
     */
    private function isInstagramUrl(string $url): bool
    {
        return (bool) preg_match('/instagram\.com|instagr\.am/i', $url);
    }

    /**
     * Check if URL is a Spotify URL
     */
    private function isSpotifyUrl(string $url): bool
    {
        return (bool) preg_match('/open\.spotify\.com/i', $url);
    }

    /**
     * Fetch metadata for TikTok URLs using their oEmbed API
     */
    private function fetchTikTokMetadata(string $url): array
    {
        try {
            // Resolve shortened URLs like vm.tiktok.com
            $resolvedUrl = $this->resolveShortenedUrl($url);

            // TikTok's official oEmbed endpoint
            $oembedUrl = 'https://www.tiktok.com/oembed?url=' . urlencode($resolvedUrl);

            $response = Http::timeout(10)
                ->withUserAgent('Mozilla/5.0 (compatible; Alexandria/1.0; +https://alexandria.app)')
                ->get($oembedUrl);

            if ($response->successful()) {
                $data = $response->json();

                $title = $data['title'] ?? null;
                $authorName = $data['author_name'] ?? null;
                $thumbnail = $data['thumbnail_url'] ?? null;

                // Build a descriptive title
                $displayTitle = $title;
                if ($authorName && $title) {
                    $displayTitle = "{$authorName}: {$title}";
                } elseif ($authorName) {
                    $displayTitle = "TikTok by @{$authorName}";
                } elseif (!$title) {
                    $displayTitle = "TikTok Video";
                }

                // Truncate title if too long
                if (strlen($displayTitle) > 200) {
                    $displayTitle = substr($displayTitle, 0, 197) . '...';
                }

                return [
                    'title' => $displayTitle,
                    'description' => $authorName ? "TikTok video by @{$authorName}" : "TikTok video",
                    'thumbnail_url' => $thumbnail,
                    'author' => $authorName,
                    'platform' => 'tiktok',
                    'original_url' => $resolvedUrl, // Return the resolved URL
                ];
            }

            Log::warning('TikTok oEmbed API returned non-success status', ['url' => $url, 'status' => $response->status()]);

        } catch (\Exception $e) {
            Log::warning('TikTok metadata fetch failed: ' . $e->getMessage(), ['url' => $url]);
        }

        // Return platform-specific fallback
        return $this->getTikTokFallbackMetadata($url);
    }

    /**
     * Fetch metadata for Instagram URLs
     */
    private function fetchInstagramMetadata(string $url): array
    {
        try {
            $resolvedUrl = $this->resolveShortenedUrl($url);

            // Try to extract info from the URL pattern
            $parsed = parse_url($resolvedUrl);
            $path = $parsed['path'] ?? '';

            // Extract username from profile URLs (/username/)
            // Extract reel/post ID from /reel/CODE/ or /p/CODE/
            if (preg_match('/^\/@?([a-zA-Z0-9_.]+)\/?$/', $path, $matches)) {
                $username = $matches[1];
                return [
                    'title' => "@{$username} on Instagram",
                    'description' => "Instagram profile of @{$username}",
                    'thumbnail_url' => null,
                    'author' => $username,
                    'platform' => 'instagram',
                    'original_url' => $resolvedUrl,
                ];
            }

            if (preg_match('/\/(reel|reels)\/([a-zA-Z0-9_-]+)/i', $path, $matches)) {
                $reelId = $matches[2];
                return [
                    'title' => "Instagram Reel",
                    'description' => "Instagram Reel ({$reelId})",
                    'thumbnail_url' => null,
                    'content_type' => 'reel',
                    'platform' => 'instagram',
                    'original_url' => $resolvedUrl,
                ];
            }

            if (preg_match('/\/p\/([a-zA-Z0-9_-]+)/i', $path, $matches)) {
                $postId = $matches[1];
                return [
                    'title' => "Instagram Post",
                    'description' => "Instagram post ({$postId})",
                    'thumbnail_url' => null,
                    'content_type' => 'post',
                    'platform' => 'instagram',
                    'original_url' => $resolvedUrl,
                ];
            }

            // Try embed/embed as fallback for Instagram
            try {
                $embed = new Embed();
                $info = $embed->get($resolvedUrl);

                if ($info->title || $info->description) {
                    return [
                        'title' => $info->title ?? 'Instagram Content',
                        'description' => $info->description ?? 'Instagram post',
                        'thumbnail_url' => $info->image ? (string) $info->image : null,
                        'platform' => 'instagram',
                        'original_url' => $resolvedUrl,
                    ];
                }
            } catch (\Exception $e) {
                // Silently continue to fallback
            }

        } catch (\Exception $e) {
            Log::warning('Instagram metadata fetch failed: ' . $e->getMessage(), ['url' => $url]);
        }

        // Return platform-specific fallback
        return $this->getInstagramFallbackMetadata($url);
    }

    /**
     * Fetch metadata for Spotify URLs
     */
    private function fetchSpotifyMetadata(string $url): array
    {
        try {
            $resolvedUrl = $this->resolveShortenedUrl($url);

            $embed = new Embed();
            $info = $embed->get($resolvedUrl);

            $title = $info->title;
            $description = $info->description;
            $thumbnail = $info->image;
            $author = $info->authorName;

            // Custom description if the main one is empty
            if (empty($description) && $author && $title) {
                $description = "{$title} by {$author}";
            } elseif (empty($description)) {
                $description = "Listen on Spotify";
            }

            return [
                'title' => $title ?? 'Spotify',
                'description' => $description,
                'thumbnail_url' => $thumbnail ? (string) $thumbnail : null,
                'author' => $author,
                'platform' => 'spotify',
                'original_url' => $resolvedUrl,
            ];

        } catch (\Exception $e) {
            Log::warning('Spotify metadata fetch failed: ' . $e->getMessage(), ['url' => $url]);
        }

        // Fallback if API fails
        return $this->getSpotifyFallbackMetadata($url);
    }

    /**
     * TikTok-specific fallback metadata
     */
    private function getTikTokFallbackMetadata(string $url): array
    {
        // Try to extract username from URL
        $username = null;
        if (preg_match('/@([a-zA-Z0-9_.]+)/i', $url, $matches)) {
            $username = $matches[1];
        }

        return [
            'title' => $username ? "TikTok by @{$username}" : 'TikTok Video',
            'description' => 'TikTok video content',
            'thumbnail_url' => null,
            'author' => $username,
            'platform' => 'tiktok',
        ];
    }

    /**
     * Instagram-specific fallback metadata
     */
    private function getInstagramFallbackMetadata(string $url): array
    {
        return [
            'title' => 'Instagram Content',
            'description' => 'Instagram content',
            'thumbnail_url' => null,
            'platform' => 'instagram',
        ];
    }

    /**
     * Spotify-specific fallback metadata
     */
    private function getSpotifyFallbackMetadata(string $url): array
    {
        return [
            'title' => 'Spotify Content',
            'description' => 'Spotify content',
            'thumbnail_url' => null,
            'platform' => 'spotify',
        ];
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

    /**
     * Resolve shortened URLs by following redirects.
     */
    private function resolveShortenedUrl(string $url): string
    {
        try {
            $response = Http::withOptions(['allow_redirects' => false])
                ->head($url);

            // If a redirect is found, return the new location
            if ($response->status() >= 300 && $response->status() < 400 && $response->header('Location')) {
                return $response->header('Location');
            }
        } catch (\Exception $e) {
            Log::warning('URL redirection check failed: ' . $e->getMessage(), ['url' => $url]);
        }

        // Return original URL if no redirect or an error occurs
        return $url;
    }
}
