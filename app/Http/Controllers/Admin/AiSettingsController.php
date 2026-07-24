<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAiSettingsRequest;
use App\Models\AiSetting;
use App\Models\FileTypeRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = AiSetting::current();
        $rules = FileTypeRule::query()->get()->keyBy(fn (FileTypeRule $rule) => $rule->type->value);

        return view('admin.ai.edit', [
            'settings' => $settings,
            'documentRules' => $rules[SubmissionType::Document->value]->rules ?? '',
            'imageRules' => $rules[SubmissionType::Image->value]->rules ?? '',
            'videoRules' => $rules[SubmissionType::Video->value]->rules ?? '',
        ]);
    }

    public function update(UpdateAiSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            $settings = AiSetting::current();
            $settings->update([
                'system_prompt' => $data['system_prompt'],
                'updated_by' => $request->user()->id,
            ]);

            foreach ([
                SubmissionType::Document->value => $data['document_rules'],
                SubmissionType::Image->value => $data['image_rules'],
                SubmissionType::Video->value => $data['video_rules'],
            ] as $type => $rules) {
                FileTypeRule::query()->updateOrCreate(
                    ['type' => $type],
                    [
                        'rules' => $rules,
                        'updated_by' => $request->user()->id,
                    ],
                );
            }
        });

        return redirect()
            ->route('admin.ai.edit')
            ->with('status', 'AI settings saved.');
    }
}
