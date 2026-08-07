<?php

use Tests\TestCase;

uses(TestCase::class);

test('sqlite connection uses wal journal mode and busy timeout by default', function () {
    expect(config('database.connections.sqlite.journal_mode'))->toBe('WAL')
        ->and(config('database.connections.sqlite.busy_timeout'))->toBe(5000);
});
