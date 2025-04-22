<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HireSessionDocument extends Model
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
        'id',
        'hire_session_id',
        'user_id',
        'title',
        'file_path',
        'file_type',
        'file_size',
        'description',
        'is_active',
        'shared_at'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'hire_session_id' => 'string',
        'user_id' => 'string',
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'shared_at' => 'datetime',
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
     * Get the hire session that this document belongs to.
     */
    public function hireSession()
    {
        return $this->belongsTo(HireSession::class);
    }
    
    /**
     * Get the user who shared this document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the download URL for this document.
     */
    public function getDownloadUrl()
    {
        return url("/api/hire-sessions/{$this->hire_session_id}/documents/{$this->id}/download");
    }
    
    /**
     * Check if the document is viewable by the given user.
     */
    public function isViewableBy(User $user)
    {
        $session = $this->hireSession;
        return $session->hireRequest->client_id === $user->id || 
               $session->hireRequest->tutor_id === $user->id;
    }
}
