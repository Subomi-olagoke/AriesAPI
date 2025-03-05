<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_reference',
        'payment_type',
        'status',
        'amount',
        'course_id',
        'plan_type',
        'paystack_code',
        'response_data',
        'metadata'
    ];

    protected $casts = [
        'response_data' => 'array',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user that owns the payment log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course if this payment is for a course enrollment.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}