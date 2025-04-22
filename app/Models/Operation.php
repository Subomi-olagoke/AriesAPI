<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Operation extends Model
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
        'type',
        'position',
        'length',
        'text',
        'version',
        'meta'
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'position' => 'integer',
        'length' => 'integer',
        'version' => 'integer',
        'meta' => 'array'
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
     * Get the content this operation belongs to.
     */
    public function content()
    {
        return $this->belongsTo(CollaborativeContent::class, 'content_id');
    }
    
    /**
     * Get the user who created this operation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Apply this operation to the given text
     */
    public function apply($text)
    {
        switch ($this->type) {
            case 'insert':
                return $this->applyInsert($text);
                
            case 'delete':
                return $this->applyDelete($text);
                
            case 'format':
                // Format operations don't change the plain text
                return $text;
                
            case 'cursor':
            case 'selection':
                // Cursor/selection operations don't change the text
                return $text;
                
            default:
                return $text;
        }
    }
    
    /**
     * Apply insert operation
     */
    private function applyInsert($text)
    {
        if ($this->position > strlen($text)) {
            return $text . $this->text;
        }
        
        return substr($text, 0, $this->position) . $this->text . substr($text, $this->position);
    }
    
    /**
     * Apply delete operation
     */
    private function applyDelete($text)
    {
        $start = $this->position;
        $end = $this->position + $this->length;
        
        if ($start >= strlen($text)) {
            return $text;
        }
        
        if ($end > strlen($text)) {
            $end = strlen($text);
        }
        
        return substr($text, 0, $start) . substr($text, $end);
    }
    
    /**
     * Transform this operation against another operation
     */
    public function transform(Operation $other)
    {
        // If operations are from the same user, no need to transform
        if ($this->user_id === $other->user_id) {
            return $this;
        }
        
        // If the other operation is a cursor/selection, no need to transform this operation
        if ($other->type === 'cursor' || $other->type === 'selection') {
            return $this;
        }
        
        // If this operation is a cursor/selection, transform it against the other operation
        if ($this->type === 'cursor' || $this->type === 'selection') {
            return $this->transformCursorAgainst($other);
        }
        
        // Handle different operation type combinations
        if ($this->type === 'insert' && $other->type === 'insert') {
            return $this->transformInsertAgainstInsert($other);
        }
        
        if ($this->type === 'insert' && $other->type === 'delete') {
            return $this->transformInsertAgainstDelete($other);
        }
        
        if ($this->type === 'delete' && $other->type === 'insert') {
            return $this->transformDeleteAgainstInsert($other);
        }
        
        if ($this->type === 'delete' && $other->type === 'delete') {
            return $this->transformDeleteAgainstDelete($other);
        }
        
        // Default: return the operation unchanged
        return $this;
    }
    
    /**
     * Transform a cursor position against another operation
     */
    private function transformCursorAgainst(Operation $other)
    {
        $newOperation = clone $this;
        
        if ($other->type === 'insert') {
            // If insertion was before or at cursor position, move cursor right
            if ($other->position <= $this->position) {
                $newOperation->position += strlen($other->text);
            }
        } elseif ($other->type === 'delete') {
            // If deletion was before cursor position
            if ($other->position < $this->position) {
                $deleteEnd = $other->position + $other->length;
                
                if ($deleteEnd <= $this->position) {
                    // Deletion was entirely before cursor, move cursor left
                    $newOperation->position -= $other->length;
                } else {
                    // Deletion includes cursor, move cursor to delete position
                    $newOperation->position = $other->position;
                }
            }
        }
        
        return $newOperation;
    }
    
    /**
     * Transform insert operation against another insert
     */
    private function transformInsertAgainstInsert(Operation $other)
    {
        $newOperation = clone $this;
        
        // If the other insert happened at an earlier position or the same position 
        // but with priority (e.g., from a user with a lower ID), adjust this position
        if ($other->position < $this->position || 
            ($other->position === $this->position && $other->user_id < $this->user_id)) {
            $newOperation->position += strlen($other->text);
        }
        
        return $newOperation;
    }
    
    /**
     * Transform insert operation against a delete
     */
    private function transformInsertAgainstDelete(Operation $other)
    {
        $newOperation = clone $this;
        $deleteEnd = $other->position + $other->length;
        
        // If insertion point is after delete start position
        if ($this->position >= $other->position) {
            if ($this->position >= $deleteEnd) {
                // Insertion is after the deletion, adjust position
                $newOperation->position -= $other->length;
            } else {
                // Insertion is within the deleted region, move to delete start
                $newOperation->position = $other->position;
            }
        }
        
        return $newOperation;
    }
    
    /**
     * Transform delete operation against an insert
     */
    private function transformDeleteAgainstInsert(Operation $other)
    {
        $newOperation = clone $this;
        $deleteEnd = $this->position + $this->length;
        
        // If the other insert is before or at the deletion start
        if ($other->position <= $this->position) {
            $newOperation->position += strlen($other->text);
        } 
        // If the other insert is inside the deletion range
        else if ($other->position < $deleteEnd) {
            $newOperation->length += strlen($other->text);
        }
        
        return $newOperation;
    }
    
    /**
     * Transform delete operation against another delete
     */
    private function transformDeleteAgainstDelete(Operation $other)
    {
        $newOperation = clone $this;
        $thisEnd = $this->position + $this->length;
        $otherEnd = $other->position + $other->length;
        
        // Case 1: Other delete is entirely before this delete
        if ($otherEnd <= $this->position) {
            $newOperation->position -= $other->length;
            return $newOperation;
        }
        
        // Case 2: Other delete completely contains this delete
        if ($other->position <= $this->position && $otherEnd >= $thisEnd) {
            $newOperation->length = 0; // This delete is a no-op now
            return $newOperation;
        }
        
        // Case 3: Other delete overlaps the beginning of this delete
        if ($other->position < $this->position && $otherEnd > $this->position && $otherEnd < $thisEnd) {
            $newOperation->position = $other->position;
            $newOperation->length = $thisEnd - $otherEnd;
            return $newOperation;
        }
        
        // Case 4: Other delete is completely inside this delete
        if ($other->position >= $this->position && $otherEnd <= $thisEnd) {
            $newOperation->length -= $other->length;
            return $newOperation;
        }
        
        // Case 5: Other delete overlaps the end of this delete
        if ($other->position > $this->position && $other->position < $thisEnd && $otherEnd > $thisEnd) {
            $newOperation->length = $other->position - $this->position;
            return $newOperation;
        }
        
        // Default case
        return $newOperation;
    }
}