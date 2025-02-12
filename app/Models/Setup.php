<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description',
        'qualifications',
        'objectives',
        'social_links',
        'payment_methods',
    ];

    protected $casts = [
        'social_links' => 'array',
        'payment_methods' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
