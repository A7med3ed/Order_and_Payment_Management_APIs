# Order and Payment Management APIs

An API-only Laravel 13 project using PHP 8.3 and MySQL 8.4. Node.js and npm are not required.
Create and confirm orders, process idempotent payments through extensible gateway
strategies, and inspect immutable financial history. Credit-card and PayPal adapters
are deterministic simulations; they do not move real money.

| Guide | Contents |
|---|---|
| [Architecture](docs/ARCHITECTURE.md) | Modules, dependencies, aggregates, data model, payment flow and concurrency |
| [Operations](docs/OPERATIONS.md) | Configuration, troubleshooting, recovery, ledger verification and deployment boundaries |
| [Improvement roadmap](docs/ROADMAP.md) | Known limitations, priorities and acceptance criteria for enhancements |
| [OpenAPI](docs/openapi/openapi.yaml) / [JSON](docs/openapi/openapi.json) | Complete API contract and examples |
| [Postman collection](docs/postman/collection.json) / [environment](docs/postman/local.environment.json) | Runnable walkthrough, saved success/error examples |
| [Decisions](docs/DECISIONS.md) / [implementation plan](PROJECT_PLAN.md) | Tradeoffs, verification evidence and completed delivery checklist |

## Task requirements checklist

The supplied task requires a Laravel API with simulated payment processing. Use
this checklist to locate each requirement and its verification.

| Requirement | Delivered implementation / documentation | Verification |
|---|---|---|
| Create orders with customer details, items, and calculated totals | [Orders module](app/Modules/Orders), `POST /api/orders` | [Order feature tests](tests/Feature/Orders/OrdersTest.php), [aggregate tests](tests/Unit/Orders/OrderTest.php), [money tests](tests/Unit/Shared/MoneyTest.php) |
| Update and delete orders; prevent deletion when any payment exists | `PUT/PATCH/DELETE /api/orders/{order}` | [Order tests](tests/Feature/Orders/OrdersTest.php), [payment tests](tests/Feature/Payments/PaymentsTest.php) |
| List orders and filter pending/confirmed/cancelled statuses | `GET /api/orders?status=pending` | [Order tests](tests/Feature/Orders/OrdersTest.php) |
| Simulate payments with id, order, status, and method; require confirmed orders | [Payments module](app/Modules/Payments), `POST /api/orders/{order}/payments` | [Payment feature tests](tests/Feature/Payments/PaymentsTest.php), [aggregate tests](tests/Unit/Payments/PaymentTest.php), [gateway tests](tests/Unit/Payments/GatewaysTest.php) |
| Add gateways with minimal changes using a strategy pattern | [Gateway interface and registry](app/Modules/Payments/Gateways), [extension guide](#how-to-add-a-payment-gateway) | [Gateway registration tests](tests/Feature/Payments/GatewayManagerTest.php), [HTTP extensibility tests](tests/Feature/Payments/PaymentHardeningTest.php) |
| Configure gateway keys/secrets through environment or database | [Gateway configuration](app/Modules/Payments/Config/payment.php), [.env.example](.env.example) | [Gateway registration tests](tests/Feature/Payments/GatewayManagerTest.php) |
| Retrieve all accessible payments or payments for one order | `GET /api/payments`, `GET /api/orders/{order}/payments`, `GET /api/payments/{payment}` | [Payment tests](tests/Feature/Payments/PaymentsTest.php) |
| RESTful methods/status codes and paginated lists | [API contract](docs/openapi/openapi.yaml), [error contract](#rate-limits-and-error-contract) | [Order tests](tests/Feature/Orders/OrdersTest.php), [payment tests](tests/Feature/Payments/PaymentsTest.php), [API error tests](tests/Feature/Shared/ApiErrorsTest.php) |
| JWT authentication, registration, and login | [Auth module](app/Modules/Auth), `/api/auth/register`, `/api/auth/login` | [Authentication tests](tests/Feature/Auth/AuthenticationTest.php) |
| Validate API inputs and return meaningful errors | Module Form Requests, aggregate invariants, JSON `message`/`code`/`errors` responses | [Authentication](tests/Feature/Auth/AuthenticationTest.php), [order](tests/Feature/Orders/OrdersTest.php), [payment](tests/Feature/Payments/PaymentsTest.php), and [error tests](tests/Feature/Shared/ApiErrorsTest.php) |
| Export grouped API documentation with success/error examples | [Postman collection](docs/postman/collection.json), [environment](docs/postman/local.environment.json), [walkthrough](#postman-walkthrough), [OpenAPI](docs/openapi/openapi.yaml) | [Route/example/export checks](tests/Feature/Shared/DocumentationTest.php) |
| Unit/feature tests, including payment gateway logic | [Unit tests](tests/Unit), [feature tests](tests/Feature), [test commands](#commands-and-quality-checks) | [CI workflow](.github/workflows/ci.yml) runs the complete MySQL suite |
| PSR-12, modular code, and reusable gateway behavior | [Pint configuration](pint.json), [architecture and responsibilities](docs/ARCHITECTURE.md) | `make pint`, aggregate and gateway tests |
| Git repository delivery with code, API export, setup, extension guide, and assumptions | This repository, this README, and the linked Postman/OpenAPI exports | [CI](.github/workflows/ci.yml) checks installation, tests, formatting, and documentation drift |

## Assumptions and task scope

- Orders and payments belong to the authenticated user. List endpoints return
  that user's records; another user's resource returns 404.
- New orders start pending. Customer details/items can be edited only while
  pending. Status changes use the documented state machine; pending or successful
  payments block cancellation, and any payment blocks deletion.
- Each order accepts one successful payment for its full total. Currency comes
  from `PAYMENT_CURRENCY` (USD by default). Partial payments, refunds, and currency
  conversion are future features.
- Item prices are non-negative decimal strings, such as `"19.99"`. Zero-value
  orders are allowed. The server calculates totals using exact decimal arithmetic.
- Payment creation requires `Idempotency-Key`. Reusing a key identifies the same
  attempt, including a failed one; retry a failed payment with a new key. A stored
  attempt returns 201 for either a successful or failed simulated outcome.
- Credit-card and PayPal gateways are deterministic simulators. Environment
  credentials are injectable configuration for future adapters. Real provider
  connections and webhooks are outside the task's simulated-payment requirement.
- Interrupted processing can leave an attempt pending. Automatic reconciliation
  is future work; see [recovery guidance](docs/OPERATIONS.md) and the
  [improvement roadmap](docs/ROADMAP.md).

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
make seed
make test
make pint
```

Windows users can run the same PHP targets with Make inside the app container:

```powershell
docker compose exec -T app make APP_EXEC= migrate
docker compose exec -T app make APP_EXEC= seed
docker compose exec -T app make APP_EXEC= test
docker compose exec -T app make APP_EXEC= pint
```

Seeding is optional and repeatable. It creates `test@example.com` with password
`StrongPass123!` if that email does not exist; it does not reset existing accounts.
These are public development credentials. Register a new account for normal use.

`make pint` checks PSR-12 formatting and strict types. To apply formatting:

```powershell
docker compose exec -T app php vendor/bin/pint
```

Other targets: `build`, `down`, `shell`, `seed`, and `fresh`. `fresh` deletes and
recreates application tables and seeds them; use it only when you intend to reset
local data. `make ledger-verify` verifies ledger hashes and sequence checkpoints. `make docs` generates
OpenAPI and exports JSON/YAML to `docs/openapi/`, then generates the Postman collection
and environment in `docs/postman/`. On Windows:

```powershell
docker compose exec -T app make APP_EXEC= docs
```

Swagger UI is at `/api/documentation` (this workspace:
http://localhost:8081/api/documentation). The spec is served at `/documentation/spec`.
Set `API_DOCUMENTATION_ENABLED=false` to hide the UI, spec, and assets. Without an
explicit flag, documentation is disabled when `APP_ENV=production`.

### Continuous integration

[GitHub Actions](.github/workflows/ci.yml) runs on pushes, pull requests, and manual
dispatches. Each job starts the Docker stack on an isolated runner, validates the
locked Composer installation and platform requirements, checks PSR-12 with Pint,
and runs all unit/feature tests against the dedicated MySQL `app_testing` database.
The documentation tests check route coverage, examples, and current exports before
CI regenerates OpenAPI/Postman and rejects any export drift. Failed jobs print
container logs; cleanup removes only that job's Compose containers and volumes.

Inspect results in the repository's **Actions** tab. These same application checks
can be run locally with the commands above. Newman walkthroughs and dependency/image
scanning remain follow-up CI improvements in the [roadmap](docs/ROADMAP.md).

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
are preserved so existing databases do not recreate tables. Payments owns the gateway
registry, payment aggregate, processing service, and real order-payment guard.
Shared contains API errors, JSON middleware, rate limiters, pagination data, and
exact decimal-string Money helpers using bcmath.

Controllers validate/map DTOs, call a service, and return resources. Order and
Payment application services only orchestrate; their aggregates own
business rules. Other models remain data, except the ledger's immutability guard.
Cross-module access uses contracts and DTOs. Gateway credentials are supplied through
the module configuration; the current adapters are deterministic simulations.

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
`confirmed -> cancelled` when no successful or pending payment exists. Cancelled is terminal.
OrderService coordinates transactions and after-commit events; all state and item
rules are enforced by the aggregate. Its only optional-data conditional selects
whether to persist a supplied replacement item collection.

The Payments provider binds the real order-payment guard. Any payment blocks deletion;
pending attempts also block cancellation until the gateway result is persisted.

## Immutable ledger

`GET /api/orders/{order}/ledger` returns owner-scoped, paginated history in sequence
order. `GET /api/payments/{payment}/ledger` provides payment-scoped history after an
ownership check through PaymentReaderInterface. No write/update/delete ledger endpoints exist.

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

The `idempotent` middleware applies to payment creation. Ordering is authentication,
throttling, then idempotency. Replays count
toward the rate limit. Keys are case-sensitive, scoped per user, and accept 8–255
ASCII letters/numbers/dots/colons/underscores/hyphens; UUIDs are recommended.

| Condition | Result |
|---|---|
| Missing key | 422 `IDEMPOTENCY_KEY_REQUIRED` |
| Same key, completed identical request | Exact stored status/body; `Idempotent-Replayed: true` |
| Same key currently processing | 409 `IDEMPOTENCY_IN_PROGRESS`, `Retry-After: 1` |
| Different method/path/query/body with same key | 422 `IDEMPOTENCY_KEY_REUSED` |
| Validation error or server error/exception | Reservation released for a retry |
| Key expires | Replay-cache claim can be renewed; the permanent payment key still prevents another charge |

Canonical JSON sorts object keys and preserves array order. Only the request hash
is persisted; raw request/card details are not stored. Response bytes are stored
as a JSON string for exact replay; content type/location/language are retained,
while cookies and authentication headers are not copied. Reservation row identity
prevents a late request from changing or releasing a newer claim.

TTL is `IDEMPOTENCY_TTL_HOURS` (24 by default). Run `php artisan idempotency:prune`
manually, or run Laravel's scheduler (`php artisan schedule:work`) to execute the
hourly pruning task. Processing claims expire too; payment key uniqueness and the
permanent request fingerprint remain effective after pruning.

## Payments

All routes require a JWT. Lists are owner-scoped, newest first, and accept `page`
and `per_page` (15 by default, maximum 100). Other users receive 404.

| Method | Endpoint | Behavior |
|---|---|---|
| POST | `/api/orders/{order}/payments` | Process a confirmed order; requires `Idempotency-Key` |
| GET | `/api/payments` | Filter by `status`, historical `method`, and `order_id` |
| GET | `/api/orders/{order}/payments` | Filter and paginate one order's attempts |
| GET | `/api/payments/{payment}` | Read the safe payment resource |
| GET | `/api/payments/{payment}/ledger` | Read immutable events for this attempt |

Example JSON payloads (use simulated data only):

```json
{"method":"credit_card","payment_details":{"card_number":"4242424242424242","expiry_month":12,"expiry_year":2030,"cvv":"123","card_holder":"Jane Doe"}}
```

```json
{"method":"paypal","payment_details":{"email":"payer@example.com"}}
```

Amount and currency come from the order snapshot and `PAYMENT_CURRENCY` (USD by
default). Credit-card details validate digit counts and month/year ranges; these
simulations do not verify card ownership, issuer acceptance, or real expiry.
Card ending `0002` declines with `CARD_DECLINED`; `0003` simulates a provider error.
PayPal `fail@...` declines with `INSUFFICIENT_FUNDS`; `error@...` simulates a provider error.
Other valid details succeed. Transaction references derive from the order and key.
Both successful and failed attempts return **201**, with `status` and `failure_code`.
Gateway errors become failed attempts with `GATEWAY_ERROR`. Raw card/CVV/credentials
are neither stored nor returned; card metadata is limited to last4 and brand.

Payment transitions are `pending -> successful|failed`; both outcomes are terminal.
The first transaction locks the order, applies aggregate eligibility rules, creates
the pending attempt, and appends a memo. The gateway runs outside the transaction.
The second transaction saves the aggregate outcome and ledger event atomically;
domain events dispatch after commit. Successful orders reject another key with
409 `ORDER_ALREADY_PAID`. Failed attempts may be retried with a new key.
A concurrent different key receives 409 `PAYMENT_IN_PROGRESS` while the original
attempt is pending. Retry-After is one second for in-progress requests.

The POST has two configurable limits: `RATE_LIMIT_PAYMENTS_PER_MINUTE` (10 per
user) and `RATE_LIMIT_PAYMENTS_PER_ORDER_PER_MINUTE` (3 per user/order), each per
minute. All payment reads use the general API limiter. Authentication precedes
throttling, which precedes idempotency; replays and invalid requests consume limits.

Known recovery limitation: if execution stops after the initial commit or the
provider charge but before the outcome commit, the attempt stays pending. Retrying
the same key returns that attempt without charging again; other keys and cancellation
remain blocked. Reconciliation is a future operational feature. Inspect provider
records and the ledger before resolving an attempt; do not retry a charge blindly.

Gateways declare their own detail rules. Add an AbstractPaymentGateway subclass,
a `payment.gateways` configuration entry, and environment credentials; no changes
to PaymentService or the HTTP layer are needed. Tests cover both runtime `extend()`
registration and configuration registration through the real HTTP endpoint.
The suite covers
aggregate behavior without a database, HTTP flows, both throttle windows, cache
expiry and permanent payment identity, real concurrent HTTP requests on MySQL,
transaction rollback and after-commit events, third-gateway extensibility, and
OpenAPI coverage/security/rate limits for every API operation. Payment pagination
preserves filters, and query-count tests guard against per-row database queries.
Pint checks all first-party PHP files. Larastan is not installed. Release verification
and exact results are recorded in [the decisions log](docs/DECISIONS.md).

## Postman walkthrough

Import `docs/postman/collection.json` and `docs/postman/local.environment.json` in
Postman. Select the local environment and set its `base_url` to your port (for this
workspace, `http://localhost:8081`). Run the **whole collection in order**. Use a
disposable local database: the walkthrough creates a new account and sample orders
and payments. It leaves financial history available for inspection.

Authentication saves the token; order/payment requests capture ids. Logout runs in
the final folder so earlier requests stay authenticated. Most payment requests use
`{{$guid}}`; the success/replay pair shares `{{replay_key}}`, regenerated once per
run and kept unchanged for that pair. Each request has saved responses derived from
its OpenAPI operation, including applicable 401/403/404/409/422/429 examples.

The runner respects `Retry-After`, waits, and retries a throttled request up to three
times. With default payment limits a full run may take several minutes; allow a
90-second script timeout. For independent requests, run registration/login first,
then set ids to resources you own. A 403 example documents authorization failures;
owner-scoped lookups intentionally return 404 for another user's resource.

Run the same collection without installing Node/npm on the host, following the
[official Newman Docker workflow](https://learning.postman.com/docs/reference/newman-cli/newman-with-docker/):

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path)/docs/postman,target=/etc/newman,readonly" postman/newman:alpine run collection.json -e local.environment.json --env-var base_url=http://host.docker.internal:8081 --timeout-script 90000 --delay-request 200
```

Docker Desktop supplies `host.docker.internal`; on Linux add
`--add-host=host.docker.internal:host-gateway`. Alternatively attach Newman to the
Compose network and use `http://nginx` as `base_url`. The runner is optional tooling;
the application and document generator use PHP only. No secrets/tokens are exported.

Swagger UI is interactive at `/api/documentation`. Import either exported OpenAPI
file into Swagger Editor or Postman to explore the contract. The enriched collection
contains the walkthrough and scripts that a plain OpenAPI import does not include.
Regenerate all exports with `make docs` after changing API attributes.

## How to add a payment gateway

There are three integration steps. These can touch multiple files; tests and API
documentation must also be updated. The example below is another
**simulator**; a real adapter must map provider outcomes and handle unknown outcomes
through the recovery design in [the roadmap](docs/ROADMAP.md).

1. Add `app/Modules/Payments/Gateways/Implementations/BankTransferGateway.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Implementations;

use App\Modules\Payments\Gateways\Abstract\AbstractPaymentGateway;
use App\Modules\Payments\Gateways\DTOs\GatewayChargeRequest;
use App\Modules\Payments\Gateways\DTOs\GatewayChargeResult;

final class BankTransferGateway extends AbstractPaymentGateway
{
    public function method(): string
    {
        return 'bank_transfer';
    }

    public function rules(): array
    {
        return ['reference' => ['required', 'string', 'max:100']];
    }

    protected function performCharge(GatewayChargeRequest $request): GatewayChargeResult
    {
        return GatewayChargeResult::success('bank_'.$request->reference());
    }
}
```

2. Add its entry to `app/Modules/Payments/Config/payment.php` under `gateways`:

```php
'bank_transfer' => [
    'driver' => \App\Modules\Payments\Gateways\Implementations\BankTransferGateway::class,
    'enabled' => env('PAYMENT_BANK_TRANSFER_ENABLED', false),
    'credentials' => ['api_key' => env('BANK_TRANSFER_API_KEY')],
],
```

3. Add these variables to `.env.example` and your ignored `.env`:

```dotenv
PAYMENT_BANK_TRANSFER_ENABLED=true
BANK_TRANSFER_API_KEY=
```

The simulator does not need credentials. A real driver can read injected
`$this->config->credentials` and `$this->config->options`; never return/log secrets
or unredacted provider messages. Drivers return a failed result for a decline.
AbstractPaymentGateway normalizes thrown provider errors to GatewayException.
Rules are relative to `payment_details`, so the existing Form Request prefixes
them dynamically. Payment method storage is a string, with no fixed method enum.
No controller, service, resource, manager, or migration changes are required.

Add a feature test to prove the configuration works through the existing endpoint:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BankTransferGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_transfer_uses_the_existing_payment_flow(): void
    {
        config(['payment.gateways.bank_transfer.enabled' => true]);
        $user = User::factory()->create();
        $order = Order::factory()->confirmed()->create(['user_id' => $user->id]);
        $token = $this->app['auth']->guard('api')->login($user);
        $this->resetAuthentication();

        $this->withToken($token)->postJson('/api/orders/'.$order->id.'/payments', [
            'method' => 'bank_transfer',
            'payment_details' => ['reference' => 'bank-example-001'],
        ], ['Idempotency-Key' => 'bank-payment-001'])
            ->assertCreated()->assertJsonPath('data.status', 'successful')
            ->assertJsonPath('data.method', 'bank_transfer');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }
}
```

Run the tests, Pint, and `make docs`. Also document the new method's request example
under Payments/Docs: dynamic validation works without documentation edits, but the
published API contract should show the new method. Existing ExampleGateway tests
prove both `extend()` and configuration registration without production changes.

## Rate limits and error contract

| Limiter | Scope | Default/minute | Environment variable |
|---|---|---|---|
| auth-login | Normalized email + IP | 5 | `RATE_LIMIT_LOGIN_PER_MINUTE` |
| auth-register | IP | 10 | `RATE_LIMIT_REGISTER_PER_MINUTE` |
| api | Authenticated user | 60 | `RATE_LIMIT_API_PER_MINUTE` |
| payments | User, across orders | 10 | `RATE_LIMIT_PAYMENTS_PER_MINUTE` |
| payments | User + order | 3 | `RATE_LIMIT_PAYMENTS_PER_ORDER_PER_MINUTE` |
| documentation | IP, shared by UI/spec/assets | 60 | `RATE_LIMIT_DOCUMENTATION_PER_MINUTE` |

```json
{"message":"Too many requests. Please try again later.","code":"TOO_MANY_REQUESTS"}
```

429 includes `Retry-After`, `X-RateLimit-Limit`, and `X-RateLimit-Remaining`.
Other errors use the same `message`/`code` envelope; 422 validation errors also have
an `errors` map. Missing/expired JWT is 401, unavailable resource is 404, invalid
method is 405, state conflicts are 409, bad input is 422, and unexpected errors
are sanitized 500 responses. See OpenAPI for endpoint-specific codes and examples.
