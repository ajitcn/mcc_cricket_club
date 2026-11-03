# MCC Sprint 1 Hardening

Status date: 2026-08-29
Overall status: `PARTIALLY_IMPLEMENTED`

This checklist records only authentication and Sprint 1 security evidence. No unrelated module is in scope.

- [x] Authentication controller: JSON/form input is bounded and server-validated; malformed JSON and unexpected fields are rejected; safe, consistent responses are returned.
- [x] Authentication lifecycle: persisted login, logout, disablement, session-version invalidation, adaptive hashing, and failed-login lockout are verified by MySQL integration tests.
- [x] Recovery delivery slice: `POST /auth/recovery/request` and `POST /auth/recovery/reset`, generic enumeration-resistant responses, disabled-account suppression, secure token persistence/expiry/single-use behavior, session invalidation, CSRF protection, audit secrecy, and controlled SMTP success/failure behavior are verified against `mcc_cricket_club_test`; real SMTP delivery/deployment verification remains unverified under approved `INT-01`.
- [x] Session security: login regeneration hook, authenticated-session use, logout destruction, stale-session rejection, reset invalidation, and disablement invalidation are verified. Exact native PHP session-ID comparison remains unverified because PHPUnit CLI has no active native session during the service call.
- [x] Authorization boundary: real HTTP requests reach the auth and permission filters; unauthenticated and unpermitted requests are denied.
- [x] Authentication audit persistence: login success/failure, logout, reset completion, and disablement events persist without passwords, hashes, tokens, or session secrets.
- [ ] Audit append protection: durable audit records have no application mutation endpoint; approved `OPS-01` database-level append-only protection, scoped read access, and audit-of-audit evidence remain unverified.
- [x] Validation: authentication controller rejects malformed JSON, missing/empty credentials, invalid email, oversized values, and unexpected fields in focused tests.
- [x] Brute-force protection: per-account failed-login lockout is verified against persisted state after five failures.
- [x] Recovery account/IP rate limiting: approved `SEC-14` policy implemented with a database-backed multi-instance abstraction; account limit is 5 per rolling 15 minutes and IP limit is 10 per rolling 15 minutes. Generic responses, fail-safe storage errors, reset clearing, lockout separation, persisted state, and sensitive-data exclusions are covered by database integration tests. No implementation defect was exposed in those tests.
- [x] Automated security tests: full PHPUnit suite passes (52 tests, 244 assertions). Database integration tests pass (35 tests, 204 assertions); focused authentication/recovery/security tests pass (42 tests, 224 assertions) against the dedicated MySQL database.
- [x] Production logging regression: the complete local/test run produced zero matches for passwords, hashes, tokens, session IDs, SMTP credentials, medical/identity fields, or signed URLs across two available application log files; production database debugging is disabled. Production/deployment-level log review remains outstanding.

## Integration environment evidence

The local ignored `.env` uses the dedicated testing database `mcc_cricket_club_test`; CodeIgniter verified `ENVIRONMENT=testing`, host `127.0.0.1`, and the `tests` connection. The migration completed successfully and the final schema was inspected. `.env.example` remains free of real credentials and continues to describe development defaults.

The required local-only configuration is:

```dotenv
CI_ENVIRONMENT = testing
database.default.hostname = 127.0.0.1
database.default.database = mcc_cricket_club_test
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306
```

The same values are configured for `database.tests`. Migrations and tests were run only after verifying the exact database name. The development database `mcc_cricket_club` was not used.

No new CRM, admissions, players, sessions, documents, reports, dashboards, notifications, portals, performance, fitness, tournament, or integration functionality was added. Attendance source-event implementation is tracked separately from this hardening register; no Attendance thresholds, reporting, alerts, or other deferred AT-06 behavior was added.

Attendance smoke/regression verification covered authenticated and authorized recording, unauthorized and cross-branch denial, roster eligibility, cancelled-session protection, duplicate prevention, valid correction, stale correction, audit persistence, and authentication/session regression. The environment has not produced reliable true multi-process worker results; this remains `UNVERIFIED` and was not repeatedly troubleshot.

## Remaining Sprint 1 gate register

| ID | Status | Missing evidence/decision | Blocks Sprint 1 completion | Exact next action |
|---|---|---|---|---|
| INT-01 | APPROVED / UNVERIFIED | No deployment SMTP host/credentials are configured; real delivery, outage, and operational evidence are absent. | Yes | Configure the approved MCC SMTP test endpoint through deployment secrets and run end-to-end recovery verification. |
| SEC-14 | APPROVED / UNVERIFIED (concurrency evidence) | The approved account/IP recovery rate-limit policy and its existing integration tests are complete. One focused true multi-process probe was attempted against `mcc_cricket_club_test`, but the existing PHPUnit run and the probe both stalled without worker results, so concurrency cannot be reliably verified in this environment. No implementation defect was exposed. | Yes for Sprint 1 hardening completion; no for the approved first vertical slice | Re-run the unchanged concurrency probe in a functioning multi-process MySQL test environment; do not alter the approved policy. |
| NFR-02 | PARTIALLY_IMPLEMENTED / UNVERIFIED | Native web-SAPI session-ID change and deployment HTTPS evidence are absent; the local web-SAPI probe could not start. | Yes | Run the session lifecycle through a functioning local/staging web SAPI and capture non-secret evidence. |
| OPS-01 | APPROVED / UNVERIFIED | The 7-year retention, scoped read access, no-export boundary, append-only behavior, database protection, and audit evidence policy is approved; deployment/database-trigger evidence remains absent. | Yes | Apply the forward-only protection migration in an approved environment and complete safe audit security verification. |
| NFR-04 | PARTIALLY_IMPLEMENTED / UNVERIFIED | Only local/test application logs were reviewed; production/deployment log sinks were not available. | Yes | Review deployed application/runtime logs and sink configuration for secret and sensitive-data exclusion. |

## Verification classification (2026-08-29)

### Code/test-complete security controls

- SEC-14 policy enforcement is implemented with the approved account limit of 5 and IP limit of 10 over a rolling 15-minute window.
- Existing database integration coverage verifies limits, independent scopes, rolling-window cleanup, reset clearing, persisted multi-instance state, fail-safe storage errors, and sensitive-data exclusion.
- This verification exposed no implementation defect.

### Environment/deployment-only evidence

- True multi-process concurrency remains `UNVERIFIED`. The exact local limitation is that the targeted PHPUnit rate-limiter run stalled without completing, and the one focused 20-worker PHP CLI probe against `mcc_cricket_club_test` also stalled without producing worker results. The probe was stopped after the allowed single attempt; no repeated environment troubleshooting was performed.
- SMTP delivery, native web-SAPI session-ID evidence, production log review, and deployment HTTPS evidence remain deployment/environment evidence as already recorded above.
- Attendance deployment-readiness checks used `mcc_cricket_club_test`; the migration was applied and schema constraints were inspected. A local CI4 front-controller probe returned HTTP 200. No production database, production logs, or production runtime were accessed. The checkout has no `spark` CLI, so deployment migration execution remains an environment/deployment task.

### Decisions still requiring approval

- `OPS-01` audit retention/access/append-only protection is approved; implementation and deployment evidence remain incomplete.
- No new SEC-14 policy decision is required; only future environment evidence remains outstanding.

Attendance MVC update (2026-08-29): added the server-rendered session list and roster/correction UI using explicit CI4 routes, allowlisted models, thin orchestration controllers, CSRF-bearing forms, and the existing AttendanceService. Focused Attendance coverage is 23 tests / 104 assertions, including browser-level permission visibility, mutation, filtering, flash-message, and safe failure checks; semantic accessible forms/tables, responsive presentation, and cancelled read-only states are included. The approved denominator and AT-06 derived behavior remain deferred. This is local test evidence only, not production deployment evidence.
