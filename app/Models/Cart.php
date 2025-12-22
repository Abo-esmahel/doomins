<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_active',
        'session_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    public function getTotalAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->price;
        });
    }

    public function addItem(Product $product, $quantity = 1, $billingPeriod = 'monthly')
    {
        $price = $billingPeriod === 'yearly' ? $product->price_yearly : $product->price_monthly;

        return $this->items()->updateOrCreate(
            [
                'product_id' => $product->id,
                'billing_period' => $billingPeriod,
            ],
            [
                'quantity' => $quantity,
                'price' => $price,
                'product_name' => $product->name,
            ]
        );
    }

    public function clear()
    {
        return $this->items()->delete();
    }

    public function isEmpty()
    {
        return $this->items()->count() === 0;
    }
}
