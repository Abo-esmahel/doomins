<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'price_monthly',
        'price_yearly',
        'description',
        'productable_id',
        'productable_type',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
    ];

    public function productable()
    {
        return $this->morphTo();
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function isAvailable()
    {
        if ($this->productable) {
            return method_exists($this->productable, 'isAvailable') && $this->productable->isAvailable();
        }
        return false;
    }
    public function inactive(){
        if ($this->productable) {
            if (method_exists($this->productable, 'inactive')) {
                $this->productable->inactive();
                $this->productable->save();
            }
        }
    }
    public function active(){
        if ($this->productable) {
            if (method_exists($this->productable, 'active')) {
                $this->productable->active();
                $this->productable->save();
            }
        }            }
        
        //مالك المنيج؟
    public function owner()
    {
        return $this->morphTo()->owner();   
    }
    // نطاق الاستعلام للمنتجات المتاحة
    public function scopeAvailable($query)
    {
        return $query->whereHasMorph('productable', [Domain::class, Server::class], function ($query) {
            $query->where('available', true);
        });
    }

    // طريقة للحصول على المنتج الحقيقي
    public function getActualProductAttribute()
    {
        return $this->productable;
    }
}
