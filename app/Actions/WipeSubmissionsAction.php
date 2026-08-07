<?php

namespace App\Actions;

use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WipeSubmissionsAction
{
    /**
     * Delete submission files from their storage disks, then remove DB rows
     * (AI advice cascades via FK).
     *
     * @param  iterable<int, Submission>|null  $submissions  null = wipe all
     * @return array{deleted_records: int, deleted_files: int, missing_files: int, failed_files: int}
     */
    public function handle(?iterable $submissions = null): array
    {
        $stats = [
            'deleted_records' => 0,
            'deleted_files' => 0,
            'missing_files' => 0,
            'failed_files' => 0,
        ];

        $process = function (Submission $submission) use (&$stats): void {
            $fileResult = $this->deleteStoredFile($submission);

            match ($fileResult) {
                'deleted' => $stats['deleted_files']++,
                'missing' => $stats['missing_files']++,
                'failed' => $stats['failed_files']++,
            };

            $submission->delete();
            $stats['deleted_records']++;
        };

        if ($submissions === null) {
            Submission::query()
                ->orderBy('id')
                ->chunkById(50, function ($chunk) use ($process): void {
                    foreach ($chunk as $submission) {
                        $process($submission);
                    }
                });
        } else {
            foreach ($submissions as $submission) {
                $process($submission);
            }
        }

        Log::info('submissions.wipe.completed', $stats);

        return $stats;
    }

    /**
     * @return 'deleted'|'missing'|'failed'
     */
    private function deleteStoredFile(Submission $submission): string
    {
        if ($submission->source !== null && ! $submission->source->ownsStoredFile()) {
            Log::info('submissions.wipe.file_skipped_external_uri', [
                'submission_id' => $submission->id,
                'disk' => $submission->disk,
                'disk_path' => $submission->disk_path,
                's3_uri' => $submission->s3Uri(),
            ]);

            return 'missing';
        }

        if (! filled($submission->disk) || ! filled($submission->disk_path)) {
            return 'missing';
        }

        try {
            $disk = Storage::disk($submission->disk);

            if (! $disk->exists($submission->disk_path)) {
                Log::warning('submissions.wipe.file_missing', [
                    'submission_id' => $submission->id,
                    'disk' => $submission->disk,
                    'disk_path' => $submission->disk_path,
                ]);

                return 'missing';
            }

            $disk->delete($submission->disk_path);

            Log::info('submissions.wipe.file_deleted', [
                'submission_id' => $submission->id,
                'disk' => $submission->disk,
                'disk_path' => $submission->disk_path,
                's3_uri' => $submission->s3Uri(),
            ]);

            return 'deleted';
        } catch (Throwable $e) {
            Log::error('submissions.wipe.file_failed', [
                'submission_id' => $submission->id,
                'disk' => $submission->disk,
                'disk_path' => $submission->disk_path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }
}
