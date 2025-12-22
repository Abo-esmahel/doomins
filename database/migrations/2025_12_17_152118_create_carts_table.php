<?php
// database/migrations/xxxx_create_carts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
     Schema::create('carts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->boolean('is_active')->default(true);
    $table->string('session_id')->nullable(); // للزوار غير المسجلين
    $table->timestamps();
});
    }
    public function down(): void {
        Schema::dropIfExists('carts');
    }
};
