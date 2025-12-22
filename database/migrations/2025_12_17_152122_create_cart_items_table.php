<?php
// database/migrations/xxxx_create_cart_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // database/migrations/2024_01_01_create_cart_items_table.php
// database/migrations/xxxx_create_cart_items_table.php
Schema::create('cart_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->integer('quantity')->default(1);
    $table->decimal('price', 10, 2);
    $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');
    $table->string('product_name'); 
    $table->timestamps();

    $table->unique(['cart_id', 'product_id', 'billing_period']);
});
    }
    public function down(): void {
        Schema::dropIfExists('cart_items');
    }
};
