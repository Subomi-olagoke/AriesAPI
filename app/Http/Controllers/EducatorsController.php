<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Educators;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EducatorsController extends Controller {

	//post courses
	public function createCourse(Request $request) {
        $user = auth()->user();
        if($user->role != User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'you are not allowed to use this'
            ], 403);
        }

		$request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'file' => 'required|file|mimes:mp4,avi,mkv,pdf,docx|max:1048576',
        ]);

        try {
            DB::beginTransaction();
            
            // Use our file upload service
            $fileUploadService = app(FileUploadService::class);
            $filePath = $fileUploadService->uploadFile(
                $request->file('file'),
                'courses/' . date('Y-m-d')
            );

            $course = new Course();
            $course->user_id = $user->id;
            $course->name = $request->name;
            $course->description = $request->description;
            $course->file = $filePath;
            $saved = $course->save();

            DB::commit();
            
            if($saved) {
                return response()->json([
                    'message' => 'Course uploaded successfully',
                    'course' => $course
                ], 200);
            }
            
            return response()->json([
                'message' => 'Failed to save course'
            ], 500);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course upload failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Course upload failed: ' . $e->getMessage()
            ], 500);
        }
	}

	public function showEducatorCourses(Request $request) {
        $user = $request->user();
		$courses = $user->courses()->get();
        if($courses) {
            return response()->json([
                'courses' => $courses,
            ], 201);
        }
	}

	public function download(Request $request, $file) {
        // This method needs to be replaced with S3 file download
        // S3 files are accessible via their URLs directly, so this method may no longer be needed
        // or it could be refactored to generate temporary signed URLs for private S3 files
        
        $filePath = 'courses/' . $file;
        
        if (Storage::disk('s3')->exists($filePath)) {
            $url = Storage::disk('s3')->temporaryUrl(
                $filePath,
                now()->addMinutes(5)
            );
            
            return response()->json([
                'download_url' => $url
            ]);
        }
        
        return response()->json(['error' => 'File not found'], 404);
    }

	public function view($id) {
		$data = Course::find($id);

        if (!$data) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        return response()->json(['data' => $data]);
	}
}