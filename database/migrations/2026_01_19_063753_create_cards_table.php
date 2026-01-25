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
        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('deck_id');
            $table->enum('status', ['proposed', 'approved', 'archived']);
            $table->text('front');
            $table->text('back');
            $table->json('tags')->nullable();
            $table->json('extra')->nullable();
            $table->uuid('source_job_id')->nullable();
            $table->timestamps();

            $table->foreign('deck_id')->references('id')->on('decks')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
