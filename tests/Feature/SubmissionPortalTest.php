<?php

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('shows the public submission form', function () {
    $this->get(route('submissions.create'))
        ->assertSuccessful()
        ->assertSee('Submit a file');
});

it('accepts a document submission as pending', function () {
    Storage::fake('local');
    config(['submissions.disk' => 'local']);

    $file = UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf');

    $this->post(route('submissions.store'), [
        'title' => 'Project brief',
        'description' => 'Q3 outline',
        'submitter_name' => 'Ada Lovelace',
        'submitter_email' => 'ada@example.com',
        'file' => $file,
    ])->assertRedirect(route('submissions.thanks'));

    $submission = Submission::query()->first();

    expect($submission)->not->toBeNull()
        ->and($submission->title)->toBe('Project brief')
        ->and($submission->status)->toBe(SubmissionStatus::Pending)
        ->and($submission->type)->toBe(SubmissionType::Document)
        ->and($submission->original_filename)->toBe('brief.pdf');

    Storage::disk('local')->assertExists($submission->disk_path);
});

it('accepts image, document, and video submissions', function (string $name, string $mime, SubmissionType $type) {
    Storage::fake('local');
    config(['submissions.disk' => 'local']);

    $file = UploadedFile::fake()->create($name, 500, $mime);

    $this->post(route('submissions.store'), [
        'title' => 'Media upload',
        'submitter_name' => 'Ada Lovelace',
        'submitter_email' => 'ada@example.com',
        'file' => $file,
    ])->assertRedirect(route('submissions.thanks'));

    expect(Submission::query()->first()->type)->toBe($type);
})->with([
    'jpg' => ['photo.jpg', 'image/jpeg', SubmissionType::Image],
    'svg' => ['icon.svg', 'image/svg+xml', SubmissionType::Image],
    'psd' => ['design.psd', 'image/vnd.adobe.photoshop', SubmissionType::Image],
    'xlsx' => ['sheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', SubmissionType::Document],
    'csv' => ['data.csv', 'text/csv', SubmissionType::Document],
    'pptx' => ['deck.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', SubmissionType::Document],
    'mp4' => ['clip.mp4', 'video/mp4', SubmissionType::Video],
    'mkv' => ['movie.mkv', 'video/x-matroska', SubmissionType::Video],
    'wmv' => ['clip.wmv', 'video/x-ms-asf', SubmissionType::Video],
    'avi' => ['clip.avi', 'video/x-msvideo', SubmissionType::Video],
]);
