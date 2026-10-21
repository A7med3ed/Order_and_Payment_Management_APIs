# Implementation Guide: Extendable Order & Payment Management API

> **Audience:** an AI coding agent working inside the IDE on an **already existing Laravel project**.
> **Your job:** build everything described here, sprint by sprint, without recreating the Laravel project.

---

## 0. Kickoff Prompt (paste this to the agent first)

```
You are working inside an existing Laravel project. Do NOT run `laravel new` or recreate the project.
Read PROJECT_PLAN.md fully, then start with Sprint 0 (audit what already exists).
Work sprint by sprint. For every sprint: list the files you will create/change, implement them,
run `pint` and the tests, tick the checklist items, make one commit per sprint, then report
a short summary and wait for my "continue" before starting the next sprint.
If something in the plan conflicts with what already exists in the project, prefer the
existing conventions, and record the decision in docs/DECISIONS.md.
```

---

## 1. Agent Working Protocol

1. **Audit first, then build.** Before creating anything, inspect: `composer.json` (Laravel and PHP versions), `bootstrap/`, `config/`, `routes/`, `app/`, existing migrations, existing Docker files, `.env.example`, `tests/`. Never overwrite an existing file without reading it first.
2. **Work in sprints** in the order given. Each sprint has a *Definition of Done (DoD)*. Do not start the next sprint until the DoD passes.
3. **One commit per sprint** (Conventional Commits, e.g. `feat(orders): add order CRUD with status transitions`).
4. **Run quality gates** at the end of every sprint: `pint` (PSR-12) and the test suite. Fix everything before committing.
5. **Record decisions and assumptions** in `docs/DECISIONS.md` (one short bullet each: decision, reason).
6. **No placeholders.** Do not leave `TODO` or fake implementations except the intentionally simulated payment gateways.
7. **Ask the human only when blocked.** For ambiguous business rules, use the decisions in this document.

### Global coding rules

- `declare(strict_types=1);` in every PHP file. Follow PSR-12. Use Laravel Pint.
- Prefer `final` classes. Use `readonly` classes for DTOs (target PHP 8.2+; check the project's PHP version and adapt if lower).
- **Controllers:** no business logic. Validate through Form Requests, map validated input to a DTO, call an application service, and return a Resource.
- **Application services (`OrderService`, `PaymentService`):** orchestration only (load, call the domain method, save, dispatch events), with no business `if`s. They coordinate transactions, gateway calls, and ledger writes; business decisions are delegated to aggregates.
- **Aggregates (`Order`, `Payment`):** own all business rules and invariants. State changes happen only through their domain methods.
- **Other models (`OrderItem`, `LedgerEntry`, `IdempotencyKey`):** plain data models, with no business rules except the ledger's immutability guard. `OrderItem` changes are controlled by the `Order` aggregate; idempotency coordination stays in `IdempotencyService`.
- **Services accept and return DTOs or models, never `Request` objects or loose arrays.**
- Use native enums for statuses. Never compare against magic strings.
- **Money**: stored as `DECIMAL(12,2)`, handled in PHP as **strings** with `bcmath` (never floats). Totals are computed server-side only.
- Use DB transactions for multi-step writes. Never hold a DB transaction open during an external (gateway) call.
- No `rand()` or time-dependent behavior in gateways. Simulated outcomes must be deterministic.
- Never log or persist raw card numbers, CVV, or secrets.
- **State changes only through domain methods** (`$order->confirm()`, `$payment->markSuccessful(...)`). Never assign `status` directly from a controller or service (section 7.1).
- Every route is **rate limited** (section 3.5) and **documented in OpenAPI** (section 8.1). A route missing either one is not finished.

---

## 2. Task Requirements (source of truth)

### Core features
| Area | Requirement |
|---|---|
| Orders | Create (user details, items: product name, quantity, price, auto total), update, delete (only if no payments), list all or filter by status (`pending`, `confirmed`, `cancelled`) |
| Payments | Process payment for an order. Fields: payment id, order id, status (`pending`, `successful`, `failed`), method (`credit_card`, `paypal`, ...). Triggers the right gateway logic. View payments per order or all |
| Business rules | Payments only for `confirmed` orders. Orders with payments cannot be deleted |
| API design | RESTful, correct HTTP methods and status codes, pagination on list endpoints |
| Auth | JWT; register and login endpoints |
| Validation | Validate all inputs, meaningful error messages |
| Extensibility | Strategy pattern (or similar). New gateway = minimal changes. Gateway config (API keys, secrets) via `.env` or DB |
| Documentation | Postman collection (success + error examples, organized by Authentication / Orders / Payments) + exported collection file |
| Testing | Unit + feature tests, including payment gateway logic |
| Delivery | Git repo with code, Postman collection, README (setup, gateway extensibility explanation, assumptions) |

### Additional requirements added to this plan
- **Docker** for reproducible environments.
- **Modular structure** (self-contained modules, each with its own provider, routes, migrations, config).
- **DTOs** inside every module for all data crossing a boundary.
- **Idempotency** for payment creation.
- **Immutable, append-only ledger** recording every financial event.
- **Strategy pattern with a proper abstraction layer** for payment gateways.
- **Domain-Driven Design for Orders (and Payments):** aggregates with intention-revealing behavior methods (`confirm()`, `cancel()`, `markSuccessful()`, ...), invariants enforced inside the domain, and domain events (section 7.1).
- **Rate limiting** on all API routes, with stricter limits on authentication and payment endpoints (section 3.5).
- **Swagger / OpenAPI documentation** for every endpoint, shaped to satisfy the task's documentation requirement: organized by functionality, success and error examples, exportable file for import (section 8.1).

### Evaluation criteria (design with these in mind)
API implementation, clean code (PSR-12, patterns, DRY), extensibility (plus documentation of how to add a gateway), documentation quality, testing quality (especially payments).

---

## 3. Architecture

### 3.1 Modules

```
app/Modules/
├── Shared/                               # cross-cutting, NO business logic
│   ├── DTOs/                             # PaginationData
│   ├── Exceptions/                       # ApiException (base), ErrorCode enum
│   ├── Http/
│   │   ├── Middleware/                   # ForceJsonResponse, IdempotencyMiddleware (throttle uses Laravel's)
│   │   └── Traits/ApiResponse.php
│   ├── Idempotency/
│   │   ├── DTOs/                         # IdempotencyContextData, StoredResponseData
│   │   ├── Enums/IdempotencyStatus.php   # processing | completed
│   │   ├── Models/IdempotencyKey.php
│   │   ├── Services/IdempotencyService.php
│   │   └── Database/Migrations/
│   ├── RateLimiting/RateLimiters.php     # named limiters: auth-login, auth-register, api, payments
│   ├── Config/rate_limits.php            # limits read from .env
│   ├── Docs/                             # OpenAPI base: info, bearerAuth, error schemas, reusable params/responses
│   ├── Support/Money.php                 # bcmath helpers (add, mul, compare, format)
│   └── Providers/SharedServiceProvider.php
│
├── Auth/
│   ├── DTOs/                             # RegisterData, LoginData, TokenData
│   ├── Http/{Controllers,Requests,Resources}/
│   ├── Models/User.php
│   ├── Services/AuthService.php
│   ├── Database/{Migrations,Factories}/
│   ├── Docs/                             # OpenAPI attributes (paths, schemas, examples)
│   ├── Routes/api.php
│   └── Providers/AuthServiceProvider.php
│
├── Orders/
│   ├── Contracts/
│   │   ├── OrderReaderInterface.php          # used by Payments (read + lock an order)
│   │   └── OrderPaymentGuardInterface.php    # implemented by Payments (has payments?)
│   ├── DTOs/                             # CreateOrderData, UpdateOrderData, OrderItemData,
│   │                                     # ChangeOrderStatusData, OrderFilterData, OrderSnapshotData
│   ├── Domain/                           # DDD building blocks (see section 7.1)
│   │   ├── Enums/OrderStatus.php         # state machine: canTransitionTo()
│   │   ├── Events/                       # OrderPlaced, OrderConfirmed, OrderCancelled, OrderItemsReplaced
│   │   └── Exceptions/                   # InvalidStatusTransition, OrderNotEditable, OrderHasPayments, ...
│   ├── Http/{Controllers,Requests,Resources}/
│   ├── Models/{Order,OrderItem}.php      # Order = aggregate root, OrderItem = child entity
│   ├── Policies/OrderPolicy.php
│   ├── Services/{OrderService,OrderReader}.php   # OrderService = thin application service
│   ├── Database/{Migrations,Factories}/
│   ├── Docs/                             # OpenAPI attributes (paths, schemas, examples)
│   ├── Routes/api.php
│   └── Providers/OrdersServiceProvider.php
│
├── Ledger/                               # immutable, append-only financial record
│   ├── Contracts/{LedgerRecorderInterface,LedgerReaderInterface}.php
│   ├── DTOs/LedgerEntryData.php
│   ├── Enums/{LedgerEntryType,LedgerDirection}.php
│   ├── Exceptions/ImmutableLedgerException.php
│   ├── Models/LedgerEntry.php            # blocks update/delete
│   ├── Services/{LedgerService,LedgerHasher,LedgerVerifier}.php
│   ├── Console/VerifyLedgerCommand.php   # php artisan ledger:verify
│   ├── Http/{Controllers,Resources}/     # read-only
│   ├── Database/{Migrations,Factories}/  # includes DB triggers
│   ├── Docs/                             # OpenAPI attributes (paths, schemas, examples)
│   ├── Routes/api.php
│   └── Providers/LedgerServiceProvider.php
│
└── Payments/
    ├── Gateways/                         # STRATEGY layer
    │   ├── Contracts/PaymentGatewayInterface.php
    │   ├── Abstract/AbstractPaymentGateway.php
    │   ├── DTOs/{GatewayChargeRequest,GatewayChargeResult,GatewayConfigData}.php
    │   ├── Exceptions/GatewayException.php
    │   ├── Implementations/{CreditCardGateway,PayPalGateway}.php
    │   └── PaymentGatewayManager.php     # registry/factory
    ├── DTOs/                             # ProcessPaymentData, PaymentFilterData, PaymentData
    ├── Enums/PaymentStatus.php           # state machine: canTransitionTo()
    ├── Exceptions/                       # OrderNotConfirmedException, OrderAlreadyPaidException, ...
    ├── Http/{Controllers,Requests,Resources}/
    ├── Models/Payment.php                # aggregate root: initiate(), markSuccessful(), markFailed()
    ├── Events/                           # PaymentSucceeded, PaymentFailed
    ├── Rules/SupportedPaymentMethod.php
    ├── Services/{PaymentService,OrderPaymentGuard}.php
    ├── Config/payment.php
    ├── Database/{Migrations,Factories}/
    ├── Docs/                             # OpenAPI attributes (paths, schemas, examples)
    ├── Routes/api.php
    └── Providers/PaymentsServiceProvider.php

docker/{php/Dockerfile, nginx/default.conf}
docker-compose.yml
Makefile
docs/{DECISIONS.md, openapi/openapi.{json,yaml}, postman/*.json}
tests/{Unit,Feature}/<Module>/...
```

### 3.2 Module dependency rules

```
        ┌──────────┐
        │  Shared  │  ◄── every module may depend on Shared
        └──────────┘

 Payments ──► Orders   (only via Orders\Contracts + OrderSnapshotData DTO)
 Payments ──► Ledger   (only via Ledger\Contracts + LedgerEntryData DTO)
 Orders   ──► (nothing) it defines OrderPaymentGuardInterface; Payments implements it
 Ledger   ──► (nothing) uses plain ids, never imports Orders/Payments models
 Auth     ──► (nothing)
```

1. **No module imports another module's Eloquent models, services, or controllers.** Cross-module communication uses Contracts and DTOs only (dependency inversion).
2. Each module's provider registers its **own** routes, migrations, config, bindings, policies, and console commands.
3. All providers are listed in `bootstrap/providers.php` (Laravel 11+) or `config/app.php` (older).
4. `Shared` must stay generic. If code mentions "order" or "payment", it does not belong in `Shared`.
5. Foreign keys between module tables are allowed at the DB level (referential integrity), but code-level coupling is not.

### 3.3 DTO conventions

- Location: `Modules/<Module>/DTOs/`. One class per use case.
- `final readonly class`, constructor-promoted public properties, fully typed.
- Created through named constructors: `fromRequest(...)` is a Form Request method `toDto()`, and `fromModel(...)` is a static factory where needed.
- Flow: **FormRequest → `toDto()` → Controller → Service(DTO) → Model → Resource**.
- Money in DTOs is a `string` (e.g. `"19.99"`). Dates are `CarbonImmutable`.
- DTOs contain no validation or persistence logic, only data and light helpers (`toArray()`).
- Example:

```php
final readonly class OrderItemData
{
    public function __construct(
        public string $productName,
        public int $quantity,
        public string $price,
    ) {}
}

final readonly class CreateOrderData
{
    /** @param list<OrderItemData> $items */
    public function __construct(
        public int $userId,
        public string $customerName,
        public string $customerEmail,
        public array $items,
    ) {}
}
```

**DTO inventory**

| Module | DTOs |
|---|---|
| Shared | `PaginationData`, `IdempotencyContextData`, `StoredResponseData` |
| Auth | `RegisterData`, `LoginData`, `TokenData` |
| Orders | `CreateOrderData`, `UpdateOrderData`, `OrderItemData`, `ChangeOrderStatusData`, `OrderFilterData`, `OrderSnapshotData` (read model exposed to Payments) |
| Ledger | `LedgerEntryData` |
| Payments | `ProcessPaymentData`, `PaymentFilterData`, `PaymentData`; gateway layer: `GatewayChargeRequest`, `GatewayChargeResult`, `GatewayConfigData` |

### 3.4 Standard error format (all modules)

```json
{
  "message": "Human readable message",
  "code": "ORDER_NOT_CONFIRMED",
  "errors": { "field": ["details (validation only)"] }
}
```

| HTTP | When | Example `code` |
|---|---|---|
| 401 | Missing/invalid/expired token | `UNAUTHENTICATED` |
| 403 | Not the owner / not allowed | `FORBIDDEN` |
| 404 | Resource not found | `NOT_FOUND` |
| 409 | State conflict | `ORDER_HAS_PAYMENTS`, `ORDER_NOT_CONFIRMED`, `ORDER_ALREADY_PAID`, `INVALID_STATUS_TRANSITION`, `IDEMPOTENCY_IN_PROGRESS` |
| 422 | Validation / bad input | `VALIDATION_ERROR`, `IDEMPOTENCY_KEY_REQUIRED`, `IDEMPOTENCY_KEY_REUSED` |
| 429 | Rate limit exceeded | `TOO_MANY_REQUESTS` |
| 500 | Unexpected | `SERVER_ERROR` (never leak internals) |

Use an `ErrorCode` enum in `Shared` and a base `ApiException` that carries `httpStatus`, `code`, `message`. API routes must always return JSON (even without an `Accept` header).

### 3.5 Rate Limiting

Protect the API from abuse, brute force, and card testing. Use Laravel's `RateLimiter::for()` named limiters, defined in `Shared/RateLimiting/RateLimiters.php`, registered by `SharedServiceProvider`, and applied per route group with `throttle:<name>`. Every number is configurable through `.env` via `Shared/Config/rate_limits.php` (never hard-coded in route files).

| Limiter | Applies to | Counted per | Default | Env variable |
|---|---|---|---|---|
| `auth-login` | `POST /api/auth/login` | lowercase email + IP | 5 / minute | `RATE_LIMIT_LOGIN_PER_MINUTE` |
| `auth-register` | `POST /api/auth/register` | IP | 10 / minute | `RATE_LIMIT_REGISTER_PER_MINUTE` |
| `api` | all other authenticated routes | user id (IP fallback) | 60 / minute | `RATE_LIMIT_API_PER_MINUTE` |
| `payments` | `POST /api/orders/{order}/payments` | user id, **and** a second limit per user id + order id | 10 / minute per user, 3 / minute per user per order | `RATE_LIMIT_PAYMENTS_PER_MINUTE`, `RATE_LIMIT_PAYMENTS_PER_ORDER_PER_MINUTE` |

Rules:
1. Exceeded limits return **429** in the standard error format (`code: TOO_MANY_REQUESTS`) with `Retry-After`, `X-RateLimit-Limit`, and `X-RateLimit-Remaining` headers. Map `ThrottleRequestsException` in the global exception handler.
2. Middleware order on the payment POST: `auth:api` → `throttle:payments` → `idempotent`. Idempotent replays still count toward the limit (simple and safe; document it).
3. Never key a limiter on something the client controls freely (such as a custom header). Key on the authenticated user id or the IP.
4. Optional (cut first if time is short): limit failed payment attempts per user per hour (default 5) as card-testing protection, returning 429.
5. Production should use the Redis cache driver for limiter state. Docker may include an optional `redis` service; tests use the `array` driver.
6. The 429 response is documented as a reusable OpenAPI response and as a Postman example.

---

## 4. Strategy Pattern for Payment Gateways (abstraction design)

### 4.1 Layers

```
PaymentService  ──uses──►  PaymentGatewayManager  ──resolves──►  PaymentGatewayInterface
                              (registry/factory)                        ▲
                                                            AbstractPaymentGateway   (template method)
                                                              ▲                ▲
                                                    CreditCardGateway     PayPalGateway   (+ any future gateway)
```

- `PaymentService` knows **only** the interface and the manager. It never contains `if ($method === 'paypal')`.
- `AbstractPaymentGateway` implements the shared flow (the template method) so each concrete gateway only writes its provider-specific part.
- The manager resolves gateways from `config/payment.php` through the container, so constructor dependencies are injected.

### 4.2 Contract

```php
interface PaymentGatewayInterface
{
    /** Unique method key, e.g. "credit_card". Must match the config key. */
    public function method(): string;

    /** Validation rules for the method-specific `payment_details` payload. */
    public function rules(): array;

    /** Execute the charge. Must NOT throw for declines; return a failed result instead. */
    public function charge(GatewayChargeRequest $request): GatewayChargeResult;
}
```

Because each gateway declares its own `rules()`, the payment request validates `payment_details` dynamically from the chosen gateway. Adding a gateway needs **no** change to the Form Request.

### 4.3 Abstract base (template method)

```php
abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(protected readonly GatewayConfigData $config) {}

    final public function charge(GatewayChargeRequest $request): GatewayChargeResult
    {
        try {
            return $this->performCharge($request);
        } catch (Throwable $e) {
            // Provider/network errors are normalized, never leaked raw
            throw new GatewayException($this->method(), $e->getMessage(), previous: $e);
        }
    }

    abstract protected function performCharge(GatewayChargeRequest $request): GatewayChargeResult;
}
```

Optional extension points (define the interfaces, implement only when needed): `RefundableGatewayInterface` (`refund(...)`), `WebhookCapableInterface`. They demonstrate that the abstraction is open for extension, but no endpoints are required for them.

### 4.4 Gateway DTOs

```php
final readonly class GatewayChargeRequest
{
    public function __construct(
        public int $paymentId,
        public int $orderId,
        public string $amount,          // "129.90"
        public string $currency,        // "USD"
        public string $idempotencyKey,  // forwarded to the provider as its own idempotency reference
        public array $details,          // validated, SANITIZED method payload (no raw card numbers/CVV)
    ) {}
}

final readonly class GatewayChargeResult
{
    private function __construct(
        public bool $successful,
        public ?string $transactionId,
        public ?string $errorCode,
        public string $message,
        public array $rawResponse,      // sanitized
    ) {}

    public static function success(string $transactionId, string $message = 'OK', array $raw = []): self { /* ... */ }
    public static function failure(string $errorCode, string $message, array $raw = []): self { /* ... */ }
}

final readonly class GatewayConfigData
{
    public function __construct(
        public string $method,
        public bool $enabled,
        public array $credentials,      // api_key, secret, ... from .env
        public array $options = [],
    ) {}
}
```

### 4.5 Manager (registry/factory)

```php
final class PaymentGatewayManager
{
    public function resolve(string $method): PaymentGatewayInterface;   // throws UnsupportedPaymentMethodException
    public function extend(string $method, string|Closure $gateway): void; // runtime registration (used by tests)
    /** @return list<string> */
    public function supportedMethods(): array;                          // enabled methods only
}
```

### 4.6 Configuration (`Modules/Payments/Config/payment.php`, merged by the provider)

```php
return [
    'currency' => env('PAYMENT_CURRENCY', 'USD'),
    'gateways' => [
        'credit_card' => [
            'driver'      => CreditCardGateway::class,
            'enabled'     => env('PAYMENT_CREDIT_CARD_ENABLED', true),
            'credentials' => [
                'api_key' => env('CREDIT_CARD_API_KEY'),
                'secret'  => env('CREDIT_CARD_SECRET'),
            ],
        ],
        'paypal' => [
            'driver'      => PayPalGateway::class,
            'enabled'     => env('PAYMENT_PAYPAL_ENABLED', true),
            'credentials' => [
                'client_id' => env('PAYPAL_CLIENT_ID'),
                'secret'    => env('PAYPAL_SECRET'),
            ],
        ],
    ],
];
```

`payment_method` is **not** a hard-coded enum. It is validated by `SupportedPaymentMethod` (a Rule reading `manager->supportedMethods()`), and stored as a string. This removes one step from the "add a gateway" procedure.

### 4.7 "Add a new gateway" must require exactly

1. One new class extending `AbstractPaymentGateway` (implement `method()`, `rules()`, `performCharge()`).
2. One new entry in `config/payment.php`.
3. Credentials in `.env` / `.env.example`.

No change to `PaymentService`, controllers, requests, resources, migrations, or the manager. **Prove this with a test** (Sprint 5) and document it in the README (Sprint 6).

### 4.8 Simulated gateways (deterministic)

- `CreditCardGateway`: `details` = `{ card_number, expiry_month, expiry_year, cvv, card_holder }`. Card ending `0002` → declined (`CARD_DECLINED`); ending `0003` → throws provider error (tests gateway exception path); anything else → success with transaction id `cc_<hash>`. Only `last4` and `brand` are kept in any stored response.
- `PayPalGateway`: `details` = `{ email }`. Emails starting with `fail@` → declined (`INSUFFICIENT_FUNDS`); otherwise success with `pp_<hash>`.
- Transaction ids are derived deterministically from the idempotency key (e.g. `hash('crc32b', $key)`), so replays are stable.

---

## 5. Idempotency (payments)

### 5.1 Contract with the client

- `POST /api/orders/{order}/payments` **requires** the header `Idempotency-Key` (a client-generated UUID, 8–255 characters).
- Retrying the same request with the same key must **never** create a second payment or a second charge.

| Situation | Behavior |
|---|---|
| Header missing | 422 `IDEMPOTENCY_KEY_REQUIRED` |
| New key | Process normally, then store the response |
| Same key, same request, already completed | Return the **stored** response (same status code and body) with header `Idempotent-Replayed: true`. No side effects |
| Same key, still processing (concurrent request) | 409 `IDEMPOTENCY_IN_PROGRESS` with `Retry-After: 1` |
| Same key, **different** payload/endpoint | 422 `IDEMPOTENCY_KEY_REUSED` |
| Response status ≥ 500 or uncaught exception | Release the key so the client can retry safely |
| Validation error (422 `VALIDATION_ERROR`) | Release the key (nothing was executed) |
| Key older than the TTL (24h, configurable) | Treated as new (cleaned by a scheduled prune command) |

### 5.2 Implementation (in `Shared/Idempotency`, reusable by any endpoint)

Table `idempotency_keys`:

| Column | Notes |
|---|---|
| `id` | bigint PK |
| `user_id` | FK users |
| `key` | string(255) |
| `request_hash` | char(64), `sha256(METHOD + path + canonical JSON body)` |
| `status` | `processing` / `completed` |
| `response_status` | smallint, nullable |
| `response_body` | json, nullable |
| `expires_at` | timestamp |
| timestamps | |
| **Unique** | `(user_id, key)` |

Algorithm in `IdempotencyService` (used by `IdempotencyMiddleware`, applied only to routes that opt in with `->middleware('idempotent')`):

1. Validate header presence/format.
2. Compute `request_hash`.
3. `INSERT` a `processing` row. A unique-violation means the key exists, so load it:
   - hash mismatch → 422 `IDEMPOTENCY_KEY_REUSED`
   - `completed` → replay the stored response
   - `processing` → 409 `IDEMPOTENCY_IN_PROGRESS`
4. Run the request. On completion, persist `response_status`/`response_body`, set `completed`.
5. On exception/5xx/validation failure, delete the row.

**Second safety net (domain level):** `payments` has a nullable `idempotency_key` column with a unique index on `(order_id, idempotency_key)`. If `PaymentService` finds an existing payment for the same key, it returns it instead of creating another. The key is also forwarded to the gateway as its idempotency reference, and recorded in the ledger.

**Business-level protection:** an order that already has a `successful` payment cannot be charged again: 409 `ORDER_ALREADY_PAID`. A `failed` payment can be retried using a **new** idempotency key.

---

## 6. Immutable Ledger

### 6.1 Purpose
Every financial event is recorded as an **append-only** entry. The `payments` table holds the current (mutable) status; the **ledger is the source of truth** for what happened and in which order. Corrections are made by appending new entries (e.g. reversals), never by editing or deleting.

### 6.2 Table `ledger_entries`

| Column | Type / notes |
|---|---|
| `id` | ULID, PK |
| `order_id` | unsigned bigint, indexed (chain scope) |
| `payment_id` | unsigned bigint, nullable, indexed |
| `user_id` | unsigned bigint |
| `chain_sequence` | unsigned int, **unique with `order_id`**: 1, 2, 3, ... per order |
| `type` | `payment_initiated`, `payment_succeeded`, `payment_failed`, `payment_reversed` |
| `direction` | `credit` (money in), `debit` (money out), `memo` (no balance effect) |
| `amount` | `DECIMAL(12,2)` |
| `currency` | char(3) |
| `gateway` | string, nullable |
| `transaction_id` | string, nullable |
| `idempotency_key` | string, nullable |
| `metadata` | json, sanitized |
| `previous_hash` | char(64), nullable (null for the first entry in a chain) |
| `entry_hash` | char(64) |
| `created_at` | `timestamp(6)`, **no `updated_at` column** |

Order balance = `SUM(credit) − SUM(debit)` over its entries.

**Entries written by the payment flow:** `payment_initiated` (memo) → then `payment_succeeded` (credit) **or** `payment_failed` (memo). `payment_reversed` (debit) is supported by `LedgerService` and tested, but there is no refund endpoint (out of scope).

### 6.3 Four layers of immutability

1. **Model layer:** `LedgerEntry` has `const UPDATED_AT = null`, throws `ImmutableLedgerException` on `updating`, `deleting`, and `saving` of an existing record. Mass operations (`query()->update()/delete()`) bypass events, hence layer 2.
2. **Database layer:** migration creates MySQL triggers `BEFORE UPDATE` and `BEFORE DELETE` on `ledger_entries` that `SIGNAL SQLSTATE '45000'`. Create them with `DB::unprepared()`; guard by driver (skip on SQLite). Add a README note recommending a production DB user without `UPDATE`/`DELETE` grants on this table.
3. **API layer:** `LedgerRecorderInterface` exposes only `record(LedgerEntryData): void`. `LedgerReaderInterface` is read-only. No write endpoints exist.
4. **Tamper evidence (hash chain):**
   `entry_hash = sha256(previous_hash | id | order_id | payment_id | type | direction | amount | currency | transaction_id | idempotency_key | created_at (ISO, microseconds) | canonical_json(metadata))`
   Each entry stores the previous entry's hash, scoped per order. `php artisan ledger:verify [--order=ID]` recomputes the chain and reports any break.

### 6.4 Concurrency
Appending must lock the order's chain: inside a transaction, lock the latest entry for that `order_id` (`lockForUpdate`), compute `chain_sequence = last + 1`, hash, insert. The unique `(order_id, chain_sequence)` index guarantees the chain cannot fork even under races.

### 6.5 Read endpoints (read-only, owner-scoped)
`GET /api/orders/{order}/ledger`, `GET /api/payments/{payment}/ledger`, both paginated, ordered by `chain_sequence`.

---

## 7. Orders: Business Decisions and Domain Model (DDD)

| Topic | Decision |
|---|---|
| Order user details | `customer_name`, `customer_email` on the order (plus `user_id` of the owner) |
| Ownership | Users only see and modify their own orders (Policy). Other users' orders return 404 or 403 consistently (pick one, document it) |
| Status transitions | `pending → confirmed`, `pending → cancelled`, `confirmed → cancelled` (only if no `successful` payment). `cancelled` is terminal |
| How an order becomes `confirmed` | `PATCH /api/orders/{order}/status` with `{ "status": "confirmed" }` |
| Editing | Items/details can be updated only while the order is `pending` (otherwise 409 `INVALID_STATUS_TRANSITION`/`ORDER_NOT_EDITABLE`) |
| Delete | Blocked with 409 `ORDER_HAS_PAYMENTS` if any payment exists (any status) |
| Total | `SUM(quantity × price)` computed server-side with bcmath; client-sent totals are ignored |
| Payments vs orders | `Orders` calls `OrderPaymentGuardInterface::hasPayments()` and `hasSuccessfulPayment()`; implemented by `Payments` |

### 7.1 Domain-Driven Design for Orders (and Payments)

Pragmatic DDD inside Laravel: Eloquent stays the persistence mechanism, but **business behavior and invariants live in the domain model**. Controllers and services never decide business rules; they ask the aggregate to do something and the aggregate accepts or refuses.

**Ubiquitous language** (use these exact terms in class names, method names, error messages, and docs): *place* an order, *confirm*, *cancel*, *replace items*, *editable*, *payable*, *paid*.

**Building blocks**

| DDD concept | In this project |
|---|---|
| Aggregate root | `Order` model: the only entry point for changing an order or its items |
| Child entity | `OrderItem`: never saved or modified directly, only through the root |
| Value objects | `Money` (Shared), `OrderStatus` enum (state machine), optional `CustomerDetails` |
| Domain behavior | `Order::place()`, `confirm()`, `cancel()`, `replaceItems()`, `changeCustomerDetails()`, `assertDeletable()`, `canBeEdited()`, `canBePaid()`, `isOwnedBy()` |
| Domain events | `OrderPlaced`, `OrderConfirmed`, `OrderCancelled`, `OrderItemsReplaced`: recorded by the aggregate, dispatched after commit by the application service |
| Domain exceptions | `InvalidStatusTransitionException`, `OrderNotEditableException`, `OrderHasPaymentsException`, `EmptyOrderException` (in `Domain/Exceptions`, extend `ApiException` so they carry code and HTTP status) |
| Application service | `OrderService`: load → call domain method → persist in a transaction → dispatch events. **No business `if`s** |
| Domain service contract | `OrderPaymentGuardInterface`: the order domain asks "does this order have payments?" and Payments answers |

**Invariants** (enforced inside the aggregate, never in controllers):
1. An order always has at least one item; each item has `quantity ≥ 1` and `price ≥ 0`.
2. `total_amount` always equals `SUM(quantity × price)`. The aggregate recalculates it whenever items change. It can never be set from outside.
3. Status changes follow the state machine: `pending → confirmed`, `pending → cancelled`, `confirmed → cancelled`. `cancelled` is terminal.
4. Only `pending` orders are editable.
5. An order with a `successful` payment cannot be cancelled.
6. An order with any payment cannot be deleted.
7. Only a `confirmed` order is payable. `OrderSnapshotData` exposes a `payable` flag computed from `canBePaid()`, so Payments never re-implements this rule.

**State machines**

```
Order:    pending ──confirm()──► confirmed ──cancel()──► cancelled (terminal)
             └──────────────cancel()──────────────────────────┘

Payment:  pending ──markSuccessful()──► successful (terminal)
             └─────markFailed()───────► failed     (terminal)
```

**Aggregate sketch** (adapt to the project; keep the behavior methods and invariants)

```php
class Order extends Model
{
    protected $fillable = [];   // nothing is mass assignable: state changes go through domain methods
    protected $casts = ['status' => OrderStatus::class];

    /** @var list<object> */
    private array $recordedEvents = [];

    /** @param list<OrderItemData> $items */
    public static function place(int $userId, string $customerName, string $customerEmail, array $items): self
    {
        $order = new self();
        $order->user_id = $userId;
        $order->customer_name = $customerName;
        $order->customer_email = $customerEmail;
        $order->status = OrderStatus::Pending;
        $order->applyItems($items);              // checks invariants 1-2, sets total
        $order->record(new OrderPlaced($userId));

        return $order;
    }

    public function confirm(): void
    {
        $this->transitionTo(OrderStatus::Confirmed);
        $this->record(new OrderConfirmed($this->getKey()));
    }

    public function cancel(OrderPaymentGuardInterface $payments): void
    {
        if ($payments->hasSuccessfulPayment($this->getKey())) {
            throw OrderHasPaymentsException::cannotCancel($this->getKey());
        }
        $this->transitionTo(OrderStatus::Cancelled);
        $this->record(new OrderCancelled($this->getKey()));
    }

    /** @param list<OrderItemData> $items */
    public function replaceItems(array $items): void
    {
        $this->assertEditable();
        $this->applyItems($items);
        $this->record(new OrderItemsReplaced($this->getKey()));
    }

    public function assertDeletable(OrderPaymentGuardInterface $payments): void
    {
        if ($payments->hasPayments($this->getKey())) {
            throw OrderHasPaymentsException::cannotDelete($this->getKey());
        }
    }

    public function canBeEdited(): bool { return $this->status === OrderStatus::Pending; }
    public function canBePaid(): bool   { return $this->status === OrderStatus::Confirmed; }
    public function isOwnedBy(int $userId): bool { return $this->user_id === $userId; }

    private function transitionTo(OrderStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw InvalidStatusTransitionException::between($this->status, $target);
        }
        $this->status = $target;
    }

    // applyItems(), assertEditable(), record(), releaseEvents() omitted for brevity.
    // applyItems() swaps the in-memory items and recalculates the total with Money;
    // OrderService persists the root and its items in ONE transaction.
}
```

**Application service sketch**

```php
public function changeStatus(ChangeOrderStatusData $data): Order
{
    return DB::transaction(function () use ($data): Order {
        $order = Order::query()->lockForUpdate()->findOrFail($data->orderId);

        match ($data->targetStatus) {
            OrderStatus::Confirmed => $order->confirm(),
            OrderStatus::Cancelled => $order->cancel($this->payments),
            default => throw InvalidStatusTransitionException::between($order->status, $data->targetStatus),
        };

        $order->save();
        DB::afterCommit(fn () => $this->events->dispatchAll($order->releaseEvents()));

        return $order;
    });
}
```

**Rules**
- `PATCH /api/orders/{order}/status` stays as the HTTP surface; the service maps the requested status to `confirm()` or `cancel()`. (Intention-revealing endpoints such as `POST /orders/{order}/confirm` and `/cancel` are an acceptable alternative: pick one and document it.)
- **The same pattern applies to `Payment`:** `Payment::initiate(...)`, `markSuccessful(GatewayChargeResult)`, `markFailed(string $code, string $message)`. `PaymentStatus::canTransitionTo()` allows `pending → successful|failed`, and both are terminal. Any other transition throws `InvalidPaymentTransitionException`. `status` is not mass assignable. Events: `PaymentSucceeded`, `PaymentFailed`.
- The ledger is **not** written by event listeners. `PaymentService` writes ledger entries explicitly inside the same transaction as the state change, so a lost listener can never lose a financial record. Domain events are for in-module reactions (logging, notifications, future features).
- `LedgerEntry` is the extreme case: no behavior except being appended (section 6).
- Cross-module rules go through contracts (`OrderPaymentGuardInterface`, `OrderSnapshotData`), never through importing another module's aggregate.
- Domain unit tests run **without a database** wherever possible (build the aggregate in memory and assert behavior, exceptions, and recorded events).

---

## 8. API Surface

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| POST | `/api/auth/register` | no | 201 |
| POST | `/api/auth/login` | no | 200 + JWT |
| POST | `/api/auth/logout` | yes | invalidates token |
| GET | `/api/auth/me` | yes | |
| GET | `/api/orders` | yes | `?status=&per_page=&page=` |
| POST | `/api/orders` | yes | 201 |
| GET | `/api/orders/{order}` | yes | |
| PUT/PATCH | `/api/orders/{order}` | yes | only while `pending` |
| PATCH | `/api/orders/{order}/status` | yes | state machine |
| DELETE | `/api/orders/{order}` | yes | 204, or 409 if payments exist |
| POST | `/api/orders/{order}/payments` | yes | **`Idempotency-Key` required**, 201 |
| GET | `/api/orders/{order}/payments` | yes | paginated |
| GET | `/api/payments` | yes | `?status=&method=&per_page=` |
| GET | `/api/payments/{payment}` | yes | |
| GET | `/api/orders/{order}/ledger` | yes | read-only, paginated |
| GET | `/api/payments/{payment}/ledger` | yes | read-only, paginated |
| GET | `/api/documentation` | no | Swagger UI (non-production, or behind an env flag) |

All authenticated routes are rate limited (section 3.5) and return the rate-limit headers.

Process-payment request body:
```json
{
  "method": "credit_card",
  "payment_details": {
    "card_number": "4242424242424242",
    "expiry_month": 12,
    "expiry_year": 2030,
    "cvv": "123",
    "card_holder": "Jane Doe"
  }
}
```
A payment attempt that reaches the gateway returns **201** with the payment resource whether its resulting `status` is `successful` or `failed` (the failure reason is in the resource). Pre-condition failures (not confirmed, already paid, unsupported method) return 409 or 422 and create **no** payment.

### 8.1 API Documentation (Swagger / OpenAPI)

The task asks for documentation in Postman "or similar tools" with detailed request/response examples (success and error), endpoints organized by functionality, and an exportable file for easy import. This project satisfies that with **OpenAPI 3 + Swagger UI as the source of truth**, plus a **Postman collection generated from it**.

**Tooling:** `darkaonline/l5-swagger` (swagger-php with PHP 8 attributes `#[OA\...]`). Configure the scan paths to `app/Modules`. Keep controllers thin: each module has a `Docs/` folder with classes that contain only OpenAPI attributes (path operations and schemas), for example `Modules/Orders/Docs/OrderEndpointsDoc.php` and `OrderSchemas.php`.

**Required shape (mapped to the task PDF)**

| Task requirement | OpenAPI implementation |
|---|---|
| Organized by functionality | Tags `Authentication`, `Orders`, `Payments`, `Ledger`, each with a description. Every operation has exactly one tag |
| Detailed request examples | `requestBody` with named `examples` (`CreateOrder`, `UpdateOrder`, `ConfirmOrder`, `PayWithCreditCard`, `PayWithPayPal`, ...) |
| Success responses | `200/201/204` with a schema and a named example per case (including a **failed payment** as a 201 with `status: failed`) |
| Error cases | Every operation documents the relevant `401, 403, 404, 409, 422, 429` responses with named examples using the real `code` values: `ORDER_NOT_CONFIRMED`, `ORDER_ALREADY_PAID`, `ORDER_HAS_PAYMENTS`, `INVALID_STATUS_TRANSITION`, `IDEMPOTENCY_KEY_REQUIRED`, `IDEMPOTENCY_KEY_REUSED`, `IDEMPOTENCY_IN_PROGRESS`, `VALIDATION_ERROR`, `TOO_MANY_REQUESTS` |
| Collection export for easy import | `docs/openapi/openapi.json` and `openapi.yaml` committed to the repo, and a Postman collection generated from them in `docs/postman/` |

**Shared components** (in `Shared/Docs`)
- `info`: title, version, description with short notes on authentication, idempotency, and rate limiting.
- Security scheme `bearerAuth` (HTTP bearer, JWT).
- Schemas: `ErrorResponse`, `ValidationErrorResponse`, `PaginationLinks`, `PaginationMeta`.
- Reusable parameters: `Page`, `PerPage`, `IdempotencyKey` (header, required on the payment POST, with the replay/conflict behavior described).
- Reusable responses: `Unauthenticated`, `Forbidden`, `NotFound`, `Conflict`, `ValidationError`, `TooManyRequests` (documenting `Retry-After` and `X-RateLimit-*` headers).

**Generation and export**
- `php artisan l5-swagger:generate` writes the spec; Swagger UI is served at `/api/documentation` (enabled outside production or behind an env flag).
- `make docs` generates the spec, copies it to `docs/openapi/openapi.json`, and converts it to `openapi.yaml`.
- Postman: import the OpenAPI file directly, or run `openapi-to-postmanv2` (npm) to produce `docs/postman/collection.json`, then enrich it (collection variables, token-saving script, `Idempotency-Key: {{$guid}}`). See Sprint 6.

**Quality gate (automated test)** that loads the generated spec and asserts:
1. It is valid JSON with `openapi: 3.x`.
2. **Every registered `api/*` route has a matching path and method** in the spec (the test fails when someone adds an undocumented route).
3. Every operation has a tag, a summary, at least one 2xx response, and at least one 4xx response.
4. Every route behind `auth:api` declares `bearerAuth` security.

---

## 9. Payment Processing Flow (`PaymentService::process(ProcessPaymentData)`)

```
Request ─► auth ─► IdempotencyMiddleware (replay / conflict / begin)
        ─► ProcessPaymentRequest (method supported? details valid via gateway->rules())
        ─► PaymentController ─► PaymentService::process(ProcessPaymentData)

 TX 1 (short):
   1. OrderReader::lockForPayment(orderId, userId) -> OrderSnapshotData   (lockForUpdate)
   2. If a payment already exists for (order, idempotency_key) -> return it
   3. Order status must be `confirmed`          else OrderNotConfirmedException (409)
   4. Order must not have a successful payment  else OrderAlreadyPaidException  (409)
   5. Create Payment(status=pending, amount=order total, method, idempotency_key)
   6. Ledger: payment_initiated (memo)
 COMMIT

 Gateway call (OUTSIDE any DB transaction):
   7. gateway = PaymentGatewayManager::resolve(method)
   8. result  = gateway->charge(GatewayChargeRequest)       (GatewayException -> treated as failure)

 TX 2 (short):
   9. Update Payment: status = successful|failed, transaction_id, sanitized gateway_response
  10. Ledger: payment_succeeded (credit) or payment_failed (memo)
 COMMIT

 Return PaymentData -> PaymentResource (201) -> middleware stores response for replay
```

Recovery note: if the process dies between TX 1 and TX 2, the payment stays `pending`. A retry with the same key finds the existing payment (step 2) and must not charge again: return it as-is (document this as a known limitation and mention reconciliation as a future improvement).

---

## 10. Sprint Plan

| Sprint | Suggested time | Theme |
|---|---|---|
| 0 | Day 1 AM | Audit, Docker, tooling |
| 1 | Day 1 PM | Modular skeleton, Shared, Auth (JWT) |
| 2 | Day 2 | Orders module |
| 3 | Day 3 AM | Ledger module + Idempotency infrastructure |
| 4 | Day 3 PM to Day 4 AM | Payments module + gateway strategy |
| 5 | Day 4 | Hardening and full test suite |
| 6 | Day 5 | Documentation and delivery |

---

### Sprint 0: Audit, Docker, Tooling

**Goal:** know exactly what exists, then make the project runnable with one command.

- [x] Audit the existing project and write findings to `docs/DECISIONS.md` (Laravel version, PHP version, DB config, existing Docker files, installed packages, existing `User` model/migrations, test setup).
- [x] Docker (create only what is missing; do not overwrite working files):
  - [x] `docker/php/Dockerfile`: PHP 8.3-fpm, extensions `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `zip`, Composer.
  - [x] `docker/nginx/default.conf` serving `public/`.
  - [x] `docker-compose.yml`: `app`, `nginx`, `mysql` (8.x, healthcheck, named volume). Optional: `phpmyadmin`, `redis` (rate limiter and cache store; tests keep using the `array` driver). Also a second database for tests (`app_testing`), created via an init script.
  - [x] `.env.example` with Docker defaults (`DB_HOST=mysql`) and all variables used later (JWT, payment gateways). `.env.testing` pointing at the test DB. **Tests run on MySQL** (the ledger triggers need it).
- [x] `Makefile` targets: `up`, `down`, `build`, `shell`, `migrate`, `fresh`, `seed`, `test`, `pint`, `ledger-verify`, `docs` (generate and export OpenAPI).
- [x] Install/configure Laravel Pint with the PSR-12 preset (`pint.json`).

**DoD:** `docker compose up -d` serves the app on `localhost:8080`; `make migrate`, `make test`, `make pint` run inside the container.

**Sprint 0 verification (2026-10-21):** Complete. App, Nginx, and MySQL are healthy; `/` and `/up` return 200; container Make targets `migrate`, `test` (4 tests, 8 assertions), and `pint` (28 files) pass. Composer validation/platform checks pass. Port 8080 is occupied by another project, so this workspace uses 8081 through `.env`; the default remains 8080. See `docs/DECISIONS.md`.

---

### Sprint 1: Modular Skeleton, Shared, Auth

**Goal:** module system in place, error handling standardized, JWT auth working.

- [x] Create the folder structure for `Shared`, `Auth`, `Orders`, `Ledger`, `Payments` (section 3.1).
- [x] Create each module's ServiceProvider (routes under `/api`, migrations, config, bindings). Register them in the provider list.
- [x] **Shared:** `ApiException`, `ErrorCode` enum, `ApiResponse` trait, global exception rendering for 401/403/404/405/409/422/429/500 in the standard format, `ForceJsonResponse` middleware for `api/*`, `PaginationData`, `Money` helper (bcmath) with unit tests.
- [x] **Rate limiting (section 3.5):** `Shared/Config/rate_limits.php` (env-driven, with the variables added to `.env.example`), named limiters `auth-login`, `auth-register`, `api` registered from `SharedServiceProvider`, 429 rendering in the standard format with `Retry-After` and `X-RateLimit-*` headers.
- [x] **OpenAPI foundation (section 8.1):** install `darkaonline/l5-swagger`, point the scan paths to `app/Modules`, create `Shared/Docs` (info, `bearerAuth`, error schemas, reusable parameters and responses), wire `make docs`.
- [x] **Auth:**
  - [x] Install `php-open-source-saver/jwt-auth`, publish config, `php artisan jwt:secret`, configure the `api` guard with the `jwt` driver.
  - [x] Move/adapt `User` into the Auth module (implement `JWTSubject`), update `config/auth.php`, keep migrations working.
  - [x] DTOs: `RegisterData`, `LoginData`, `TokenData`.
  - [x] `RegisterRequest`, `LoginRequest` (`toDto()`), `AuthService`, `AuthController`, `UserResource`.
  - [x] Routes: register, login, logout, me (the rest of the API is behind `auth:api`). Apply `throttle:auth-login` and `throttle:auth-register`, and the `api` limiter on the authenticated group.
  - [x] Document the Auth endpoints in `Auth/Docs` (tag `Authentication`) with success and error examples (401, 422, 429).
- [x] Feature tests for Auth.

**DoD:** register → login → `GET /api/auth/me` with Bearer token works; unauthenticated calls return the standard JSON 401; repeated failed logins return the standard 429; Swagger UI shows the Authentication endpoints; tests and Pint pass.

**Sprint 1 verification (2026-10-21):** Complete. 35 tests pass (129 assertions), Pint passes (74 files), OpenAPI JSON/YAML export succeeds, and live Swagger UI/assets/spec return 200 at `http://localhost:8081/api/documentation`. JWT authentication, token invalidation/expiry, JSON errors, and configured rate limits are tested. See `docs/DECISIONS.md`.

---

### Sprint 2: Orders Module

**Goal:** complete order management with all business rules.

- [ ] Migrations: `orders` (`id`, `user_id`, `customer_name`, `customer_email`, `status`, `total_amount` DECIMAL(12,2), timestamps; index on `status` and `user_id`), `order_items` (`id`, `order_id`, `product_name`, `quantity`, `price` DECIMAL(12,2), timestamps, cascade on delete).
- [ ] `OrderStatus` enum with `canTransitionTo(self): bool`. Models, relationships, casts, factories (with states: `pending`, `confirmed`, `cancelled`).
- [ ] **DDD domain model (section 7.1):** `Order` as aggregate root with `place()`, `confirm()`, `cancel()`, `replaceItems()`, `changeCustomerDetails()`, `assertDeletable()`, `canBeEdited()`, `canBePaid()`, `isOwnedBy()`. Invariants enforced inside the aggregate. `status` and `total_amount` are not mass assignable. Domain events (`OrderPlaced`, `OrderConfirmed`, `OrderCancelled`, `OrderItemsReplaced`) are recorded in the aggregate and dispatched after commit.
- [ ] DTOs (see inventory). `StoreOrderRequest` / `UpdateOrderRequest` / `ChangeOrderStatusRequest` / index filter validation, each with `toDto()` and custom messages (items: min 1, `quantity` integer ≥ 1, `price` numeric ≥ 0 with max 2 decimals).
- [ ] `OrderService` as a thin **application service**: `create`, `update`, `changeStatus` (maps the target status to `confirm()` / `cancel()`), `delete`, `list(OrderFilterData)`. It loads the aggregate, calls the domain method, persists in a transaction, and dispatches events after commit. No business rules in the service. The total is computed by the aggregate with `Money`.
- [ ] Contracts: `OrderReaderInterface` (with `lockForPayment`) returning `OrderSnapshotData`; `OrderPaymentGuardInterface`. Bind a **null/default implementation** temporarily (always "no payments") until Payments provides the real one; the Payments provider overrides the binding in Sprint 4.
- [ ] `OrderPolicy` (ownership), `OrderResource` (with items), paginated collection (`per_page` default 15, max 100).
- [ ] Domain exceptions in `Domain/Exceptions`: `OrderHasPaymentsException` (409), `InvalidStatusTransitionException` (409), `OrderNotEditableException` (409), `EmptyOrderException` (422).
- [ ] Feature + unit tests (CRUD, status filter, pagination, validation errors, ownership, transitions, total calculation). Include **domain unit tests without a database** for every aggregate method and invariant (valid and invalid transitions, editing a non-pending order, cancel blocked by a successful payment via a fake guard, recorded events, `status`/`total_amount` not mass assignable).
- [ ] Apply the `api` rate limiter to the Orders routes.
- [ ] Document all Orders endpoints in `Orders/Docs` (tag `Orders`): create and update examples, status change examples (`confirmed`, `cancelled`), and error examples (401, 403, 404, 409 `INVALID_STATUS_TRANSITION` / `ORDER_HAS_PAYMENTS` / `ORDER_NOT_EDITABLE`, 422, 429).

**DoD:** all endpoints work with validation, pagination, and filtering; delete is blocked when the guard reports payments; users cannot access other users' orders; order state can only change through domain methods; Orders endpoints appear in Swagger UI with examples.

---

### Sprint 3: Ledger Module + Idempotency Infrastructure

**Goal:** the two foundations that the payment flow depends on.

**Ledger**
- [ ] Migration `ledger_entries` (section 6.2) + MySQL triggers (guarded by driver) + indexes.
- [ ] Enums `LedgerEntryType`, `LedgerDirection`. `LedgerEntryData` DTO.
- [ ] `LedgerEntry` model (immutable guards), `LedgerHasher`, `LedgerService` (implements `LedgerRecorderInterface` and `LedgerReaderInterface`, locks the chain per order), `LedgerVerifier`, `VerifyLedgerCommand`.
- [ ] Read-only controller, resource, routes (`/orders/{order}/ledger`, `/payments/{payment}/ledger`). The ownership check goes through contracts (`OrderReaderInterface`); wire the payment-ledger route in Sprint 4 if needed.
- [ ] Tests:
  - [ ] append creates correct `chain_sequence` and hash linkage
  - [ ] updating or deleting an entry throws `ImmutableLedgerException`
  - [ ] raw `query()->update()` / `delete()` is rejected by the DB trigger
  - [ ] tampering with a row (via a test-only bypass such as dropping the trigger in the test) makes `ledger:verify` fail
  - [ ] concurrent append keeps a gap-free sequence

**Idempotency**
- [ ] Migration `idempotency_keys` (section 5.2), model, `IdempotencyStatus` enum, DTOs, `IdempotencyService`, `IdempotencyMiddleware` (alias `idempotent`).
- [ ] Scheduled prune command for expired keys (`idempotency:prune`).
- [ ] Tests using a dummy test route: new key, replay (same body + `Idempotent-Replayed` header), mismatch payload (422), in-progress (409), missing header (422), 5xx releases the key, validation error releases the key.
- [ ] Document the Ledger endpoints in `Ledger/Docs` (tag `Ledger`, read-only, paginated) and make sure the `IdempotencyKey` header parameter and its behaviors are defined in `Shared/Docs`.

**DoD:** both pieces fully tested and decoupled from Orders/Payments code; `ledger:verify` runs clean.

---

### Sprint 4: Payments Module + Gateway Strategy

**Goal:** idempotent, ledger-backed payment processing built on the strategy abstraction.

- [ ] Migration `payments`: `id`, `order_id` (FK), `user_id`, `status` (`pending`/`successful`/`failed`), `method` (string), `amount` DECIMAL(12,2), `currency`, `transaction_id` nullable, `idempotency_key` nullable, `gateway_response` json nullable (sanitized), `failure_code` nullable, timestamps; unique `(order_id, idempotency_key)`; indexes on `order_id`, `status`, `method`.
- [ ] `PaymentStatus` enum with `canTransitionTo()` (`pending → successful|failed`, both terminal). `Payment` model as aggregate root with `initiate()`, `markSuccessful(GatewayChargeResult)`, `markFailed(string $code, string $message)`; illegal transitions throw `InvalidPaymentTransitionException`; `status` is not mass assignable; domain events `PaymentSucceeded` / `PaymentFailed`. Factory.
- [ ] **Gateway layer** (section 4): interface, `AbstractPaymentGateway`, DTOs, `GatewayException`, `CreditCardGateway`, `PayPalGateway`, `PaymentGatewayManager`, `config/payment.php`, `SupportedPaymentMethod` rule, `UnsupportedPaymentMethodException`.
- [ ] `PaymentsServiceProvider`: merge config, bind the manager as a singleton, bind `OrderPaymentGuardInterface` → `OrderPaymentGuard` (replaces the temporary Orders binding), register routes.
- [ ] DTOs: `ProcessPaymentData`, `PaymentFilterData`, `PaymentData`.
- [ ] `ProcessPaymentRequest`: `method` validated by `SupportedPaymentMethod`; `payment_details` rules pulled dynamically from the resolved gateway (`rules()`); `toDto()` injects the `Idempotency-Key` header and authenticated user id.
- [ ] `PaymentService::process()` exactly as in section 9 (two short transactions, gateway call outside, ledger entries, idempotent lookup). It reads orders only through `OrderSnapshotData` (`payable` flag) and changes payment state only through the aggregate methods, never by assigning `status`. Plus `list`, `listForOrder`, `find` with ownership scoping.
- [ ] Exceptions: `OrderNotConfirmedException` (409), `OrderAlreadyPaidException` (409), `UnsupportedPaymentMethodException` (422).
- [ ] `PaymentController`, `PaymentResource` (never expose raw gateway internals), routes with `->middleware('idempotent')` on the POST only. Wire the `/payments/{payment}/ledger` route.
- [ ] Rate limiting: apply the `payments` limiter (per user, and per user + order) on the POST route in the order `auth:api` → `throttle:payments` → `idempotent`. Other Payments routes use the `api` limiter.
- [ ] Document the Payments endpoints in `Payments/Docs` (tag `Payments`): request examples for `credit_card` and `paypal`, a successful payment, a **failed** payment (201 with `status: failed`), an idempotent replay, and error examples (401, 403, 404, 409 `ORDER_NOT_CONFIRMED` / `ORDER_ALREADY_PAID` / `IDEMPOTENCY_IN_PROGRESS`, 422 `VALIDATION_ERROR` / `IDEMPOTENCY_KEY_REQUIRED` / `IDEMPOTENCY_KEY_REUSED`, 429). Include the `IdempotencyKey` header parameter.
- [ ] Verify: deleting an order with payments returns 409 end to end (real guard now bound).

**DoD:** successful and failed payments persist correctly with matching ledger entries; non-confirmed and already-paid orders are rejected; retrying with the same key never double-charges; a third dummy gateway works with **zero** changes to `PaymentService`; the payment POST returns 429 beyond its limit; Payments endpoints appear in Swagger UI with examples.

---

### Sprint 5: Hardening and Full Test Suite

**Goal:** confidence in correctness, plus a quality pass.

- [ ] **Unit tests**
  - [ ] each gateway: success, decline, provider error → `GatewayException`; sanitization (no card number/CVV in results)
  - [ ] `PaymentGatewayManager`: resolves, unknown method throws, disabled gateway not supported, `extend()` works
  - [ ] `PaymentService` with a mocked gateway and mocked ledger: state changes and ledger calls in the right order
  - [ ] order total calculation, `OrderStatus::canTransitionTo`, `Money`
  - [ ] **domain model:** every `Order` behavior (`place`, `confirm`, `cancel`, `replaceItems`, `assertDeletable`) and each invariant, in memory without a DB; `Payment` transitions (terminal states cannot change); `status` and `total_amount` are not mass assignable
  - [ ] ledger hasher determinism
- [ ] **Feature tests (payments)**
  - [ ] success path (payment `successful` + ledger `payment_initiated` + `payment_succeeded`, hash chain valid)
  - [ ] failed path (declined card → payment `failed` + ledger `payment_failed`)
  - [ ] gateway exception path → payment `failed`, API still returns a clean response
  - [ ] order not confirmed → 409, no payment, no ledger entry
  - [ ] already paid → 409; retry after failure with a new key → allowed
  - [ ] unsupported method → 422; invalid `payment_details` → 422 with per-field messages
  - [ ] **idempotency:** same key twice → one payment, one gateway call (assert with a spy), identical response, replay header; different payload with same key → 422; missing key → 422
  - [ ] ownership: other user's order/payment → 403/404
  - [ ] listing by order and globally, filters, pagination
  - [ ] delete order with payments → 409
- [ ] **Rate limiting tests:** login is limited after the configured attempts (429 + `Retry-After` + standard error body); limits are per user (user B unaffected by user A); payments limiter per user and per user + order; counters reset after the window (use Carbon test time travel); limits are read from config.
- [ ] **OpenAPI gate test** (section 8.1): spec is valid, every `api/*` route is documented, every operation has a tag, a summary, a 2xx and a 4xx response, and protected routes declare `bearerAuth`.
- [ ] **Extensibility test:** register a fake gateway through `PaymentGatewayManager::extend()` (or a test config entry) and process a payment through the real HTTP endpoint without touching production code.
- [ ] Review all Form Requests (rules and messages), error format consistency across modules, N+1 queries (eager loading), indexes, `ledger:verify` after the full suite.
- [ ] Run Pint, fix violations. Run static analysis if Larastan is already installed.

**DoD:** `make test` is green; Pint is clean; payment logic is covered by unit and feature tests.

---

### Sprint 6: Documentation and Delivery

**Goal:** a reviewer can run and understand the project in minutes.

- [ ] **Swagger / OpenAPI** (section 8.1): run `make docs`, verify Swagger UI at `/api/documentation`, commit `docs/openapi/openapi.json` and `openapi.yaml`. Confirm every endpoint has request examples, success examples, and error examples, grouped by the tags `Authentication`, `Orders`, `Payments`, `Ledger`.
- [ ] **Postman collection** (`docs/postman/`), generated from the OpenAPI file (import it, or use `openapi-to-postmanv2`) and then enriched. Folders: `Authentication`, `Orders`, `Payments` (include ledger requests under Payments or a `Ledger` sub-folder).
  - [ ] Collection variables: `base_url`, `token`, `order_id`, `payment_id`; the login request has a test script that saves the token; order creation saves `order_id`.
  - [ ] Payment requests send `Idempotency-Key: {{$guid}}` (and one request showing a replay with a fixed key).
  - [ ] Saved examples per request: success **and** errors (401, 403, 404, 409, 422, 429).
  - [ ] Examples specific to this design: payment success, card declined (`...0002`), order not confirmed, already paid, idempotent replay, key reused with a different body, missing key, invalid order status transition, rate limit exceeded (429).
  - [ ] Export the collection (and an environment file) as JSON into the repo.
- [ ] **README.md**
  - [ ] Overview, tech stack, features
  - [ ] Setup: `cp .env.example .env`, `docker compose up -d`, `make migrate`, `make seed`, JWT secret, running tests, running Pint
  - [ ] Architecture: module structure, dependency rules, DTO conventions, diagram of the payment flow
  - [ ] Endpoint summary table
  - [ ] **"How to add a new payment gateway"**: step-by-step with a full example class, config entry, env vars, and a test (the 3-step procedure from section 4.7)
  - [ ] **Idempotency**: header usage, behavior table, TTL
  - [ ] **Ledger**: what is recorded, immutability layers, `ledger:verify`, production DB-grant recommendation
  - [ ] **Rate limiting**: limiter table, env variables, 429 response format, middleware order on payments
  - [ ] **Domain model (DDD)**: aggregates, invariants, and the state machines for orders and payments
  - [ ] **API documentation**: where Swagger UI lives, how to regenerate (`make docs`), where the exported OpenAPI and Postman files are
  - [ ] Assumptions and notes (simulated gateways, how orders get confirmed, status transitions, money handling, ownership behavior, 201 on failed payments, known limitation of the pending-payment recovery)
- [ ] Final review: fresh-clone test (clone → `docker compose up` → migrate → tests → import Postman and run), clean commit history, tag a release.

**DoD:** following only the README, a fresh clone runs end to end; the Postman collection imports and works against the Docker setup; the OpenAPI file imports into Swagger Editor and Postman without errors.

---

## 11. Final Acceptance Checklist

- [ ] All task requirements implemented (orders, payments, rules, JWT, validation, pagination)
- [ ] Docker one-command startup works
- [ ] Modules are self-contained; no cross-module model/service imports
- [ ] DTOs used at every module boundary; no `Request` or raw arrays inside services
- [ ] Payment strategy: adding a gateway = 1 class + 1 config entry + env vars (proved by a test)
- [ ] `Idempotency-Key` enforced on payment creation; replay, conflict, and mismatch behaviors tested
- [ ] Ledger is append-only (model guard, DB triggers, hash chain, `ledger:verify`) and tested
- [ ] Standard JSON error format everywhere
- [ ] Rate limiting configured and tested (auth, api, payments) with the standard 429 response
- [ ] Orders and Payments follow DDD: aggregates enforce invariants and state changes happen only through domain methods
- [ ] OpenAPI/Swagger complete (every route, success and error examples), exported to `docs/openapi/`, Postman collection generated from it
- [ ] PSR-12 clean (Pint), tests green
- [ ] Postman collection exported, README complete, `docs/DECISIONS.md` filled

## 12. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Existing project conflicts with the planned structure | Sprint 0 audit; adapt, record in `DECISIONS.md`, do not break existing code |
| Over-engineering the modules | Contracts only where modules actually talk; no repositories unless needed |
| Ledger triggers fail on SQLite | Tests run on MySQL; trigger creation is driver-guarded |
| Race conditions on payments/ledger | Row locks, unique indexes, short transactions, concurrency tests |
| Flaky payment tests | Deterministic gateways, no `rand()`, fixed idempotency keys in tests |
| Running behind schedule | Cut optional items first: phpMyAdmin, redis service, failed-payment limiter, token refresh, `RefundableGatewayInterface` implementation, DB-backed gateway config |
| Rate limits block legitimate retries | Per-user keys, sane defaults, everything configurable via `.env`, `Retry-After` header |
| API docs drift from the code | The OpenAPI gate test fails when an `api/*` route is undocumented |
| DDD over-engineering | Keep the Eloquent model as the aggregate (no separate persistence mapping layer); only Orders and Payments get rich behavior |
