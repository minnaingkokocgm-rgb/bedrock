<?php

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects guests away from the review queue', function () {
    $this->get(route('review.submissions.index'))
        ->assertRedirect(route('login'));
});

it('lets a reviewer approve a pending submission', function () {
    $reviewer = User::factory()->create();
    $submission = Submission::factory()->create();

    $this->actingAs($reviewer)
        ->post(route('review.submissions.approve', $submission))
        ->assertRedirect(route('review.submissions.show', $submission));

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Approved)
        ->and($submission->reviewed_by)->toBe($reviewer->id)
        ->and($submission->reviewed_at)->not->toBeNull();
});

it('requires a rejection reason', function () {
    $reviewer = User::factory()->create();
    $submission = Submission::factory()->create();

    $this->actingAs($reviewer)
        ->from(route('review.submissions.show', $submission))
        ->post(route('review.submissions.reject', $submission), [])
        ->assertRedirect(route('review.submissions.show', $submission))
        ->assertSessionHasErrors('rejection_reason');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Pending);
});

it('lets a reviewer reject a pending submission', function () {
    $reviewer = User::factory()->create();
    $submission = Submission::factory()->create();

    $this->actingAs($reviewer)
        ->post(route('review.submissions.reject', $submission), [
            'rejection_reason' => 'Blurry image.',
        ])
        ->assertRedirect(route('review.submissions.show', $submission));

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Rejected)
        ->and($submission->rejection_reason)->toBe('Blurry image.')
        ->and($submission->reviewed_by)->toBe($reviewer->id);
});

it('does not allow approving an already reviewed submission', function () {
    $reviewer = User::factory()->create();
    $submission = Submission::factory()->approved($reviewer)->create();

    $this->actingAs($reviewer)
        ->post(route('review.submissions.approve', $submission))
        ->assertForbidden();
});

it('lets a reviewer download the stored file', function () {
    Storage::fake('local');

    $reviewer = User::factory()->create();
    $path = 'submissions/brief.pdf';
    Storage::disk('local')->put($path, 'pdf-bytes');

    $submission = Submission::factory()->create([
        'disk' => 'local',
        'disk_path' => $path,
        'original_filename' => 'brief.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($reviewer)
        ->get(route('review.submissions.download', $submission))
        ->assertSuccessful()
        ->assertDownload('brief.pdf');
});
