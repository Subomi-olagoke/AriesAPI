<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\comments;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function statCheckPost(Post $post) {
        return Like::where([['user_id', '=', auth()->user()->id],
        ['post_id', '=', $post->id]])->count();
    }

    public function statCheckComment(comments $comment) {
        return Like::where([['user_id', '=', auth()->user()->id],
        ['comment_id', '=', $comment->id]])->count();
    }

    public function LikePost(Post $post, comments $comment) {
        $CheckPost = $this->statCheckPost($post);

        if(!$CheckPost) {
            $newLike = new Like();
            $newLike->user_id = auth()->user()->id;
            $newLike->post_id = $post->id;
            $newLike->save();

            return response()->json([
                'message' => 'successful'
            ], 201);
        }

    }

    public function likeComment(comments $comment) {
        $CheckComment = $this->statCheckComment($comment);

        if(!$CheckComment) {
            $newLike = new Like();
            $newLike->user_id = auth()->user()->id;
            $newLike->comment_id = $comment->id;
            $newLike->save();

            return response()->json([
                'message' => 'successful'
            ], 201);
        }
    }

    public function removeLikePost(Post $post) {
        $removed = Like::where([['user_id', '=', auth()->user()->id],
        ['post_id', '=', $post->id]])->delete();

        if($removed) {
            return response()->json([
                'message' => 'success'
            ], 200);
        }
        return response()->json([
            'message' => 'Like not found'
        ], 404);

    }

    public function removeLikeComment(comments $comment) {
        $removed = Like::where([['user_id', '=', auth()->user()->id],
        ['comment_id', '=', $comment->id]])->delete();

        if($removed) {
            return response()->json([
                'message' => 'success'
            ], 200);
        }
        return response()->json([
            'message' => 'Like not found'
        ], 404);

    }

    public function displayLikes() {
        return Like::with('likeable')
                    ->where('user_id', auth()->user()->id)
                    ->get();
    }

}
