<?php

namespace Database\Factories;

use App\Enums\AdviceStatus;
use App\Enums\AiVerdict;
use App\Enums\ExtractionStatus;
use App\Models\Submission;
use App\Models\SubmissionAiAdvice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionAiAdvice>
 */
class SubmissionAiAdviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'extracted_content' => 'Sample extracted content.',
            'extraction_status' => ExtractionStatus::Extracted,
            'extraction_error' => null,
            'system_prompt_snapshot' => 'System prompt snapshot',
            'type_rules_snapshot' => 'Type rules snapshot',
            'model_id' => 'global.amazon.nova-2-lite-v1:0',
            'ai_verdict' => AiVerdict::Accept,
            'ai_reason' => 'Looks acceptable.',
            'ai_raw_response' => '{"verdict":"accept","reason":"Looks acceptable."}',
            'status' => AdviceStatus::Completed,
            'analyzed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'ai_verdict' => null,
            'ai_reason' => null,
            'ai_raw_response' => null,
            'status' => AdviceStatus::Pending,
            'analyzed_at' => null,
        ]);
    }
}
