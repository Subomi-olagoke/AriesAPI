<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\HireInstructorNotification;
use Illuminate\Http\Request;
use App\Models\HireInstructor;

class HireController extends Controller
{
    public function hireInstructor(Request $request, $id) {
        $user = $request->user();
        $existCheck = HireInstructor::where([['user_id', '=', $user->id],
            ['hireduser', '=', $id]])->count();
            if($existCheck) {
                return response()->json([
                    'message' => 'already hired'
                ], 409);
            }
            $newHire = new HireInstructor();
            $newHire->user_id = $user->id;
            $newHire->hireduser = $id;
            $save = $newHire->save();

            if($save) {
                $notifiable = User::find($newHire->hireduser);
                $notifiable->notify(new HireInstructorNotification($user));

                return response()->json([
                    'message' => 'hire request sent'
                ], 201);
            }
    }
    
    /**
     * End a hiring session, making the educator available to be hired again
     *
     * @param Request $request
     * @param int $id The ID of the hiring record to end
     * @return \Illuminate\Http\JsonResponse
     */
    public function endHireSession(Request $request, $id = null) {
        $user = $request->user();
        
        // If ID is provided, end a specific hiring relationship
        if ($id) {
            $hireRecord = HireInstructor::where('id', $id)
                ->where(function($query) use ($user) {
                    // Either user is the educator or the one who hired
                    $query->where('hireduser', $user->id)
                          ->orWhere('user_id', $user->id);
                })
                ->first();
                
            if (!$hireRecord) {
                return response()->json([
                    'message' => 'Hire record not found or you do not have permission to end it'
                ], 404);
            }
            
            $deleted = $hireRecord->delete();
            
            if ($deleted) {
                return response()->json([
                    'message' => 'Hiring session ended successfully'
                ], 200);
            }
        } 
        // If no ID is provided, end all sessions where user is the educator
        else {
            $deleted = HireInstructor::where('hireduser', $user->id)->delete();
            
            if ($deleted > 0) {
                return response()->json([
                    'message' => 'All hiring sessions ended successfully',
                    'sessions_ended' => $deleted
                ], 200);
            } else {
                return response()->json([
                    'message' => 'No active hiring sessions found'
                ], 404);
            }
        }
        
        return response()->json([
            'message' => 'Failed to end hiring session'
        ], 500);
    }
}