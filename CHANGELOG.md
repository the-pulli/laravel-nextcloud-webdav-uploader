# Changelog

All notable changes to `Laravel Nextcloud Webdav Uploader` will be documented in this file.

## v1.0.3 - 2026-07-23

Require Laravel 13 only. The #[Signature]/#[Description] attributes UploadCommand relies on are only processed by Laravel 13's Command base class — under Laravel 12 the command ended up with an empty name, breaking package discovery entirely.

## v1.0.2 - 2026-07-23

Drop PHP 8.3 from the CI test matrix — composer.json requires php ^8.4 (pulli/pullbox itself requires >=8.4), so 8.3 could never pass.

## v1.0.1 - 2026-07-23

- Drop the local `path` repository override for `pulli/nextcloud-webdav-uploader` now that it's published on Packagist
- Fix CI: enable `pcov` so Pest's "no code coverage driver" warning doesn't abort every test run (`phpunit.xml` sets `failOnWarning="true"`)
