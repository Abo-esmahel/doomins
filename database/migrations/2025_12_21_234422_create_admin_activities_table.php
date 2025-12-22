<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->enum('action', ['login', 'logout', 'create', 'update', 'delete', 'view', 'other']);
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('record_id')->nullable(); 
            $table->text('details')->nullable(); 
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['admin_id', 'created_at']);
            $table->index('action');
            $table->index(['table_name', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
    }
};