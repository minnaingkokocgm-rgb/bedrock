<?php

use App\Models\AiSetting;
use App\Models\FileTypeRule;
use App\Models\User;
use Database\Seeders\AiSettingsSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AiSettingsSeeder::class);
});

it('redirects guests away from ai settings', function () {
    $this->get(route('admin.ai.edit'))
        ->assertRedirect(route('login'));
});

it('lets a reviewer update the system prompt and type rules', function () {
    $reviewer = User::factory()->create();

    $this->actingAs($reviewer)
        ->put(route('admin.ai.update'), [
            'system_prompt' => 'Updated system prompt for advisory review.',
            'document_rules' => 'Updated document rules.',
            'image_rules' => 'Updated image rules.',
            'video_rules' => 'Updated video rules.',
        ])
        ->assertRedirect(route('admin.ai.edit'));

    expect(AiSetting::current()->system_prompt)->toBe('Updated system prompt for advisory review.')
        ->and(FileTypeRule::query()->where('type', 'document')->value('rules'))->toBe('Updated document rules.')
        ->and(FileTypeRule::query()->where('type', 'image')->value('rules'))->toBe('Updated image rules.')
        ->and(FileTypeRule::query()->where('type', 'video')->value('rules'))->toBe('Updated video rules.');
});
