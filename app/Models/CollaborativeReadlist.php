<?php

namespace App\Models;

use App\Models\Hive\Activity;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CollaborativeReadlist extends Readlist
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'readlists';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'image_url',
        'is_public',
        'is_system',
        'share_key',
        'is_collaborative',
        'collaboration_type' // 'open', 'invitation', 'closed'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_public' => 'boolean',
        'is_system' => 'boolean',
        'is_collaborative' => 'boolean',
    ];

    /**
     * Get the collaborators of this readlist.
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'readlist_collaborators')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get the activity logs for this collaborative readlist.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'target_id')
            ->where('target_type', CollaborativeReadlist::class)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Add a collaborator to the readlist.
     *
     * @param int $userId
     * @param string $role
     * @return void
     */
    public function addCollaborator(int $userId, string $role = 'editor')
    {
        // Don't add if user is already a collaborator
        if ($this->collaborators()->where('user_id', $userId)->exists()) {
            return;
        }

        $this->collaborators()->attach($userId, [
            'role' => $role,
            'joined_at' => now()
        ]);

        // Log activity
        $user = User::find($userId);
        $this->logActivity($user->id, 'collaborator_added', "joined as a {$role}");
    }

    /**
     * Remove a collaborator from the readlist.
     *
     * @param int $userId
     * @return void
     */
    public function removeCollaborator(int $userId)
    {
        $this->collaborators()->detach($userId);
        
        // Log activity
        $this->logActivity($userId, 'collaborator_removed', "left the readlist");
    }

    /**
     * Check if a user is a collaborator on this readlist.
     *
     * @param int $userId
     * @return bool
     */
    public function hasCollaborator(int $userId): bool
    {
        return $this->collaborators()->where('user_id', $userId)->exists();
    }

    /**
     * Get the role of a collaborator.
     *
     * @param int $userId
     * @return string|null
     */
    public function getCollaboratorRole(int $userId): ?string
    {
        $collaborator = $this->collaborators()->where('user_id', $userId)->first();
        return $collaborator ? $collaborator->pivot->role : null;
    }

    /**
     * Check if a user has permission to edit the readlist.
     *
     * @param int $userId
     * @return bool
     */
    public function canEdit(int $userId): bool
    {
        // Owner can always edit
        if ($this->user_id === $userId) {
            return true;
        }

        // Check collaborator permissions
        $role = $this->getCollaboratorRole($userId);
        return in_array($role, ['editor', 'admin']);
    }

    /**
     * Override the addItem method to log activity
     */
    public function addItem($item, $order = null, $notes = null, $userId = null)
    {
        $result = parent::addItem($item, $order, $notes);
        
        // Log activity if userId provided
        if ($userId) {
            $itemType = get_class($item);
            $itemName = '';
            
            if (method_exists($item, 'getTitle')) {
                $itemName = $item->getTitle();
            } elseif (property_exists($item, 'title')) {
                $itemName = $item->title;
            } elseif (property_exists($item, 'name')) {
                $itemName = $item->name;
            }
            
            $this->logActivity($userId, 'item_added', "added {$itemType} \"{$itemName}\" to the readlist");
        }
        
        return $result;
    }

    /**
     * Override the removeItem method to log activity
     */
    public function removeItem($item, $userId = null)
    {
        // Get item info before removal
        $itemType = get_class($item);
        $itemName = '';
        
        if (method_exists($item, 'getTitle')) {
            $itemName = $item->getTitle();
        } elseif (property_exists($item, 'title')) {
            $itemName = $item->title;
        } elseif (property_exists($item, 'name')) {
            $itemName = $item->name;
        }
        
        $result = parent::removeItem($item);
        
        // Log activity if userId provided
        if ($userId) {
            $this->logActivity($userId, 'item_removed', "removed {$itemType} \"{$itemName}\" from the readlist");
        }
        
        return $result;
    }
    
    /**
     * Override the reorderItems method to log activity
     */
    public function reorderItems(array $itemsOrder, $userId = null)
    {
        $result = parent::reorderItems($itemsOrder);
        
        // Log activity if userId provided
        if ($userId) {
            $this->logActivity($userId, 'items_reordered', "reordered items in the readlist");
        }
        
        return $result;
    }

    /**
     * Log an activity for this readlist.
     *
     * @param int $userId
     * @param string $type
     * @param string $actionText
     * @return Activity
     */
    public function logActivity(int $userId, string $type, string $actionText): Activity
    {
        return Activity::create([
            'user_id' => $userId,
            'target_user_id' => $this->user_id, // Owner of the readlist
            'type' => $type,
            'action_text' => $actionText,
            'target_id' => $this->id,
            'target_type' => get_class($this),
            'metadata' => [
                'target_details' => [
                    'readlist_title' => $this->title,
                    'readlist_id' => $this->id,
                    'url' => "/readlists/{$this->share_key}"
                ]
            ],
            'is_read' => false
        ]);
    }

    /**
     * Generate a unique collaboration invitation link.
     *
     * @return string
     */
    public function generateInviteLink(): string
    {
        $inviteCode = Str::random(12);
        $this->update(['invite_code' => $inviteCode]);
        return url("/readlists/invite/{$inviteCode}");
    }
}