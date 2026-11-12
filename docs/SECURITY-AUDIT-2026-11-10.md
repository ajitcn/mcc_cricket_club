# Security Audit — 2026-11-10

## Scope

Read-only review of authentication, CSRF, RBAC, branch isolation, portal
authorization, private documents, uploads, output escaping, audit logging, and
state-changing routes. No production environment or database records were
changed.

## Confirmed controls

- Browser state-changing routes use CSRF protection through the global filter
  and/or explicit form tokens.
- Staff routes use centralized authentication and permission filters, with
  services providing the second authorization boundary.
- Portal routes use the portal session filter and relationship-scoped queries.
- Portal document downloads require an authenticated Student/guardian link,
  active Player/branch records, verified clean status, and non-sensitive type.
- Uploaded documents and Trial media use private storage and server-generated
  filenames.
- Passwords use password hashing; reset tokens and portal OTPs are stored as
  hashes rather than plaintext.
- Audit events are used for portal support requests and other critical writes.

## Findings and blockers

1. The managed environment cannot read `.env`, so migration status and live
   database security tests cannot currently run.
2. PHPUnit cannot start because the CodeIgniter test bootstrap file is
   unavailable: `vendor/codeigniter4/framework/system/Test/bootstrap.php`.
3. Development database exceptions can reach CodeIgniter's incompatible debug
   exception renderer and produce a secondary fatal error while masking
   connection data. Production must keep debug output disabled; the framework
   dependency/runtime should be repaired before deployment.
4. Migration history contains previously documented duplicate/inconsistent
   entries and requires reconciliation before production migration work.
5. The staff support-request queue and response/status workflow remain to be
   implemented; portal submission is branch-scoped and does not enable direct
   chat.

6. Portal support-request failures previously flashed raw exception messages to
   the user. This was fixed by mapping validation errors to safe messages and
   returning a generic message for unexpected failures.

## No confirmed bypass found

This review did not confirm an authorization bypass, cross-branch data leak,
public document exposure, plaintext credential storage, or missing CSRF control
in the inspected portal and state-changing workflows.

## Required next actions

- Repair the managed `.env` and PHPUnit bootstrap environment.
- Run authenticated Student, Parent, Branch Admin, Coach, and Super Admin
  security tests with isolated fixtures.
- Reconcile migration history before any production migration.
- Finish the staff support-request queue and test portal branch isolation.
