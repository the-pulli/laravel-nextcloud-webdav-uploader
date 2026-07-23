<?php

declare(strict_types=1);

namespace Pulli\LaravelNextcloudWebdavUploader\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Pulli\LaravelNextcloudWebdavUploader\NextcloudWebdavUploaderServiceProvider;
use Pulli\Pullbox\AppleScript;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        AppleScript::fake();
    }

    protected function getPackageProviders($app)
    {
        return [
            NextcloudWebdavUploaderServiceProvider::class,
        ];
    }
}
