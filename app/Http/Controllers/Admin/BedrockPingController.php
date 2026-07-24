<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Bedrock\BedrockService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class BedrockPingController extends Controller
{
    public function __invoke(BedrockService $bedrock): View
    {
        $modelId = (string) config('services.bedrock.model_id');
        $region = (string) config('services.bedrock.region');
        $prompt = 'Reply with exactly this sentence: Bedrock is working.';

        $ok = false;
        $response = null;
        $error = null;

        try {
            $response = $bedrock->converse($prompt);
            $ok = true;

            Log::info('submission.bedrock.ping_ok', [
                'model_id' => $modelId,
                'region' => $region,
                'response_length' => mb_strlen($response),
            ]);
        } catch (Throwable $e) {
            $error = $e->getMessage();

            Log::error('submission.bedrock.ping_failed', [
                'model_id' => $modelId,
                'region' => $region,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'previous' => $e->getPrevious()?->getMessage(),
            ]);
        }

        return view('admin.bedrock-ping', [
            'ok' => $ok,
            'prompt' => $prompt,
            'response' => $response,
            'error' => $error,
            'modelId' => $modelId,
            'region' => $region,
            'usingExplicitKeys' => filled(config('services.bedrock.key')) && filled(config('services.bedrock.secret')),
        ]);
    }
}
