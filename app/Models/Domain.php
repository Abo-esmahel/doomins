<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $guarded = [];
 protected $table = 'domains';
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
        'expires_at' => 'datetime',
        'isActive' => 'boolean',
        'expired_at_in_user' => 'datetime',
        'available' => 'boolean',
        'status' => 'string',
        'active_in_user' => 'boolean',
     
        'tld' => 'string',
    ];

    public function product()
    {
        return $this->morphOne(Product::class, 'productable');
    }

    public function isAvailable()
    {
        return $this->available ==='available';
    }
     public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
     public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    // نطاقات الاستعلام
    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }
    public function inactive(){
     
          $this->isActive=false;
    }
    public function active(){
      $this->isActive=true;
    }
    

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
                    ->where('expires_at', '>', now());
    }

    // طريقة للحصول على الاسم الكامل
    public function getFullNameAttribute()
    {
        return $this->name . '.' . $this->tld;
    }
}
