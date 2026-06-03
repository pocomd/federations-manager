<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure .installed exists so CheckInstalled middleware does not
        // intercept non-installer tests. Install-specific tests unlink it
        // in their own beforeEach, which runs after this setUp.
        $flag = storage_path('app/.installed');
        if (! file_exists($flag)) {
            @mkdir(storage_path('app'), 0755, true);
            file_put_contents($flag, json_encode(['installed_at' => now()->toISOString(), 'version' => 'test']));
        }
    }
}
