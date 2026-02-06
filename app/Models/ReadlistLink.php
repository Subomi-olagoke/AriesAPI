<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadlistLink extends Model
{
    use HasFactory;

    protected $table = 'readlist_links';

    protected $fillable = [
        'readlist_id',
        'url',
        'title',
        'description',
        'added_by',
    ];

    public function readlist()
    {
        return $this->belongsTo(Readlist::class);
    }
} 