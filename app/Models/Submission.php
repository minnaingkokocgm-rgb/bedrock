<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'title',
    'description',
    'submitter_name',
    'submitter_email',
    'original_filename',
    'disk_path',
    'disk',
    'mime_type',
    'size',
    'type',
    'status',
    'rejection_reason',
    'reviewed_by',
    'reviewed_at',
])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SubmissionType::class,
            'status' => SubmissionStatus::class,
            'size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasOne<SubmissionAiAdvice, $this>
     */
    public function aiAdvice(): HasOne
    {
        return $this->hasOne(SubmissionAiAdvice::class)->latestOfMany();
    }

    public function isPending(): bool
    {
        return $this->status === SubmissionStatus::Pending;
    }

    public function usesS3(): bool
    {
        return $this->disk === 's3';
    }

    public function s3Uri(): ?string
    {
        if (! $this->usesS3()) {
            return null;
        }

        $bucket = config('filesystems.disks.s3.bucket');

        if (! filled($bucket)) {
            return null;
        }

        return 's3://'.$bucket.'/'.ltrim($this->disk_path, '/');
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->disk_path);
    }
}
