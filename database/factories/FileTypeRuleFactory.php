<?php

namespace Database\Factories;

use App\Enums\SubmissionType;
use App\Models\FileTypeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileTypeRule>
 */
class FileTypeRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SubmissionType::Document,
            'rules' => 'Review document clarity, relevance, and completeness.',
            'updated_by' => null,
        ];
    }
}
