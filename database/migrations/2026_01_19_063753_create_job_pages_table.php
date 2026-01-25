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
        Schema::create('job_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_id');
            $table->unsignedInteger('page_index');
            $table->text('image_path');
            $table->json('extraction_json')->nullable();
            $table->json('raw_response')->nullable();
            $table->float('confidence')->nullable();
            $table->enum('status', ['queued', 'extracted', 'failed']);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('extraction_jobs')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_pages');
    }
};
