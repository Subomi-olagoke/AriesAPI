<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class livestreamController extends Controller
{
    public function someAction()
    {
        $response = Http::get("http://localhost:44123/test");

        // Process the response from Go
        $data = $response->json();
        return $data;
    }

}
