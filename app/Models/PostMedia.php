<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'media_link',
        'media_type',
        'media_thumbnail',
        'original_filename',
        'mime_type',
        'order'
    ];

    /**
     * Get the post that owns the media.
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the file extension attribute based on original filename
     */
    public function getFileExtensionAttribute()
    {
        if (!$this->original_filename) {
            return null;
        }

        $parts = explode('.', $this->original_filename);
        return end($parts);
    }
}