# Laravel Nextcloud WebDAV Uploader

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pulli/laravel-nextcloud-webdav-uploader.svg?style=flat-square)](https://packagist.org/packages/pulli/laravel-nextcloud-webdav-uploader)
[![Tests](https://github.com/the-pulli/laravel-nextcloud-webdav-uploader/actions/workflows/run-tests.yml/badge.svg)](https://github.com/the-pulli/laravel-nextcloud-webdav-uploader/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/pulli/laravel-nextcloud-webdav-uploader.svg?style=flat-square)](https://packagist.org/packages/pulli/laravel-nextcloud-webdav-uploader)

An Artisan `nextcloud:upload` command for uploading files and folders to Nextcloud over WebDAV. Built on top of [pulli/nextcloud-webdav-uploader](https://github.com/the-pulli/nextcloud-webdav-uploader) — this package just wires its `NextcloudClient` into Laravel's config and console.

## Features

- `php artisan nextcloud:upload` — upload single files or whole directories (optionally recursive)
- Automatic chunked uploads for large files, with a Laravel Prompts progress bar
- SHA1 checksum skip-if-unchanged: rerunning against the same destination only transfers what changed
- Post-upload checksum verification, so a corrupted transfer fails loudly instead of silently
- Optional public share link creation, copied to the clipboard (macOS only)
- Desktop notification on completion (macOS only, via [pulli/pullbox](https://github.com/the-pulli/pullbox-php))

## Installation

You can install the package via composer:

```bash
composer require pulli/laravel-nextcloud-webdav-uploader
```

The config file is merged automatically — no need to publish it. It reads the same environment variables as the standalone CLI:

```bash
NEXTCLOUD_URL=https://cloud.example.com
NEXTCLOUD_USERNAME=jane
NEXTCLOUD_PASSWORD=app-password

# Optional overrides
NEXTCLOUD_CHUNK_THRESHOLD=4294967296  # bytes; default ~4 GiB
NEXTCLOUD_CHUNK_SIZE=536870912        # bytes; default 512 MiB
NEXTCLOUD_TIMEOUT=300                 # seconds
```

If you do want to customize it, publish the config file with:

```bash
php artisan vendor:publish --tag="nextcloud-webdav-uploader-config"
```

## Usage

```bash
# Upload a single file into /Uploads
php artisan nextcloud:upload --file=/path/to/file.pdf

# Upload into a specific folder (created if missing)
php artisan nextcloud:upload Documents/2026 --file=/path/to/file.pdf

# Upload a directory (its direct files only) as a subfolder of the target folder
php artisan nextcloud:upload Backups --file=/path/to/folder

# ...including nested subdirectories, preserving structure
php artisan nextcloud:upload Backups --file=/path/to/folder --include-subdirs

# Multiple files/folders in one run
php artisan nextcloud:upload Backups --file=/path/a --file=/path/b.txt

# Create (or reuse) a public share link for the target folder afterwards
php artisan nextcloud:upload Shared --file=/path/to/file.pdf --share-dir

# ...or for the uploaded file itself (only valid with exactly one file)
php artisan nextcloud:upload Shared --file=/path/to/file.pdf --share-file
```

Rerunning the same command only re-uploads files whose content actually changed — everything else is reported as `unchanged, skipped`.

### Options

| Option                | Description                                                                                 |
|------------------------|-----------------------------------------------------------------------------------------------|
| `folder`               | Target folder in Nextcloud, relative to the user's files root (default: `Uploads`)            |
| `--file`, `-f`         | Local file or directory to upload (repeatable)                                                |
| `--include-subdirs`    | Recurse into subdirectories of a `--file` directory, preserving structure                     |
| `--chunk-size`         | Override the chunk size in MB for files above the chunking threshold                          |
| `--force-chunk-above`  | Override the chunking threshold in MB (useful to exercise chunking without a huge test file)  |
| `--share-dir`          | Create (or reuse) a public link share for the target folder, print it, and copy it to the clipboard |
| `--share-file`         | Create (or reuse) a public link share for the uploaded file, print it, and copy it to the clipboard (exactly one file only) |

The clipboard copy and the completion notification both shell out to macOS-only tools (`pbcopy`, `osascript`) and are silently skipped on Linux/Windows — the share link is still printed either way.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [PuLLi](https://github.com/the-pulli)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
