<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model {
    protected $fillable = [
        'bio', 
        'avatar', 
        'qualifications', 
        'teaching_style', 
        'availability', 
        'hire_rate', 
        'hire_currency', 
        'social_links'
    ];
    
    protected $casts = [
        'qualifications' => 'array',
        'availability' => 'array',
        'social_links' => 'array',
    ];

    public function User() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}