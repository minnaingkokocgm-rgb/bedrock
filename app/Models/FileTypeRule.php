<?php

namespace App\Models;

use App\Enums\SubmissionType;
use Database\Factories\FileTypeRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'rules', 'updated_by'])]
class FileTypeRule extends Model
{
    /** @use HasFactory<FileTypeRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SubmissionType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function forType(SubmissionType $type): ?self
    {
        return static::query()->where('type', $type->value)->first();
    }
}
