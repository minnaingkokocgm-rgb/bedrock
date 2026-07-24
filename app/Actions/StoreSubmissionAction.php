<?php

namespace App\Actions;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Jobs\AnalyzeSubmissionJob;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

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
            throw new InvalidArgumentException('AWS_BUCKET must be set when SUBMISSIONS_DISK=s3.');
        }

        $path = $file->store('submissions', $disk);

        if ($path === false) {
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

        AnalyzeSubmissionJob::dispatch($submission);

        return $submission;
    }
}
