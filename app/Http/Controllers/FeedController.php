<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\Courses;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function feed(Request $request) {
        $user = auth()->user();
        $topicIds = $user->topic()->pluck('id');

        $courses = Courses::whereIn('topic_id', $topicIds)->get();

        return $courses;
    }
}
