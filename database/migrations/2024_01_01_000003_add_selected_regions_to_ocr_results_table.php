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
            $table->json('selected_regions')->nullable()->after('ocr_results');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ocr_results', function (Blueprint $table) {
            $table->dropColumn('selected_regions');
        });
    }
};