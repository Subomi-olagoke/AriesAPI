<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContentComment extends Model
{
    use HasFactory;
    
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    
    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'content_id',
        'user_id',
        'comment_text',
        'position',
        'resolved',
        'parent_id'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'position' => 'array',
        'resolved' => 'boolean',
    ];
    
    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }
    
    /**
     * Get the content that owns the comment.
     */
    public function content()
    {
        return $this->belongsTo(CollaborativeContent::class, 'content_id');
    }
    
    /**
     * Get the user who created the comment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the parent comment if this is a reply.
     */
    public function parent()
    {
        return $this->belongsTo(ContentComment::class, 'parent_id');
    }
    
    /**
     * Get the replies to this comment.
     */
    public function replies()
    {
        return $this->hasMany(ContentComment::class, 'parent_id');
    }
    
    /**
     * Resolve this comment.
     */
    public function resolve()
    {
        $this->resolved = true;
        $this->save();
        
        return $this;
    }
    
    /**
     * Unresolve this comment.
     */
    public function unresolve()
    {
        $this->resolved = false;
        $this->save();
        
        return $this;
    }
}