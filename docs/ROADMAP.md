# Improvement roadmap

All required features in PROJECT_PLAN.md belong to the delivered local simulation.
The items below are future enhancements, not claims that production payment
processing is ready. Prioritize recovery and provider semantics before real money.

[CI](../.github/workflows/ci.yml) already runs the full MySQL test suite, Pint,
Composer/platform validation, and documentation coverage/example/export checks on
pushes and pull requests. The CI roadmap item below covers further automation.

| Priority | Enhancement | Reason / acceptance criteria |
|---|---|---|
| P0 before real payments | Reconciliation for pending/unknown outcomes | Provider status lookup using original idempotency reference; authenticated operator or verified webhook; atomic aggregate outcome + ledger event; concurrent duplicate recovery cannot append twice; audit evidence retained |
| P0 | Real gateway adapters and hosted/tokenized collection | Provider sandbox/contract tests, secure credentials, timeouts and retry policy; never store PAN/CVV; unknown outcomes remain pending; same provider reference reused safely |
| P0 | Production deployment and restore readiness | Immutable build without dev dependencies/bind mounts, TLS, secret rotation procedure, least-privilege runtime DB user, backup/restore exercise, staging release checks |
| P1 | Transactional outbox and reliable events | Persist event delivery atomically with outcome; crash/retry tests prove eventual delivery without duplicate effects |
| P1 | Webhook inbox and signature verification | Reject invalid signatures/replays, deduplicate provider event ids, support out-of-order messages, use aggregate transitions and append-only ledger |
| P1 | Monitoring and operational alerts | Pending-age, failure ratio, provider latency, limiter pressure, ledger verification jobs and alerts; correlation ids; sanitized structured logs |
| P1 | Stronger financial evidence | Independently retain signed/checkpointed chain heads; archive and restore verification; alert on missing/tampered entries |
| P1 | Extend CI and automate releases | Add Newman smoke tests, external schema validation, locked dependency/image scans, and release-artifact verification to the existing test/formatting/documentation workflow |
| P1 | Shared cache and abuse protection | Redis limiter state for multiple replicas, reviewed proxy/IP configuration, configurable failed-attempt limits, capacity/load tests under concurrent keys |
| P2 | Gateway validation refinements | Real token validation and provider-supported expiry/currency rules; keep deterministic simulations for stable tests |
| P2 | Refunds and partial reversals | Product-approved rules, new aggregate behavior and ledger debit events; idempotent provider calls; prevent reversing more than the original successful amount |
| P2 | Currency/money expansion | Explicit order currency, per-currency precision and limits; supported-provider currency validation; migration/backfill and tests before adding FX |
| P2 | Large history/query performance | Index/query-plan review on production-size datasets, cursor pagination where justified, archive policy preserving ledger evidence |
| P2 | Authentication lifecycle | Refresh/revocation policy, account lifecycle and audit retention; never cascade-delete financial history |

The current simulated card validator checks structure and ranges, not issuer
ownership or real expiry. Zero-value orders are allowed. A failed attempt needs a
new key to retry; the same key always identifies the same attempt. Financial rows
and fingerprints outlive the 24-hour response cache. Decide retention and privacy
requirements before production rather than deleting evidence on TTL expiry.

Every enhancement should preserve the existing boundary: controllers map HTTP,
services orchestrate, aggregates own business invariants, and cross-module access
uses contracts/DTOs. Add a failing behavioral/concurrency test for new guarantees,
update OpenAPI/Postman, record the tradeoff in DECISIONS.md, and validate recovery
and rollback as well as the successful path.
