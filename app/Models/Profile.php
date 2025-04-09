<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Profile extends Model {
    protected $fillable = [
        'bio', 
        'avatar', 
        'qualifications', 
        'teaching_style', 
        'availability', 
        'hire_rate', 
        'hire_currency', 
        'social_links',
        'share_key'
    ];
    
    protected $casts = [
        'qualifications' => 'array',
        'availability' => 'array',
        'social_links' => 'array',
    ];
    
    protected $appends = ['share_url'];
    
    protected static function boot()
    {
        parent::boot();

        // Generate share key when creating a profile
        static::creating(function ($profile) {
            // Generate a unique share key if not already set
            if (!$profile->share_key) {
                $profile->share_key = Str::random(20);
            }
        });

        // Prevent modifications to share_key unless explicitly regenerating
        static::updating(function ($profile) {
            // If someone tries to change the share_key without explicit regenerate flag, revert it
            if ($profile->isDirty('share_key') && !$profile->regenerate_share_key) {
                $profile->share_key = $profile->getOriginal('share_key');
            }
        });
    }

    public function User() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    /**
     * Get the shareable URL for this profile
     */
    public function getShareUrlAttribute()
    {
        if ($this->share_key) {
            return url("/profile/shared/{$this->share_key}");
        }
        return null;
    }
    
    /**
     * Regenerate the share key for this profile
     */
    public function regenerateShareKey()
    {
        $this->regenerate_share_key = true;
        $this->share_key = Str::random(20);
        $this->save();
        
        return $this->share_key;
    }
}