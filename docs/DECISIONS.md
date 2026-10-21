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

## Sprint 3 decisions and verification

- LedgerEntry is plain data with immutable model guards; writes go through LedgerRecorderInterface and LedgerEntryData. LedgerService imports no Orders/Payments models or services. Order-history ownership is checked by the controller through OrderReaderInterface; payment-scoped history is deliberately wired in Sprint 4 when a payment-ownership contract exists.
- Add a per-order ledger_chain_heads row as a stable mutex/checkpoint. Lock it before the latest entry so simultaneous first appends also serialize; retain the unique order/sequence index and transaction retry support. See the [MySQL locking-read documentation](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html).
- Hash an unambiguous canonical JSON envelope instead of delimiter-concatenated fields. Cover all planned fields plus owner id, gateway, and sequence, and normalize UTC microsecond timestamps. Check the head checkpoint during verification to detect a missing final entry as well as changed rows or broken links.
- Financial event directions are enforced by the ledger recorder: success=credit, reversal=debit, initiation/failure=memo. Strip known card/credential keys recursively from metadata; trusted recorders must supply sanitized provider information. No financial write API or refund endpoint is added.
- MySQL triggers block bulk UPDATE/DELETE even when Eloquent events are bypassed. Integrity tests remove a trigger only in the dedicated test database, restore it in finally, and verify that tampering makes ledger:verify fail. Separate PHP processes synchronize at a barrier to prove two simultaneous first appends produce exactly sequences 1 and 2.
- Idempotency stores SHA-256 of canonical method/path/query/body only; raw request bodies are not persisted. JSON object-key order is normalized while arrays keep their order. Header keys use binary collation for case-sensitive per-user uniqueness and accept 8–255 portable ASCII characters (UUID recommended).
- Store the exact response body as a JSON string inside the response_body JSON column, with status and whitelisted content/location/language headers. Replays preserve response bytes and add Idempotent-Replayed. Do not store/replay cookie or authentication headers; current outer throttle middleware adds current rate headers.
- Reservation completion/release match the row id, user, fingerprint, and processing status, so an expired request cannot overwrite a newer claim. Validation and server errors release claims, mismatched requests return 422, processing claims return 409 with Retry-After: 1, and expiry permits a new claim.
- Register idempotent middleware but do not expose a production dummy endpoint or apply it to Orders; the payment POST opts in during Sprint 4. Register hourly idempotency:prune; scheduler execution remains an operator choice documented in README.
- Combined quality gates ran only after both implementations, as requested: 84 tests passed with 329 assertions; Pint passed 154 files; migrations and OpenAPI JSON/YAML export succeeded; Composer strict validation/platform requirements passed. All 13 API operations have a limiter and OpenAPI operation, and protected routes declare bearer security.
- ledger:verify is clean on the main development database (currently zero financial entries); tests also verify populated chains, reversals, tampering failures, and real concurrent appends. Swagger UI and spec return live HTTP 200 and show Authentication, Orders, Ledger, and Documentation tags. No Sprint 4 payment processing was implemented.

## Sprint 4 decisions

- Implement Sprints 4 and 5 together, then run combined quality gates and make/push separate short Conventional Commits, as requested. Stop before Sprint 6.
- Payment owns eligibility, terminal transitions, request identity, and ledger event selection. PaymentService has no business eligibility conditionals; its replay/create selection only coordinates persistence and avoids another gateway call.
- Serialize initiation on the Orders reader lock. Reject different keys while an attempt is pending with PAYMENT_IN_PROGRESS (409); also block order cancellation while pending. Without these rules, two keys or cancellation could race during the intentionally unlocked gateway call.
- Add a permanent request_hash alongside the binary-collated per-order key. It contains only a SHA-256 fingerprint, allowing the domain safety net to reject changed details even after idempotency-cache pruning. Same-key attempts return existing state without another charge, including pending and failed attempts.
- Run simulated gateway calls between two short transactions. Initiation/payment memo and outcome/ledger write each commit atomically; aggregate events dispatch only after outcome commit. If the outcome cannot be saved, leave the attempt pending for future reconciliation and never charge it again automatically.
- Gateway details use relative field rules, prefixed dynamically by the request. Reject undeclared detail keys. Manager configuration and extend() support injected drivers without editing services, requests, controllers, or schema. History filters accept disabled historical methods.
- Keep only last4/brand for simulated card results. Provider exception text/chains are discarded to avoid persisting or logging credentials; gateway errors produce GATEWAY_ERROR with a clean failed payment resource. PayPal error@ complements fail@ as a deterministic provider-error test case.
- Payment transaction references include the order id and idempotency key, so the same client key used on different orders cannot collide at the simulated provider. Gateway credentials remain configuration-only and the adapters perform no network calls.
- Payments registers its named limiter because its per-order key is module-specific. Shared retains the generic limiter configuration. The POST order is auth, payment throttle, idempotency. Other payment routes use the general API limiter.
- Ledger owns payment-history routing/resources and uses PaymentReaderInterface for ownership. Recorders import no Orders/Payments models or services. Payments uses LedgerEntryData and its public enum value types when constructing financial events.
- Add payment indexes for owner listing and order/status checks, plus restrictive order/user foreign keys. List resources have no relationships to lazy-load and expose no raw provider data, request hash, or idempotency key.
- Combined verification after both implementations: 145 tests pass with 783 assertions; Pint passes 203 PHP files; Composer strict validation/platform requirements pass; OpenAPI JSON/YAML export and all 18 API-operation gates pass. The development payment migration is applied, Swagger/spec return live HTTP 200, anonymous payment POST returns JSON 401, and all three Docker services remain healthy. ledger:verify is clean on the development database (zero entries); populated payment chains are verified in the isolated MySQL tests.
