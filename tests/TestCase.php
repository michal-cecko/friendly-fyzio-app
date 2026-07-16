<?php

namespace Tests;

use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The settings cache is primed lazily and never expires (see App\Support\Settings),
        // while RefreshDatabase rolls the settings rows back without firing the model
        // events that would flush it — start every test cold so the cache always matches
        // the test's own database state.
        Cache::forget(Settings::CACHE_KEY);
    }
}
