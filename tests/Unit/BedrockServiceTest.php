<?php

use App\Services\Bedrock\BedrockService;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Aws\Result;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.bedrock.model_id' => 'amazon.nova-lite-v1:0',
        'services.bedrock.max_tokens' => 512,
        'services.bedrock.temperature' => 0.5,
    ]);
});

it('sends a message through the converse api', function () {
    $client = mock(BedrockRuntimeClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('converse')
            ->once()
            ->with([
                'modelId' => 'amazon.nova-lite-v1:0',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [['text' => 'Hello']],
                    ],
                ],
                'inferenceConfig' => [
                    'maxTokens' => 512,
                    'temperature' => 0.5,
                ],
            ])
            ->andReturn(new Result([
                'output' => [
                    'message' => [
                        'content' => [
                            ['text' => 'Hello from Bedrock'],
                        ],
                    ],
                ],
            ]));
    });

    $service = new BedrockService($client);

    expect($service->converse('Hello'))->toBe('Hello from Bedrock');
});

it('allows overriding the model id and inference config', function () {
    $client = mock(BedrockRuntimeClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('converse')
            ->once()
            ->withArgs(function (array $args): bool {
                return $args['modelId'] === 'anthropic.claude-3-haiku-20240307-v1:0'
                    && $args['inferenceConfig']['maxTokens'] === 256
                    && $args['inferenceConfig']['temperature'] === 0.2;
            })
            ->andReturn(new Result([
                'output' => [
                    'message' => [
                        'content' => [
                            ['text' => 'Custom model reply'],
                        ],
                    ],
                ],
            ]));
    });

    $service = new BedrockService($client);

    expect($service->converse(
        'Hello',
        'anthropic.claude-3-haiku-20240307-v1:0',
        ['maxTokens' => 256, 'temperature' => 0.2],
    ))->toBe('Custom model reply');
});

it('wraps aws exceptions in a runtime exception', function () {
    $client = mock(BedrockRuntimeClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('converse')
            ->once()
            ->andThrow(new AwsException('Access denied', new \Aws\Command('Converse'), [
                'message' => 'Access denied',
            ]));
    });

    $service = new BedrockService($client);

    $service->converse('Hello');
})->throws(RuntimeException::class, 'Failed to invoke Bedrock model [amazon.nova-lite-v1:0]');

it('passes a system prompt to the converse api', function () {
    $client = mock(BedrockRuntimeClient::class, function (MockInterface $mock) {
        $mock->shouldReceive('converse')
            ->once()
            ->withArgs(function (array $args): bool {
                return ($args['system'][0]['text'] ?? null) === 'Be concise.';
            })
            ->andReturn(new Result([
                'output' => [
                    'message' => [
                        'content' => [
                            ['text' => 'ok'],
                        ],
                    ],
                ],
            ]));
    });

    $service = new BedrockService($client);

    expect($service->converse('Hello', systemPrompt: 'Be concise.'))->toBe('ok');
});

it('resolves the bedrock runtime client from the container', function () {
    expect(app(BedrockRuntimeClient::class))->toBeInstanceOf(BedrockRuntimeClient::class);
});
