<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOcrResultsTable extends Migration
{
    public function up()
    {
        Schema::table('ocr_results', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('filename');
            $table->json('selected_regions')->nullable()->after('image_path');
            $table->json('ocr_results')->nullable()->after('text');
            $table->dropColumn('text');
        });
    }

    public function down()
    {
        Schema::table('ocr_results', function (Blueprint $table) {
            $table->text('text')->nullable();
            $table->dropColumn('ocr_results');
            $table->dropColumn('selected_regions');
            $table->dropColumn('image_path');
        });
    }
}