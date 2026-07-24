<?php

use App\Enums\ExtractionStatus;
use App\Models\Submission;
use App\Services\ContentExtractor;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('extracts text from txt files', function () {
    Storage::disk('local')->put('submissions/note.txt', "Hello portal\n");

    $submission = new Submission([
        'original_filename' => 'note.txt',
        'disk_path' => 'submissions/note.txt',
    ]);

    $result = app(ContentExtractor::class)->extract($submission);

    expect($result['status'])->toBe(ExtractionStatus::Extracted)
        ->and($result['content'])->toBe('Hello portal')
        ->and($result['error'])->toBeNull();
});

it('extracts text from csv files', function () {
    Storage::disk('local')->put('submissions/data.csv', "name,role\nAda,Engineer\n");

    $submission = new Submission([
        'original_filename' => 'data.csv',
        'disk_path' => 'submissions/data.csv',
    ]);

    $result = app(ContentExtractor::class)->extract($submission);

    expect($result['status'])->toBe(ExtractionStatus::Extracted)
        ->and($result['content'])->toContain('Ada,Engineer');
});

it('marks unsupported extensions without extracting', function () {
    Storage::disk('local')->put('submissions/clip.mp4', 'binary');

    $submission = new Submission([
        'original_filename' => 'clip.mp4',
        'disk_path' => 'submissions/clip.mp4',
    ]);

    $result = app(ContentExtractor::class)->extract($submission);

    expect($result['status'])->toBe(ExtractionStatus::Unsupported)
        ->and($result['content'])->toBeNull();
});
