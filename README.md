# Order and Payment Management APIs

An API-only Laravel 13 project using PHP 8.3 and MySQL 8.4. Node.js and npm are not required.
Sprints 0–3 provide Docker, modular infrastructure, JWT authentication, rate limits,
order management, immutable ledger history, idempotency infrastructure, and Swagger.
Payment processing and gateway implementations arrive in Sprint 4.
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
local data. `make ledger-verify` verifies ledger hashes and sequence checkpoints. `make docs` generates
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
`sync`. Ledger integrity tests use real MySQL triggers and independent PHP processes
to test simultaneous first appends. The test base class rejects cached configuration and other databases
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
are preserved so existing databases do not recreate tables. Payments currently
contains only its provider and registration directories.
Shared contains API errors, JSON middleware, rate limiters, pagination data, and
exact decimal-string Money helpers using bcmath.

Controllers validate/map DTOs, call a service, and return resources. Order and
Payment application services will only orchestrate; their aggregates will own
business rules. Other models remain data, except the ledger's immutability guard.
Cross-module access uses contracts and DTOs. Gateway variables remain reserved for Sprint 4.

## Orders

All order routes require a JWT and the API rate limiter. Lists are scoped to the
current user; looking up another user's order returns 404 consistently.

| Method | Endpoint | Behavior |
|---|---|---|
| GET | `/api/orders` | Filter by `status`, paginate with `page` and `per_page` (15 by default, maximum 100) |
| POST | `/api/orders` | Place a pending order with customer details and at least one item |
| GET | `/api/orders/{order}` | Read the order and its items |
| PUT/PATCH | `/api/orders/{order}` | Update supplied customer fields/items while pending |
| PATCH | `/api/orders/{order}/status` | Confirm or cancel using the domain state machine |
| DELETE | `/api/orders/{order}` | Delete only when no payment of any status exists |

Items contain `product_name`, integer `quantity` (at least 1), and decimal-string
`price` (for example `"19.99"`). Totals are calculated server-side using bcmath;
client totals, user ids, and initial statuses are ignored. Supplied update items
replace the whole item collection. Empty updates are rejected. Up to 1000 items
per request are accepted; prices and totals must fit DECIMAL(12,2).

Transitions: `pending -> confirmed`, `pending -> cancelled`, and
`confirmed -> cancelled` when no successful payment exists. Cancelled is terminal.
OrderService coordinates transactions and after-commit events; all state and item
rules are enforced by the aggregate. Its only optional-data conditional selects
whether to persist a supplied replacement item collection.

Until Sprint 4 implements Payments, a temporary no-payments guard is bound as
specified in the plan. Payment-aware rejection is already exercised with a test guard.

## Immutable ledger

`GET /api/orders/{order}/ledger` returns owner-scoped, paginated history in sequence
order. No write/update/delete ledger endpoints exist. Payment-scoped history is
deferred until Sprint 4 can check payment ownership through a contract.

Recorders use Ledger contracts and LedgerEntryData, not other modules' models.
Initiated/failed events are memos, successful payments are credits, and reversals
are debits. Balance is the sum of credits minus debits. Corrections append entries;
there is no refund endpoint. Known card/credential keys are stripped from metadata.

Model guards and MySQL BEFORE UPDATE/DELETE triggers prevent edits. A per-order
lock/checkpoint row serializes even the first concurrent append. Hashes cover
canonical JSON with financial fields, owner, sequence, gateway, predecessor hash,
and UTC microsecond timestamp. Checkpoints also expose missing tail entries.

```powershell
docker compose exec -T app php artisan ledger:verify
docker compose exec -T app php artisan ledger:verify --order=1
```

Production should use a runtime DB account without UPDATE/DELETE/DROP privileges
on ledger entries; run migrations with a separate deployment account. Hash chains
provide tamper evidence, not protection from an administrator rewriting the database.

## Idempotency infrastructure

The `idempotent` middleware is opt-in and will be applied to payment creation in
Sprint 4. Ordering is authentication, throttling, then idempotency. Replays count
toward the rate limit. Keys are case-sensitive, scoped per user, and accept 8–255
ASCII letters/numbers/dots/colons/underscores/hyphens; UUIDs are recommended.

| Condition | Result |
|---|---|
| Missing key | 422 `IDEMPOTENCY_KEY_REQUIRED` |
| Same key, completed identical request | Exact stored status/body; `Idempotent-Replayed: true` |
| Same key currently processing | 409 `IDEMPOTENCY_IN_PROGRESS`, `Retry-After: 1` |
| Different method/path/query/body with same key | 422 `IDEMPOTENCY_KEY_REUSED` |
| Validation error or server error/exception | Reservation released for a retry |
| Key expires | Can be claimed anew |

Canonical JSON sorts object keys and preserves array order. Only the request hash
is persisted; raw request/card details are not stored. Response bytes are stored
as a JSON string for exact replay; content type/location/language are retained,
while cookies and authentication headers are not copied. Reservation row identity
prevents a late request from changing or releasing a newer claim.

TTL is `IDEMPOTENCY_TTL_HOURS` (24 by default). Run `php artisan idempotency:prune`
manually, or run Laravel's scheduler (`php artisan schedule:work`) to execute the
hourly pruning task. Processing claims expire too; provider/domain safeguards and
pending-payment reconciliation are part of the later payment integration.
