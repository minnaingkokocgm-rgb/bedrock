<?php

namespace App\Models;

use App\Enums\AdviceStatus;
use App\Enums\AiVerdict;
use App\Enums\ExtractionStatus;
use Database\Factories\SubmissionAiAdviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'submission_id',
    'extracted_content',
    'extraction_status',
    'extraction_error',
    'system_prompt_snapshot',
    'type_rules_snapshot',
    'model_id',
    'ai_verdict',
    'ai_reason',
    'ai_raw_response',
    'status',
    'analyzed_at',
])]
class SubmissionAiAdvice extends Model
{
    /** @use HasFactory<SubmissionAiAdviceFactory> */
    use HasFactory;

    protected $table = 'submission_ai_advices';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extraction_status' => ExtractionStatus::class,
            'ai_verdict' => AiVerdict::class,
            'status' => AdviceStatus::class,
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
