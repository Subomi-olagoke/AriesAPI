<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\LibraryUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class HighlightController extends Controller
{
    /**
     * Fetch all highlights for a specific URL (by the authenticated user)
     * 
     * @param Request $request
     * @param string $urlId
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchHighlights(Request $request, $urlId)
    {
        try {
            $user = $request->user();

            // Fetch highlights for the given URL and user
            $highlights = Highlight::where('url_id', $urlId)
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($highlights->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No highlights found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'highlights' => $highlights
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching highlights: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch highlights: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new highlight
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createHighlight(Request $request)
    {
        try {
            $user = $request->user();

            // Validate input
            $validator = Validator::make($request->all(), [
                'url_id' => 'required|string',
                'url' => 'required|url',
                'selected_text' => 'required|string|min:1',
                'note' => 'nullable|string',
                'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'range_start' => 'required|integer|min:0',
                'range_end' => 'required|integer|gt:range_start'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Create the highlight
            $highlight = Highlight::create([
                'url_id' => $request->input('url_id'),
                'user_id' => $user->id,
                'url' => $request->input('url'),
                'selected_text' => $request->input('selected_text'),
                'note' => $request->input('note'),
                'color' => $request->input('color', '#FFEB3B'),
                'range_start' => $request->input('range_start'),
                'range_end' => $request->input('range_end'),
            ]);

            Log::info('Highlight created successfully', [
                'highlight_id' => $highlight->id,
                'user_id' => $user->id,
                'url_id' => $request->input('url_id')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Highlight created successfully',
                'highlight' => $highlight
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating highlight: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create highlight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a highlight's note
     * 
     * @param Request $request
     * @param string $highlightId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateHighlightNote(Request $request, $highlightId)
    {
        try {
            $user = $request->user();

            // Find the highlight
            $highlight = Highlight::find($highlightId);

            if (!$highlight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Highlight not found'
                ], 404);
            }

            // Check if the user owns this highlight
            if ($highlight->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => "You don't have permission to update this highlight"
                ], 403);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'note' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Update the note
            $highlight->note = $request->input('note');
            $highlight->save();

            Log::info('Highlight updated successfully', [
                'highlight_id' => $highlight->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Highlight updated successfully',
                'highlight' => $highlight
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating highlight: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update highlight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a highlight
     * 
     * @param Request $request
     * @param string $highlightId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteHighlight(Request $request, $highlightId)
    {
        try {
            $user = $request->user();

            // Find the highlight
            $highlight = Highlight::find($highlightId);

            if (!$highlight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Highlight not found'
                ], 404);
            }

            // Check if the user owns this highlight
            if ($highlight->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => "You don't have permission to delete this highlight"
                ], 403);
            }

            // Delete the highlight
            $highlight->delete();

            Log::info('Highlight deleted successfully', [
                'highlight_id' => $highlightId,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Highlight deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting highlight: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete highlight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all highlights for the authenticated user (across all URLs)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllUserHighlights(Request $request)
    {
        try {
            $user = $request->user();

            $highlights = Highlight::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $highlights->count(),
                'highlights' => $highlights
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching user highlights: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch highlights: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get highlight statistics for the authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHighlightStats(Request $request)
    {
        try {
            $user = $request->user();

            $stats = [
                'total_highlights' => Highlight::where('user_id', $user->id)->count(),
                'highlights_with_notes' => Highlight::where('user_id', $user->id)
                    ->whereNotNull('note')
                    ->where('note', '!=', '')
                    ->count(),
                'unique_urls' => Highlight::where('user_id', $user->id)
                    ->distinct('url_id')
                    ->count(),
                'color_breakdown' => Highlight::where('user_id', $user->id)
                    ->select('color', DB::raw('count(*) as count'))
                    ->groupBy('color')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching highlight stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
