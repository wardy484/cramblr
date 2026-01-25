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
        Schema::table('extraction_jobs', function (Blueprint $table) {
            $table->boolean('import_audio')->default(true)->after('translation_preference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extraction_jobs', function (Blueprint $table) {
            $table->dropColumn('import_audio');
        });
    }
};
