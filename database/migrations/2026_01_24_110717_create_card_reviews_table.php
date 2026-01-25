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
        Schema::create('card_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('card_id');
            $table->string('rating');
            $table->unsignedInteger('interval');
            $table->decimal('ease', 4, 2)->nullable();
            $table->timestamp('reviewed_at');
            $table->string('algorithm');
            $table->timestamp('due_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete();
            $table->index(['card_id', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_reviews');
    }
};
