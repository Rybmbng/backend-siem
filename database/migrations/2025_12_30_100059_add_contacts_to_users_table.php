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
       Schema::table('users', function (Blueprint $table) {
        $table->string('phone_number')->nullable()->unique(); // Untuk WA (628xxx)
        $table->string('telegram_chat_id')->nullable(); // Untuk Telegram
        $table->enum('siem_role', ['full_access', 'notify_only'])->default('full_access'); // Role SIEM
    });

    // 2. Buat Tabel Pivot (User megang Server apa aja?)
    Schema::create('agent_user', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('agent_id')->constrained()->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('agent_user');
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['phone_number', 'telegram_chat_id', 'siem_role']);
    });
}
};
