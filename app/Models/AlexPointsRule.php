<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlexPointsRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_type',
        'points',
        'description',
        'is_active',
        'is_one_time',
        'daily_limit',
        'metadata'
    ];

    protected $casts = [
        'points' => 'integer',
        'is_active' => 'boolean',
        'is_one_time' => 'boolean',
        'daily_limit' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get transactions with this action type
     */
    public function transactions()
    {
        return AlexPointsTransaction::where('action_type', $this->action_type);
    }
}