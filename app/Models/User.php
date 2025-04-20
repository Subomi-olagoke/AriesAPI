<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['setup_completed'];

    public function getSetupCompletedAttribute(): bool {
        return !empty($this->role) && $this->topic()->exists();
    }
    
    const ROLE_EDUCATOR = 'educator';
    const ROLE_LEARNER = 'learner';
    const ROLE_EXPLORER = 'explorer';

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    protected $fillable = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'role',
        'avatar',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the topics that belong to the user.
     */
    public function topic()
    {
        return $this->belongsToMany(Topic::class, 'user_topic', 'user_id', 'topic_id');
    }

    public function profile() {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }

    public function followers() {
        return $this->hasMany(Follow::class, 'followeduser');
    }

    public function following() {
        return $this->hasMany(Follow::class, 'user_id');
    }

    public function posts() {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }

    public function comments() {
        return $this->hasMany(Comment::class, 'user_id');
    }

    public function likes() {
        return $this->hasMany(Like::class, 'user_id');
    }

    public function courses() {
        return $this->hasMany(Course::class, 'user_id');
    }

    public function bookmarks() {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Get enrollments for the user.
     */
    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Get courses the user is enrolled in.
     */
    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'course_enrollments')
                ->withPivot('status', 'progress', 'transaction_reference')
                ->withTimestamps();
    }

    /**
     * Get lesson progress records for the user.
     */
    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Check if user is enrolled in a course.
     */
    public function isEnrolledIn(Course $course)
    {
        return $this->enrollments()
                ->where('course_id', $course->id)
                ->whereIn('status', ['active', 'completed'])
                ->exists();
    }

    /**
     * Get the user's saved payment methods.
     */
    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get the count of unread messages.
     */
    public function unreadMessagesCount()
    {
        return $this->hasManyThrough(Message::class, Conversation::class, 'user_two_id', 'conversation_id')
                ->where('sender_id', '!=', $this->id)
                ->where('is_read', false)
                ->count();
    }
    
    /**
     * Get the live classes that the user teaches.
     */
    public function taughtLiveClasses()
    {
        return $this->hasMany(LiveClass::class, 'teacher_id');
    }

    /**
     * Get the live class participations for the user.
     */
    public function liveClassParticipation()
    {
        return $this->hasMany(LiveClassParticipant::class);
    }

    /**
     * Get the live classes that the user has participated in.
     */
    public function participatedLiveClasses()
    {
        return $this->belongsToMany(LiveClass::class, 'live_class_participants')
            ->withPivot('role', 'preferences', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    /**
     * Get all live class chat messages sent by the user.
     */
    public function liveClassChatMessages()
    {
        return $this->hasMany(LiveClassChat::class);
    }
    
    /**
     * Get all hire requests made by the user (as client)
     */
    public function hireRequestsMade()
    {
        return $this->hasMany(HireRequest::class, 'client_id');
    }
    
    /**
     * Get all hire requests received by the user (as educator)
     */
    public function hireRequestsReceived()
    {
        return $this->hasMany(HireRequest::class, 'tutor_id');
    }
    
    /**
     * Get all ratings received by the user (as educator)
     */
    public function ratingsReceived()
    {
        return $this->hasMany(EducatorRating::class, 'educator_id');
    }
    
    /**
     * Get all ratings given by the user
     */
    public function ratingsGiven()
    {
        return $this->hasMany(EducatorRating::class, 'user_id');
    }
    
    /**
     * Get the average rating for this educator
     */
    public function getAverageRatingAttribute()
    {
        if ($this->role !== self::ROLE_EDUCATOR) {
            return null;
        }
        
        return $this->ratingsReceived()->avg('rating') ?? 0;
    }
    
    /**
     * Get users that this user has blocked
     */
    public function blockedUsers()
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'user_id', 'blocked_user_id')
            ->withTimestamps();
    }
    
    /**
     * Get users that have blocked this user
     */
    public function blockedBy()
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocked_user_id', 'user_id')
            ->withTimestamps();
    }
    
    /**
     * Get users that this user has muted
     */
    public function mutedUsers()
    {
        return $this->belongsToMany(User::class, 'user_mutes', 'user_id', 'muted_user_id')
            ->withTimestamps();
    }
    
    /**
     * Get users that have muted this user
     */
    public function mutedBy()
    {
        return $this->belongsToMany(User::class, 'user_mutes', 'muted_user_id', 'user_id')
            ->withTimestamps();
    }
    
    /**
     * Check if user has blocked another user
     */
    public function hasBlocked(User $user)
    {
        return $this->blockedUsers()->where('blocked_user_id', $user->id)->exists();
    }
    
    /**
     * Check if user has muted another user
     */
    public function hasMuted(User $user)
    {
        return $this->mutedUsers()->where('muted_user_id', $user->id)->exists();
    }
    
    /**
     * Get channels created by this user
     */
    public function createdChannels()
    {
        return $this->hasMany(Channel::class, 'creator_id');
    }
    
    /**
     * Get channel memberships
     */
    public function channelMemberships()
    {
        return $this->hasMany(ChannelMember::class);
    }
    
    /**
     * Get channels this user is a member of
     */
    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'channel_members', 'user_id', 'channel_id')
            ->withPivot('role', 'is_active', 'joined_at', 'last_read_at')
            ->withTimestamps();
    }
    
    /**
     * Get channel messages sent by this user
     */
    public function channelMessages()
    {
        return $this->hasMany(ChannelMessage::class, 'sender_id');
    }
    
    /**
     * Get the active subscription for this user
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->latest();
    }
    
    /**
     * Get all subscriptions for this user
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
    
    /**
     * Check if user has an active subscription
     */
    public function hasActiveSubscription()
    {
        return $this->activeSubscription()->exists();
    }
    
    /**
     * Check if user can create channels
     */
    public function canCreateChannels()
    {
        $subscription = $this->activeSubscription;
        return $subscription && $subscription->canCreateChannels();
    }
    
    /**
     * Check if user can message an educator
     */
    public function canMessageEducator(User $educator)
    {
        // If user is an admin, they can message anyone
        if ($this->isAdmin) {
            return true;
        }
        
        // If the user is not a learner or the other user is not an educator, no restrictions
        if ($this->role !== self::ROLE_LEARNER || $educator->role !== self::ROLE_EDUCATOR) {
            return true;
        }
        
        // Check if they have an active hire session
        return HireSession::where([
            'learner_id' => $this->id,
            'educator_id' => $educator->id,
            'status' => 'active',
            'can_message' => true
        ])->exists();
    }
}