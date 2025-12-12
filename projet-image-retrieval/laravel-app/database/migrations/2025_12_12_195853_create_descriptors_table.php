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
        Schema::create('descriptors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detection_id')->constrained()->cascadeOnDelete();

            $table->json('color_hist');
            $table->json('dominant_colors');
            $table->json('gabor');
            $table->json('tamura');
            $table->json('hu_moments');
            $table->json('orientation_hist');
            $table->json('extra')->nullable();

            $table->json('feature_vector');
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('descriptors');
    }
};
