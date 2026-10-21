# Order and Payment Management APIs

An API-only Laravel 13 project using PHP 8.3 and MySQL 8.4. Node.js and npm are not required.
Sprints 0–1 provide Docker, modular infrastructure, JWT authentication, rate limits,
and Swagger documentation. Orders, payments, and the ledger arrive in later sprints.
See [PROJECT_PLAN.md](PROJECT_PLAN.md)
and [docs/DECISIONS.md](docs/DECISIONS.md).

## Docker setup

Install Docker Desktop with Linux containers and Docker Compose. No host PHP,
Composer, MySQL, or Make installation is required.

```powershell
Copy-Item .env.example .env
docker compose up -d --build --wait --wait-timeout 300
```

On macOS/Linux, use `cp .env.example .env` instead of `Copy-Item`.
If `.env` already exists, edit it to match the Docker database settings in
`.env.example` rather than overwriting your application key or other local values.

The app is available at http://localhost:8080 by default. If that port is occupied,
set `APP_PORT=8081` in `.env`, then rerun the startup command. `APP_URL` follows
`APP_PORT`. This workspace uses port 8081 because another project owns 8080.

Startup installs the locked Composer dependencies into a named Linux volume,
creates `.env` if missing, generates application/JWT keys only if missing, and runs
migrations after MySQL is ready, and generates the runtime Swagger spec.
The first build/download can take several minutes.
`GET /` returns JSON status; `GET /up` is Laravel's health endpoint.

MySQL data persists in a named volume. The database is available to the app as
`mysql:3306` and is not published on the host. The credentials in the example
environment and `.env.testing` are for local development only.

```powershell
docker compose ps
docker compose logs --tail 100 app
docker compose down
```

Stopping the stack preserves database and dependency volumes.

## Commands and quality checks

With GNU Make installed on the host:

```sh
make up
make migrate
make test
make pint
```

Windows users can run the same PHP targets with Make inside the app container:

```powershell
docker compose exec -T app make APP_EXEC= migrate
docker compose exec -T app make APP_EXEC= test
docker compose exec -T app make APP_EXEC= pint
```

`make pint` checks PSR-12 formatting and strict types. To apply formatting:

```powershell
docker compose exec -T app php vendor/bin/pint
```

Other targets: `build`, `down`, `shell`, `seed`, and `fresh`. `fresh` deletes and
recreates application tables and seeds them; use it only when you intend to reset
local data. `ledger-verify` becomes functional in Sprint 3. `make docs` generates
OpenAPI and exports JSON/YAML to `docs/openapi/`. On Windows:

```powershell
docker compose exec -T app make APP_EXEC= docs
```

Swagger UI is at `/api/documentation` (this workspace:
http://localhost:8081/api/documentation). The spec is served at `/documentation/spec`.
Set `API_DOCUMENTATION_ENABLED=false` to hide the UI, spec, and assets. Without an
explicit flag, documentation is disabled when `APP_ENV=production`.

## Test database

Tests use MySQL `app_testing` with a separate `app_test` account. The account has
permissions only on that database. Cache and sessions use `array`, and queues use
`sync`. The test base class rejects cached configuration and other databases
before migration-based test setup runs. Run the suite inside Docker; the hostname
`mysql` belongs to the Compose network.

MySQL initialization creates both databases on the first startup. If using an
existing volume created before the test database existed, apply the init file
without deleting the volume:

```powershell
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot < /docker-entrypoint-initdb.d/01-testing.sql'
```

## Composer and background jobs

Docker startup already performs setup. For subsequent manual setup inside the
running app container, `composer run setup` installs dependencies, preserves an
existing application/JWT keys, runs migrations, and generates the runtime Swagger spec.
`composer run dev` starts Laravel's
PHP development server; Docker normally uses Nginx and PHP-FPM instead.

When queued jobs are introduced, run a worker in a separate terminal:

```powershell
docker compose exec app php artisan queue:work
```

## Authentication and module conventions

| Method | Endpoint | Result |
|---|---|---|
| POST | `/api/auth/register` | 201 with a user resource |
| POST | `/api/auth/login` | 200 with a JWT and user resource |
| GET | `/api/auth/me` | 200 with the current user; JWT required |
| POST | `/api/auth/logout` | 204 and the JWT is invalidated |

Registration accepts `name`, `email`, `password`, and `password_confirmation`.
Passwords require at least eight characters with letters and numbers, up to 72
UTF-8 bytes (bcrypt's limit). Emails are trimmed and stored in lowercase. Login accepts `email` and
`password`; send the returned `data.access_token` in `Authorization: Bearer <token>`.
`data.expires_in` is seconds (default 3600). Obtain a token by logging in after
registration. Validation returns 422 with `message`, `code`, and field `errors`.
Missing, invalid, expired, or logged-out tokens return JSON 401, including when no
`Accept` header is provided. Unexpected errors never expose debug details.

Limits are configured through `.env`: login defaults to 5/minute per normalized
email + IP, registration to 10/minute per IP, authenticated API routes to 60/minute
per user, and documentation to 60/minute per IP. Exceeded limits return JSON 429
with `Retry-After`, `X-RateLimit-Limit`, and `X-RateLimit-Remaining` headers.

Modules live in `app/Modules/{Shared,Auth,Orders,Ledger,Payments}`. Each provider
loads its module's routes, migrations, and config. Auth owns its User model,
factory, DTOs, requests, resources, service, and user migration. Migration filenames
are preserved so existing databases do not recreate tables. The future domain
modules currently contain only their providers and registration directories.
Shared contains API errors, JSON middleware, rate limiters, pagination data, and
exact decimal-string Money helpers using bcmath.

Controllers validate/map DTOs, call a service, and return resources. Order and
Payment application services will only orchestrate; their aggregates will own
business rules. Other models remain data, except the ledger's immutability guard.
Cross-module access will use contracts and DTOs. Gateway/idempotency variables
remain reserved for their later sprints.
