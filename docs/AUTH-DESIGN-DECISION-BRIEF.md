# MCC Authentication Design Decision Brief

**Status:** `NEEDS_DECISION`  
**Prepared:** 2026-09-20  
**Purpose:** Present the current authentication implementation and recorded approvals so an authorized approver can resolve the authentication design boundary for the replacement requirements baseline. This brief describes current code; it does not recommend or select an authentication design.

## Decision authority and boundary

`AGENTS.md` states that the current generic `AuthService` is only a boundary, that it is not the final implementation, and that authentication design must be approved before implementation. The current repository contains a substantial database-backed authentication and recovery implementation. The client approval packet records `AUTH-DESIGN` as `NEEDS_DECISION` and asks whether the existing single database-backed identity and recorded recovery decisions are sufficient for the replacement baseline.

The decision is about whether the architecture and behavior described below are approved for the replacement baseline and what specific requirements or changes remain. Code presence and prior test results do not answer that question. Do not use this decision to silently approve role permissions, branch access, parent/player portal eligibility, or document/medical visibility; those are separately governed, including by `RBAC-MATRIX` and `REL-04`.

## What the current implementation does

The following is a description of files in the current checkout, not a finding that each behavior or configuration is approved for production.

| Area | Current code evidence |
|---|---|
| Account identity and persistence | `app/Models/UserModel.php` reads the `users` table by normalized email and exposes account fields including password hash, display name, enabled state, failed-login count, session version, lockout timestamp, and last login. The initial schema is in `app/Database/Migrations/202608280001_CreateSprintOneSchema.php`. `AuthService` loads role, permission, and branch-assignment records during login. |
| Login and account state | `app/Services/AuthService.php` checks that the account exists, is enabled, is not currently locked, and passes `password_verify`. It resets failed-attempt state after success, rehashes when PHP `PASSWORD_DEFAULT` changes, records last login, regenerates the session ID, populates session data, and records a login audit event. Failed attempts increment a per-account counter; code currently locks after 5 attempts for 900 seconds. That login-lockout threshold is distinct from the approved recovery rate limits. |
| Session state and invalidation | `AuthService::isCurrentSession()` compares the session’s user ID and `session_version` to the current enabled database account. Password reset and account disablement increment `session_version`; logout removes MCC session values and destroys the session. The session data includes the user, roles, permissions, and authorized branch IDs. |
| Session configuration | `app/Config/Session.php` currently selects CodeIgniter `FileHandler`, a 7,200-second expiry, `regenerateDestroy = true`, and `timeToUpdate = 300`. `app/Config/App.php` sets secure global requests and secure cookies only in production, `HttpOnly` cookies, and `SameSite=Lax`. These are repository defaults; actual production behavior depends on deployment configuration, HTTPS termination, filesystem/session storage, and the running PHP web SAPI. |
| Recovery request and reset | `app/Modules/Auth/Config/Routes.php` defines explicit POST routes for login, logout, recovery request, and recovery reset. `app/Modules/Auth/Controllers/Auth.php` validates bounded JSON request bodies and fields, and returns a generic accepted response for valid recovery requests. `AuthService` generates a 32-byte random token, stores its SHA-256 hash, marks earlier unused tokens used, and currently sets a 900-second expiry. Reset accepts one unused, unexpired token, hashes the new password using `PASSWORD_DEFAULT`, increments `session_version`, clears failed-login state, marks the token used, clears account recovery-rate state, and audits the action. `docs/REQUIREMENTS.md` describes the approved recovery HTTP contract, including 12–1,024 byte password input; the exact duration and broader password-policy approval must be read from the decision record rather than inferred from these code values. |
| Recovery rate limiting | `app/Services/DatabaseRecoveryRateLimiter.php` uses database-backed account and IP scopes, hashed identifiers, transactions, and row locks. It applies 5 requests per account and 10 per IP in rolling 15-minute windows. `app/Database/Migrations/20260828000300_CreateRecoveryRateLimitTables.php` creates its persistence tables. |
| Recovery email delivery | `app/Services/SmtpRecoveryDelivery.php` uses CodeIgniter Email and requires SMTP protocol, host, port, timeout, crypto, authentication, credentials, and a sender. `app/Config/Email.php` reads these values from environment configuration and contains empty defaults. Delivery errors are wrapped by the service; the controller keeps valid-request responses generic. |
| Authorization boundary | `app/Filters/Auth.php`, `app/Filters/Permission.php`, and `app/Services/AuthorizationService.php` enforce authenticated and permission checks on protected routes. The final role-to-permission matrix is not established by authentication decisions. The authentication flow’s session population of permissions/branches is implementation evidence, not approval of those assignments. |
| Tests and reported evidence | Relevant files include `tests/Feature/AuthControllerTest.php`, `tests/database/AuthLifecycleIntegrationTest.php`, `tests/database/AuthRecoveryIntegrationTest.php`, `tests/database/RecoveryRateLimiterIntegrationTest.php`, and `tests/Unit/SmtpRecoveryDeliveryTest.php`. `docs/SPRINT-1-HARDENING.md` records historical results and identifies unverified SMTP, web-SAPI, multi-process, HTTPS, and production-log evidence. No tests were run to prepare this brief. |

## What the existing decisions approve

| Decision | Recorded approved scope | Boundary of approval |
|---|---|---|
| `SEC-01` | One database-backed identity model for staff, parents, and players. Recorded in `docs/DECISIONS.md` as approved by Client / Architecture on 2026-08-28. | Approves the common identity model. It does not by itself approve the full authentication architecture, session handler/settings, every person’s account eligibility, portal visibility, permission grants, or branch access. |
| `SEC-14` | Account recovery must use expiring, single-use recovery, rate limits, verified relationship checks, session invalidation, and audit events. The accompanying approved recovery-rate-limit policy specifies 5/account and 10/IP per rolling 15 minutes, generic enumeration-resistant responses, hashed short-lived single-use tokens, clearing account recovery-request rate state after successful reset, multi-instance-capable storage, and safe failure behavior. | The detailed approved thresholds and behavior apply to recovery requests. `DECISIONS.md` explicitly says not to conflate them with the existing authentication account lockout. The decision does not record an exact reset-token lifetime or an approved failed-login lockout threshold. Guardian verification and portal visibility are not interchangeable; `REL-03`–`REL-05` remain unresolved. |
| `INT-01` | Recovery delivery through an MCC-operated, self-managed SMTP/relay. MCC infrastructure owns and operates the infrastructure. SMTP authentication and TLS are required where supported; secrets must remain in deployment/environment secret configuration; recovery responses remain generic; token/secret logging is prohibited; retry must be bounded and idempotent; outages must be observable and auditable. Recorded approved on 2026-08-28. | Does not select host, port, domain, credentials, TLS mode, retry values, or other deployment parameters. It authorizes planning and verification of this recovery mechanism only, not arbitrary integrations. |

`REQ-AUTH-001` is marked `PARTIALLY_IMPLEMENTED` in `docs/REQUIREMENTS.md`. That status and the test evidence do not establish client acceptance of the implementation as the final design. The replacement packet explicitly requests an `AUTH-DESIGN` decision while preserving `SEC-01`, `SEC-14`, and `INT-01` as currently recorded unless separately superseded.

## Unclear matters requiring recorded decisions

Each item below remains `NEEDS_DECISION`. The approver should record the decision and exact approved wording, not merely check a box. The brief does not select a design or propose an answer.

### AUTH-DESIGN — architecture and replacement-baseline acceptance

Decide whether the current architecture described above—including the database-backed identity, `AuthService` responsibilities, CodeIgniter session integration, recovery-token flow, database rate limiter, and SMTP delivery adapter—is approved as the authentication design for the replacement baseline. If not approved, identify which boundaries must be reconsidered and the required architecture/acceptance record. Resolve the conflict with `AGENTS.md`, which still calls `AuthService` a non-final boundary and requires authentication design approval before implementation.

**Status:** `NEEDS_DECISION` — `docs/CLIENT-APPROVAL-PACKET-REPLACEMENT-BASELINE.md`, `AUTH-DESIGN`; `AGENTS.md`, “Authentication”.

### AUTH-SESSION — session policy and deployment assumptions

Decide and record the accepted session policy and any required implementation constraints for the repository’s current FileHandler, 7,200-second expiry, 300-second update interval, session-ID regeneration/destruction, production secure-cookie/HTTPS behavior, and multi-instance/persistence expectations. Identify which items are design requirements and which are deployment configuration/evidence. Do not infer approval from the current defaults.

**Status:** `NEEDS_DECISION` — `AGENTS.md`, “Authentication” and “Security”; `docs/PROJECT-RULES.md`, “Identity, sessions, and audit”; current values in `app/Config/Session.php` and `app/Config/App.php`; no dedicated approved session-configuration decision identified in `docs/DECISIONS.md`.

### AUTH-LOGIN-LOCKOUT — login failure policy

Decide whether the current account lockout behavior (5 failed attempts and 900 seconds) is an accepted login policy, and record the approved threshold, duration, reset/expiry behavior, and required audit/response behavior. Keep this decision separate from `SEC-14` recovery-request limits.

**Status:** `NEEDS_DECISION` — current behavior in `app/Services/AuthService.php`; `AGENTS.md` and `docs/PROJECT-RULES.md` require applicable authentication rate-limit thresholds to be documented. No approval for this exact login-lockout threshold was identified in `docs/DECISIONS.md`.

### AUTH-RECOVERY-DETAILS — token lifetime, password rule, and guardian verification boundary

Confirm whether the current 900-second reset-token lifetime and the recovery contract’s 12–1,024 byte password input are accepted requirements, and record any password policy beyond input bounds and adaptive hashing. Clarify how SEC-14’s approved “verified relationship checks” apply to account recovery for guardian-linked accounts. The current `requestReset()` lookup is email-based and does not itself verify a guardian-child relationship. Coordinate this boundary with `REL-03`–`REL-05`; do not decide parent/player portal access through the authentication brief.

**Status:** `NEEDS_DECISION` — `SEC-14` approves short-lived single-use recovery and relationship checks but does not specify an exact reset-token TTL or password-composition policy; code values appear in `AuthService.php`; the HTTP contract appears in `docs/REQUIREMENTS.md`; `REL-03`–`REL-05` are unresolved in `docs/DECISIONS.md`.

### AUTH-ACCOUNT-SCOPE — account eligibility versus common identity

Confirm that the replacement baseline retains `SEC-01`’s common identity model while recording separately which account types are eligible for login and recovery. This decision must not silently assign role permissions or grant Parent/Player portal access; those remain governed by RBAC and relationship decisions.

**Status:** `NEEDS_DECISION` for replacement-baseline scope — `SEC-01` is approved; `REL-04` and `RBAC-MATRIX` remain unresolved in the approval packet. The code’s `is_enabled` flag and role/branch session values do not settle account eligibility policy.

## Approver response record

Complete a separate row for each item. If a decision changes an existing approved decision, cite the superseded ID/version and preserve its history. Leave unresolved rows `NEEDS_DECISION`.

| Decision item | Status | Decision (APPROVE / REJECT / NEEDS_DISCUSSION) | Exact decision / required wording | Comments | Approver name and authority | Date |
|---|---|---|---|---|---|---|
| `AUTH-DESIGN` | `NEEDS_DECISION` | __________________ | ______________________________________________ | ______________________________________________ | ______________________________ | __________ |
| `AUTH-SESSION` | `NEEDS_DECISION` | __________________ | ______________________________________________ | ______________________________________________ | ______________________________ | __________ |
| `AUTH-LOGIN-LOCKOUT` | `NEEDS_DECISION` | __________________ | ______________________________________________ | ______________________________________________ | ______________________________ | __________ |
| `AUTH-RECOVERY-DETAILS` | `NEEDS_DECISION` | __________________ | ______________________________________________ | ______________________________________________ | ______________________________ | __________ |
| `AUTH-ACCOUNT-SCOPE` | `NEEDS_DECISION` | __________________ | ______________________________________________ | ______________________________________________ | ______________________________ | __________ |

## Evidence to be supplied by the deployment owner

Repository inspection can show configuration defaults and code paths; it cannot prove the behavior of the deployed infrastructure. These items belong to MCC infrastructure/operations or the authorized deployment owner under `INT-01`.

| Evidence | Deployment owner must provide | Related code/record |
|---|---|---|
| SMTP configuration and ownership | Approved endpoint/host, port, sender/domain, supported TLS/auth mode, secret-store reference and access controls, credential rotation owner, bounded retry behavior, and confirmation that no credentials are committed to the repository. Do not place secret values in this brief or decision register. | `app/Config/Email.php`, `SmtpRecoveryDelivery.php`, `INT-01`. |
| Real recovery delivery and failure behavior | Non-production end-to-end delivery evidence, outage/failure handling, generic client response, operational alert/audit behavior, retry/idempotency result, and evidence of safe recovery after credential rotation. | `SmtpRecoveryDelivery.php`, `Auth.php`, `INT-01`, `docs/SPRINT-1-HARDENING.md`. |
| Web-SAPI sessions and HTTPS | Evidence from the deployed/staging PHP web SAPI that session IDs rotate on login, sessions invalidate on logout/reset/disablement, HTTPS is enforced as configured, and actual cookie attributes match the session policy confirmed by the authorized approver behind the deployed proxy/load balancer. | `app/Config/Session.php`, `app/Config/App.php`, `AuthService.php`, `NFR-02`. |
| Session storage behavior | Runtime confirmation of FileHandler path permissions, persistence across process restarts, and whether the deployment has one or multiple application instances. If deployment topology requires a different persistence arrangement, obtain the required architecture approval before changing the handler. | `app/Config/Session.php`; current `FileHandler` selection. |
| Multi-process recovery rate limiting | A reliable concurrent request run against the approved database/deployment topology that demonstrates both approved scopes and safe behavior under backing-store failure. Record worker count, environment, database engine/configuration, and result without exposing account or IP identifiers. | `DatabaseRecoveryRateLimiter.php`, `SEC-14`, `docs/SPRINT-1-HARDENING.md`. |
| Production log and monitoring evidence | Review of actual application, PHP/web-server, SMTP, and centralized log/monitoring sinks for absence of passwords, hashes, reset tokens, session IDs, SMTP credentials, and unnecessary personal data; evidence that delivery/storage failures are observable without exposing secrets. | `AuthService.php`, `Auth.php`, `SmtpRecoveryDelivery.php`, `INT-01`, `NFR-04`, `OPS-12` (`NEEDS_DECISION`). |

These deployment observations do not approve an authentication design or change the status of any client decision. Any discrepancy between deployed behavior and approved policy should be recorded and assessed before code changes.

## Source records reviewed

- `AGENTS.md` — architecture, authentication, sessions, RBAC, branch isolation, security, testing, requirements discipline.
- `docs/PROJECT-RULES.md` — approved decisions govern business behavior; identity, sessions, audit and change safety.
- `docs/DECISIONS.md` — `SEC-01`, `SEC-14`, recovery rate-limit details, `INT-01`.
- `docs/CLIENT-APPROVAL-PACKET-REPLACEMENT-BASELINE.md` — `AUTH-DESIGN`, RBAC, relationship and baseline conflicts.
- `docs/REQUIREMENTS.md` — `REQ-AUTH-001` and password-recovery HTTP contract.
- `docs/SPRINT-1-HARDENING.md` — dated implementation and deployment evidence status.
- Current auth services, controller/routes, user model, session/app/email configuration and initial schema migration.
