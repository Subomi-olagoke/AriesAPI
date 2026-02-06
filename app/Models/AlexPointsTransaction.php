<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlexPointsTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'points',
        'action_type',
        'reference_type',
        'reference_id',
        'description',
        'metadata'
    ];

    protected $casts = [
        'points' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related reference model
     */
    public function reference()
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        return app($this->reference_type)->find($this->reference_id);
    }
}