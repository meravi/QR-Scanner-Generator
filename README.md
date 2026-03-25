# QR Scanner Generator

A refreshed core PHP QR generator that works cleanly on modern PHP, exports PNG files locally, and optionally stores a simple history in MySQL.

## What changed

- safer request handling with CSRF protection and escaped output
- prepared statements instead of raw SQL inserts
- responsive generator UI with preview and recent history
- local file downloads instead of the old hard-coded remote URL
- database becomes optional for generation; if MySQL is offline, PNG export still works

## Requirements

- PHP 8.x with `mysqli`, `session`, and `gd`
- MySQL or MariaDB if you want database history
- write access to `userQr/`

## Setup

1. Import `qrcodes.sql` if you want database-backed history.
2. Configure database credentials through environment variables or by editing `config.php`.
3. Serve the project through PHP or Apache/Nginx and open `index.php`.

Supported environment variables:

- `QR_DB_HOST`
- `QR_DB_USER`
- `QR_DB_PASS`
- `QR_DB_NAME`

## Notes

- Generated PNG files are written to `userQr/`.
- The bundled QR library under `meRaviQr/` was left in place to keep the update focused on the app layer.
