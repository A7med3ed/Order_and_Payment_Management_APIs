# Order and Payment Management APIs

An API-only Laravel 13 project using PHP 8.3 and MySQL 8.4. Node.js and npm are not required.
Sprint 0 provides the development environment; authentication, orders, payments, and
the ledger will be implemented in later sprints. See [PROJECT_PLAN.md](PROJECT_PLAN.md)
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
creates `.env` if missing, generates an application key only if missing, and runs
migrations after MySQL is ready. The first build/download can take several minutes.
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
local data. `ledger-verify` becomes functional in Sprint 3. The `docs` generation
and export workflow will be completed with Swagger in Sprint 1.

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
existing application key, and runs migrations. `composer run dev` starts Laravel's
PHP development server; Docker normally uses Nginx and PHP-FPM instead.

When queued jobs are introduced, run a worker in a separate terminal:

```powershell
docker compose exec app php artisan queue:work
```

JWT, gateway, idempotency, rate limit, and documentation variables are reserved in
`.env.example` for later sprints; those features are not implemented in Sprint 0.
