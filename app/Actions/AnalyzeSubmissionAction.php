<?php

namespace App\Actions;

use App\Enums\AdviceStatus;
use App\Enums\AiVerdict;
use App\Enums\ExtractionStatus;
use App\Enums\SubmissionType;
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

            $fileSource = $this->contentMapper->supportsS3Location($modelId) ? 's3Location' : 'bytes';
            $fileBlock = $this->contentMapper->contentBlock($submission, $modelId);

            if ($fileBlock !== null) {
                Log::info('submission.analyze.mode_file_converse', [
                    'submission_id' => $submission->id,
                    's3_uri' => $submission->s3Uri(),
                    'file_source' => $fileSource,
                    'file_block_keys' => array_keys($fileBlock),
                    'model_id' => $modelId,
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
                    'mode' => $fileSource,
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
                    'file_source' => $fileSource,
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
                        $fileBlock,
                    ],
                    systemPrompt: $systemPrompt,
                );
            } else {
                if ($submission->usesS3()) {
                    Log::warning('submission.analyze.file_block_unavailable', [
                        'submission_id' => $submission->id,
                        's3_uri' => $submission->s3Uri(),
                        'type' => $submission->type->value,
                        'original_filename' => $submission->original_filename,
                        'file_source' => $fileSource,
                        'model_id' => $modelId,
                        'hint' => 'Falling back to text-only extraction; extension may be unsupported for Converse file attachment.',
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

            [$verdict, $reason, $storedRaw] = $this->resolveVerdict($raw);

            $advice->update([
                'ai_verdict' => $verdict,
                'ai_reason' => $reason,
                'ai_raw_response' => $storedRaw,
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

        $evidenceHint = match ($submission->type) {
            SubmissionType::Document => 'In your reason, quote a short snippet from the document (heading, sentence, or cell value).',
            SubmissionType::Image => 'In your reason, briefly describe what you see in the image (subject, scene, or any readable text).',
            SubmissionType::Video => 'In your reason, briefly describe key visual scenes or on-screen text. Do not claim you heard audio unless available.',
        };

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
- Base your verdict on the file content itself (attached file and/or extracted text), not filename alone.
- Do NOT reject only because the file is small or short.
- If content is present (even brief), evaluate that content.
- {$evidenceHint}

MESSAGE;

        if ($requireAttachedFileReview) {
            $message .= match ($submission->type) {
                SubmissionType::Document => "An original document is attached. Review it together with any extracted text below.\n\n",
                SubmissionType::Image => "An original image is attached. Visually inspect it; do not rely on metadata alone.\n\n",
                SubmissionType::Video => "An original video is attached. Visually inspect the frames/scenes; do not rely on metadata alone.\n\n",
            };
        }

        if ($includeContentBody) {
            $content = $extraction['content'] ?? '';
            $contentLimit = (int) config('services.bedrock.content_char_limit', 500_000);
            if ($contentLimit > 0 && mb_strlen($content) > $contentLimit) {
                $content = mb_substr($content, 0, $contentLimit)."\n...[truncated]";
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

        return $message.<<<'TAIL'
CRITICAL OUTPUT FORMAT:
- Reply with ONLY one JSON object. No markdown. No code fences. No prose before or after.
- Put any file description inside the "reason" field (1-3 short sentences max).
- Exact shape: {"verdict":"accept"|"reject"|"inconclusive","reason":"..."}
TAIL;
    }

    /**
     * @return array{0: AiVerdict, 1: string, 2: string}
     */
    private function resolveVerdict(string $raw): array
    {
        $decoded = $this->decodeVerdictJson($raw);

        if ($decoded !== null) {
            return [...$this->verdictFromDecoded($decoded), $raw];
        }

        Log::warning('submission.analyze.json_repair_attempt', [
            'raw_length' => mb_strlen($raw),
            'raw_preview' => mb_substr($raw, 0, 240),
        ]);

        try {
            $repaired = $this->bedrock->converse(
                $this->buildJsonRepairPrompt($raw),
                inferenceConfig: [
                    'maxTokens' => 512,
                    'temperature' => 0.0,
                ],
                systemPrompt: 'You convert content reviews into JSON. Reply with ONLY valid JSON. No markdown.',
            );

            $decoded = $this->decodeVerdictJson($repaired);

            if ($decoded !== null) {
                Log::info('submission.analyze.json_repair_success', [
                    'repaired_length' => mb_strlen($repaired),
                ]);

                return [...$this->verdictFromDecoded($decoded), $raw."\n\n--- JSON repair ---\n\n".$repaired];
            }
        } catch (Throwable $e) {
            Log::warning('submission.analyze.json_repair_failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return [AiVerdict::Inconclusive, 'Model response was not valid JSON.', $raw];
    }

    private function buildJsonRepairPrompt(string $raw): string
    {
        return <<<PROMPT
Convert the following content review into ONLY this JSON object (no markdown, no extra text):
{"verdict":"accept"|"reject"|"inconclusive","reason":"1-3 concise sentences"}

Verdict guide:
- accept: review describes real, usable, non-abusive content
- reject: empty, corrupt, spam, abusive, or clearly deceptive content
- inconclusive: review is too ambiguous to decide

Review text:
{$raw}
PROMPT;
    }

    /**
     * @return array{verdict: string, reason: string}|null
     */
    private function decodeVerdictJson(string $raw): ?array
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $fenced) === 1) {
            $trimmed = trim($fenced[1]);
        }

        $decoded = json_decode($trimmed, true);
        if ($this->isVerdictPayload($decoded)) {
            /** @var array{verdict: mixed, reason?: mixed} $decoded */
            return $decoded;
        }

        if (preg_match('/\{[^{}]*"verdict"\s*:\s*"[^"]+"[^{}]*\}/s', $trimmed, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if ($this->isVerdictPayload($decoded)) {
                /** @var array{verdict: mixed, reason?: mixed} $decoded */
                return $decoded;
            }
        }

        // Fallback for reasons that contain nested braces/quotes noise: take outermost object.
        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);
            if ($this->isVerdictPayload($decoded)) {
                /** @var array{verdict: mixed, reason?: mixed} $decoded */
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array{0: AiVerdict, 1: string}
     */
    private function verdictFromDecoded(array $decoded): array
    {
        $verdict = AiVerdict::tryFrom(strtolower((string) ($decoded['verdict'] ?? '')))
            ?? AiVerdict::Inconclusive;

        $reason = trim((string) ($decoded['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'No reason provided by the model.';
        }

        return [$verdict, $reason];
    }

    private function isVerdictPayload(mixed $decoded): bool
    {
        return is_array($decoded) && array_key_exists('verdict', $decoded);
    }
}
