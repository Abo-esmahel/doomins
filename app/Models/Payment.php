<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'amount', 'method', 'status', 'transaction_id', 'details'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'details' => 'array', // لتحويل حقل JSON تلقائياً لمصفوفة
    ];

    // ---------- العلاقات ----------
    public function order() {
        return $this->belongsTo(Order::class);
    }

    // ---------- دوال مساعدة ----------
    public function isCompleted() {
        return $this->status === 'completed';
    }
    public function markAsCompleted($transactionId = null) {
        $this->update([
            'status' => 'completed',
            'transaction_id' => $transactionId ?? $this->transaction_id
        ]);
        $this->order->markAsPaid(); // تحديث حالة الطلب المرتبط
    }
}
