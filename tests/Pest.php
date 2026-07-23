<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Process;
use Psr\Http\Message\RequestInterface;
use Pulli\LaravelNextcloudWebdavUploader\Tests\TestCase;
use Pulli\NextcloudWebdavUploader\NextcloudClient;

uses(TestCase::class)->in(__DIR__);

/**
 * Build a NextcloudClient backed by a Guzzle MockHandler instead of a real
 * connection, and bind it into the container so the package's Artisan
 * command resolves this instance instead of one built from config.
 *
 * @param  array<int, Response|callable>  $responses
 * @param  array<int, array{request: RequestInterface}>|null  $history
 */
function fakeNextcloudClient(array $responses, ?array &$history = [], array $options = []): NextcloudClient
{
    $handlerStack = HandlerStack::create(new MockHandler($responses));
    $handlerStack->push(Middleware::history($history));

    $client = new NextcloudClient(
        baseUrl: $options['baseUrl'] ?? 'https://cloud.test',
        username: $options['username'] ?? 'testuser',
        password: $options['password'] ?? 'testpass',
        chunkThreshold: $options['chunkThreshold'] ?? 4 * 1024 ** 3,
        chunkSize: $options['chunkSize'] ?? 512 * 1024 ** 2,
        timeoutSeconds: $options['timeoutSeconds'] ?? 30,
        httpClient: new Client(['handler' => $handlerStack, 'http_errors' => false]),
    );

    app()->instance(NextcloudClient::class, $client);

    return $client;
}

function checksumPropfindResponse(string $sha1, int $status = 207): Response
{
    return new Response($status, [], <<<XML
        <?xml version="1.0"?>
        <d:multistatus xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
          <d:response>
            <d:propstat>
              <d:prop>
                <oc:checksums>
                  <oc:checksum>SHA1:{$sha1}</oc:checksum>
                </oc:checksums>
              </d:prop>
              <d:status>HTTP/1.1 200 OK</d:status>
            </d:propstat>
          </d:response>
        </d:multistatus>
        XML);
}

function noRemoteChecksum(): Response
{
    return new Response(404);
}

/**
 * pbcopy is macOS-only: assert the clipboard copy actually ran when the
 * test itself is running on Darwin, and that it was skipped everywhere
 * else (CI runs on ubuntu-latest / windows-latest).
 */
function assertClipboardCopiedOnMacOnly(string $expectedInput): void
{
    if (PHP_OS_FAMILY === 'Darwin') {
        Process::assertRan(fn ($process) => $process->command === 'pbcopy'
            && $process->input === $expectedInput);
    } else {
        Process::assertDidntRun(fn ($process) => $process->command === 'pbcopy');
    }
}
