<?php
// database/migrations/xxxx_create_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->boolean('hasVerifiedEmail')->default(false);
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->enum('role', ['user', 'admin', 'superadmin'])->default('user');
            $table->decimal('balance', 10, 2)->default(0.00); // الرصيد
            $table->rememberToken();
            $table->timestamps();
            $table->string('verification_token')->nullable();
            $table->timestamp('verification_token_expires_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('inactive');
        });

            Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamps();
            $table->timestamp('expires_at');
        });


    }

};
