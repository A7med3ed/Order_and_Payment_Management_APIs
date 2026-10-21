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

## Sprint 1 decisions and verification

- Added compatible releases `php-open-source-saver/jwt-auth` 2.9.3 and L5-Swagger 11.2.0 (swagger-php 6.18.0); existing Laravel and locked dependency versions remain unchanged.
- Each module provider inherits generic route/migration/config registration from Shared. Orders, Ledger, and Payments remain registration skeletons; their business behavior is deferred to the assigned sprints.
- Auth owns User, its factory, and the stock user/session/password-reset migration. Preserve the migration filename so existing MySQL databases do not recreate those tables. Keep Laravel's root auth config and the published JWT/Swagger config files for their framework/package conventions.
- Registration returns a 201 UserResource; clients log in separately for a JWT. Login returns a data envelope containing access_token, token_type, expires_in (seconds), and a UserResource. Logout returns 204 and blacklists the token.
- Normalize registration/login email by trimming and lowercasing; passwords require at least eight characters with letters and numbers. Enforce bcrypt's 72-byte limit on both endpoints so multibyte input yields validation errors rather than truncation or server errors.
- AuthService receives the authentication factory and resolves the current API guard per call, avoiding retained request/user state. Feature tests clear the package's token cache between simulated HTTP requests so multiple users behave like independent PHP-FPM requests.
- Setup now parses `.env` values and generates only missing application/JWT secrets. The JWT package's --always-no option skips even an existing empty JWT_SECRET line, so use its --force option only after confirming that the value is empty. Never print or commit generated secrets.
- Standard API errors include 405 METHOD_NOT_ALLOWED in addition to the planned codes. Validation exposes field errors; unexpected 500 responses never reveal debug details. Preserve HTTP exception headers, including Allow, Retry-After, and rate-limit headers.
- Named login, registration, and API limiters follow the plan. Public Swagger UI/spec/assets/callback routes share a separate configurable IP limiter (60/minute by default). API_DOCUMENTATION_ENABLED controls every documentation route, defaulting off in production when no explicit flag is set.
- Swagger's view requires its OAuth callback route even with bearer-only API authentication; place this package route and spec/assets under /documentation, while documenting /api/documentation itself. Keep schemas and reusable parameter attributes in separate classes because swagger-php does not retain the schema attributes when mixed with parameter attributes on the same class.
- Docker/Composer setup generate the runtime Swagger spec; make docs also exports JSON and YAML into docs/openapi without Node. Both formats are validated by generation and tested for equivalent content.
- Money uses exact bcmath decimal strings, rejects extra precision/scientific notation, and rejects results exceeding DECIMAL(12,2). No floating-point money arithmetic is introduced.
- Tightened .gitignore to exclude .env variants and storage/api-docs while allowing the intentional example/test environment files and docs/openapi exports.
- Verification: 35 tests pass with 129 assertions on MySQL; Pint passes all 74 PHP files. Composer strict validation and platform requirements pass. Rebuilt Docker services are healthy and startup preserves existing application/JWT secrets.
- Live HTTP checks: root, /up, Swagger UI, spec, Swagger CSS, and Swagger JS return 200 through Nginx at localhost:8081. An anonymous /api/auth/me request returns JSON 401 UNAUTHENTICATED without an Accept header. Feature tests cover register/login/me/logout, expiry, validation, per-user/per-IP limits, 429 headers/window reset, and standardized errors.

## Sprint 2 decisions and verification

- User requested Sprints 2 and 3 be implemented together and tested only after both implementations; defer commits until the combined quality gates pass. Keep separate sprint commits and stop before Payments.
- Orders use owner-scoped queries (404 for another owner's records), backed by a registered OrderPolicy. OrderReader exposes only snapshots and requires an active transaction for lockForPayment.
- Order is the aggregate root; OrderItem remains plain data. All order fields are guarded against mass assignment. place/confirm/cancel/replaceItems/changeCustomerDetails/assertDeletable enforce state and item invariants; changeStatus delegates to the intention-revealing domain methods so the service contains no transition rule.
- OrderService only coordinates loading, policy checks, transactions, persistence, and after-commit event dispatch. Its optional-items conditional chooses which supplied data to persist; it does not decide business eligibility. Domain events retain the aggregate reference so newly assigned database ids are available after commit.
- API item prices must be decimal strings to avoid creating floating-point money values during JSON decoding. Quantities fit unsigned integer storage, requests allow at most 1000 items, and aggregate calculations reject DECIMAL(12,2) overflow as 422. Supplied replacement items replace the full collection; omitted fields remain unchanged.
- The plan's temporary NullOrderPaymentGuard is bound until Sprint 4. Both deletion with any payment and cancellation with a successful payment are tested through an injected guard; real payment integration remains unimplemented.
- Order factories construct roots and named states through aggregate methods. Standalone fixtures create a user row through the database rather than importing Auth models across module boundaries. Ledger fixtures will use its recorder to keep real hash chains.
- Combined verification: 84 tests pass with 329 assertions, including database-free aggregate tests, owner-scoped CRUD/status/filter/pagination, validation/overflow/atomicity, payment guards, and after-commit/rollback event behavior. Pint passes all 154 PHP files. Orders OpenAPI generation passes, and every API operation has documentation and rate limiting.
