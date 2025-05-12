<?php

namespace App\Services;

use App\Services\ApiClientService;
use Illuminate\Support\Facades\Log;

class LibraryApiService
{
    /**
     * The API client instance
     */
    protected $apiClient;
    
    /**
     * Create a new library API service instance
     */
    public function __construct(ApiClientService $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->apiClient->setAdminToken(config('services.heroku_api.admin_token'));
    }
    
    /**
     * Get all libraries from the API
     */
    public function getAllLibraries($filters = [])
    {
        return $this->apiClient->get('admin/libraries', $filters);
    }
    
    /**
     * Get a specific library from the API
     */
    public function getLibrary($id)
    {
        return $this->apiClient->get("admin/libraries/{$id}");
    }
    
    /**
     * Create a new library through the API
     */
    public function createLibrary($data)
    {
        return $this->apiClient->post('libraries', $data);
    }
    
    /**
     * Update a library through the API
     */
    public function updateLibrary($id, $data)
    {
        return $this->apiClient->put("libraries/{$id}", $data);
    }
    
    /**
     * Approve a library through the API
     */
    public function approveLibrary($id, $generateCover = false)
    {
        return $this->apiClient->post("admin/libraries/{$id}/approve", [
            'generate_cover' => $generateCover
        ]);
    }
    
    /**
     * Reject a library through the API
     */
    public function rejectLibrary($id, $reason)
    {
        return $this->apiClient->post("admin/libraries/{$id}/reject", [
            'reason' => $reason
        ]);
    }
    
    /**
     * Generate a cover image for a library through the API
     */
    public function generateCoverImage($id)
    {
        return $this->apiClient->post("admin/libraries/{$id}/generate-cover");
    }
    
    /**
     * Add content to a library through the API
     */
    public function addContent($libraryId, $contentType, $contentId, $relevanceScore = 0.7)
    {
        return $this->apiClient->post("libraries/{$libraryId}/content", [
            'content_type' => $contentType,
            'content_id' => $contentId,
            'relevance_score' => $relevanceScore
        ]);
    }
    
    /**
     * Remove content from a library through the API
     */
    public function removeContent($libraryId, $contentId)
    {
        return $this->apiClient->delete("libraries/{$libraryId}/content", [
            'content_id' => $contentId
        ]);
    }
}