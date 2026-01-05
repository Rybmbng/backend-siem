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
    Schema::create('wa_histories', function (Blueprint $table) {
        $table->id();
        $table->string('sender'); // Nomor pengirim
        $table->text('message'); // Isi pesan
        $table->string('command')->nullable(); // Perintah yang dideteksi
        $table->enum('status', ['allowed', 'denied', 'executed'])->default('allowed');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_histories');
    }
};
