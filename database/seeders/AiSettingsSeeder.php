<?php

namespace Database\Seeders;

use App\Enums\SubmissionType;
use App\Models\AiSetting;
use App\Models\FileTypeRule;
use Illuminate\Database\Seeder;

class AiSettingsSeeder extends Seeder
{
    public function run(): void
    {
        AiSetting::query()->firstOrCreate([], [
            'system_prompt' => <<<'PROMPT'
You are an advisory content reviewer for a document submission portal.
Your job is to recommend whether a human reviewer should ACCEPT or REJECT a submission.
You do not make the final decision. Always be cautious when content is missing or unclear.

Respond with ONLY valid JSON in this exact shape:
{"verdict":"accept"|"reject"|"inconclusive","reason":"short explanation"}
PROMPT,
        ]);

        $defaults = [
            SubmissionType::Document->value => <<<'RULES'
Document rules:
- Prefer clear, relevant documents that match the stated title/description.
- Reject obviously empty, corrupt, or spam-like text.
- If extracted text is missing or too thin to judge, recommend inconclusive.
RULES,
            SubmissionType::Image->value => <<<'RULES'
Image rules:
- Text extraction is limited for images in v1; judge mainly from metadata and submitter context.
- Recommend inconclusive unless metadata clearly indicates spam or misuse.
- Prefer accept only when title/description look legitimate and non-abusive.
RULES,
            SubmissionType::Video->value => <<<'RULES'
Video rules:
- Text extraction is not available for videos in v1; judge mainly from metadata and submitter context.
- Recommend inconclusive unless metadata clearly indicates spam or misuse.
- Prefer accept only when title/description look legitimate and non-abusive.
RULES,
        ];

        foreach ($defaults as $type => $rules) {
            FileTypeRule::query()->firstOrCreate(
                ['type' => $type],
                ['rules' => $rules],
            );
        }
    }
}
