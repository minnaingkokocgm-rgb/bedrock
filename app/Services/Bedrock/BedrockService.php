<?php

namespace App\Services\Bedrock;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use RuntimeException;

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

        try {
            $response = $this->client->converse($payload);
        } catch (AwsException $e) {
            throw new RuntimeException(
                "Failed to invoke Bedrock model [{$modelId}]: {$e->getAwsErrorMessage()}",
                0,
                $e,
            );
        }

        $text = $response['output']['message']['content'][0]['text'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new RuntimeException("Bedrock model [{$modelId}] returned an empty response.");
        }

        return $text;
    }
}
