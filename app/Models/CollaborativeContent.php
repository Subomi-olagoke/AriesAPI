<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CollaborativeContent extends Model
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
        'space_id',
        'version',
        'content_type',
        'content_data',
        'metadata',
        'created_by'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'version' => 'integer',
        'metadata' => 'array',
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
            
            // Create an initial version
            $model->afterCreate(function ($content) {
                ContentVersion::create([
                    'content_id' => $content->id,
                    'version_number' => $content->version,
                    'content_data' => $content->content_data,
                    'created_by' => $content->created_by
                ]);
            });
        });
        
        static::updating(function ($model) {
            if ($model->isDirty('content_data')) {
                // Increment version number
                $model->version = $model->version + 1;
                
                // Create a new version record after save
                $model->afterSave(function ($content) {
                    ContentVersion::create([
                        'content_id' => $content->id,
                        'version_number' => $content->version,
                        'content_data' => $content->content_data,
                        'diff' => json_encode($this->generateDiff($content)),
                        'created_by' => auth()->id()
                    ]);
                });
            }
        });
    }
    
    /**
     * Generate a diff between the current and previous version
     */
    protected function generateDiff($content)
    {
        // Simple implementation - in a real app you'd use a proper diff algorithm
        $previousVersion = ContentVersion::where('content_id', $content->id)
            ->orderBy('version_number', 'desc')
            ->first();
            
        return [
            'from_version' => $previousVersion ? $previousVersion->version_number : 0,
            'to_version' => $content->version,
            'changes' => 'Updated content' // Would contain actual diff data
        ];
    }
    
    /**
     * Get the collaborative space that owns the content.
     */
    public function space()
    {
        return $this->belongsTo(CollaborativeSpace::class, 'space_id');
    }
    
    /**
     * Get the creator of the content.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the versions of this content.
     */
    public function versions()
    {
        return $this->hasMany(ContentVersion::class, 'content_id')->orderBy('version_number', 'desc');
    }
    
    /**
     * Get the permissions for this content.
     */
    public function permissions()
    {
        return $this->hasMany(ContentPermission::class, 'content_id');
    }
    
    /**
     * Get the comments for this content.
     */
    public function comments()
    {
        return $this->hasMany(ContentComment::class, 'content_id')->whereNull('parent_id');
    }
    
    /**
     * Get permission for a specific user.
     */
    public function getPermissionFor(User $user)
    {
        // First check for specific user permission
        $permission = $this->permissions()->where('user_id', $user->id)->first();
        if ($permission) {
            return $permission;
        }
        
        // Then check for default permission (null user_id)
        return $this->permissions()->whereNull('user_id')->first();
    }
    
    /**
     * Check if user can view this content.
     */
    public function canView(User $user)
    {
        // Content creator can always view
        if ($this->created_by === $user->id) {
            return true;
        }
        
        // Space creator can always view
        if ($this->space->created_by === $user->id) {
            return true;
        }
        
        // Check if user is a member of the channel
        if (!$this->space->channel->isMember($user)) {
            return false;
        }
        
        // Check permissions
        $permission = $this->getPermissionFor($user);
        if ($permission) {
            return true; // Any permission means can at least view
        }
        
        // Default to true for channel members if no specific permissions are set
        return true;
    }
    
    /**
     * Check if user can edit this content.
     */
    public function canEdit(User $user)
    {
        // Content creator can always edit
        if ($this->created_by === $user->id) {
            return true;
        }
        
        // Space creator can always edit
        if ($this->space->created_by === $user->id) {
            return true;
        }
        
        // Channel admin can always edit
        if ($this->space->channel->isAdmin($user)) {
            return true;
        }
        
        // Check permissions
        $permission = $this->getPermissionFor($user);
        if ($permission && ($permission->role === 'owner' || $permission->role === 'editor')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can comment on this content.
     */
    public function canComment(User $user)
    {
        // If they can edit, they can comment
        if ($this->canEdit($user)) {
            return true;
        }
        
        // Check for commenter permission
        $permission = $this->getPermissionFor($user);
        if ($permission && ($permission->role === 'commenter' || $permission->role === 'editor')) {
            return true;
        }
        
        return false;
    }
}