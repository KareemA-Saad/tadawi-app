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
        // حل مشكلة طول الـ index في MySQL
        Schema::defaultStringLength(191);

        Schema::create('medicines', function (Blueprint $table) {
    $table->id();
    $table->string('brand_name', 100); // قللنا الطول
    $table->string('form', 100)->nullable();
    $table->string('dosage_strength', 100)->nullable();
    $table->string('manufacturer')->nullable();
    $table->decimal('price', 10, 2)->nullable();

    $table->foreignId('active_ingredient_id')->constrained('active_ingredients')->onDelete('cascade');

    $table->timestamps();
    $table->softDeletes();

    $table->index('brand_name', 'idx_medicines_brand');
    $table->index('price', 'idx_medicines_price');
    $table->index(['brand_name', 'form'], 'idx_medicines_brand_form');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
