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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // رقم الفاتورة الفريد
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // المستخدم
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade'); // الطلب المرتبط
            $table->decimal('subtotal', 10, 2)->default(0); // الإجمالي قبل الضريبة
            $table->decimal('tax_amount', 10, 2)->default(0); // مبلغ الضريبة
            $table->decimal('discount_amount', 10, 2)->default(0); // مبلغ الخصم
            $table->decimal('total_amount', 10, 2)->default(0); // المبلغ الإجمالي
            $table->enum('status', ['pending', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'])->default('pending'); // حالة الفاتورة
            $table->date('invoice_date')->default(now()); // تاريخ إصدار الفاتورة
            $table->date('due_date'); // تاريخ الاستحقاق
            $table->date('paid_date')->nullable(); // تاريخ الدفع
            $table->string('payment_method')->nullable(); // طريقة الدفع
            $table->string('payment_reference')->nullable(); // مرجع الدفع
            $table->text('notes')->nullable(); // ملاحظات إضافية
            $table->json('items')->nullable(); // تفاصيل العناصر (يمكن تخزينها كـ JSON)
            $table->json('billing_info')->nullable(); // معلومات الفاتورة (الاسم، العنوان، إلخ)
            $table->json('tax_info')->nullable(); // معلومات الضريبة
            $table->timestamps();
            $table->softDeletes(); // حذف ناعم
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
