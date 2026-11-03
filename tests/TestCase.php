<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Container env vars (docker env_file) override phpunit.xml, so pin the test values before the app boots. */
    public function createApplication(): Application
    {
        foreach ([
            'CACHE_STORE'             => 'array',
            'AI_PROVIDER'             => 'anthropic',
            'AI_LOG_CHANNEL'          => 'null',
            'AI_PROVIDER_LOG_CHANNEL' => 'null',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }
}
