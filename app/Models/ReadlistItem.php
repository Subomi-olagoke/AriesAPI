<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReadlistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'readlist_id',
        'item_id',
        'item_type',
        'order',
        'notes',
        'title',
        'description',
        'url',
        'type'
    ];

    public function readlist(): BelongsTo
    {
        return $this->belongsTo(Readlist::class);
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }
}