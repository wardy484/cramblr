<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_jobs', function (Blueprint $table) {
            $table->enum('translation_preference', ['phonetic', 'thai'])->default('phonetic')->after('refinement_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_jobs', function (Blueprint $table) {
            $table->dropColumn('translation_preference');
        });
    }
};
