<?php

namespace App\Providers;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BedrockRuntimeClient::class, function (): BedrockRuntimeClient {
            $config = [
                'version' => 'latest',
                'region' => config('services.bedrock.region'),
            ];

            $key = config('services.bedrock.key');
            $secret = config('services.bedrock.secret');

            if (filled($key) && filled($secret)) {
                $credentials = [
                    'key' => $key,
                    'secret' => $secret,
                ];

                $token = config('services.bedrock.token');

                if (filled($token)) {
                    $credentials['token'] = $token;
                }

                $config['credentials'] = $credentials;
            }

            return new BedrockRuntimeClient($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
