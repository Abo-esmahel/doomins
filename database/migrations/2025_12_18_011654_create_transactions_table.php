<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', ['payment', 'refund', 'withdrawal', 'deposit', 'adjustment']); // نوع المعاملة
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0); // رسوم المعاملة
            $table->string('currency')->default('SAR'); // العملة
            $table->string('gateway')->nullable(); // بوابة الدفع (stripe, paypal, wallet, etc.)
            $table->string('gateway_transaction_id')->nullable(); // معرف المعاملة في البوابة
            $table->text('description')->nullable(); // وصف المعاملة
            $table->json('metadata')->nullable(); // بيانات إضافية
            $table->json('gateway_response')->nullable(); // استجابة البوابة
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
