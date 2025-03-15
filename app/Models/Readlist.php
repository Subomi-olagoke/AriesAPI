<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Readlist extends Model
{
    use HasFactory;

    // Allow manual ID assignment by setting incrementing to false
    public $incrementing = false;
    
    // Make sure the key type is int
    protected $keyType = 'int';
    
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'image_url',
        'is_public',
        'share_key'
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        // Automatically generate a unique share_key when creating a readlist
        static::creating(function ($model) {
            if (!$model->share_key) {
                $model->share_key = \Illuminate\Support\Str::random(10);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReadlistItem::class)->orderBy('order');
    }

    public function getCourses()
    {
        $courseIds = $this->items()
            ->where('item_type', Course::class)
            ->pluck('item_id');
            
        return Course::whereIn('id', $courseIds)->get();
    }

    public function getPosts()
    {
        $postIds = $this->items()
            ->where('item_type', Post::class)
            ->pluck('item_id');
            
        return Post::whereIn('id', $postIds)->get();
    }

    public function addItem($item, $order = null, $notes = null)
    {
        $itemType = get_class($item);
        $itemId = $item->id;
        
        if ($order === null) {
            $maxOrder = $this->items()->max('order') ?? 0;
            $order = $maxOrder + 1;
        }
        
        return $this->items()->updateOrCreate(
            [
                'item_id' => $itemId,
                'item_type' => $itemType
            ],
            [
                'order' => $order,
                'notes' => $notes
            ]
        );
    }

    public function removeItem($item)
    {
        $itemType = get_class($item);
        $itemId = $item->id;
        
        return $this->items()
            ->where('item_id', $itemId)
            ->where('item_type', $itemType)
            ->delete();
    }

    public function reorderItems(array $itemsOrder)
    {
        foreach ($itemsOrder as $itemData) {
            $this->items()
                ->where('id', $itemData['id'])
                ->update(['order' => $itemData['order']]);
        }
        
        return $this;
    }
    
    public function getShareUrlAttribute()
    {
        return url("/readlists/shared/{$this->share_key}");
    }
}