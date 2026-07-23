<?php

declare(strict_types=1);

namespace Pulli\LaravelNextcloudWebdavUploader;

use Pulli\LaravelNextcloudWebdavUploader\Commands\UploadCommand;
use Pulli\NextcloudWebdavUploader\NextcloudClient;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NextcloudWebdavUploaderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-nextcloud-webdav-uploader')
            ->hasConfigFile('nextcloud-webdav-uploader')
            ->hasCommand(UploadCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(NextcloudClient::class, fn (): NextcloudClient => new NextcloudClient(
            baseUrl: (string) config('nextcloud-webdav-uploader.url'),
            username: (string) config('nextcloud-webdav-uploader.username'),
            password: (string) config('nextcloud-webdav-uploader.password'),
            chunkThreshold: (int) config('nextcloud-webdav-uploader.chunk_threshold'),
            chunkSize: (int) config('nextcloud-webdav-uploader.chunk_size'),
            timeoutSeconds: (int) config('nextcloud-webdav-uploader.timeout_seconds'),
        ));
    }
}
