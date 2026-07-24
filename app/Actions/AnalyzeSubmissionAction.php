<?php

namespace App\Actions;

use App\Enums\AdviceStatus;
use App\Enums\AiVerdict;
use App\Enums\ExtractionStatus;
use App\Models\AiSetting;
use App\Models\FileTypeRule;
use App\Models\Submission;
use App\Models\SubmissionAiAdvice;
use App\Services\Bedrock\BedrockService;
use App\Services\BedrockContentMapper;
use App\Services\ContentExtractor;
use Throwable;

class AnalyzeSubmissionAction
{
    private const CONTENT_LIMIT = 40_000;

    public function __construct(
        private ContentExtractor $extractor,
        private BedrockService $bedrock,
        private BedrockContentMapper $contentMapper,
    ) {}

    public function handle(Submission $submission): SubmissionAiAdvice
    {
        $advice = SubmissionAiAdvice::query()->create([
            'submission_id' => $submission->id,
            'extraction_status' => ExtractionStatus::Unsupported,
            'system_prompt_snapshot' => '',
            'type_rules_snapshot' => '',
            'model_id' => (string) config('services.bedrock.model_id'),
            'status' => AdviceStatus::Pending,
        ]);

        try {
            $systemPrompt = AiSetting::current()->system_prompt;
            $typeRules = FileTypeRule::forType($submission->type)?->rules
                ?? 'No specific rules configured for this file type.';

            $s3Block = $submission->usesS3()
                ? $this->contentMapper->s3ContentBlock($submission)
                : null;

            if ($s3Block !== null) {
                $textExtraction = $this->extractor->extract($submission);
                $hasTextBody = $textExtraction['status'] === ExtractionStatus::Extracted
                    && filled($textExtraction['content']);

                $extraction = [
                    'status' => ExtractionStatus::S3Referenced,
                    'content' => $hasTextBody
                        ? $textExtraction['content']
                        : $submission->s3Uri(),
                    'error' => $textExtraction['error'],
                ];

                $advice->fill([
                    'extracted_content' => $extraction['content'],
                    'extraction_status' => $extraction['status'],
                    'extraction_error' => $extraction['error'],
                    'system_prompt_snapshot' => $systemPrompt,
                    'type_rules_snapshot' => $typeRules,
                ]);
                $advice->save();

                $raw = $this->bedrock->converseContent(
                    [
                        ['text' => $this->buildPromptText(
                            $submission,
                            $extraction,
                            includeContentBody: $hasTextBody,
                            requireAttachedFileReview: true,
                        )],
                        $s3Block,
                    ],
                    systemPrompt: $systemPrompt,
                );
            } else {
                $extraction = $this->extractor->extract($submission);

                $advice->fill([
                    'extracted_content' => $extraction['content'],
                    'extraction_status' => $extraction['status'],
                    'extraction_error' => $extraction['error'],
                    'system_prompt_snapshot' => $systemPrompt,
                    'type_rules_snapshot' => $typeRules,
                ]);
                $advice->save();

                $raw = $this->bedrock->converse(
                    $this->buildPromptText($submission, $extraction, includeContentBody: true),
                    systemPrompt: $systemPrompt,
                );
            }

            [$verdict, $reason] = $this->parseVerdict($raw);

            $advice->update([
                'ai_verdict' => $verdict,
                'ai_reason' => $reason,
                'ai_raw_response' => $raw,
                'status' => AdviceStatus::Completed,
                'analyzed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $advice->update([
                'status' => AdviceStatus::Failed,
                'ai_reason' => $e->getMessage(),
                'ai_raw_response' => $advice->ai_raw_response,
                'analyzed_at' => now(),
            ]);
        }

        return $advice->refresh();
    }

    /**
     * @param  array{status: ExtractionStatus, content: ?string, error: ?string}  $extraction
     */
    private function buildPromptText(
        Submission $submission,
        array $extraction,
        bool $includeContentBody,
        bool $requireAttachedFileReview = false,
    ): string {
        $typeRules = FileTypeRule::forType($submission->type)?->rules
            ?? 'No specific rules configured for this file type.';

        $message = <<<MESSAGE
File type rules for {$submission->type->value}:
{$typeRules}

Submission metadata:
- Title: {$submission->title}
- Description: {$submission->description}
- Submitter: {$submission->submitter_name} <{$submission->submitter_email}>
- Original filename: {$submission->original_filename}
- MIME type: {$submission->mime_type}
- Type: {$submission->type->value}
- Storage: {$submission->disk}

Important:
- Base your verdict on the file content itself.
- Do NOT reject only because the file is small.
- If content is present (even one short word), evaluate that content.
- In your reason, quote a short snippet of the actual file content you used.

MESSAGE;

        if ($requireAttachedFileReview) {
            $message .= "An original file is also attached from Amazon S3. Use it together with any extracted text below.\n\n";
        }

        if ($includeContentBody) {
            $content = $extraction['content'] ?? '';
            if (mb_strlen($content) > self::CONTENT_LIMIT) {
                $content = mb_substr($content, 0, self::CONTENT_LIMIT)."\n...[truncated]";
            }

            $contentSection = match ($extraction['status']) {
                ExtractionStatus::Extracted, ExtractionStatus::S3Referenced => $content,
                ExtractionStatus::Unsupported => '[No extracted text: unsupported file type for v1 extraction]',
                ExtractionStatus::Failed => '[No extracted text: extraction failed — '.($extraction['error'] ?? 'unknown error').']',
            };

            $message .= "File content:\n{$contentSection}\n\n";
        } elseif (! $requireAttachedFileReview) {
            $message .= match ($extraction['status']) {
                ExtractionStatus::Unsupported => "File content:\n[No extracted text: unsupported file type for v1 extraction]\n\n",
                ExtractionStatus::Failed => 'File content:'."\n[No extracted text: extraction failed — ".($extraction['error'] ?? 'unknown error')."]\n\n",
                default => '',
            };
        }

        return $message.'Return ONLY JSON: {"verdict":"accept"|"reject"|"inconclusive","reason":"..."}';
    }

    /**
     * @return array{0: AiVerdict, 1: string}
     */
    private function parseVerdict(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $raw, $matches) === 1) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (! is_array($decoded)) {
            return [AiVerdict::Inconclusive, 'Model response was not valid JSON.'];
        }

        $verdict = AiVerdict::tryFrom(strtolower((string) ($decoded['verdict'] ?? '')))
            ?? AiVerdict::Inconclusive;

        $reason = trim((string) ($decoded['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'No reason provided by the model.';
        }

        return [$verdict, $reason];
    }
}
