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
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedInteger('learning_step_index')->nullable()->after('study_state');
            $table->boolean('is_learning')->default(false)->after('learning_step_index');
            $table->boolean('is_relearning')->default(false)->after('is_learning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'learning_step_index',
                'is_learning',
                'is_relearning',
            ]);
        });
    }
};
