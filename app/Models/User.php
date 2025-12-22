<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, \Laravel\Sanctum\HasApiTokens;

   protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'balance',
        'verification_token',
        'verification_token_expires_at',
        'blocked_reason',
        'hasVerifiedEmail',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_token_expires_at',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'balance' => 'float',
        'hasVerifiedEmail' => 'boolean',
          'verification_token_expires_at' => 'datetime',
    ];
    public $timestamps = true;

    /* ========== RELATIONS ========== */

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'user_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /* ========== SCOPES ========== */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['admin', 'superadmin']);
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', 'user');
    }

    public function scopeWithBalance($query, $min = 0)
    {
        return $query->where('balance', '>=', $min);
    }

    public function scopeRegisteredBetween($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /* ========== ACCESSORS ========== */

    public function getIsAdminAttribute(): bool
    {
        return in_array($this->role, ['admin', 'superadmin']);
    }

    public function getIsSuperadminAttribute(): bool
    {
        return $this->role === 'superadmin';
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getTotalSpentAttribute(): float
    {
        return $this->orders()->where('payment_status', 'paid')->sum('total_amount');
    }

    public function getPendingOrdersCountAttribute(): int
    {
        return $this->orders()->whereIn('status', ['pending', 'processing'])->count();
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2) . ' $';
    }

    public function getProfileCompletionAttribute(): int
    {
        $completion = 20; // Base
        if (!empty($this->name)) $completion += 20;
        if (!empty($this->email)) $completion += 20;
        if (!empty($this->phone)) $completion += 20;
        if ($this->hasVerifiedEmail) $completion += 20;

        return min(100, $completion);
    }

    /* ========== METHODS ========== */

    public function verifyEmail(): bool
    {
        return $this->update([
            'hasVerifiedEmail' => true,
            'email_verified_at' => now(),
            'status' => 'active',
            'verification_token' => null,
            'verification_token_at' => null
        ]);
    }

    public function addToBalance(float $amount): float
    {
        $this->increment('balance', $amount);
        return $this->balance;
    }

    public function deductFromBalance(float $amount): bool
    {
        if ($this->balance >= $amount) {
            $this->decrement('balance', $amount);
            return true;
        }
        return false;
    }

    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    public function deactivate(): bool
    {
        return $this->update(['status' => 'inactive']);
    }

    public function block(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'blocked',
            'blocked_reason' => $reason
        ]);
    }

    public function unblock(): bool
    {
        return $this->update(['status' => 'active', 'blocked_reason' => null]);
    }

    public function promoteToAdmin(): bool
    {
        return $this->update(['role' => 'admin']);
    }

    public function promoteToSuperadmin(): bool
    {
        return $this->update(['role' => 'superadmin']);
    }

    public function demoteToUser(): bool
    {
        return $this->update(['role' => 'user']);
    }

    public function getDashboardStats(): array
    {
        return [
            'total_orders' => $this->orders()->count(),
            'pending_orders' => $this->orders()->whereIn('status', ['pending', 'processing'])->count(),
            'completed_orders' => $this->orders()->where('status', 'completed')->count(),
            'total_spent' => $this->total_spent,
            'open_invoices' => $this->invoices()->where('status', 'pending')->count(),
            'current_balance' => $this->balance,
            'profile_completion' => $this->profile_completion,
            'last_order' => $this->orders()->latest()->first(),
            'recent_transactions' => $this->transactions()->latest()->limit(5)->get()
        ];
    }

    public function getRecentActivity(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->orders()
            ->with('items.product')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function generateVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->update([
            'verification_token' => $token,
            'verification_token_at' => now()->addHours(24)
        ]);

        return $token;
    }

    public function isValidVerificationToken(string $token)
    {

    }

    public function sendEmailVerificationNotification(): void
    {
    }

    public function sendPasswordResetNotification($token): void
    {
    }
}
