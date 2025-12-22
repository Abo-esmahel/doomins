<?php
// database/migrations/xxxx_create_tickets_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['open', 'closed', 'pending', 'resolved'])->default('open');
            $table->unsignedBigInteger('admin_id')->nullable(); // من قام بالرد
            $table->text('response')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('tickets');
    }
};
