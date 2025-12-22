<?php
// database/migrations/xxxx_create_orders_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('order_number')->unique();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
    $table->decimal('total', 10, 2);
    $table->enum('status', ['pending', 'paid', 'processing', 'completed', 'cancelled'])
          ->default('pending');
    $table->enum('transaction_type',['renewal','new_order','additional_service'])->default('new_order');
    $table->enum('order_type',['domain','server','cart'])->default('domain');
    $table->string('payment_method')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->json('billing_info')->nullable(); 
    $table->timestamps();
});

    }
    public function down(): void {
        Schema::dropIfExists('orders');

}};
