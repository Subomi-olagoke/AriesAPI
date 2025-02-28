<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable;
    use Searchable;

    /**
     * Define the searchable data.
     */

     protected $appends = ['setup_completed'];

     public function getSetupCompletedAttribute(): bool {
        return !empty($this->role) && $this->topic()->exists();
    }
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
        ];
    }


    const ROLE_EDUCATOR = 'educator';
    const ROLE_LEARNER = 'learner';
    const ROLE_EXPLORER = 'explorer';

    public $incrementing = false; // Disable auto-incrementing
    protected $keyType = 'string'; // Use string type for the primary key

    protected static function boot()
    {
        parent::boot();

        // Automatically generate a UUID when creating a new user
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getRouteKeyName() {
        return 'username';
    }

    public function posts() {
        return $this->hasMany(Post::class, 'user_id');
    }

    public function profile() {
        return $this->hasOne(Profile::class, 'user_id');
    }

    public function comments() {
        return $this->hasMany(Comment::class, 'user_id');
    }

    public function topic() {
        return $this->belongsToMany(Topic::class, 'user_topic', 'user_id', 'topic_id');
    }

    public function courses() {
        return $this->hasMany(Course::class);
    }

    public function followers() {
        return $this->hasMany(Follow::class, 'followeduser');
    }

    public function following() {
        return $this->hasMany(Follow::class,'user_id');
    }

    public function likes() {
        return $this->belongsTo(Like::class, 'user_id');
    }

    public function hires() {
        return $this->hasMany(HireInstructor::class, 'user_id');
    }

    public function sentHireRequests()
    {
        return $this->hasMany(HireRequest::class, 'client_id');
    }

    public function receivedHireRequests()
    {
        return $this->hasMany(HireRequest::class, 'tutor_id');
    }

    /**
     * Get conversations where the user is a participant (user_one or user_two).
     */
    public function conversations()
    {
        return Conversation::where('user_one_id', $this->id)
            ->orWhere('user_two_id', $this->id)
            ->orderBy('last_message_at', 'desc');
    }

    /**
     * Get messages sent by the user.
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Start or get a conversation with another user.
     */
    public function getConversationWith(User $otherUser)
    {
        // Check if a conversation already exists
        $conversation = Conversation::where(function ($query) use ($otherUser) {
            $query->where('user_one_id', $this->id)
                  ->where('user_two_id', $otherUser->id);
        })->orWhere(function ($query) use ($otherUser) {
            $query->where('user_one_id', $otherUser->id)
                  ->where('user_two_id', $this->id);
        })->first();

        // If no conversation exists, create a new one
        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $this->id,
                'user_two_id' => $otherUser->id,
                'last_message_at' => now(),
            ]);
        }

        return $conversation;
    }

    /**
     * Get total unread messages count.
     */
    public function unreadMessagesCount()
    {
        $count = 0;
        
        $conversations = $this->conversations()->get();
        
        foreach ($conversations as $conversation) {
            $count += $conversation->messages()
                ->where('sender_id', '!=', $this->id)
                ->where('is_read', false)
                ->count();
        }
        
        return $count;
    }
}