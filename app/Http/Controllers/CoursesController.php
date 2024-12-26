<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CoursesController extends Controller {
	/**
	 * Display a listing of the resource.
	 */
	public function postCourse(Request $request) {
		$this->validate($request, [
			'video' => 'required|mimes:mp4,mov,avi|max:102400',
		]);

		$video = $request->file('video');

		$filename = 'course_video_' . time() . '.' . $video->getClientOriginalExtension();

		// store in 'course_videos' directory on S3
		Storage::disk('s3')->put('course_videos/' . $filename, file_get_contents($video));

		// Save the file path (URL) in the database
		$videoUrl = config('filesystems.disks.s3.url') . '/course_videos/' . $filename;

		$course = Course::create([
			'title' => $request->input('title'),
			'description' => $request->input('description'),
			'price' => $request->input('price'),
			'video_url' => $videoUrl,
		]);

		return response()->json(['message' => 'Video uploaded and course created successfully', 'course' => $course]);
	}

	public function updateCourse(Request $request, $id) {
		$course = Course::findOrFail($id);

		$this->validate($request, [
			'title' => 'required|string',
			'description' => 'required|string',
			'price' => 'required|numeric',
			'video' => 'sometimes|mimes:mp4,mov,avi|max:102400',
		]);

		$course->update([
			'title' => $request->input('title'),
			'description' => $request->input('description'),
			'price' => $request->input('price'),
		]);

		// Check if a new video is provided for update
		if ($request->hasFile('video')) {
			$video = $request->file('video');

			$filename = 'course_video_' . time() . '.' . $video->getClientOriginalExtension();

			Storage::disk('s3')->put('course_videos/' . $filename, file_get_contents($video));

			if ($course->video_url) {
				Storage::disk('s3')->delete('course_videos/' . basename($course->video_url));
			}

			// Update the 'video_url' in the database
			$course->update(['video_url' => config('filesystems.disks.s3.url') . '/course_videos/' . $filename]);
		}

		return response()->json(['message' => 'Course updated successfully', 'course' => $course]);
	}

	public function showCourse($id) {
		$course = Course::findOrFail($id);

		return response()->json(['course' => $course]);
	}

}
