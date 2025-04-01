<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducatorRating extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'educator_id',
        'hire_session_id',
        'rating',
        'comment'
    ];
    
    protected $casts = [
        'rating' => 'integer',
    ];
    
    /**
     * Get the user who gave the rating.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Get the educator who received the rating.
     */
    public function educator()
    {
        return $this->belongsTo(User::class, 'educator_id');
    }
    
    /**
     * Get the hire session that was rated.
     */
    public function hireSession()
    {
        return $this->belongsTo(HireSession::class, 'hire_session_id');
    }
}
