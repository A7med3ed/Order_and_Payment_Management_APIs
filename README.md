# Order and Payment Management APIs

Laravel API backend requiring PHP 8.3+ and Composer. Node.js and npm are not required.

## Local setup

```powershell
composer run setup
composer run dev
```

Setup installs PHP dependencies, creates `.env` and the SQLite database when missing,
generates the application key, and runs migrations. Run it for initial setup; it
regenerates the application key. Keep `.env` private.

The server runs at http://localhost:8000. `/` returns a JSON status response;
`/up` provides Laravel's health check. Order and payment endpoints are not yet implemented.

## Checks

```powershell
composer test
php vendor/bin/pint --test
```

## Background jobs

Run the worker in a separate terminal when working with queued jobs:

```powershell
php artisan queue:work
```

Docker configuration will be added separately.
