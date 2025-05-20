<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CollaborativeSpace extends Model
{
    use HasFactory;
    
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    
    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'channel_id',
        'title',
        'description',
        'type',
        'settings',
        'created_by',
        'is_shared',
        'shared_from_id'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'settings' => 'array',
        'is_shared' => 'boolean',
    ];
    
    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }
    
    /**
     * Get the channel that owns the collaborative space.
     */
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
    
    /**
     * Get the creator of the collaborative space.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the contents in this collaborative space.
     */
    public function contents()
    {
        return $this->hasMany(CollaborativeContent::class, 'space_id');
    }
    
    /**
     * Check if a user can access this space.
     */
    public function canAccess(User $user)
    {
        // Space creator can always access
        if ($this->created_by === $user->id) {
            return true;
        }
        
        // Admin or moderator of the channel can always access
        if ($this->channel->isAdmin($user) || $this->channel->isModerator($user)) {
            return true;
        }
        
        // Regular channel members need to check visibility settings
        if ($this->channel->isMember($user)) {
            // If the space has specific visibility settings, check those
            if (isset($this->settings['visibility'])) {
                if ($this->settings['visibility'] === 'public') {
                    return true;
                }
                
                if ($this->settings['visibility'] === 'private') {
                    // For private, check if user is explicitly given access
                    if (isset($this->settings['allowed_users']) && 
                        in_array($user->id, $this->settings['allowed_users'])) {
                        return true;
                    }
                    
                    // Also check content-level permissions
                    foreach ($this->contents as $content) {
                        $permission = $content->getPermissionFor($user);
                        if ($permission) {
                            return true;
                        }
                    }
                    
                    return false;
                }
            }
            
            // Default to allow access for channel members if no visibility setting
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a user can edit this space.
     */
    public function canEdit(User $user)
    {
        // Owner can always edit
        if ($this->created_by === $user->id) {
            return true;
        }
        
        // Channel admins can edit
        if ($this->channel->isAdmin($user)) {
            return true;
        }
        
        // Channel moderators can edit
        if ($this->channel->isModerator($user)) {
            return true;
        }
        
        // Check specific permissions on contents
        foreach ($this->contents as $content) {
            $permission = $content->getPermissionFor($user);
            if ($permission && ($permission->role === 'owner' || $permission->role === 'editor')) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all spaces shared with this channel
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getSharedWithChannel($channelId)
    {
        return self::where('channel_id', $channelId)
            ->whereNotNull('shared_from_id')
            ->with(['creator', 'contents' => function($query) {
                $query->latest('updated_at')->limit(1);
            }])
            ->get();
    }
    
    /**
     * Get all spaces that were shared from this space
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSharedCopies()
    {
        return self::where('shared_from_id', $this->id)
            ->with(['channel', 'creator'])
            ->get();
    }
    
    /**
     * Get the original space this was shared from
     * 
     * @return CollaborativeSpace|null
     */
    public function getOriginalSpace()
    {
        if (!$this->shared_from_id) {
            return null;
        }
        
        return self::find($this->shared_from_id);
    }
}