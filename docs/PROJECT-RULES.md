# MCC Cricket Club Management System — Permanent Project Rules

These are enforceable engineering and product rules. `SRS-original.md` must never be modified. Approved decisions in `DECISIONS.md` control ambiguous business behavior; `PROPOSED` and `NEEDS_DECISION` items must not be implemented as policy.

## Mandatory readable coding

The complete coding standard is [`CODING-STANDARD.md`](CODING-STANDARD.md) and
is mandatory for every new or modified source section. Do not write or retain
new compressed single-line PHP, HTML, view, CSS, JavaScript, route,
configuration, migration, service, controller, model, or test code. Use
readable multi-line control flow, calls, arrays, markup, selectors, and
assertions. Inspect every changed source file before completion and run
available syntax checks. Avoid unrelated mass reformatting of legacy code.

## Mandatory database data preservation

This rule is permanent and applies to all development, testing, debugging, UI work, migrations, seeders, fixtures, and feature implementation.

- Before every task, read this file.
- Before any database-affecting command, verify the active environment, database host, database name, and command safety.
- Before implementation or testing, run the approved safe/idempotent base seeder and seed all required prerequisite data.
- Reuse existing records where possible; create only uniquely identifiable additional fixtures when required.
- Preserve all existing database data. Never delete, truncate, drop, reset, recreate, overwrite, or replace existing business or fixture records.
- Never use destructive cleanup, `DELETE`, `TRUNCATE`, `DROP TABLE`, `DROP DATABASE`, `migrate:fresh`, database reset commands, or equivalent operations during normal development work.
- Migrations may create tables or add approved schema elements, but must not remove existing business data without explicit approval and a documented data-migration strategy.
- If any command, test, migration, seeder, fixture cleanup, or script could delete existing data, stop and request explicit approval before executing it.
- Test with the existing dataset plus newly created fixtures. Prefer transactions or isolated uniquely identifiable fixtures; never clear tables to obtain a clean state.
- The required result for normal development work is `Records deleted: 0`.
- After every database-affecting task, report the database used, seeder executed, migrations applied, records inserted, records updated, and records deleted.

## Architecture and change safety

- Keep business functionality in `app/Modules`; use the established CodeIgniter structure and explicit routes.
- Keep controllers thin; put workflows, transactions, integrations, and reusable logic in services.
- Use models for persistence, not business workflows.
- Do not create business modules, tables, migrations, APIs, or dependencies without approved requirements and decisions.
- Use migrations for all schema changes and transactions for related atomic changes.
- Do not modify unrelated files or overwrite user work.

## Authorization and branch isolation

- Deny access by default on every protected endpoint, record, report, export, and document download.
- Enforce authorization server-side; never authorize from hidden fields, URL parameters, frontend filtering, or UI visibility.
- Evaluate explicit permissions and explicit branch assignments for every request.
- Prevent unauthorized users from inferring protected record existence.
- Add tests for unauthorized access, cross-branch denial, authorized cross-branch access, and sensitive-category restrictions.
- Do not hardcode business permissions in controllers.

## Privacy, documents, and logging

- Treat medical, identity, passport, visa, consent, photograph, guardian/contact, authentication, and private player data as sensitive.
- Store sensitive files privately; never put them in `public/assets` or expose permanent public URLs.
- Authorize downloads at request time. Short-lived signed URLs require an approved design.
- Validate upload error, file type, MIME type, size, category, safe server-side filename, and malware scan result before persistence.
- Preserve replacement/version audit history; use logical retirement where retention applies.
- Never log passwords, access/refresh tokens, secrets, full medical notes, identity-document numbers, document contents, or signed URLs.
- Minimize sensitive fields in lists, responses, logs, and exports.
- Do not place sensitive information in URLs, notifications, or error messages unless an approved policy explicitly permits the minimum necessary data.

## Identity, sessions, and audit

- Use one database-backed identity model with multiple roles and explicit branch assignments.
- Rotate sessions at login and invalidate them on logout, password reset, and account disablement.
- Password recovery and guardian verification must use secure expiring mechanisms and be audited.
- Apply rate limiting to authentication and recovery flows where applicable; document thresholds before implementation.
- Audit actor, action, entity, entity ID, timestamp, branch scope, and appropriate before/after summary or reason.
- Keep audit records append-oriented and protect them from normal user modification.

## Data integrity and ownership

- Keep lead/trial/admission lifecycle separate from player lifecycle.
- Make conversion controlled, auditable, idempotent, and duplicate-safe; duplicate detection warns and never silently merges.
- Store cancelled sessions as session events, not player attendance statuses. Attendance always belongs to an eligible player/session relationship.
- Preserve branch-transfer history and effective dates; do not rewrite historical ownership.
- Keep authoritative data in its owning module. Dashboards and 360 views are read/aggregation layers only.
- Define optimistic/concurrency behavior for workflow updates; retries must not duplicate records.

## Validation, calculations, reports, and APIs

- Validate all input server-side and keep validation separate from authorization.
- Do not implement undefined formulas or thresholds. Record formula version, source, units, precision, timezone, null behavior, denominator, examples, owner, and approval date first.
- Authorize and log every export; filter it to permitted fields and include generation timestamp and scope.
- Introduce APIs only for approved consumers under `/api/v1/`, with documented authentication, authorization, and response structure.
- Use `Asia/Kolkata` as the default timezone unless an approved decision changes it; define storage/report date semantics consistently.

## Testing and release

- Every meaningful module requires Unit, Feature, and database tests for rules, authorization, branch isolation, validation, workflows, and persistence.
- Test safe upload failure, notification failure without record corruption, export failure, idempotent retry, and conflicting updates.
- Core workflows must support keyboard navigation, visible focus, semantic labels, sufficient contrast, and responsive layouts.
- Target NFR-01 is p95 server response ≤3 seconds under agreed production-like data/load; do not substitute averages.
- Production changes require migration guidance, automated test results, and an owner-approved release checklist.
- Backups must be encrypted and restored at least quarterly; retention and recovery objectives require approval.

## Release gates

- Do not implement Phase 1 business functionality until its requirements, permissions, branch scope, data ownership, validation, acceptance criteria, migrations, and blocking decisions are approved.
- Do not move Phase 2/3 capabilities into Phase 1 without an explicit approved change.
- Mark implementation status only from inspected code and tests; if evidence is unavailable, write `Evidence not found.`
