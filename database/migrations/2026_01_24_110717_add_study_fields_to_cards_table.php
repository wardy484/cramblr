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
            $table->string('study_state')->default('new')->after('status');
            $table->timestamp('due_at')->nullable()->after('study_state');
            $table->unsignedInteger('interval')->default(0)->after('due_at');
            $table->decimal('ease', 4, 2)->default(2.50)->after('interval');
            $table->unsignedInteger('repetitions')->default(0)->after('ease');
            $table->unsignedInteger('lapses')->default(0)->after('repetitions');
            $table->timestamp('last_reviewed_at')->nullable()->after('lapses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'study_state',
                'due_at',
                'interval',
                'ease',
                'repetitions',
                'lapses',
                'last_reviewed_at',
            ]);
        });
    }
};
