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

    public function bookmarkPost(Request $request, $postId)
    {
        $post = Post::findOrFail($postId);

        // Check if the user has already bookmarked the post
        $existingBookmark = $request->user()->bookmarks()
            ->where('post_id', $post->id)
            ->first();

        if ($existingBookmark) {
            return response()->json(['message' => 'Already bookmarked'], 400);
        }

        // Create a new bookmark
        $bookmark = $request->user()->bookmarks()->create([
            'post_id' => $post->id,
        ]);

        return response()->json(['message' => 'Post bookmarked successfully', 'bookmark' => $bookmark], 201);
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

    // Remove a bookmark for a post
    public function removeBookmarkPost(Request $request, $postId)
    {
        $post = Post::findOrFail($postId);

        $bookmark = $request->user()->bookmarks()
            ->where('post_id', $post->id)
            ->first();

        if (!$bookmark) {
            return response()->json(['message' => 'Bookmark not found'], 404);
        }

        $bookmark->delete();

        return response()->json(['message' => 'Bookmark removed successfully']);
    }
    
    /**
     * Get the number of bookmarks for a specific post
     */
    public function getPostBookmarkCount($postId)
    {
        $post = Post::findOrFail($postId);
        
        // Count bookmarks for this post
        $bookmarkCount = \App\Models\Bookmark::where('post_id', $post->id)->count();
        
        return response()->json([
            'post_id' => $post->id,
            'bookmark_count' => $bookmarkCount
        ]);
    }
    
    /**
     * Get the number of bookmarks for a specific course
     */
    public function getCourseBookmarkCount($courseId)
    {
        $course = Course::findOrFail($courseId);
        
        // Count bookmarks for this course
        $bookmarkCount = \App\Models\Bookmark::where('course_id', $course->id)->count();
        
        return response()->json([
            'course_id' => $course->id,
            'bookmark_count' => $bookmarkCount
        ]);
    }
}
