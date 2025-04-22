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
        'created_by'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'settings' => 'array',
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
        // Check if user is a member of the channel
        return $this->channel->isMember($user);
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
        
        // Check specific permissions on contents
        foreach ($this->contents as $content) {
            $permission = $content->getPermissionFor($user);
            if ($permission && ($permission->role === 'owner' || $permission->role === 'editor')) {
                return true;
            }
        }
        
        return false;
    }
}