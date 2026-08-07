<?php

use App\Enums\AdviceStatus;
use App\Models\Submission;
use App\Models\SubmissionAiAdvice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('redirects guests away from wipe submissions', function () {
    $this->get(route('admin.wipe.show'))
        ->assertRedirect(route('login'));
});

it('shows wipe page totals for authenticated reviewers', function () {
    Storage::fake('s3');
    Storage::fake('local');

    Submission::factory()->count(2)->create(['disk' => 's3']);
    Submission::factory()->create(['disk' => 'local']);

    $reviewer = User::factory()->create();

    $this->actingAs($reviewer)
        ->get(route('admin.wipe.show'))
        ->assertSuccessful()
        ->assertSee('Wipe submissions')
        ->assertSee('3');
});

it('requires WIPE confirmation before deleting', function () {
    $reviewer = User::factory()->create();
    Submission::factory()->create();

    $this->actingAs($reviewer)
        ->delete(route('admin.wipe.destroy'), [
            'confirmation' => 'DELETE',
        ])
        ->assertSessionHasErrors('confirmation');

    expect(Submission::query()->count())->toBe(1);
});

it('wipes submission records and stored files from s3 and local', function () {
    Storage::fake('s3');
    Storage::fake('local');

    Storage::disk('s3')->put('submissions/a.mov', 'video-bytes');
    Storage::disk('local')->put('submissions/b.txt', 'text-bytes');

    $s3Submission = Submission::factory()->create([
        'disk' => 's3',
        'disk_path' => 'submissions/a.mov',
        'original_filename' => 'a.mov',
    ]);
    $localSubmission = Submission::factory()->create([
        'disk' => 'local',
        'disk_path' => 'submissions/b.txt',
        'original_filename' => 'b.txt',
    ]);

    SubmissionAiAdvice::factory()->create([
        'submission_id' => $s3Submission->id,
        'status' => AdviceStatus::Completed,
    ]);

    $reviewer = User::factory()->create();

    $this->actingAs($reviewer)
        ->delete(route('admin.wipe.destroy'), [
            'confirmation' => 'WIPE',
        ])
        ->assertRedirect(route('admin.wipe.show'))
        ->assertSessionHas('status');

    expect(Submission::query()->count())->toBe(0)
        ->and(SubmissionAiAdvice::query()->count())->toBe(0);

    Storage::disk('s3')->assertMissing('submissions/a.mov');
    Storage::disk('local')->assertMissing('submissions/b.txt');
});

it('still removes db rows when a stored file is already missing', function () {
    Storage::fake('s3');

    Submission::factory()->create([
        'disk' => 's3',
        'disk_path' => 'submissions/gone.mov',
    ]);

    $reviewer = User::factory()->create();

    $this->actingAs($reviewer)
        ->delete(route('admin.wipe.destroy'), [
            'confirmation' => 'WIPE',
        ])
        ->assertRedirect(route('admin.wipe.show'));

    expect(Submission::query()->count())->toBe(0);
});

it('wipes s3 uri submission records without deleting the referenced object', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.bucket' => 'portal-uploads']);

    Storage::disk('s3')->put('existing/shared.pdf', 'shared-bytes');

    Submission::factory()->fromS3Uri()->create([
        'disk_path' => 'existing/shared.pdf',
        'original_filename' => 'shared.pdf',
    ]);

    $reviewer = User::factory()->create();

    $this->actingAs($reviewer)
        ->delete(route('admin.wipe.destroy'), [
            'confirmation' => 'WIPE',
        ])
        ->assertRedirect(route('admin.wipe.show'));

    expect(Submission::query()->count())->toBe(0);
    Storage::disk('s3')->assertExists('existing/shared.pdf');
});
