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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

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
    
    public function conversations()
    {
        return Conversation::where(function ($query) {
            $query->where('user_one_id', $this->id)
                  ->orWhere('user_two_id', $this->id);
        });
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function getConversationWith(User $otherUser)
    {
        return Conversation::where(function ($query) use ($otherUser) {
            $query->where('user_one_id', $this->id)
                  ->where('user_two_id', $otherUser->id);
        })->orWhere(function ($query) use ($otherUser) {
            $query->where('user_one_id', $otherUser->id)
                  ->where('user_two_id', $this->id);
        })->first();
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'course_enrollments')
                ->withPivot('status', 'progress', 'transaction_reference')
                ->withTimestamps();
    }

    public function activeEnrollments()
    {
        return $this->enrollments()->where('status', 'active');
    }

    public function completedEnrollments()
    {
        return $this->enrollments()->where('status', 'completed');
    }

    public function isEnrolledIn(Course $course)
    {
        return $this->enrollments()
                ->where('course_id', $course->id)
                ->whereIn('status', ['active', 'completed'])
                ->exists();
    }
    
    public function getActiveEnrollmentsCountAttribute()
    {
        return $this->activeEnrollments()->count();
    }
    
    public function getCompletedEnrollmentsCountAttribute()
    {
        return $this->completedEnrollments()->count();
    }
    
    public function getTotalSpentAttribute()
    {
        $enrollments = $this->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->with('course')
            ->get();
            
        return $enrollments->sum(function($enrollment) {
            return $enrollment->course->price ?? 0;
        });
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
    
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

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }
    
    public function completedLessons()
    {
        return $this->hasManyThrough(
            CourseLesson::class,
            LessonProgress::class,
            'user_id',
            'id',
            'id',
            'lesson_id'
        )->where('lesson_progress.completed', true);
    }
    
    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function defaultPaymentMethod()
    {
        return $this->hasOne(PaymentMethod::class)->where('is_default', true);
    }
}