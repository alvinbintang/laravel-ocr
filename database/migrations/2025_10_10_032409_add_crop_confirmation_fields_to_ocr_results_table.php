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
            // ADDED: Timestamp when crop was confirmed by user
            $table->timestamp('crop_confirmed_at')->nullable()->after('page_rotations');
            
            // ADDED: Store crop preview data (regions, dimensions, etc.) for confirmation page
            $table->json('crop_preview_data')->nullable()->after('crop_confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ocr_results', function (Blueprint $table) {
            $table->dropColumn(['crop_confirmed_at', 'crop_preview_data']);
        });
    }
};
