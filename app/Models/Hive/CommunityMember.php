<?php

namespace App\Models\Hive;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityMember extends Model
{
    use HasFactory;
    
    protected $table = 'hive_community_members';
    
    protected $fillable = [
        'community_id',
        'user_id',
        'role',
        'status',
        'notifications_enabled',
        'joined_at',
        'last_read_at'
    ];
    
    protected $casts = [
        'notifications_enabled' => 'boolean',
        'joined_at' => 'datetime',
        'last_read_at' => 'datetime',
    ];
    
    /**
     * Get the community this membership belongs to.
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
    }
    
    /**
     * Get the user this membership belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Check if this membership is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    /**
     * Update the last read timestamp for this membership.
     */
    public function markAsRead(): self
    {
        $this->update(['last_read_at' => now()]);
        return $this;
    }
    
    /**
     * Toggle the notification settings for this membership.
     */
    public function toggleNotifications(bool $enabled = null): self
    {
        if ($enabled === null) {
            $enabled = !$this->notifications_enabled;
        }
        
        $this->update(['notifications_enabled' => $enabled]);
        return $this;
    }
    
    /**
     * Update the status of this membership.
     */
    public function updateStatus(string $status): self
    {
        $this->update(['status' => $status]);
        return $this;
    }
}
