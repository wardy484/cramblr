<?php

namespace Database\Factories;

use App\Enums\JobPageStatus;
use App\Models\ExtractionJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobPage>
 */
class JobPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => ExtractionJob::factory(),
            'page_index' => 1,
            'image_path' => 'jobs/sample/page.jpg',
            'extraction_json' => null,
            'raw_response' => null,
            'confidence' => null,
            'status' => JobPageStatus::Queued,
            'error_message' => null,
        ];
    }
}
