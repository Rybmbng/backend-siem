<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up() {
    Schema::table('agents', function (Blueprint $table) {
        // Simpan array channel, misal: ["whatsapp", "telegram"]
        $table->json('notification_channels')->nullable();
        $table->string('admin_phone')->nullable(); // Nomor WA
    });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            //
        });
    }
};
