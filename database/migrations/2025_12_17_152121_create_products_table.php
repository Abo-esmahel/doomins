<?php
// database/migrations/xxxx_create_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('type', ['domain', 'server']);
    $table->decimal('price_monthly', 10, 2);
    $table->decimal('price_yearly', 10, 2);
    $table->text('description')->nullable();
    $table->morphs('productable'); // يضيف productable_id و productable_type
   
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
