<?php
// database/migrations/xxxx_create_payments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('method'); // 'credit_card', 'paypal', 'stripe', 'crypto'
            $table->enum('status', ['initiated', 'completed', 'failed', 'cancelled']);
            $table->string('transaction_id')->nullable()->unique(); // المرجع من بوابة الدفع
            $table->json('details')->nullable(); // لحفظ بيانات إضافية (مثل رد بوابة الدفع)
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('payments');
    }
};
