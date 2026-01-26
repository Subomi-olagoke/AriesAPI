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
        // Setup is complete if role is set
        // Since we're defaulting everyone to 'learner', this will be true for all new users
        return !empty($this->role);
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
        'apple_id',
        'isadmin',
        'is_banned',
        'banned_at',
        'ban_reason',
        'alex_points',
        'point_level',
        'points_to_next_level',
        'setup_completed',
        'device_token',
        'device_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'isadmin' => 'boolean',
        'is_banned' => 'boolean',
        'is_verified' => 'boolean',
        'banned_at' => 'datetime',
        'verified_at' => 'datetime',
        'alex_points' => 'integer',
        'point_level' => 'integer',
        'points_to_next_level' => 'integer',
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
     * Check if user is enrolled in a course (active or completed only).
     */
    public function isEnrolledIn(Course $course)
    {
        return $this->enrollments()
                ->where('course_id', $course->id)
                ->whereIn('status', ['active', 'completed'])
                ->exists();
    }

    /**
     * Check if user has any enrollment in a course (including pending).
     */
    public function hasAnyEnrollment(Course $course)
    {
        return $this->enrollments()
                ->where('course_id', $course->id)
                ->exists();
    }

    /**
     * Get pending enrollment for a course.
     */
    public function getPendingEnrollment(Course $course)
    {
        return $this->enrollments()
                ->where('course_id', $course->id)
                ->where('status', 'pending')
                ->first();
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
            ->withPivot('role', 'status', 'is_active', 'joined_at', 'last_read_at')
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
        // Allow all users to create channels regardless of subscription status
        return true;
        
        // Previous logic that required subscription:
        // $subscription = $this->activeSubscription;
        // return $subscription && $subscription->canCreateChannels();
    }
    
    /**
     * Get the maximum allowed video size for this user in KB
     */
    public function getMaxVideoSizeKb()
    {
        $subscription = $this->activeSubscription;
        
        // Default for free users: 50MB (50,000 KB)
        $defaultLimit = 50000;
        
        if ($subscription) {
            return $subscription->max_video_size_kb;
        }
        
        return $defaultLimit;
    }
    
    /**
     * Get the maximum allowed image size for this user in KB
     */
    public function getMaxImageSizeKb()
    {
        $subscription = $this->activeSubscription;
        
        // Default for free users: 5MB (5,000 KB)
        $defaultLimit = 5000;
        
        if ($subscription) {
            return $subscription->max_image_size_kb;
        }
        
        return $defaultLimit;
    }
    
    /**
     * Check if user can analyze posts with Cogni
     */
    public function canAnalyzePosts()
    {
        $subscription = $this->activeSubscription;
        
        if ($subscription) {
            return $subscription->canAnalyzePosts();
        }
        
        return false;
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
    
    /**
     * Get reports submitted by this user
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }
    
    /**
     * Get reports about this user
     */
    public function reported()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
    
    /**
     * Get verification requests submitted by this user
     */
    public function verificationRequests()
    {
        return $this->hasMany(VerificationRequest::class);
    }
    
    /**
     * Check if user is verified
     * 
     * @return bool
     */
    public function isVerified(): bool
    {
        return (bool) $this->is_verified;
    }
    
    /**
     * Check if user can be hired (educator must be verified)
     * 
     * @return bool
     */
    public function canBeHired(): bool
    {
        return $this->role === self::ROLE_EDUCATOR && $this->isVerified();
    }
    
    /**
     * Get points transactions for this user
     */
    public function pointsTransactions()
    {
        return $this->hasMany(AlexPointsTransaction::class);
    }
    
    /**
     * Add points to the user's account
     * 
     * @param integer $points
     * @param string $actionType
     * @param string|null $referenceType
     * @param string|null $referenceId
     * @param string|null $description
     * @param array|null $metadata
     * @return AlexPointsTransaction
     */
    public function addPoints($points, $actionType, $referenceType = null, $referenceId = null, $description = null, $metadata = null)
    {
        // Create the transaction
        $transaction = $this->pointsTransactions()->create([
            'points' => $points,
            'action_type' => $actionType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'metadata' => $metadata
        ]);
        
        // Update the user's points
        $this->alex_points += $points;
        
        // Check if level up is needed
        $this->checkLevelUp();
        
        $this->save();
        
        return $transaction;
    }
    
    /**
     * Deduct points from the user's account
     * 
     * @param integer $points
     * @param string $actionType
     * @param string|null $referenceType
     * @param string|null $referenceId
     * @param string|null $description
     * @param array|null $metadata
     * @return AlexPointsTransaction|false
     */
    public function deductPoints($points, $actionType, $referenceType = null, $referenceId = null, $description = null, $metadata = null)
    {
        // Ensure points is positive
        $points = abs($points);
        
        // Check if user has enough points
        if ($this->alex_points < $points) {
            return false;
        }
        
        // Create the transaction with negative points
        $transaction = $this->pointsTransactions()->create([
            'points' => -$points,
            'action_type' => $actionType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'metadata' => $metadata
        ]);
        
        // Update the user's points
        $this->alex_points -= $points;
        
        // Check if level down is needed
        $this->checkLevelDown();
        
        $this->save();
        
        return $transaction;
    }
    
    /**
     * Check if the user can level up
     */
    public function checkLevelUp()
    {
        // If point_level is null, initialize to 1
        if ($this->point_level === null) {
            $this->point_level = 1;
        }
        
        // Get the next level
        $nextLevel = AlexPointsLevel::where('points_required', '<=', $this->alex_points)
            ->where('level', '>', $this->point_level)
            ->orderBy('level', 'asc')
            ->first();
            
        if ($nextLevel) {
            $this->point_level = $nextLevel->level;
            
            // Find the threshold for the next level after this one
            $nextNextLevel = AlexPointsLevel::where('level', '>', $nextLevel->level)
                ->orderBy('level', 'asc')
                ->first();
                
            if ($nextNextLevel) {
                $this->points_to_next_level = $nextNextLevel->points_required - $this->alex_points;
            } else {
                $this->points_to_next_level = 0; // Max level reached
            }
            
            // Only notify if this is not a new user
            if ($this->created_at && $this->created_at->diffInHours(now()) > 1) {
                // Notify the user of level up
                $this->notify(new \App\Notifications\LevelUpNotification($nextLevel));
            }
            
            return true;
        }
        
        // Calculate points to next level
        $nextLevelThreshold = AlexPointsLevel::where('level', '>', ($this->point_level ?? 0))
            ->orderBy('level', 'asc')
            ->first();
            
        if ($nextLevelThreshold) {
            $this->points_to_next_level = $nextLevelThreshold->points_required - $this->alex_points;
        }
        
        return false;
    }
    
    /**
     * Check if the user should level down
     */
    public function checkLevelDown()
    {
        // If point_level is null, initialize to 1
        if ($this->point_level === null) {
            $this->point_level = 1;
            return false;
        }
        
        // Find the highest level the user qualifies for
        $highestQualifiedLevel = AlexPointsLevel::where('points_required', '<=', $this->alex_points)
            ->orderBy('level', 'desc')
            ->first();
            
        if ($highestQualifiedLevel && $highestQualifiedLevel->level < $this->point_level) {
            $this->point_level = $highestQualifiedLevel->level;
            
            // Calculate points to next level
            $nextLevel = AlexPointsLevel::where('level', '>', $this->point_level)
                ->orderBy('level', 'asc')
                ->first();
                
            if ($nextLevel) {
                $this->points_to_next_level = $nextLevel->points_required - $this->alex_points;
            }
            
            return true;
        }
        
        // Recalculate points to next level
        $nextLevel = AlexPointsLevel::where('level', '>', $this->point_level)
            ->orderBy('level', 'asc')
            ->first();
            
        if ($nextLevel) {
            $this->points_to_next_level = $nextLevel->points_required - $this->alex_points;
        }
        
        return false;
    }
    
    /**
     * Get the user's current level details
     */
    public function getCurrentLevel()
    {
        return AlexPointsLevel::where('level', $this->point_level)->first();
    }
    
    /**
     * Get the next level details
     */
    public function getNextLevel()
    {
        return AlexPointsLevel::where('level', '>', $this->point_level)
            ->orderBy('level', 'asc')
            ->first();
    }
    
    /**
     * Get the user's avatar with proper fallback logic
     */
    public function getAvatarUrl()
    {
        // Prioritize profile avatar, then user avatar
        if ($this->profile && $this->profile->avatar) {
            return $this->profile->avatar;
        }
        
        return $this->avatar;
    }
    
    /**
     * Get the readlists that belong to the user.
     */
    public function readlists()
    {
        return $this->hasMany(Readlist::class);
    }
}