<?php

namespace App\Models\Hive;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use HasFactory;
    
    protected $table = 'hive_activities';
    
    protected $fillable = [
        'user_id',
        'target_user_id',
        'type',
        'action_text',
        'target_id',
        'target_type',
        'metadata',
        'is_read'
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
    ];
    
    /**
     * Get the user who performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Get the target user who receives this activity.
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
    
    /**
     * Get the target of this activity (post, comment, etc.)
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
    
    /**
     * Get a formatted representation of the activity for the API.
     */
    public function toActivityResponse(): array
    {
        $user = $this->user;
        $targetInfo = $this->getTargetInfo();
        
        return [
            'id' => $this->id,
            'type' => $this->type,
            'action_text' => $this->action_text,
            'created_at' => $this->created_at->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'first_name' => $user->profile ? $user->profile->first_name : '',
                'last_name' => $user->profile ? $user->profile->last_name : '',
                'username' => $user->username,
                'avatar' => $user->profile ? $user->profile->profile_picture : null,
            ],
            'target' => $targetInfo,
        ];
    }
    
    /**
     * Get formatted target information based on target type.
     */
    protected function getTargetInfo(): array
    {
        $targetInfo = [
            'type' => strtolower(class_basename($this->target_type)),
            'id' => $this->target_id,
        ];
        
        // Add different properties based on the target type
        if ($this->target) {
            switch ($targetInfo['type']) {
                case 'post':
                    $targetInfo['title'] = $this->target->title ?? 'Post';
                    $targetInfo['url'] = "/posts/{$this->target->id}";
                    break;
                case 'comment':
                    $targetInfo['content'] = substr($this->target->content ?? '', 0, 100);
                    $targetInfo['post_id'] = $this->target->post_id ?? null;
                    $targetInfo['url'] = "/posts/{$this->target->post_id}#comment-{$this->target->id}";
                    break;
                default:
                    // For other types, include any additional data from metadata
                    if ($this->metadata && isset($this->metadata['target_details'])) {
                        $targetInfo = array_merge($targetInfo, $this->metadata['target_details']);
                    }
            }
        } else {
            // If target has been deleted, use data from metadata
            if ($this->metadata && isset($this->metadata['target_details'])) {
                $targetInfo = array_merge($targetInfo, $this->metadata['target_details']);
            } else {
                $targetInfo['title'] = 'Deleted content';
                $targetInfo['url'] = '#';
            }
        }
        
        return $targetInfo;
    }
    
    /**
     * Mark this activity as read.
     */
    public function markAsRead(): self
    {
        $this->update(['is_read' => true]);
        return $this;
    }
}
