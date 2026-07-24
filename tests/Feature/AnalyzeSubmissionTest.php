<?php

use App\Actions\AnalyzeSubmissionAction;
use App\Enums\AdviceStatus;
use App\Enums\AiVerdict;
use App\Enums\SubmissionStatus;
use App\Jobs\AnalyzeSubmissionJob;
use App\Models\AiSetting;
use App\Models\FileTypeRule;
use App\Models\Submission;
use App\Models\User;
use App\Services\Bedrock\BedrockService;
use Database\Seeders\AiSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AiSettingsSeeder::class);
    config([
        'services.bedrock.model_id' => 'global.amazon.nova-2-lite-v1:0',
        'services.bedrock.max_tokens' => 512,
        'services.bedrock.temperature' => 0.5,
    ]);
});

it('dispatches an analysis job when a submission is stored', function () {
    Queue::fake();
    Storage::fake('local');

    $file = UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf');

    $this->post(route('submissions.store'), [
        'title' => 'Project brief',
        'submitter_name' => 'Ada Lovelace',
        'submitter_email' => 'ada@example.com',
        'file' => $file,
    ])->assertRedirect(route('submissions.thanks'));

    Queue::assertPushed(AnalyzeSubmissionJob::class);
    expect(Submission::query()->first()->status)->toBe(SubmissionStatus::Pending);
});

it('stores completed ai advice without changing submission status', function () {
    Storage::fake('local');
    Storage::disk('local')->put('submissions/note.txt', 'This is a clean policy document.');

    $submission = Submission::factory()->create([
        'original_filename' => 'note.txt',
        'disk_path' => 'submissions/note.txt',
        'mime_type' => 'text/plain',
        'status' => SubmissionStatus::Pending,
    ]);

    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('converse')
            ->once()
            ->andReturn('{"verdict":"accept","reason":"Content looks legitimate."}');
    });

    $advice = app(AnalyzeSubmissionAction::class)->handle($submission);

    expect($advice->status)->toBe(AdviceStatus::Completed)
        ->and($advice->ai_verdict)->toBe(AiVerdict::Accept)
        ->and($advice->ai_reason)->toBe('Content looks legitimate.')
        ->and($advice->extracted_content)->toContain('clean policy document')
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::Pending);
});

it('shows ai advice on the review page', function () {
    $reviewer = User::factory()->create();
    $submission = Submission::factory()->create();
    $submission->aiAdvice()->create([
        'extracted_content' => 'text',
        'extraction_status' => 'extracted',
        'system_prompt_snapshot' => AiSetting::current()->system_prompt,
        'type_rules_snapshot' => FileTypeRule::query()->first()->rules,
        'model_id' => 'global.amazon.nova-2-lite-v1:0',
        'ai_verdict' => AiVerdict::Reject,
        'ai_reason' => 'Looks incomplete.',
        'ai_raw_response' => '{"verdict":"reject","reason":"Looks incomplete."}',
        'status' => AdviceStatus::Completed,
        'analyzed_at' => now(),
    ]);

    $this->actingAs($reviewer)
        ->get(route('review.submissions.show', $submission))
        ->assertSuccessful()
        ->assertSee('AI recommendation')
        ->assertSee('Recommend reject')
        ->assertSee('Looks incomplete.');
});

it('passes an s3 location to bedrock for s3-backed submissions', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put(
        'submissions/employee-onboarding-checklist.txt',
        "Employee Onboarding Checklist\nComplete HR paperwork.",
    );

    config([
        'filesystems.disks.s3.bucket' => 'portal-uploads',
        'submissions.disk' => 's3',
    ]);

    $submission = Submission::factory()->create([
        'original_filename' => 'employee-onboarding-checklist.txt',
        'disk_path' => 'submissions/employee-onboarding-checklist.txt',
        'disk' => 's3',
        'mime_type' => 'text/plain',
        'status' => SubmissionStatus::Pending,
    ]);

    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('converseContent')
            ->once()
            ->withArgs(function (array $content): bool {
                $text = $content[0]['text'] ?? '';

                return str_contains($text, 'Employee Onboarding Checklist')
                    && ($content[1]['document']['source']['s3Location']['uri'] ?? null)
                    === 's3://portal-uploads/submissions/employee-onboarding-checklist.txt';
            })
            ->andReturn('{"verdict":"accept","reason":"Content includes Employee Onboarding Checklist."}');
    });

    $advice = app(AnalyzeSubmissionAction::class)->handle($submission);

    expect($advice->status)->toBe(AdviceStatus::Completed)
        ->and($advice->extraction_status->value)->toBe('s3_referenced')
        ->and($advice->extracted_content)->toContain('Employee Onboarding Checklist')
        ->and($advice->ai_verdict)->toBe(AiVerdict::Accept)
        ->and($submission->fresh()->status)->toBe(SubmissionStatus::Pending);
});
it('stores new uploads on s3 when configured', function () {
    Queue::fake();
    Storage::fake('s3');
    config([
        'submissions.disk' => 's3',
        'filesystems.disks.s3.bucket' => 'portal-uploads',
    ]);

    $file = UploadedFile::fake()->create('notes.txt', 20, 'text/plain');

    $this->post(route('submissions.store'), [
        'title' => 'Notes',
        'submitter_name' => 'Ada Lovelace',
        'submitter_email' => 'ada@example.com',
        'file' => $file,
    ])->assertRedirect(route('submissions.thanks'));

    $submission = Submission::query()->first();

    expect($submission->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($submission->disk_path);
});

it('records extraction failure when local stored file is missing', function () {
    Storage::fake('local');

    $submission = Submission::factory()->create([
        'original_filename' => 'missing.txt',
        'disk_path' => 'submissions/does-not-exist.txt',
        'disk' => 'local',
        'mime_type' => 'text/plain',
        'status' => SubmissionStatus::Pending,
    ]);

    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('converse')
            ->once()
            ->andReturn('{"verdict":"reject","reason":"No extracted text available — stored file was not found."}');
    });

    $advice = app(AnalyzeSubmissionAction::class)->handle($submission);

    expect($advice->status)->toBe(AdviceStatus::Completed)
        ->and($advice->extraction_status->value)->toBe('failed')
        ->and($advice->extraction_error)->toBe('Stored file was not found.')
        ->and($advice->ai_verdict)->toBe(AiVerdict::Reject);
});
