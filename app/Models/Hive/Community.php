<?php

namespace App\Models\Hive;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Community extends Model
{
    use HasFactory;
    
    protected $table = 'hive_communities';
    
    protected $fillable = [
        'name',
        'description',
        'avatar',
        'privacy',
        'creator_id',
        'member_count',
        'status',
        'join_code'
    ];
    
    protected $casts = [
        'member_count' => 'integer',
    ];
    
    /**
     * Get the creator of the community.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    
    /**
     * Get the members of the community.
     */
    public function members(): HasMany
    {
        return $this->hasMany(CommunityMember::class, 'community_id');
    }
    
    /**
     * Check if a user is a member of the community.
     */
    public function hasUser(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->where('status', 'active')->exists();
    }
    
    /**
     * Add a new member to the community.
     */
    public function addMember(int $userId, string $role = 'member'): CommunityMember
    {
        $member = $this->members()->updateOrCreate(
            ['user_id' => $userId],
            [
                'role' => $role,
                'status' => 'active',
                'joined_at' => now()
            ]
        );
        
        // Increment the member count
        $this->increment('member_count');
        
        return $member;
    }
    
    /**
     * Remove a member from the community.
     */
    public function removeMember(int $userId): bool
    {
        $result = $this->members()->where('user_id', $userId)->delete();
        
        if ($result) {
            // Decrement the member count, but ensure it doesn't go below 0
            $this->decrement('member_count');
            if ($this->member_count < 0) {
                $this->update(['member_count' => 0]);
            }
        }
        
        return (bool)$result;
    }
    
    /**
     * Generate a unique join code for the community.
     */
    public function generateJoinCode(): string
    {
        $joinCode = Str::random(8);
        $this->update(['join_code' => $joinCode]);
        return $joinCode;
    }
}
