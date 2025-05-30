<?php

namespace App\Models\Hive;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;
    
    protected $table = 'hive_channels';
    
    protected $fillable = [
        'name',
        'description',
        'color',
        'creator_id',
        'privacy',
        'status'
    ];
    
    /**
     * Get the creator of the channel.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    
    /**
     * Get the members of the channel.
     */
    public function members(): HasMany
    {
        return $this->hasMany(ChannelMember::class, 'channel_id');
    }
    
    /**
     * Get the member count for the channel.
     */
    public function getMemberCountAttribute(): int
    {
        return $this->members()->count();
    }
    
    /**
     * Check if a user is a member of the channel.
     */
    public function hasUser(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }
    
    /**
     * Add a new member to the channel.
     */
    public function addMember(int $userId, string $role = 'member'): ChannelMember
    {
        return $this->members()->updateOrCreate(
            ['user_id' => $userId],
            ['role' => $role]
        );
    }
    
    /**
     * Remove a member from the channel.
     */
    public function removeMember(int $userId): int
    {
        return $this->members()->where('user_id', $userId)->delete();
    }
}
