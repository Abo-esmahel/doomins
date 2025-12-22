<?php
// database/migrations/xxxx_create_domains_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
       Schema::create('domains', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('added_by')->nullable()->constrained('users')->onDelete('SET NULL');
    $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('SET NULL');
    $table->enum('tld', [
    // الأساسية
    '.com', '.net', '.org', '.info', '.biz',
    
    // دول عربية
    '.sa', '.ae', '.eg', '.qa', '.om', '.bh', '.kw', '.jo', '.lb',
    
    // دول عالمية
    '.uk', '.us', '.ca', '.de', '.fr', '.au', '.in', '.jp', '.cn',
    
    // تقنية
    '.io', '.ai', '.dev', '.app', '.tech', '.digital', '.cloud',
    
    // تجارية
    '.shop', '.store', '.business', '.company',
    
    // تعليمية
    '.edu', '.academy', '.school',
    
    // حكومية
    '.gov',
    
    // أخرى شائعة
    '.xyz', '.online', '.site', '.world', '.space', '.media',
    '.blog', '.news', '.press', '.reviews', '.guide', '.expert',
    
    // نطاقات مدن
    '.nyc', '.london', '.dubai',
    
    // تخصصية
    '.law', '.medical', '.finance', '.realestate'
])->default('.com');    $table->decimal('price_monthly', 10, 2);
    $table->decimal('price_yearly', 10, 2);
    $table->timestamp('expires_at');
    $table->timestamp('expires_at_in_user')->nullable();
    $table->boolean('active_in_user')->default(false);
    
    $table->boolean('available')->default(true);
    $table->boolean('isActive')->default(true);    
    $table->enum('status', ['available', 'sold_out'])->default('available');
    $table->timestamps();

    $table->unique(['name', 'tld']);
});
    }
    public function down(): void {
        Schema::dropIfExists('domains');
    }
};
