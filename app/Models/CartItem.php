<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'price',
        'billing_period',
        'product_name',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
    ];

    // العلاقات
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // طريقة حساب الإجمالي للعنصر
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->price;
    }

    // طريقة للحصول على نوع المنتج
    public function getProductTypeAttribute()
    {
        return $this->product->type ?? null;
    }

    // طريقة للحصول على المنتج الحقيقي (Domain أو Server)
    public function getActualProductAttribute()
    {
        return $this->product->productable ?? null;
    }
}
