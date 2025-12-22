<?php
// database/migrations/xxxx_create_servers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
    Schema::create('servers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('added_by')->constrained('users')->onDelete('cascade');
    $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
    $table->string('name');
    $table->string('cpu_speed');
    $table->integer('cpu_cores');
    $table->string('ram');
    $table->enum('category', ['VPS', 'Dedicated', 'Cloud'])->default('VPS');
    $table->text('description')->nullable();
    $table->enum('storage_type', ['SSD', 'HDD', 'NVMe'])->default('SSD');
    $table->integer('storage');
    $table->string('bandwidth')->nullable();
    $table->string('datacenter_location');
    $table->string('os');
    $table->boolean('active_in_user')->default(false);
    $table->decimal('price_monthly', 10, 2);
    $table->timestamp('expires_at');
    $table->timestamp('expires_at_in_user')->nullable();
    $table->decimal('price_yearly', 10, 2);
    $table->boolean('isActive')->default(true);    
    $table->enum('status', ['available', 'sold_out', 'maintenance'])->default('available');
    $table->timestamps();
});


    }
    public function down(): void {
        Schema::dropIfExists('servers');
    }
};
