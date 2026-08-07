<?php

use App\Enums\SubmissionSource;
use App\Enums\SubmissionType;
use App\Jobs\AnalyzeSubmissionJob;
use App\Models\Submission;
use App\Models\User;
use App\Services\BedrockContentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Storage::fake('s3');
    config([
        'filesystems.disks.s3.bucket' => 'portal-uploads',
        'submissions.disk' => 's3',
        'services.bedrock.model_id' => 'global.amazon.nova-2-lite-v1:0',
    ]);
});

it('accepts an s3 uri submission without uploading a file', function () {
    Storage::disk('s3')->put('existing/brief.pdf', 'pdf-bytes');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('submissions.store'), [
            'title' => 'Existing brief',
            'description' => 'Already on S3',
            'submitter_name' => 'Ada Lovelace',
            'submitter_email' => 'ada@example.com',
            'source' => 's3_uri',
            's3_uri' => 's3://portal-uploads/existing/brief.pdf',
        ])
        ->assertRedirect(route('submissions.thanks'));

    $submission = Submission::query()->first();

    expect($submission)->not->toBeNull()
        ->and($submission->source)->toBe(SubmissionSource::S3Uri)
        ->and($submission->disk)->toBe('s3')
        ->and($submission->disk_path)->toBe('existing/brief.pdf')
        ->and($submission->original_filename)->toBe('brief.pdf')
        ->and($submission->type)->toBe(SubmissionType::Document)
        ->and($submission->s3Uri())->toBe('s3://portal-uploads/existing/brief.pdf');

    Queue::assertPushed(AnalyzeSubmissionJob::class);

    $block = app(BedrockContentMapper::class)->contentBlock($submission);
    expect($block)->toMatchArray([
        'document' => [
            'format' => 'pdf',
            'name' => 'brief',
            'source' => [
                's3Location' => [
                    'uri' => 's3://portal-uploads/existing/brief.pdf',
                ],
            ],
        ],
    ]);
});

it('rejects s3 uris from another bucket', function () {
    Storage::disk('s3')->put('existing/brief.pdf', 'pdf-bytes');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('submissions.create'))
        ->post(route('submissions.store'), [
            'title' => 'Wrong bucket',
            'submitter_name' => 'Ada Lovelace',
            'submitter_email' => 'ada@example.com',
            'source' => 's3_uri',
            's3_uri' => 's3://other-bucket/existing/brief.pdf',
        ])
        ->assertRedirect(route('submissions.create'))
        ->assertSessionHasErrors('s3_uri');

    expect(Submission::query()->count())->toBe(0);
});

it('rejects malformed s3 uris and unsupported extensions', function (string $uri) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('submissions.create'))
        ->post(route('submissions.store'), [
            'title' => 'Bad uri',
            'submitter_name' => 'Ada Lovelace',
            'submitter_email' => 'ada@example.com',
            'source' => 's3_uri',
            's3_uri' => $uri,
        ])
        ->assertRedirect(route('submissions.create'))
        ->assertSessionHasErrors('s3_uri');
})->with([
    'missing key' => ['s3://portal-uploads'],
    'not s3' => ['https://portal-uploads/brief.pdf'],
    'unsupported extension' => ['s3://portal-uploads/archive.zip'],
]);

it('rejects missing s3 objects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('submissions.create'))
        ->post(route('submissions.store'), [
            'title' => 'Missing object',
            'submitter_name' => 'Ada Lovelace',
            'submitter_email' => 'ada@example.com',
            'source' => 's3_uri',
            's3_uri' => 's3://portal-uploads/missing/brief.pdf',
        ])
        ->assertRedirect(route('submissions.create'))
        ->assertSessionHasErrors('s3_uri');
});
