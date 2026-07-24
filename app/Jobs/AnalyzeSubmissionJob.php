<?php

namespace App\Jobs;

use App\Actions\AnalyzeSubmissionAction;
use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeSubmissionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission) {}

    public function handle(AnalyzeSubmissionAction $analyze): void
    {
        Log::info('submission.job.start', [
            'submission_id' => $this->submission->id,
            'disk' => $this->submission->disk,
            'disk_path' => $this->submission->disk_path,
        ]);

        try {
            $advice = $analyze->handle($this->submission);

            Log::info('submission.job.finished', [
                'submission_id' => $this->submission->id,
                'advice_id' => $advice->id,
                'advice_status' => $advice->status->value,
                'extraction_status' => $advice->extraction_status?->value,
                'extraction_error' => $advice->extraction_error,
                'verdict' => $advice->ai_verdict?->value,
            ]);
        } catch (Throwable $e) {
            Log::error('submission.job.exception', [
                'submission_id' => $this->submission->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
