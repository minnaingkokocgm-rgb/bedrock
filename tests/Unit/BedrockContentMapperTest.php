<?php

use App\Enums\SubmissionType;
use App\Models\Submission;
use App\Services\BedrockContentMapper;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

it('detects nova models as supporting s3 location', function (string $modelId, bool $expected) {
    expect(app(BedrockContentMapper::class)->supportsS3Location($modelId))->toBe($expected);
})->with([
    ['global.amazon.nova-2-lite-v1:0', true],
    ['amazon.nova-pro-v1:0', true],
    ['global.anthropic.claude-sonnet-5', false],
    ['anthropic.claude-sonnet-4-20250514-v1:0', false],
]);

it('builds an s3 location block for nova', function () {
    config([
        'filesystems.disks.s3.bucket' => 'portal-uploads',
        'services.bedrock.model_id' => 'global.amazon.nova-2-lite-v1:0',
    ]);

    $submission = new Submission([
        'original_filename' => 'brief.docx',
        'disk_path' => 'submissions/brief.docx',
        'disk' => 's3',
        'type' => SubmissionType::Document,
    ]);

    $block = app(BedrockContentMapper::class)->contentBlock($submission);

    expect($block['document']['format'])->toBe('docx')
        ->and($block['document']['source']['s3Location']['uri'])
        ->toBe('s3://portal-uploads/submissions/brief.docx');
});

it('builds a bytes block for non-nova models', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('submissions/brief.docx', 'docx-binary');

    config([
        'filesystems.disks.s3.bucket' => 'portal-uploads',
        'services.bedrock.model_id' => 'global.anthropic.claude-sonnet-5',
    ]);

    $submission = new Submission([
        'original_filename' => 'brief.docx',
        'disk_path' => 'submissions/brief.docx',
        'disk' => 's3',
        'type' => SubmissionType::Document,
    ]);

    $block = app(BedrockContentMapper::class)->contentBlock($submission);

    expect($block['document']['format'])->toBe('docx')
        ->and($block['document']['source']['bytes'])->toBe('docx-binary')
        ->and($block['document']['source'])->not->toHaveKey('s3Location');
});

it('does not attach video bytes for non-nova models', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('submissions/clip.mp4', 'video-binary');

    config([
        'filesystems.disks.s3.bucket' => 'portal-uploads',
        'services.bedrock.model_id' => 'global.anthropic.claude-sonnet-5',
    ]);

    $submission = new Submission([
        'original_filename' => 'clip.mp4',
        'disk_path' => 'submissions/clip.mp4',
        'disk' => 's3',
        'type' => SubmissionType::Video,
    ]);

    expect(app(BedrockContentMapper::class)->contentBlock($submission))->toBeNull();
});
