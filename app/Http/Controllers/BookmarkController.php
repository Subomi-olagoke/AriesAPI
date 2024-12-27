<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Course;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function bookmarkCourse(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        // Check if user has already bookmarked the course
        $existingBookmark = $request->user()->bookmarks()
            ->where('course_id', $course->id)
            ->first();

        if ($existingBookmark) {
            return response()->json(['message' => 'Already bookmarked'], 400);
        }

        // Create a new bookmark
        $bookmark = $request->user()->bookmarks()->create([
            'course_id' => $course->id,
        ]);

        return response()->json(['message' => 'Course bookmarked successfully', 'bookmark' => $bookmark], 201);
    }

    public function bookmarkPost(Request $request, $lessonId)
    {
        $lesson = Post::findOrFail($lessonId);

        // Check if the user has already bookmarked the lesson
        $existingBookmark = $request->user()->bookmarks()
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($existingBookmark) {
            return response()->json(['message' => 'Already bookmarked'], 400);
        }

        // Create a new bookmark
        $bookmark = $request->user()->bookmarks()->create([
            'lesson_id' => $lesson->id,
        ]);

        return response()->json(['message' => 'Lesson bookmarked successfully', 'bookmark' => $bookmark], 201);
    }
    public function removeBookmarkCourse(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        $bookmark = $request->user()->bookmarks()
            ->where('course_id', $course->id)
            ->first();

        if (!$bookmark) {
            return response()->json(['message' => 'Bookmark not found'], 404);
        }

        $bookmark->delete();

        return response()->json(['message' => 'Bookmark removed successfully']);
    }

    // Remove a bookmark for a lesson
    public function removeBookmarkPost(Request $request, $lessonId)
    {
        $lesson = Post::findOrFail($lessonId);

        $bookmark = $request->user()->bookmarks()
            ->where('lesson_id', $lesson->id)
            ->first();

        if (!$bookmark) {
            return response()->json(['message' => 'Bookmark not found'], 404);
        }

        $bookmark->delete();

        return response()->json(['message' => 'Bookmark removed successfully']);
    }
}
