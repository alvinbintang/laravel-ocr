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
        Schema::table('ocr_results', function (Blueprint $table) {
            $table->json('cropped_images')->nullable()->after('selected_regions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ocr_results', function (Blueprint $table) {
            $table->dropColumn('cropped_images');
        });
    }
};