<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_log_id',
        'recipient_id',
        'recipient_type',
        'amount',
        'percentage',
        'status',
        'transaction_reference',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the payment log associated with this split.
     */
    public function paymentLog()
    {
        return $this->belongsTo(PaymentLog::class);
    }

    /**
     * Get the recipient of this payment split (polymorphic).
     */
    public function recipient()
    {
        return $this->morphTo();
    }

    /**
     * Calculate the split amount based on a percentage of the total
     *
     * @param float $totalAmount
     * @param float $percentage
     * @return float
     */
    public static function calculateSplitAmount($totalAmount, $percentage)
    {
        return round(($totalAmount * $percentage) / 100, 2);
    }
}