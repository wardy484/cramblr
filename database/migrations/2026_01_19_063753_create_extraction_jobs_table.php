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
        Schema::create('extraction_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('refinement_prompt')->nullable();
            $table->enum('status', ['queued', 'processing', 'needs_review', 'failed', 'completed']);
            $table->unsignedInteger('progress_current')->default(0);
            $table->unsignedInteger('progress_total')->default(0);
            $table->json('generation_json')->nullable();
            $table->json('generation_raw')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extraction_jobs');
    }
};
