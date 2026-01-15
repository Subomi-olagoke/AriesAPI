<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryContent extends Model
{
    use HasFactory;

    protected $table = 'library_content';

    protected $fillable = [
        'library_id',
        'content_id',
        'content_type',
        'relevance_score'
    ];

    /**
     * Get the library this content belongs to
     */
    public function library()
    {
        return $this->belongsTo(OpenLibrary::class, 'library_id');
    }

    /**
     * Get the content item (polymorphic)
     */
    public function content()
    {
        return $this->morphTo();
    }
}