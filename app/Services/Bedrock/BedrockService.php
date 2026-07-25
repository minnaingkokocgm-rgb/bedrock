<?php

namespace App\Services\Bedrock;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Aws\Result;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BedrockService
{
    public function __construct(private BedrockRuntimeClient $client) {}

    /**
     * Send a single user message via Bedrock's Converse API.
     *
     * @param  array{maxTokens?: int, temperature?: float, topP?: float}  $inferenceConfig
     */
    public function converse(
        string $message,
        ?string $modelId = null,
        array $inferenceConfig = [],
        ?string $systemPrompt = null,
    ): string {
        return $this->converseMessages([
            [
                'role' => 'user',
                'content' => [['text' => $message]],
            ],
        ], $modelId, $inferenceConfig, $systemPrompt);
    }

    /**
     * @param  list<array<string, mixed>>  $content
     * @param  array{maxTokens?: int, temperature?: float, topP?: float}  $inferenceConfig
     */
    public function converseContent(
        array $content,
        ?string $modelId = null,
        array $inferenceConfig = [],
        ?string $systemPrompt = null,
    ): string {
        return $this->converseMessages([
            [
                'role' => 'user',
                'content' => $content,
            ],
        ], $modelId, $inferenceConfig, $systemPrompt);
    }

    /**
     * Send a multi-turn conversation via Bedrock's Converse API.
     *
     * @param  list<array{role: string, content: list<array<string, mixed>>}>  $messages
     * @param  array{maxTokens?: int, temperature?: float, topP?: float}  $inferenceConfig
     */
    public function converseMessages(
        array $messages,
        ?string $modelId = null,
        array $inferenceConfig = [],
        ?string $systemPrompt = null,
    ): string {
        $modelId ??= (string) config('services.bedrock.model_id');
        $region = (string) config('services.bedrock.region');

        $inferenceConfig = array_merge([
            'maxTokens' => (int) config('services.bedrock.max_tokens'),
            'temperature' => (float) config('services.bedrock.temperature'),
        ], $inferenceConfig);

        $payload = [
            'modelId' => $modelId,
            'messages' => $messages,
            'inferenceConfig' => $inferenceConfig,
        ];

        if (filled($systemPrompt)) {
            $payload['system'] = [
                ['text' => $systemPrompt],
            ];
        }

        Log::info('submission.bedrock.request', [
            'model_id' => $modelId,
            'region' => $region,
            'message_count' => count($messages),
            'has_system_prompt' => filled($systemPrompt),
        ]);

        try {
            $response = $this->client->converse($payload);
        } catch (AwsException $e) {
            Log::error('submission.bedrock.aws_error', [
                'model_id' => $modelId,
                'region' => $region,
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_type' => $e->getAwsErrorType(),
                'aws_error_message' => $e->getAwsErrorMessage(),
                'status_code' => $e->getStatusCode(),
            ]);

            $detail = $e->getAwsErrorMessage() ?: $e->getMessage();

            throw new RuntimeException(
                "Failed to invoke Bedrock model [{$modelId}]: {$detail}",
                0,
                $e,
            );
        } catch (Throwable $e) {
            Log::error('submission.bedrock.request_failed', [
                'model_id' => $modelId,
                'region' => $region,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Failed to invoke Bedrock model [{$modelId}]: {$e->getMessage()}",
                0,
                $e,
            );
        }

        $text = $this->extractText($response);

        if ($text === '') {
            Log::error('submission.bedrock.empty_response', [
                'model_id' => $modelId,
                'region' => $region,
                'stop_reason' => $response['stopReason'] ?? null,
            ]);

            throw new RuntimeException("Bedrock model [{$modelId}] returned an empty response.");
        }

        Log::info('submission.bedrock.success', [
            'model_id' => $modelId,
            'region' => $region,
            'response_length' => mb_strlen($text),
            'stop_reason' => $response['stopReason'] ?? null,
            'max_tokens' => $inferenceConfig['maxTokens'] ?? null,
        ]);

        return $text;
    }

    /**
     * @param  array<string, mixed>|Result  $response
     */
    private function extractText(array|Result $response): string
    {
        $payload = $response instanceof Result ? $response->toArray() : $response;
        $blocks = $payload['output']['message']['content'] ?? [];

        if (! is_array($blocks)) {
            return '';
        }

        $parts = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (isset($block['text']) && is_string($block['text']) && $block['text'] !== '') {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n", $parts));
    }
}
