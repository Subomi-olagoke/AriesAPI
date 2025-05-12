<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiClientService
{
    /**
     * The base URL for the Heroku API
     */
    protected $baseUrl;
    
    /**
     * Default headers to include with every request
     */
    protected $headers = [];
    
    /**
     * Create a new API client instance
     */
    public function __construct()
    {
        $this->baseUrl = config('services.heroku_api.url', 'https://ariesmvp-9903a26b3095.herokuapp.com/api');
        $this->headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
    
    /**
     * Set the admin API token for authentication
     */
    public function setAdminToken($token)
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;
        return $this;
    }
    
    /**
     * Send a GET request to the API
     */
    public function get($endpoint, $params = [])
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get($this->baseUrl . '/' . ltrim($endpoint, '/'), $params);
                
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('API GET request failed: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'params' => $params
            ]);
            
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send a POST request to the API
     */
    public function post($endpoint, $data = [])
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->post($this->baseUrl . '/' . ltrim($endpoint, '/'), $data);
                
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('API POST request failed: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'data' => $data
            ]);
            
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send a PUT request to the API
     */
    public function put($endpoint, $data = [])
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->put($this->baseUrl . '/' . ltrim($endpoint, '/'), $data);
                
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('API PUT request failed: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'data' => $data
            ]);
            
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send a DELETE request to the API
     */
    public function delete($endpoint, $data = [])
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->delete($this->baseUrl . '/' . ltrim($endpoint, '/'), $data);
                
            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('API DELETE request failed: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'data' => $data
            ]);
            
            return [
                'success' => false,
                'message' => 'API request failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle the API response
     */
    protected function handleResponse($response)
    {
        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json()
            ];
        }
        
        $error = $response->json();
        Log::error('API request failed with status ' . $response->status(), [
            'url' => $response->effectiveUri(),
            'response' => $error
        ]);
        
        return [
            'success' => false,
            'message' => $error['message'] ?? 'API request failed',
            'errors' => $error['errors'] ?? [],
            'status' => $response->status()
        ];
    }
}