<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class livestreamController extends Controller
{
    public function someAction()
    {
        // Use environment variable for Go service URL with HTTPS fallback
        $goServiceUrl = env('GO_SERVICE_URL', 'https://localhost:44123/test');
        $response = Http::get($goServiceUrl);

        // Process the response from Go
        $data = $response->json();
        return $data;
    }

}
