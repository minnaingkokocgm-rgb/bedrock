<?php

namespace Database\Factories;

use App\Models\AiSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSetting>
 */
class AiSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'system_prompt' => 'You are an advisory reviewer. Recommend accept or reject with a short reason. Never make the final decision.',
            'updated_by' => null,
        ];
    }
}
