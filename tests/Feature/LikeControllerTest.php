<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Models\comments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LikeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Laravel\Sanctum\Sanctum;



class LikeControllerTest extends TestCase
{
    use RefreshDatabase; // This ensures the database is refreshed between tests
    use WithoutMiddleware;
   protected  $controller;

    protected $user;
    protected $comments;

    protected $likes;



    public function setUp(): void
    {
        parent::setUp();
        // Create a user and log them in
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->actingAs($this->user);
        $this->controller = app()->make(LikeController::class);
    }

    public function testStatCheckPost()
    {

        $post = Post::factory()->create(['user_id' => $this->user->id]); // Create a post
        $likeCount = $this->controller->statCheckPost($post);

        $this->assertEquals(0, $likeCount); // Initially, no likes

        // Like the post
        $this->controller->LikePost($post);

        $likeCount = $this->controller->statCheckPost($post);

        $this->assertEquals(1, $likeCount); // After liking, should be 1
    }

    public function testStatCheckComment()
    {
    $post = Post::factory()->create(['user_id' => $this->user->id]); // Create post
    $comment = comments::factory()->create(['user_id' => $this->user->id, 'post_id' => $post->id]); // Create a comment
    $likeCount = $this->controller->statCheckComment($comment);

    $this->assertEquals(0, $likeCount); // Initially, no likes

    $this->controller->likeComment($comment);

    $likeCount = $this->controller->statCheckComment($comment);

    $this->assertEquals(1, $likeCount); // After liking, should be 1
    }

    public function testLikePost()
{
    //$user = User::factory()->create();
    $token = $this->user->createToken('Test token')->plainTextToken;
    $post = Post::factory()->create(['user_id' => $this->user->id]); //create post
    //dd($post);

    //the post should not have been liked
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
        ])->postJson(route('likePost', ['likeable_id' => $post->id]), ['likeable_type' => 'App\Models\Post']);
            $response->assertStatus(201);
            $response->assertJson(['message' => 'successful']);

    // Making sure the like was created in the database
    $this->assertDatabaseHas('likes', [
        'user_id' => $this->user->id,
        'likeable_id' => $post->id,      // Check that the like is associated with the post (likeable_id)
        'likeable_type' => Post::class,
    ]);
}


    public function testLikeComment()
{
    $comment = comments::factory()->create(); // Create a comment

    // Initially, the comment should not have been liked
    $response = $this->postJson(route('likeComment', ['comment' => $comment]));
    $response->assertStatus(201);
    $response->assertJson(['message' => 'successful']);

    // Making sure the like was created in the database
    $this->assertDatabaseHas('likes', [
        'user_id' => $this->user->id,
        'comment_id' => $comment->id,
    ]);
}
    public function testRemoveLikePost()
{
    $post = Post::factory()->create(); // Create a post
    $this->controller->LikePost($post, null); // Like the post

    // Ensure the like exists in the database
    $this->assertDatabaseHas('likes', [
        'user_id' => $this->user->id,
        'post_id' => $post->id,
    ]);

    // Remove the like
    $response = $this->deleteJson(route('removeLikePost', ['post' => $post]));
    $response->assertStatus(200);
    $response->assertJson(['message' => 'success']);

    // Ensure the like has been removed
    $this->assertDatabaseMissing('likes', [
        'user_id' => $this->user->id,
        'post_id' => $post->id,
    ]);
}

    public function testRemoveLikeComment()
{
    $comment = comments::factory()->create(); // Create a comment
    $this->controller->likeComment($comment); // Like the comment

    // Ensure the like exists in the database
    $this->assertDatabaseHas('likes', [
        'user_id' => $this->user->id,
        'comment_id' => $comment->id,
    ]);

    // Remove the like
    $response = $this->deleteJson(route('removeLikeComment', ['comment' => $comment]));
    $response->assertStatus(200);
    $response->assertJson(['message' => 'success']);

    // Ensure the like has been removed
    $this->assertDatabaseMissing('likes', [
        'user_id' => $this->user->id,
        'comment_id' => $comment->id,
    ]);
}

    public function testDisplayLikes()
{
    $post = Post::factory()->create();
    $comment = comments::factory()->create();

    // Like both post and comment
    $this->controller->LikePost($post, null);
    $this->controller->likeComment($comment);

    $response = $this->getJson(route('displayLikes'));
    $response->assertStatus(200);

    // Ensure the response contains the likes
    $response->assertJsonCount(2); // Two likes (one for post, one for comment)
}

}
