<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'cart_id',
        'total',
        'status',
        'payment_method',
        'paid_at',
        'billing_info',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'billing_info' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    protected static function generateOrderNumber()
    {
        return 'ORD-' . strtoupper(uniqid());
    }

    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function getItemsAttribute()
    {
        return $this->cart->items ?? collect();
    }

    public function markAsPaid($paymentMethod)
    {
        $this->update([
            'status' => 'paid',
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
        ]);


    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function getOrderDetailsAttribute()
    {
        return $this->cart->items->map(function ($item) {
            return [
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'subtotal' => $item->subtotal,
                'billing_period' => $item->billing_period,
                'product_type' => $item->product_type,
                'actual_product' => $item->actual_product,
            ];
        });
    }
}
