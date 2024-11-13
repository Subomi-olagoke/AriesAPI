<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\comments;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function statCheckPost(Post $post) {
        $check = Like::where([['user_id', '=', auth()->user()->id],
        ['likeable_id', '=', $post->id], 'likeable_type', '=', Post::class])->exists();

        return $check;
    }

    public function statCheckComment(comments $comment) {
        return Like::where([['user_id', '=', auth()->user()->id],
        ['comment_id', '=', $comment->id]])->count();
    }

    $request->input('likeable_type')

    public function likePost(Request $request, $likeable_id)
{
    $user = auth()->user();
    $likeable_type = $request->input('likeable_type');
    $likeable =  $likeable_type::find($likeable_id);

    if(!$likeable) {
        return response()->json(['message' => 'Likeable entity not found.'], 404);
    }

    //checking if like already exists
    $exists = $user->likes()->where([
        ['likeable_id', '=', $likeable_id],
        ['likeable_type', '=', $likeable_type]
    ])->exists();

    if ($exists) {
        return response()->json(['message' => 'Already liked'], 200);
    }

    //create like if it doesn’t exist
    $like = $user->likes()->create([
        'user_id' => $user->id,
        'likeable_id' => $likeable_id,
        'likeable_type' => $likeable_type
    ]);
    $like->save();

    return response()->json(['message' => 'successful'], 201);
}


    public function likeComment(comments $comment) {
        $CheckComment = $this->statCheckComment($comment);

        if(!$CheckComment) {
            $newLike = new Like();
            $newLike->user_id = auth()->user()->id;
            $newLike->post_id = $comment->post_id;
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
