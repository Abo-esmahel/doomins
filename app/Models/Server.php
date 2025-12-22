<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'servers';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $hidden = [
        'added_by',
        'user_id',
        'created_at',
        'updated_at',
    ];
    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'cpu_cores' => 'integer',
        'storage' => 'integer',
        'active_in_user' => 'boolean',
        'expires_at' => 'datetime',
        'expires_at_in_user' => 'datetime',
        'isActive' => 'boolean',
        'status' => 'string',
        'category' => 'string',
        'storage_type' => 'string',
        'bandwidth' => 'string',
        'cpu_speed' => 'string',
        'ram' => 'string',
    ];

    // العلاقة مع Product
    public function product()
    {
        return $this->morphOne(Product::class, 'productable');
    }
  public function isAvailable()
    {
        return $this->status === 'available';
    }
    public function inactive(){
     
          $this->isActive=false;
    }
    public function active(){
      $this->isActive=true;
    }

   
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
    // نطاقات الاستعلام
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // طريقة للحصول على المواصفات كاملة
    public function getFullSpecsAttribute()
    {
        return "{$this->cpu_cores} Core @ {$this->cpu_speed}, {$this->ram} RAM, {$this->storage}GB {$this->storage_type}";
    }
}
