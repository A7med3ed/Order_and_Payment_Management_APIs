# Decisions and audit

## Sprint 0 audit

- Existing application: Laravel 13.38.0, Composer requirement `^13.17`, PHP `^8.3`; local PHP is 8.3.33. Extend the existing project rather than recreating it.
- Existing packages: Laravel Tinker 3, Pail, Pao, Pint, Faker, Mockery, Collision, and PHPUnit 12.5. JWT and Swagger are deferred to Sprint 1.
- Existing structure: standard Laravel bootstrap/provider registration, JSON root endpoint, `/up` health route, default configuration, and no business modules or Docker files.
- Existing data model: `App\Models\User`, UserFactory, DatabaseSeeder, and the three stock migrations for users/sessions/password resets, cache, and jobs. Keep these until Auth is moved in Sprint 1.
- Previous environment: local SQLite with migrated tables; PHPUnit used in-memory SQLite and two example tests. Keep the ignored SQLite file as a local backup, but switch Docker and the integration suite to MySQL.
- Repository: the user initialized and pushed `main`; Sprint 0 begins after `f248ec4`. Make one local Conventional Commit for the sprint. No push is needed to complete the sprint.

## Implementation decisions

- Domain responsibilities follow the user's clarification: controllers validate/call services/return resources; application services orchestrate without business decisions; Order and Payment aggregates enforce invariants; other models are data, except LedgerEntry's immutability guard.
- Runtime: use PHP 8.3 FPM and MySQL 8.4, matching the planned PHP baseline and MySQL 8.x requirement. Use health-gated startup, Composer's lock file, and a dedicated vendor volume for Linux dependencies.
- Startup: install locked dependencies, create `.env` only when missing, generate the app key only when missing, and apply migrations after MySQL is healthy. Retain an existing key across restarts and repeated setup.
- Local port: another project already owns port 8080. Keep the portable default at 8080, override this workspace's ignored `.env` to `APP_PORT=8081`, and leave the other project running.
- Database isolation: the app account owns only the main database. A separate local-only test account owns `app_testing`; `.env.testing` is intentionally tracked with non-production credentials. PHPUnit refuses cached configuration or a database other than MySQL `app_testing` before RefreshDatabase runs.
- Database initialization: MySQL's init SQL provisions the test database/account on the first volume initialization. The README explains how to apply it to an existing volume without deleting data.
- Local MySQL enables `log_bin_trust_function_creators` so the scoped application/test accounts can create the ledger triggers in Sprint 3 without global superuser privileges. See the [MySQL binary logging documentation](https://dev.mysql.com/doc/refman/8.4/en/stored-programs-logging.html).
- Optional services: omit phpMyAdmin and Redis in Sprint 0 to keep the base stack small. Laravel's existing database cache/session/queue drivers work with MySQL; tests use array/sync drivers. Redis for production rate limiting remains a later deployment decision.
- API-only tooling: no Node/npm or frontend build is required. Preserve the earlier cleanup.
- Windows tooling: GNU Make is not installed on the host. Include it in the PHP image; `docker compose exec -T app make APP_EXEC= <target>` runs PHP targets directly inside the container. Hosts with Make can use the ordinary `make <target>` commands.
- Future targets: `make docs` invokes Swagger generation, which becomes available and gains export support in Sprint 1. `make ledger-verify` invokes the ledger verifier introduced in Sprint 3. Do not add fake implementations before those sprints.
- Quality baseline: configure Pint with PSR-12 and `declare_strict_types`; apply it to the existing first-party PHP files without changing their business behavior.
- Planning source: copy the clarified Downloads plan into the repository as `PROJECT_PLAN.md` so sprint progress and decisions are versioned.

## Sprint 0 verification (2026-10-21)

- Built the PHP image and started the isolated Compose project from an empty project vendor volume and a new project MySQL volume; all three services became healthy. Runtime PHP is 8.3.35.
- Verified HTTP 200 from both `/` (JSON status) and `/up` at `http://localhost:8081`.
- Ran `make APP_EXEC= migrate`, `test`, and `pint` inside the app container: migrations are current, 4 tests pass with 8 assertions on MySQL, and all 28 PHP files pass PSR-12/strict-types formatting.
- Composer strict validation and all platform requirements pass; Nginx configuration and the shell entrypoint pass syntax validation.
- Re-ran Composer setup and verified the existing `.env` and application key did not change.
- Deliberately supplied the main database to PHPUnit and confirmed the test base rejected it before migration-based setup; the failure was expected and proves the isolation guard.
- Local environment, database files, and vendor dependencies remain ignored by Git. The optional services and subsequent sprint features were not started.
