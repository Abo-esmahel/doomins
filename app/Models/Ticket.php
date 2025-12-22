<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'subject', 'message', 'status', 'admin_id', 'response', 'closed_at'
    ];

    protected $casts = ['closed_at' => 'datetime'];

    // ---------- العلاقات ----------
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // ---------- دوال مساعدة ----------
    public function scopeOpen($query) {
        return $query->where('status', 'open');
    }
    public function close($response = null, $adminId = null) {
        $this->update([
            'status' => 'closed',
            'response' => $response,
            'admin_id' => $adminId,
            'closed_at' => now()
        ]);
    }
    public function isClosed() {
        return $this->status === 'closed';
    }
}
