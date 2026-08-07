<?php

namespace App\Actions;

use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Jobs\AnalyzeSubmissionJob;
use App\Models\Submission;
use App\Support\S3Uri;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class StoreSubmissionAction
{
    /**
     * @param  array{
     *     title: string,
     *     description?: ?string,
     *     submitter_name: string,
     *     submitter_email: string,
     *     source: SubmissionSource|string,
     *     file?: UploadedFile|null,
     *     s3_uri?: string|null
     * }  $data
     */
    public function handle(array $data): Submission
    {
        $source = $data['source'] instanceof SubmissionSource
            ? $data['source']
            : SubmissionSource::from((string) $data['source']);

        $submission = match ($source) {
            SubmissionSource::Upload => $this->storeUpload($data),
            SubmissionSource::S3Uri => $this->storeS3Uri($data),
        };

        AnalyzeSubmissionJob::dispatch($submission);

        Log::info('submission.store.job_dispatched', [
            'submission_id' => $submission->id,
            'source' => $submission->source->value,
            'queue_connection' => config('queue.default'),
        ]);

        return $submission;
    }

    /**
     * @param  array{title: string, description?: ?string, submitter_name: string, submitter_email: string, file?: UploadedFile|null}  $data
     */
    private function storeUpload(array $data): Submission
    {
        /** @var UploadedFile $file */
        $file = $data['file'];
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $type = SubmissionType::fromUploadedFile($file);

        if ($type === null) {
            throw new InvalidArgumentException('Unsupported file type.');
        }

        $disk = (string) config('submissions.disk', 'local');

        if ($disk === 's3' && blank(config('filesystems.disks.s3.bucket'))) {
            Log::error('submission.store.s3_bucket_missing', [
                'disk' => $disk,
            ]);

            throw new InvalidArgumentException('AWS_BUCKET must be set when SUBMISSIONS_DISK=s3.');
        }

        try {
            $path = $file->store('submissions', $disk);
        } catch (Throwable $e) {
            Log::error('submission.store.upload_failed', [
                'disk' => $disk,
                'bucket' => $disk === 's3' ? config('filesystems.disks.s3.bucket') : null,
                'region' => config('filesystems.disks.s3.region'),
                'original_filename' => $file->getClientOriginalName(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new InvalidArgumentException('Unable to store uploaded file: '.$e->getMessage(), 0, $e);
        }

        if ($path === false) {
            Log::error('submission.store.upload_returned_false', [
                'disk' => $disk,
                'original_filename' => $file->getClientOriginalName(),
            ]);

            throw new InvalidArgumentException('Unable to store uploaded file.');
        }

        $submission = Submission::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'submitter_name' => $data['submitter_name'],
            'submitter_email' => $data['submitter_email'],
            'original_filename' => $file->getClientOriginalName(),
            'disk_path' => $path,
            'disk' => $disk,
            'source' => SubmissionSource::Upload,
            'mime_type' => $mime ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'type' => $type,
            'status' => SubmissionStatus::Pending,
        ]);

        Log::info('submission.store.success', [
            'submission_id' => $submission->id,
            'source' => SubmissionSource::Upload->value,
            'disk' => $disk,
            'disk_path' => $path,
            'bucket' => $disk === 's3' ? config('filesystems.disks.s3.bucket') : null,
            'type' => $type->value,
            'size' => $submission->size,
            'absolute_hint' => $disk === 'local'
                ? storage_path('app/private/'.$path)
                : null,
        ]);

        return $submission;
    }

    /**
     * @param  array{title: string, description?: ?string, submitter_name: string, submitter_email: string, s3_uri?: string|null}  $data
     */
    private function storeS3Uri(array $data): Submission
    {
        $configuredBucket = (string) config('filesystems.disks.s3.bucket');

        if ($configuredBucket === '') {
            throw new InvalidArgumentException('AWS_BUCKET must be set to submit an S3 URI.');
        }

        $uri = S3Uri::parse((string) ($data['s3_uri'] ?? ''));

        if ($uri->bucket !== $configuredBucket) {
            throw new InvalidArgumentException("The S3 URI bucket must be \"{$configuredBucket}\".");
        }

        $type = SubmissionType::fromExtension($uri->extension());

        if ($type === null) {
            throw new InvalidArgumentException('Unsupported file type.');
        }

        $size = 0;

        try {
            $size = (int) Storage::disk('s3')->size($uri->key);
        } catch (Throwable) {
            $size = 0;
        }

        $mimeTypes = MimeTypes::getDefault()->getMimeTypes($uri->extension());
        $mime = $mimeTypes[0] ?? 'application/octet-stream';

        $submission = Submission::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'submitter_name' => $data['submitter_name'],
            'submitter_email' => $data['submitter_email'],
            'original_filename' => $uri->filename(),
            'disk_path' => $uri->key,
            'disk' => 's3',
            'source' => SubmissionSource::S3Uri,
            'mime_type' => $mime,
            'size' => $size,
            'type' => $type,
            'status' => SubmissionStatus::Pending,
        ]);

        Log::info('submission.store.success', [
            'submission_id' => $submission->id,
            'source' => SubmissionSource::S3Uri->value,
            'disk' => 's3',
            'disk_path' => $uri->key,
            'bucket' => $configuredBucket,
            's3_uri' => $submission->s3Uri(),
            'type' => $type->value,
            'size' => $submission->size,
        ]);

        return $submission;
    }
}
