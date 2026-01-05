<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    // 1. Tabel Aturan (Rules)
    Schema::create('detection_rules', function (Blueprint $table) {
        $table->id();
        $table->string('name');             // Contoh: "Brute Force SSH"
        $table->string('log_type');         // Contoh: "auth" atau "nginx"
        $table->string('search_keyword');   // Contoh: "Failed password" atau "404"
        $table->integer('threshold');       // Batas picu (Misal: 5x)
        $table->integer('time_window_m');   // Dalam berapa menit? (Misal: 1 menit)
        $table->boolean('auto_block')->default(false); // Kalau kena, langsung blok IP?
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    // 2. Tabel Alert (Hasil Deteksi)
    Schema::create('security_alerts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('rule_id')->constrained('detection_rules');
        $table->string('attacker_ip');
        $table->string('hostname'); // Server mana yang diserang
        $table->integer('hits');    // Berapa kali dia melakukan aksi (misal: 12x)
        $table->text('evidence');   // Contoh log terakhirnya
        $table->enum('status', ['new', 'investigating', 'resolved'])->default('new');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siem_rules_tables');
    }
};
