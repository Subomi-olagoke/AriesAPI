<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class ContentModerationService
{
    /**
     * Domains that are allowed in messages (your own domains)
     */
    private $allowedDomains;

    /**
     * Words that might indicate inappropriate content
     */
    private $inappropriateWordsList;
    
    /**
     * Whether content moderation is enabled
     */
    private $enabled;
    
    /**
     * Maximum file size in MB
     */
    private $maxFileSize;
    
    /**
     * File extensions that are considered dangerous
     */
    private $dangerousExtensions;
    
    /**
     * ContentModerationService constructor.
     */
    public function __construct()
    {
        $this->enabled = Config::get('content_moderation.enabled', true);
        $this->allowedDomains = Config::get('content_moderation.allowed_domains', [
            "ariesmvp-9903a26b3095.herokuapp.com",
            "aries-app.com",
            "ariesapi.com"
        ]);
        $this->inappropriateWordsList = Config::get('content_moderation.inappropriate_words', [
            "porn", "xxx", "nude", "naked", "sex", "adult content"
        ]);
        $this->maxFileSize = Config::get('content_moderation.max_file_size', 10);
        $this->dangerousExtensions = Config::get('content_moderation.dangerous_extensions', [
            'exe', 'dll', 'js', 'bat', 'sh', 'command', 'app'
        ]);
    }

    /**
     * Analyze text for inappropriate content
     *
     * @param string|null $text
     * @return array
     */
    public function analyzeText(?string $text): array
    {
        if (empty($text) || !$this->enabled) {
            return ['isAllowed' => true, 'reason' => null];
        }

        // Check for external links
        $linkResult = $this->checkForExternalLinks($text);
        if (!$linkResult['isAllowed']) {
            return $linkResult;
        }

        // Check for inappropriate words
        $wordResult = $this->checkForInappropriateWords($text);
        if (!$wordResult['isAllowed']) {
            return $wordResult;
        }

        // Content passed all checks
        return ['isAllowed' => true, 'reason' => null];
    }

    /**
     * Check for external links in the text
     *
     * @param string $text
     * @return array
     */
    private function checkForExternalLinks(string $text): array
    {
        // URL pattern matching
        $pattern = '/https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)/';
        
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[0] as $url) {
                try {
                    $parsedUrl = parse_url($url);
                    if (!isset($parsedUrl['host'])) {
                        continue;
                    }

                    $host = $parsedUrl['host'];
                    $isAllowed = false;

                    foreach ($this->allowedDomains as $allowedDomain) {
                        if ($host === $allowedDomain || Str::endsWith($host, '.' . $allowedDomain)) {
                            $isAllowed = true;
                            break;
                        }
                    }

                    if (!$isAllowed) {
                        return [
                            'isAllowed' => false,
                            'reason' => 'External links are not allowed in messages'
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Error parsing URL during content moderation', [
                        'url' => $url,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return ['isAllowed' => true, 'reason' => null];
    }

    /**
     * Check for inappropriate words in the text
     *
     * @param string $text
     * @return array
     */
    private function checkForInappropriateWords(string $text): array
    {
        $lowercaseText = strtolower($text);

        foreach ($this->inappropriateWordsList as $word) {
            if (Str::contains($lowercaseText, $word)) {
                return [
                    'isAllowed' => false,
                    'reason' => 'Your message may contain inappropriate content'
                ];
            }
        }

        return ['isAllowed' => true, 'reason' => null];
    }

    /**
     * Analyze file content and metadata
     *
     * @param \Illuminate\Http\UploadedFile|null $file
     * @return array
     */
    public function analyzeFile($file): array
    {
        if (empty($file) || !$this->enabled) {
            return ['isAllowed' => true, 'reason' => null];
        }

        // Check file extension for potentially dangerous files
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (in_array($extension, $this->dangerousExtensions)) {
            return [
                'isAllowed' => false,
                'reason' => 'Executable files are not allowed for security reasons'
            ];
        }

        // Check file size (limit based on config)
        $maxSizeBytes = $this->maxFileSize * 1024 * 1024;
        if ($file->getSize() > $maxSizeBytes) {
            return [
                'isAllowed' => false,
                'reason' => "Files larger than {$this->maxFileSize}MB are not allowed"
            ];
        }

        // For specific content types, perform deeper analysis
        $mimeType = $file->getMimeType();
        
        if (Str::startsWith($mimeType, 'image/')) {
            // For images, we could implement more checks
            // For now, we just check basic mime type and size
        }

        return ['isAllowed' => true, 'reason' => null];
    }

    /**
     * Moderate message content before sending
     *
     * @param string|null $text
     * @param \Illuminate\Http\UploadedFile|null $file
     * @return array
     */
    public function moderateMessage(?string $text, $file = null): array
    {
        if (!$this->enabled) {
            return ['isAllowed' => true, 'reason' => null];
        }
        
        // First check text
        if (!empty($text)) {
            $textResult = $this->analyzeText($text);
            if (!$textResult['isAllowed']) {
                return $textResult;
            }
        }

        // Check file if present
        if (!empty($file)) {
            $fileResult = $this->analyzeFile($file);
            if (!$fileResult['isAllowed']) {
                return $fileResult;
            }
        }

        return ['isAllowed' => true, 'reason' => null];
    }
}