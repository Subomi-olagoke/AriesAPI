<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlexPointsLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'points_required',
        'name',
        'description',
        'rewards'
    ];

    protected $casts = [
        'level' => 'integer',
        'points_required' => 'integer',
        'rewards' => 'array',
    ];

    /**
     * Get users at this level
     */
    public function users()
    {
        return User::where('point_level', $this->level);
    }

    /**
     * Get the next level
     */
    public function nextLevel()
    {
        return self::where('level', '>', $this->level)
            ->orderBy('level', 'asc')
            ->first();
    }

    /**
     * Get the previous level
     */
    public function previousLevel()
    {
        return self::where('level', '<', $this->level)
            ->orderBy('level', 'desc')
            ->first();
    }
}