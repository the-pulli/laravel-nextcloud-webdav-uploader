<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Pulli\Pullbox\AppleScript;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/lnwu-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->tmpDir);
});

afterEach(function () {
    File::deleteDirectory($this->tmpDir);
});

it('registers the nextcloud:upload command', function () {
    expect(Artisan::all())->toHaveKey('nextcloud:upload');
});

it('fails with a clear error when no --file option is given', function () {
    fakeNextcloudClient([]);

    $this->artisan('nextcloud:upload', ['folder' => 'Documents'])
        ->expectsOutputToContain('No files given')
        ->assertFailed();
});

it('fails when a given local file does not exist', function () {
    fakeNextcloudClient([]);

    $this->artisan('nextcloud:upload', [
        'folder' => 'Documents',
        '--file' => [$this->tmpDir.'/missing.txt'],
    ])
        ->expectsOutputToContain('File not found')
        ->assertFailed();
});

it('uploads a file end to end and reports it as verified', function () {
    $file = $this->tmpDir.'/report.txt';
    File::put($file, 'hello world');
    $checksum = hash_file('sha1', $file);

    fakeNextcloudClient([
        new Response(201),                   // MKCOL Documents
        noRemoteChecksum(),                  // pre-check
        new Response(201),                   // PUT
        checksumPropfindResponse($checksum), // post-verify
    ]);

    Process::fake();

    $this->artisan('nextcloud:upload', ['folder' => 'Documents', '--file' => [$file]])
        ->expectsOutputToContain('verified')
        ->assertSuccessful();
});

it('reports a skipped file as unchanged without failing the command', function () {
    $file = $this->tmpDir.'/report.txt';
    File::put($file, 'hello world');
    $checksum = hash_file('sha1', $file);

    fakeNextcloudClient([
        new Response(201),                   // MKCOL Documents
        checksumPropfindResponse($checksum), // pre-check: already up to date
    ]);

    $this->artisan('nextcloud:upload', ['folder' => 'Documents', '--file' => [$file]])
        ->expectsOutputToContain('unchanged, skipped')
        ->assertSuccessful();
});

it('includes nested subdirectories when --include-subdirs is passed, preserving structure', function () {
    File::ensureDirectoryExists($this->tmpDir.'/Project/Sub');
    File::put($this->tmpDir.'/Project/top.txt', 'top');
    File::put($this->tmpDir.'/Project/Sub/nested.txt', 'nested');

    $history = [];
    fakeNextcloudClient(array_fill(0, 20, new Response(201)), $history);

    $this->artisan('nextcloud:upload', [
        'folder' => 'Backups',
        '--file' => [$this->tmpDir.'/Project'],
        '--include-subdirs' => true,
    ])->assertSuccessful();

    $mkcolFolders = array_values(array_map(
        fn ($h) => (string) $h['request']->getUri(),
        array_filter($history, fn ($h) => $h['request']->getMethod() === 'MKCOL')
    ));

    expect($mkcolFolders)->toContain('https://cloud.test/remote.php/dav/files/testuser/Backups/Project/Sub');
});

it('creates a share link, prints it, and copies it to the clipboard with --share', function () {
    $file = $this->tmpDir.'/f.txt';
    File::put($file, 'x');
    $checksum = hash_file('sha1', $file);

    fakeNextcloudClient([
        new Response(201),                   // MKCOL Shared
        noRemoteChecksum(),                  // pre-check
        new Response(201),                   // PUT
        checksumPropfindResponse($checksum), // post-verify
        new Response(200, [], json_encode(['ocs' => ['data' => []]])), // share lookup
        new Response(200, [], json_encode(['ocs' => ['data' => ['url' => 'https://cloud.test/s/abc123']]])), // share create
    ]);

    Process::fake();

    $this->artisan('nextcloud:upload', ['folder' => 'Shared', '--file' => [$file], '--share' => true])
        ->expectsOutputToContain('Share link: https://cloud.test/s/abc123')
        ->assertSuccessful();

    Process::assertRan(fn ($process) => $process->command === 'pbcopy'
        && $process->input === 'https://cloud.test/s/abc123');
});

it('sends a desktop notification with the number of uploaded files', function () {
    $file = $this->tmpDir.'/f.txt';
    File::put($file, 'x');
    $checksum = hash_file('sha1', $file);

    fakeNextcloudClient([
        new Response(201),
        noRemoteChecksum(),
        new Response(201),
        checksumPropfindResponse($checksum),
    ]);

    Process::fake();

    $this->artisan('nextcloud:upload', ['folder' => 'Documents', '--file' => [$file]])->assertSuccessful();

    expect(AppleScript::lastScript())->toContain('1 file(s) uploaded to Nextcloud.');
});
