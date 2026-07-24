<?php

use App\Models\User;
use App\Services\Bedrock\BedrockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests away from the bedrock ping page', function () {
    $this->get(route('admin.bedrock.ping'))
        ->assertRedirect(route('login'));
});

it('shows a successful bedrock ping response for reviewers', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('converse')
            ->once()
            ->andReturn('Bedrock is working.');
    });

    $this->actingAs(User::factory()->create())
        ->get(route('admin.bedrock.ping'))
        ->assertSuccessful()
        ->assertSee('Bedrock ping')
        ->assertSee('Bedrock OK')
        ->assertSee('Bedrock is working.');
});

it('shows the bedrock error when the call fails', function () {
    $this->mock(BedrockService::class, function ($mock) {
        $mock->shouldReceive('converse')
            ->once()
            ->andThrow(new RuntimeException('Failed to invoke Bedrock model [test]: AccessDenied'));
    });

    $this->actingAs(User::factory()->create())
        ->get(route('admin.bedrock.ping'))
        ->assertSuccessful()
        ->assertSee('Bedrock failed')
        ->assertSee('AccessDenied');
});
