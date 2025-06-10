<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryUrl extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url',
        'title',
        'summary',
        'notes',
        'created_by'
    ];

    /**
     * The libraries that this URL belongs to.
     */
    public function libraries()
    {
        return $this->morphToMany(OpenLibrary::class, 'content', 'library_content', 'content_id', 'library_id');
    }

    /**
     * The user who created this URL.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}