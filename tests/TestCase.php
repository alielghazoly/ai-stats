<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** متغيرات بيئة الـ container (docker env_file) بتغلب على phpunit.xml، فنثبّت قيم الاختبار قبل ما التطبيق يشتغل. */
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
