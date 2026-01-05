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
    Schema::create('menus', function (Blueprint $table) {
        $table->id();
        $table->string('title');            // Nama Menu
        $table->string('route')->nullable();// Nama Route Laravel
        $table->text('icon')->nullable();   // SVG String
        $table->json('active_routes')->nullable(); // Pattern URL buat highlight menu aktif
        $table->integer('order')->default(0); // Urutan menu
        $table->boolean('is_visible')->default(true); // Show/Hide
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
