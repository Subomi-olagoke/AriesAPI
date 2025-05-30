<?php

namespace App\Models\Hive;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelMember extends Model
{
    use HasFactory;
    
    protected $table = 'hive_channel_members';
    
    protected $fillable = [
        'channel_id',
        'user_id',
        'role',
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
     * Get the channel this membership belongs to.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
    
    /**
     * Get the user this membership belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
}
