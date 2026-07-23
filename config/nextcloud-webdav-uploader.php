<?php

declare(strict_types=1);

return [
    'url' => env('NEXTCLOUD_URL'),
    'username' => env('NEXTCLOUD_USERNAME'),
    'password' => env('NEXTCLOUD_PASSWORD'),

    // A single WebDAV PUT is unreliable above ~4 GiB (proxies / 32-bit off_t
    // limits). Files larger than this use Nextcloud's NG chunking API instead.
    'chunk_threshold' => (int) env('NEXTCLOUD_CHUNK_THRESHOLD', 4 * 1024 ** 3),

    // Chunk size for chunked uploads. Nextcloud allows 5 MB – 5 GB per chunk.
    'chunk_size' => (int) env('NEXTCLOUD_CHUNK_SIZE', 512 * 1024 ** 2),

    'timeout_seconds' => (int) env('NEXTCLOUD_TIMEOUT', 300),
];
