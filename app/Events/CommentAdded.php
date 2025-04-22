<?php

namespace App\Events;

use App\Models\ContentComment;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $comment;
    public $channel_id;
    public $space_id;
    public $content_id;
    public $user;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\ContentComment  $comment
     * @param  string  $channel_id
     * @param  string  $space_id
     * @param  string  $content_id
     * @param  \App\Models\User  $user
     * @return void
     */
    public function __construct(ContentComment $comment, string $channel_id, string $space_id, string $content_id, User $user)
    {
        $this->comment = $comment;
        $this->channel_id = $channel_id;
        $this->space_id = $space_id;
        $this->content_id = $content_id;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PresenceChannel('collaboration.content.' . $this->content_id);
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'comment_id' => $this->comment->id,
            'content_id' => $this->content_id,
            'channel_id' => $this->channel_id,
            'space_id' => $this->space_id,
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'comment' => [
                'id' => $this->comment->id,
                'text' => $this->comment->comment_text,
                'position' => $this->comment->position,
                'resolved' => $this->comment->resolved,
                'parent_id' => $this->comment->parent_id,
                'created_at' => $this->comment->created_at->toIso8601String(),
                'user' => [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'avatar' => $this->user->avatar
                ]
            ],
            'timestamp' => now()->toIso8601String()
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'comment.added';
    }
}