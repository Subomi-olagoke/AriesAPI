<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Courses;
use App\Models\Educators;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class EducatorsController extends Controller {


	//post courses
	public function storeCourse(Request $request) {
        $user = $request->auth()->user();
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

		$file = $request->file('file');
		$filename = time() . '.' . $file->getClientOriginalExtension();
		$filePath = $file->storeAs('courses', $filename, 'public');

		$course = new Courses();
		$course->user_id = $user->id;
		$course->name = $request->name;
		$course->description = $request->description;
		$course->file = $filePath;
		$saved = $course->save();

		if($saved) {
			return response()->json([
				'message' => 'Course uploaded successfully'
			], 200);
		}

	}

	public function show() {
		$data = Courses::all();
		return response()->json(['data' => $data]);
	}

	public function download(Request $request, $file) {

		return response()->download(public_path('assets/' . $file));

	}

	public function view($id) {
		$data = Courses::find($id);

		return response()->json(['data' => $data]);
	}

}
