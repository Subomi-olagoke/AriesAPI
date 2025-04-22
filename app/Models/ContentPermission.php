<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContentPermission extends Model
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
        'role',
        'granted_by'
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
            
            if (empty($model->granted_by)) {
                $model->granted_by = auth()->id();
            }
        });
    }
    
    /**
     * Get the content that owns the permission.
     */
    public function content()
    {
        return $this->belongsTo(CollaborativeContent::class, 'content_id');
    }
    
    /**
     * Get the user this permission applies to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the user who granted this permission.
     */
    public function grantor()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}