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
use Illuminate\Support\Facades\Log;
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
        $modelId = (string) config('services.bedrock.model_id');

        Log::info('submission.analyze.start', [
            'submission_id' => $submission->id,
            'disk' => $submission->disk,
            'disk_path' => $submission->disk_path,
            'type' => $submission->type->value,
            'original_filename' => $submission->original_filename,
            'model_id' => $modelId,
            'uses_s3' => $submission->usesS3(),
            's3_uri' => $submission->s3Uri(),
            'bedrock_region' => config('services.bedrock.region'),
        ]);

        $advice = SubmissionAiAdvice::query()->create([
            'submission_id' => $submission->id,
            'extraction_status' => ExtractionStatus::Unsupported,
            'system_prompt_snapshot' => '',
            'type_rules_snapshot' => '',
            'model_id' => $modelId,
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
                Log::info('submission.analyze.mode_s3_converse', [
                    'submission_id' => $submission->id,
                    's3_uri' => $submission->s3Uri(),
                    's3_block_keys' => array_keys($s3Block),
                ]);

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

                Log::info('submission.analyze.extraction_result', [
                    'submission_id' => $submission->id,
                    'mode' => 's3',
                    'extraction_status' => $textExtraction['status']->value,
                    'has_text_body' => $hasTextBody,
                    'extraction_error' => $textExtraction['error'],
                ]);

                $advice->fill([
                    'extracted_content' => $extraction['content'],
                    'extraction_status' => $extraction['status'],
                    'extraction_error' => $extraction['error'],
                    'system_prompt_snapshot' => $systemPrompt,
                    'type_rules_snapshot' => $typeRules,
                ]);
                $advice->save();

                Log::info('submission.analyze.bedrock_request', [
                    'submission_id' => $submission->id,
                    'mode' => 'converseContent',
                    'model_id' => $modelId,
                ]);

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
                if ($submission->usesS3()) {
                    Log::warning('submission.analyze.s3_block_unavailable', [
                        'submission_id' => $submission->id,
                        's3_uri' => $submission->s3Uri(),
                        'type' => $submission->type->value,
                        'original_filename' => $submission->original_filename,
                        'hint' => 'Falling back to text-only extraction; extension may be unsupported for Converse s3Location.',
                    ]);
                }

                $extraction = $this->extractor->extract($submission);

                Log::info('submission.analyze.extraction_result', [
                    'submission_id' => $submission->id,
                    'mode' => 'local_or_text',
                    'extraction_status' => $extraction['status']->value,
                    'extraction_error' => $extraction['error'],
                    'content_length' => is_string($extraction['content']) ? mb_strlen($extraction['content']) : 0,
                ]);

                $advice->fill([
                    'extracted_content' => $extraction['content'],
                    'extraction_status' => $extraction['status'],
                    'extraction_error' => $extraction['error'],
                    'system_prompt_snapshot' => $systemPrompt,
                    'type_rules_snapshot' => $typeRules,
                ]);
                $advice->save();

                Log::info('submission.analyze.bedrock_request', [
                    'submission_id' => $submission->id,
                    'mode' => 'converse',
                    'model_id' => $modelId,
                ]);

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

            Log::info('submission.analyze.completed', [
                'submission_id' => $submission->id,
                'advice_id' => $advice->id,
                'verdict' => $verdict->value,
                'extraction_status' => $advice->extraction_status?->value,
                'extraction_error' => $advice->extraction_error,
            ]);
        } catch (Throwable $e) {
            Log::error('submission.analyze.failed', [
                'submission_id' => $submission->id,
                'advice_id' => $advice->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'previous' => $e->getPrevious()?->getMessage(),
            ]);

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
