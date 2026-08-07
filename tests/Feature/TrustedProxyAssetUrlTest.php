<?php

use Illuminate\Support\Facades\URL;

test('https app url forces https asset scheme', function () {
    config(['app.url' => 'https://ai-test.q-pass.jp']);

    URL::forceScheme('https');

    expect(asset('build/assets/app.css'))->toStartWith('https://');
});
