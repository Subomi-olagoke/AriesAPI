<?php

namespace App\Http\Controllers;

use App\Services\CognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CognitionController extends Controller
{
    protected $cognitionService;
    
    public function __construct(CognitionService $cognitionService)
    {
        $this->cognitionService = $cognitionService;
    }
    
    /**
     * Get the user's Cognition readlist
     */
    public function getCognitionReadlist()
    {
        try {
            $user = Auth::user();
            $readlist = $this->cognitionService->getCognitionReadlist($user);
            
            // Load the items
            $readlist->load('items');
            
            return response()->json([
                'success' => true,
                'readlist' => $readlist
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Cognition readlist: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the user's Cognition readlist with new resources
     */
    public function updateCognitionReadlist(Request $request)
    {
        try {
            $user = Auth::user();
            $maxItems = $request->input('max_items', 5);
            
            $result = $this->cognitionService->updateCognitionReadlist($user, $maxItems);
            
            if ($result) {
                // Get the updated readlist with items
                $readlist = $this->cognitionService->getCognitionReadlist($user);
                $readlist->load('items');
                
                return response()->json([
                    'success' => true,
                    'message' => 'Cognition readlist updated successfully',
                    'readlist' => $readlist
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No new resources were added to your Cognition readlist'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Cognition readlist: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate and view the user's interest profile
     */
    public function viewInterestProfile()
    {
        try {
            $user = Auth::user();
            $profile = $this->cognitionService->generateUserInterestProfile($user);
            
            return response()->json([
                'success' => true,
                'profile' => $profile
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate interest profile: ' . $e->getMessage()
            ], 500);
        }
    }
}