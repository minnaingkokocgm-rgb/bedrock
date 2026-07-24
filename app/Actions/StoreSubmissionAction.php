<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Jobs\AnalyzeSubmissionJob;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class StoreSubmissionAction
{
    /**
     * @param  array{title: string, description?: ?string, submitter_name: string, submitter_email: string, file: UploadedFile}  $data
     */
    public function handle(array $data): Submission
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
            'mime_type' => $mime,
            'size' => $file->getSize() ?: 0,
            'type' => $type,
            'status' => SubmissionStatus::Pending,
        ]);

        Log::info('submission.store.success', [
            'submission_id' => $submission->id,
            'disk' => $disk,
            'disk_path' => $path,
            'bucket' => $disk === 's3' ? config('filesystems.disks.s3.bucket') : null,
            'type' => $type->value,
            'size' => $submission->size,
            'absolute_hint' => $disk === 'local'
                ? storage_path('app/private/'.$path)
                : null,
        ]);

        AnalyzeSubmissionJob::dispatch($submission);

        Log::info('submission.store.job_dispatched', [
            'submission_id' => $submission->id,
            'queue_connection' => config('queue.default'),
        ]);

        return $submission;
    }
}
