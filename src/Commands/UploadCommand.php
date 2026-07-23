<?php

declare(strict_types=1);

namespace Pulli\LaravelNextcloudWebdavUploader\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Number;
use Laravel\Prompts\Progress;
use Pulli\NextcloudWebdavUploader\Exceptions\NextcloudException;
use Pulli\NextcloudWebdavUploader\NextcloudClient;
use Pulli\Pullbox\Notification;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function basename;
use function file_exists;
use function filesize;
use function is_file;
use function Laravel\Prompts\progress;
use function realpath;
use function rtrim;
use function sprintf;
use function trim;

#[Description('Uploads file(s) and/or whole folder(s) to Nextcloud via WebDAV, chunking automatically above the single-PUT size limit')]
#[Signature('nextcloud:upload {folder=Uploads : Target folder in Nextcloud (relative to the user files root; created if missing)} {--f|file=* : Local file or directory to upload (repeatable); a directory is uploaded as a subfolder of the same name} {--include-subdirs : When a --file path is a directory, also include its subdirectories recursively (default: only that directory\'s direct files)} {--chunk-size= : Override the chunk size in MB used for files above the chunking threshold} {--force-chunk-above= : Override the chunking threshold in MB, bypassing the ~4 GiB default (useful to test chunked uploads with a small file)} {--share : Create (or reuse) a public link share for the target folder, print it, and copy it to the clipboard}')]
class UploadCommand extends Command
{
    public function handle(NextcloudClient $client): int
    {
        $folder = (string) $this->argument('folder');
        $includeSubdirs = (bool) $this->option('include-subdirs');

        $paths = Collection::make($this->option('file'))
            ->map(fn (string $path) => realpath($path) ?: $path);

        if ($paths->isEmpty()) {
            $this->error('No files given. Pass one or more --file=path options.');

            return self::FAILURE;
        }

        $missing = $paths->reject(fn (string $path) => file_exists($path));

        if ($missing->isNotEmpty()) {
            $missing->each(fn (string $path) => $this->error(sprintf('File not found: %s', $path)));

            return self::FAILURE;
        }

        $jobs = $paths->flatMap(fn (string $path) => $this->expand($path, $folder, $includeSubdirs));

        if ($jobs->isEmpty()) {
            $this->error('No files found to upload.');

            return self::FAILURE;
        }

        if ($this->option('chunk-size') !== null) {
            $client->setChunkSize(((int) $this->option('chunk-size')) * 1024 * 1024);
        }

        if ($this->option('force-chunk-above') !== null) {
            $client->setChunkThreshold(((int) $this->option('force-chunk-above')) * 1024 * 1024);
        }

        try {
            foreach ($jobs->pluck('folder')->push($folder)->unique() as $remoteFolder) {
                $client->ensureRemoteDirectory($remoteFolder);
            }
        } catch (NextcloudException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $failed = false;

        foreach ($jobs as $job) {
            $failed = ! $this->uploadOne($client, $job['folder'], $job['local']) || $failed;
        }

        if ($failed) {
            return self::FAILURE;
        }

        if ($this->option('share')) {
            try {
                $link = $client->shareLink($folder);
            } catch (NextcloudException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info(sprintf('Share link: %s', $link));
            Process::input($link)->run('pbcopy');
        }

        Notification::display(
            sprintf('%d file(s) uploaded to Nextcloud.', $jobs->count()),
            'Nextcloud Upload'
        );

        return self::SUCCESS;
    }

    /**
     * Expand a raw --file path into one or more upload jobs. A plain file
     * becomes a single job under $folder. A directory becomes one job per
     * file found inside it, placed under $folder/<directory name> — its own
     * subfolder structure is preserved only when $includeSubdirs is true,
     * otherwise only the directory's direct files are included.
     *
     * @return Collection<int, array{local: string, folder: string}>
     */
    private function expand(string $path, string $folder, bool $includeSubdirs): Collection
    {
        if (is_file($path)) {
            return Collection::make([['local' => $path, 'folder' => $folder]]);
        }

        $root = sprintf('%s/%s', trim($folder, '/'), basename(rtrim($path, '/')));

        $finder = new Finder;
        $finder->files()->in($path);

        if (! $includeSubdirs) {
            $finder->depth('== 0');
        }

        return Collection::make(iterator_to_array($finder))
            ->map(function (SplFileInfo $file) use ($root): array {
                $relativeDir = $file->getRelativePath();

                return [
                    'local' => $file->getPathname(),
                    'folder' => $relativeDir === '' ? $root : sprintf('%s/%s', $root, $relativeDir),
                ];
            })
            ->values();
    }

    private function uploadOne(NextcloudClient $client, string $folder, string $file): bool
    {
        $size = (int) filesize($file);
        $chunked = $size > $client->chunkThreshold();

        $progress = null;

        try {
            $result = $client->upload($file, $folder, function (int $chunk, int $total) use (&$progress) {
                if ($progress === null) {
                    $progress = progress(label: 'Uploading chunks', steps: $total);
                    $progress->start();
                }

                $progress->advance();
            });
        } catch (NextcloudException $e) {
            /** @var Progress<int>|null $progress */
            $progress?->finish();
            $this->error(sprintf('%s', basename($file)));
            $this->error(sprintf('  ! %s', $e->getMessage()));

            return false;
        }

        /** @var Progress<int>|null $progress */
        $progress?->finish();

        if ($result['skipped']) {
            $this->info(sprintf('%s (%s) — unchanged, skipped', basename($file), Number::fileSize($size, precision: 2)));

            return true;
        }

        $this->info(sprintf(
            '%s (%s)%s',
            basename($file),
            Number::fileSize($size, precision: 2),
            $chunked ? ' — chunked upload' : ''
        ));
        $this->line(sprintf('  → /%s (verified)', $result['path']));

        return true;
    }
}
