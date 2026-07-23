# Changelog

All notable changes to `Laravel Nextcloud Webdav Uploader` will be documented in this file.

## v1.0.1 - 2026-07-23

- Drop the local `path` repository override for `pulli/nextcloud-webdav-uploader` now that it's published on Packagist
- Fix CI: enable `pcov` so Pest's "no code coverage driver" warning doesn't abort every test run (`phpunit.xml` sets `failOnWarning="true"`)
