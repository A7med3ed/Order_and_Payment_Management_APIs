# Operations and recovery

## Local lifecycle

Follow README setup; never overwrite an existing `.env` to regenerate secrets.
Startup installs locked dependencies, generates only missing APP_KEY/JWT_SECRET,
migrates, and generates runtime Swagger. `make docs` also exports all delivery
artifacts. `docker compose down` preserves data; do not remove volumes unless
intentionally resetting this project's local data.

| Setting | Purpose |
|---|---|
| APP_PORT / APP_URL | Local HTTP port and base URL |
| DB_* / MYSQL_ROOT_PASSWORD | Compose development database; main DB is order_payment |
| JWT_SECRET / JWT_TTL / JWT_BLACKLIST_ENABLED | Signing key, lifetime in minutes, logout invalidation |
| PAYMENT_CURRENCY | Three uppercase letters, USD by default; no conversion |
| PAYMENT_*_ENABLED and gateway credentials | Enable configured strategies; current providers are simulations |
| IDEMPOTENCY_TTL_HOURS | Replay-cache TTL (24); permanent payment identity is separate |
| RATE_LIMIT_* | Limits documented in README |
| API_DOCUMENTATION_ENABLED | UI/spec/assets/callback visibility; set false on public production |
| CACHE_STORE / QUEUE_CONNECTION | Database cache and queues locally; tests use array/sync |

The `.env.testing` file deliberately contains public, isolated development values.
Tests reject cached configuration and every database except MySQL app_testing.
Its app_test account has no privileges on the development database. Workers used
by concurrency tests receive the same dedicated settings. Do not substitute live
credentials in this file.

## Useful commands (Windows)

```powershell
docker compose ps
docker compose logs --tail 100 app
docker compose logs --tail 100 nginx
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan route:list --path=api
docker compose exec -T app php artisan ledger:verify
docker compose exec -T app php artisan ledger:verify --order=1
docker compose exec -T app php artisan idempotency:prune
docker compose exec -T app php artisan schedule:list
docker compose exec app php artisan schedule:work
```

The hourly prune schedule requires an actual scheduler process or cron invoking
`schedule:run` each minute. Docker's base three-service stack does not start a
scheduler/queue worker. There are currently no payment queue jobs.

| Symptom | Investigation / action |
|---|---|
| Port already allocated | Set a free APP_PORT, rerun compose; leave unrelated projects running |
| App not healthy | Inspect compose logs, MySQL health, dependency downloads and environment values |
| 401 | Log in again; verify bearer header, TTL, JWT signing secret and logout blacklist |
| 409 ORDER_NOT_CONFIRMED | Confirm the order through PATCH /api/orders/{id}/status |
| 409 ORDER_ALREADY_PAID | Read the existing payment; do not submit a second charge |
| 409 IDEMPOTENCY_IN_PROGRESS | Wait Retry-After, retry the identical request with the same key |
| 409 PAYMENT_IN_PROGRESS | Inspect pending attempt and follow reconciliation below |
| 422 IDEMPOTENCY_KEY_REUSED | Keep original payload for retries; use a new key only for an intended new attempt |
| 429 | Respect Retry-After; inspect configured user/order/IP bucket; replays count |
| Swagger 404 | Check documentation flag and route/config cache; run make docs when enabled |
| Missing app_testing on an older volume | Apply docker/mysql/init/01-testing.sql as described in README |
| Ledger verification fails | Preserve evidence, investigate tampering/checkpoint mismatch; never rewrite entries to make verification green |

## Pending payment recovery

An interrupted request or failed outcome commit can leave a pending payment even
if a provider was called. The current API returns it unchanged on same-key retries
and blocks new keys and cancellation. It has no reconciliation endpoint/command.

1. Preserve the payment id, order id, gateway, idempotency key, timestamps and ledger.
   Access only sanitized data; never collect card/CVV values for troubleshooting.
2. Check provider records using its original idempotency reference. For this release
   the gateways are simulations, so no bank/card network was charged.
3. Do not clear payment keys, change database statuses or submit a fresh key to
   bypass the guard. An external outcome must be established before retrying.
4. For real-provider adoption, implement the audited reconciliation workflow in
   ROADMAP.md before enabling charges. It must use aggregate transitions and append
   the outcome exactly once, with permissions, evidence and operator accountability.

A sanitized GATEWAY_ERROR is a terminal failure in the simulation. Real network
timeouts can represent unknown outcomes; a real adapter must not treat every
timeout as safely retryable. That semantic change belongs to the recovery design.

## Production readiness boundaries

The supplied image/Compose settings are for development: bind-mounted source,
development PHP configuration, installation/migration during startup, public local
credentials and simulated gateways. Production requires a reviewed deployment
image, TLS, secret management, disabled debug/docs, backups/restore testing,
monitoring, capacity limits and separate runtime/deployment database users.

Use a runtime DB account without UPDATE/DELETE/DROP on ledger entries; provision
triggers with a deployment account. Restrict ledger-chain-head access as well.
Privileged database administrators can rewrite hashes/checkpoints; independent
checkpoints/backups are needed for stronger external evidence.

Production limiter state should be shared (for example Redis); the default
database cache works locally. Do not rotate APP_KEY/JWT_SECRET casually: encrypted
data and existing tokens depend on them. Do not print gateway credentials, tokens,
raw provider payloads, card numbers or CVV in logs or exported Postman environments.
