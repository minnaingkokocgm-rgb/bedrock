<?php

namespace Database\Factories;

use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'submitter_name' => fake()->name(),
            'submitter_email' => fake()->safeEmail(),
            'original_filename' => 'example.pdf',
            'disk_path' => 'submissions/example.pdf',
            'disk' => 'local',
            'source' => SubmissionSource::Upload,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 500_000),
            'type' => SubmissionType::Document,
            'status' => SubmissionStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function image(): static
    {
        return $this->state(fn (): array => [
            'original_filename' => 'photo.jpg',
            'disk_path' => 'submissions/photo.jpg',
            'mime_type' => 'image/jpeg',
            'type' => SubmissionType::Image,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'original_filename' => 'clip.mp4',
            'disk_path' => 'submissions/clip.mp4',
            'mime_type' => 'video/mp4',
            'type' => SubmissionType::Video,
        ]);
    }

    public function fromS3Uri(): static
    {
        return $this->state(fn (): array => [
            'disk' => 's3',
            'source' => SubmissionSource::S3Uri,
        ]);
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::Approved,
            'reviewed_by' => $reviewer?->id ?? User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(?User $reviewer = null, string $reason = 'Does not meet requirements.'): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::Rejected,
            'reviewed_by' => $reviewer?->id ?? User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
