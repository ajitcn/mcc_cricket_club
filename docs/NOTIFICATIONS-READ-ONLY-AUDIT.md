# Notifications Read-Only Audit

**Review date:** 2026-09-27  
**Scope:** Notification routes, controller, service, view, schema migration, navigation, permissions, tests, and current requirements/decision records.  
**Change status:** Read-only review. No application code, tests, database records, or existing decision documents were changed.  
**Authority note:** `docs/SRS-original.md` is unavailable. `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, and `docs/SRS.md` are working records and are not treated as a replacement client approval baseline.

## Executive summary

The Notifications module currently provides an in-app persistence foundation:

- authenticated, permission-gated listing at `/notifications`;
- manual creation through `NotificationService::create()` (there is no browser create route);
- recipient-scoped unread/read filtering;
- a POST mark-read action; and
- a service-level history reader backed by `notification_history`.

The repository contains no notification trigger wiring from Admissions, Trials, Attendance, Sessions, Documents, or other business services. It contains no email, WhatsApp, SMS, push, queue, outbox, provider, retry, delivery-state, preference, opt-out, or retention workflow. Those items remain decision-gated by `NOT-01`–`NOT-03` and related operational decisions.

## Source and navigation map

| Area | Evidence | Current state |
|---|---|---|
| Routes | `app/Modules/Notifications/Config/Routes.php` | Authenticated GET list and authenticated POST mark-read route. GET requires `notification.view`; POST requires `notification.read`. |
| Controller | `app/Modules/Notifications/Controllers/Browser.php` | Thin `index()` and `read()` methods. `read()` redirects back after service mutation. |
| Service | `app/Services/NotificationService.php` | Recipient-scoped list, manual create, mark-read, and recipient-authorized history lookup. Uses transactions for creation and UTC timestamps. |
| View | `app/Modules/Notifications/Views/index.php` | Basic filter form and unordered notification list with a mark-read form. |
| Schema and grants | `app/Database/Migrations/20260904000900_CreateNotifications.php` | `notifications`, `notification_recipients`, and `notification_history`; grants view/create/read to Super Admin, Branch Admin, and Coach. Migration is forward-only. |
| Navigation | `app/Services/NavigationService.php` | Notifications appears under Communication when `notification.view` is present. |
| Tests | `tests/Unit/NotificationContractTest.php`, `tests/database/NotificationIntegrationTest.php` | Contract checks permission/read-only boundaries and absence of named external integrations. Database test covers manual create, recipient listing, mark-read, and one history row. |

## Existing in-app behavior

### Listing and filters

`NotificationService::list()`:

- requires `notification.view`;
- identifies the actor from the authenticated session;
- joins `notification_recipients` to `notifications`;
- returns only rows where `recipient_user_id` is the actor;
- supports `state=read` and `state=unread` using `read_at`; and
- supports exact category filtering.

Rows are ordered newest first. There is no pagination, maximum page size, archive, delete, expiry, or retention query.

### Creation

`NotificationService::create()` is a reusable service operation, not a browser workflow. It:

- requires `notification.create` scoped to the supplied branch;
- requires non-empty title, body, category, and at least one recipient;
- limits title length to 180 bytes/characters as enforced by the service and schema;
- deduplicates recipient IDs within the request;
- verifies every recipient has a `user_branches` assignment to the notification branch;
- inserts one notification, recipient rows, and one `created` history row in a database transaction; and
- stores the creation timestamp with `gmdate()`.

The service accepts optional `source_type` and `source_id` values but does not validate them against an entity registry or foreign key. The schema permits a nullable `branch_id`, although the intended service path is branch-scoped and recipient validation effectively requires a valid assigned branch.

### Read state and history

`markRead()` requires `notification.read`, loads the recipient row by recipient-row ID and current actor, and sets `read_at` to a UTC timestamp. It does not create a history row for the read action.

`history()` requires `notification.view` and returns history only if the current actor is a recipient of the notification. The service method has no exposed route or view, so recipients cannot currently inspect history through the browser module.

## Triggers, recipients, and delivery boundaries

### Implemented triggers

No application call to `service('notification')->create()` was found outside `NotificationIntegrationTest.php`. No automatic trigger exists for:

- Lead, Trial, Admission, or Player changes;
- Session creation, cancellation, or reassignment;
- Attendance recording, correction, or alerts;
- Coach leave, availability, feedback, or KPI events;
- Document expiry or verification;
- Password/recovery events; or
- Administrative changes.

The only exercised creation path is a test/manual service call.

### Recipients

Recipients are supplied as explicit user IDs to the service. The service verifies branch assignment but does not define event-specific recipient rules, role-based recipient expansion, disabled-account handling, user preferences, duplicate delivery policy beyond the unique recipient constraint, or recipient consent/opt-out behavior.

### External channels

No email notification adapter, WhatsApp integration, SMS provider, push provider, queue, webhook, or delivery boundary was found. The existing authentication recovery email path is a separate SMTP boundary and is not used by Notifications. `tests/Unit/NotificationContractTest.php` explicitly checks that named WhatsApp/Firebase integrations are absent; this is evidence of no such implementation, not approval to add one.

## Retry and failure handling

Creation is transaction-wrapped and checks transaction status, so a failed database transaction raises `Unable to create notification.` No delivery attempt is created because there is no delivery channel.

There is no outbox, provider response, delivery status, attempt counter, retry schedule, dead-letter state, idempotency key, or operator failure view. Retry and failure semantics therefore remain unavailable and must not be inferred from the database transaction.

## Permissions, roles, and branch visibility

The notification migration grants `notification.view`, `notification.create`, and `notification.read` to the existing Super Admin, Branch Admin, and Coach roles. The permission catalog and descriptions are also listed in `AdminService`.

- Listing and history are recipient-scoped rather than branch-query scoped.
- Creation is branch-scoped through `authorization.can('notification.create', branch)` and recipient branch assignments.
- Mark-read is actor/recipient scoped by recipient-row ID; a forged recipient ID is rejected by the service.
- The view hides the Notifications navigation item unless `notification.view` is present, but server-side route and service checks remain authoritative.

No role-specific notification categories, event visibility, cross-branch recipient policy, Super Admin global scope, Coach self-only scope, or disabled-user behavior is documented. The current recipient check prevents selecting a user without an assignment to the supplied branch, but it does not define whether multi-branch users receive one branch's event or all applicable events.

## UI findings

### Confirmed defects or incomplete implementation

1. **Missing CSRF field on the mark-read form.** `app/Modules/Notifications/Views/index.php` posts to the CSRF-protected browser route but does not render `csrf_field()`. Depending on global filter configuration, marking a notification read can be rejected before `NotificationService::markRead()` runs.
2. **Filter state is not preserved.** The state select does not mark the current `state` as selected after a filtered request. Category is preserved, but state is not.
3. **Basic, inconsistent presentation.** The view uses a raw `<main>`, `<ul>`, and compressed inline form/table markup rather than the shared card, form, button, alert, and empty-state patterns used elsewhere. The mark-read button has no shared `mcc-button` styling, and the list has no responsive card/table treatment, semantic grouping, or clear per-item action layout.
4. **History is not reachable from the UI.** A recipient-authorized service method exists, but no route/controller/view exposes notification history.
5. **No pagination or retention boundary.** The list returns every notification for the actor. This is a scalability and retention gap, not an authorization bypass.

### Current safe behavior

- Notification title, body, category, and timestamps are escaped in the view.
- Empty results have a plain empty message.
- Read/unread state is represented by `read_at` and can be filtered server-side.
- Mark-read is a POST route with permission and recipient ownership checks.

## Database and retention behavior

The migration creates:

- `notifications`: nullable branch, title, body, category, optional source type/ID, creator, UTC-capable timestamp;
- `notification_recipients`: unique notification/user pair, `read_at`, and creation timestamp; and
- `notification_history`: action, actor, free-form metadata, and timestamp.

Foreign keys protect notification and recipient relationships. Notification deletion cascades to recipients and history at the database level, but no application delete route exists. There is no `expires_at`, `archived_at`, retention job, read-history row, delivery-attempt table, provider message ID, or immutable source snapshot.

`OPS-01` approves append-oriented audit principles for the wider system, but it does not define the Notifications event history, retention period, delivery audit, or read-state history contract. `NOT-03` remains unresolved.

## Requirements and decision comparison

| Record | Current status | Evidence/impact |
|---|---|---|
| `docs/REQUIREMENTS-TRACEABILITY.md / Notifications` | Implemented with limitations | Foundation listing, targeting/read state, and history support exist; triggers, delivery, preferences, retries, and external providers are deferred. |
| `NOT-01` | `NEEDS_DECISION` | Required MVP channels and whether email is MVP or WhatsApp is future-only are unspecified. |
| `NOT-02` | `NEEDS_DECISION` | Event recipients, templates, timing, scheduling, retries, and opt-out are unspecified. |
| `NOT-03` | `NEEDS_DECISION` | Delivery status, provider failure handling, audit contents, and retry evidence are unspecified. |
| `REQ-ADV-001` | `NEEDS_DECISION` | Advanced notifications remain later scope along with other advanced capabilities. |
| `OPS-01` | Approved for general audit boundary | Does not resolve notification delivery/read/retention policy. |

## Clear defects versus NEEDS_DECISION

### Confirmed defects or concrete implementation gaps

- Missing CSRF token in the mark-read form.
- State filter selection is not preserved in the view.
- Notification UI does not use shared card/button/empty-state patterns and has weak responsive/accessibility structure.
- Notification history has no browser route or display.
- No list pagination or explicit retention boundary exists.
- No automatic trigger integration exists despite the database foundation.

### NEEDS_DECISION

- `NOT-01`: channel scope and delivery phase, including email, WhatsApp, SMS, push, or in-app-only behavior.
- `NOT-02`: trigger catalogue, recipient rules, templates, timing, preferences, opt-out, and branch/multi-branch behavior.
- `NOT-03`: delivery attempt state, retries/backoff, provider failure handling, idempotency, audit fields, operator visibility, and read-state history.
- Retention, archive, deletion, and privacy rules for notifications and history.
- Whether `notification.create` remains a manual/internal service capability or becomes a protected administration workflow.
- Role-specific notification visibility and whether Coach recipients may see only their own branch or assigned-session events.

## Smallest safe next task

The smallest clearly authorized implementation task is a focused in-app foundation repair that does not choose any unresolved delivery policy:

1. Add the existing CSRF field to the mark-read form.
2. Preserve the selected `state` filter in the view.
3. Apply shared notification list, empty-state, and button styles without changing routes, permissions, recipients, or delivery behavior.
4. Add focused browser/service coverage for CSRF-protected mark-read, state-filter preservation, recipient isolation, and cross-recipient denial.

Automatic triggers, external channels, retries, preferences, retention, and delivery history must wait for explicit decisions under `NOT-01`–`NOT-03`.

