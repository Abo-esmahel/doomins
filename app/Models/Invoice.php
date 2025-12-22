<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date'
    ];

    // العلاقات
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    
    public static function generateInvoiceNumber()
    {
        $date = date('Ymd');
        $lastInvoice = self::where('invoice_number', 'like', "INV-{$date}-%")
                          ->latest('id')
                          ->first();

        $number = $lastInvoice ? (int)substr($lastInvoice->invoice_number, -4) + 1 : 1;

        return "INV-{$date}-" . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
