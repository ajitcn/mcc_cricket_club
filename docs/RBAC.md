# MCC RBAC Record

This document records the Equipment-specific user direction and implementation. It does not resolve the wider client role-permission matrix.

## Programs and Batches (`PROG-BATCH-01`)

**Status:** User-provided limited direction implemented 2026-09-22; not client approval.

| Existing role | Permissions | Scope |
|---|---|---|
| Super Admin | `program.view`, `program.create`, `batch.view`, `batch.create` | Programs use global permission checks under the limited name-only scope. Batch records are listed/created only for current authorized branches; creation selects an authorized branch server-side. |
| Branch Admin | `program.view`, `program.create`, `batch.view`, `batch.create` | Programs use global permission checks under the limited name-only scope. Batch creation derives the validated active branch server-side; submitted `branch_id` is ignored. |
| Coach | `program.view`, `program.create` | Programs are global reference records. The existing Program workflow uses `program.create` for create and edit; no Batch or branch-management permission is granted. |
| Other roles | None from this scope | No Program or Batch management access is granted. `player.batch.assign` remains assignment-only. |

Routes use centralized permission filters, and services repeat permission and current branch checks. Permission values are loaded into the login session; users must sign out and back in after the permission migration for new grants to appear. Update/delete/archive permissions, Program branch scope, capacity, and other future workflows remain `NEEDS_DECISION`.

## Admissions reopening (`AD-01` amendment)

**Status:** User-provided direction implemented for this scoped change; **not client approval**. Direction recorded on 2026-09-22. Client reconciliation remains `NEEDS_DECISION`.

Reopening a `REJECTED` admission requires the existing `admission.update` permission and current authorization for the admission's branch. The only permitted target is `IN_REVIEW`; no new permission or role grant is introduced. The service enforces the branch and permission checks server-side, requires a non-empty reopening reason, and records the actor, timestamp, previous status, new status, and reason through the existing admission history and audit paths. The existing checklist and conversion safeguards are preserved. No other rejected-status transition is authorized by this direction.

**Source:** `AD-01` user-provided amendment in `docs/DECISIONS.md` and `REQUIREMENTS.md`. This record does not claim client approval or resolve the wider role-permission matrix.

## Equipment inventory (`EQUIP-01`)

**Status:** User-provided direction, implemented for the limited scope; not client approval. Direction date: 2026-09-21. No client approver identity or approval date is recorded.

| Existing role | Module permissions | Branch behavior |
|---|---|---|
| Super Admin | `equipment.view`, `equipment.create`, `equipment.update`, `equipment.delete` | Lists and acts only within current `user_branches` assignments. Creation chooses an active assigned branch. Update and soft delete preserve the existing record branch. Whether Super Admin may create or manage records under an inactive but assigned branch remains `NEEDS_DECISION`. |
| Branch Admin (user-confirmed mapping for “Branch Manager”) | `equipment.view`, `equipment.create`, `equipment.update`, `equipment.delete` | Lists and acts only in the validated active branch. A sole active assignment is automatic. With multiple active assignments, selects through shared navigation. Create and edit forms display the resolved branch without an input or hidden field; services ignore submitted `branch_id`. No active authorized branch prevents create, update, and delete. |
| Other roles | None from this scope | No Equipment permissions are granted. |

Routes use the centralized permission filter, and services call `AuthorizationService` for the operation's separate module permission. `BranchContextService` checks the record's branch against current database assignments. The navigation selector uses an authenticated CSRF-protected POST and is not itself an authorization control. Current database assignments are checked at selection and on every Equipment operation; stale session branch values are cleared. The list exposes Edit and Delete actions only when the session has the corresponding permission; route and service checks remain authoritative. Delete is a soft delete recorded in `deleted_at` with an audit event; no hard-delete endpoint exists.

If a user has both mapped roles, Equipment access is denied until the role precedence is explicitly decided. `SEC-02` permits multiple roles but does not define precedence between these two Equipment branch-selection modes; this remains `NEEDS_DECISION`.

Only these four Equipment permissions are granted. Existing permission values are loaded into the authentication session at login, so users must sign in again after the migration for these grants to appear in their session. A restore action for soft-deleted inventory, the full `RBAC-MATRIX`, Super Admin's wider permission catalog/scope, and permission policy for any future Equipment workflow remain `NEEDS_DECISION`.

## Trial booking (`TRIAL-BOOK-01`)

**Status:** User-provided direction, implemented for this scoped change; not client approval. Direction date: 2026-09-21. Approver identity and client approval date are not recorded.

| Permission | Scope | Role grants and branch enforcement |
|---|---|---|
| `trial.book` | Open the existing booking form and create an eligible Trial. It does not authorize Lead edits or other Trial management actions. | A forward-only migration copies this permission to every role that already has `lead.update`, preserving existing booking access. Super Admin can book only when granted `trial.book` and only for a Lead in a currently authorized branch. The booking service checks branch authorization and eligibility on every request. Other Trial operations continue to require `lead.update`. |

The role-permission catalog includes `trial.book`. Permission values are loaded into the session at login; users need to sign out and back in after the migration to refresh permissions. No `lead.update` grant is added by this direction.

**Conflict:** Approved `TRIAL-01` says Trial Management uses existing Lead/CRM permissions and does not authorize a new permission. This user-provided direction introduces `trial.book` only for booking. The discrepancy remains `NEEDS_DECISION` for client reconciliation; this entry does not claim client approval or amend the approved decision.
## Coach and Attendance scoped user direction (2026-09-25; not client approval)

Existing permissions remain the boundary: `coach.view`, `coach.create`, `coach.update`, `coach.assign`, `coach.kpi.view`, `attendance.view`, `attendance.record`, and `attendance.correct`. Coach and Attendance KPI/alert reads must require the relevant existing view permission and current authorized branch scope. Coaches may mark only assigned sessions when `attendance.record` is granted; Branch Admin and Super Admin correction remains branch-scoped under `attendance.correct`. No new permission or global branch grant is introduced by this direction.

Coach Management scoped user direction (2026-09-25; not client approval): `coach.assign` governs Coach branch assignment and session assignment changes within current authorized branches. `coach.update` governs authorized leave/availability writes and corrections unless an existing more-specific permission is already established. `coach.kpi.view` governs feedback and performance KPI visibility with current branch scope. Scheduling permissions remain authoritative for session create/update and any session correction route. No broad administrative grant, global branch grant, or public/anonymous feedback visibility is introduced.

Coach availability policy direction (2026-09-25; user-provided, not client approval): existing `coach.update` is the only management permission used for availability create, edit, and cancellation. The service rechecks that permission against the availability record's branch and the Coach's current branch assignment. Coaches retain view-only access through their existing `coach.view` scope unless an existing permission explicitly grants update access. No new permission or branch grant is introduced.

Coach leave approval direction (2026-09-25; user-provided, not client approval): existing `coach.update` authorizes Branch Admin and Super Admin leave create, edit, approve, reject, and manager cancellation within the actor's authorized branches. A Coach's own pending cancellation is checked in the service against the authenticated Coach identity and authorized branch; no new permission is granted. Leave reads remain subject to `coach.view` and branch scope. UI visibility never replaces server-side checks.


Performance Improvement Index direction (PII-v1; user-provided, not client approval): existing `performance.update` authorizes approval and reasoned source corrections within the record branch. Existing `coach.kpi.view` authorizes the Coach KPI display, with current authorized branch and Player/Coach assignment scope enforced server-side. No new permission or branch grant is introduced. Hiding the KPI does not replace service authorization.

## Documents secure-default direction (2026-09-27; user-provided direction — not client approval)

Document authorization remains centralized and branch-scoped. The direction requires private storage, generic not-found responses, and server-side checks on every list, detail, upload, verify, preview, download, replacement, and retirement action. No new permission is granted by this record.

- Verification (`PENDING`, `VERIFIED`, `REJECTED`) may be performed only by explicitly authorized staff with access to the document's branch.
- Medical-document access must use an explicitly authorized role/type policy; the existing broad document permissions must not be interpreted as approval for medical visibility.
- Parent/Guardian and Player visibility requires an approved relationship and branch policy; no portal or self-service access is inferred.
- Retired documents remain visible only to authorized audit/history paths and cannot be downloaded.
- Replacement, scanning/quarantine, consent, expiry, and retention actions must reuse centralized authorization and append-only audit logging.
- Deployment-owner confirmation is required for scanner, private storage, retention, and encryption settings.

The exact role/type matrix, any future narrow permission names, and effective dates remain `NEEDS_DECISION`. This direction does not modify role grants or session permissions.

Implementation evidence (2026-09-27; user-provided direction — not client approval): verification reuses the existing branch-scoped `document.upload` staff permission for the action; no role grant is added. Medical documents require the explicit `document.medical.view` permission, which is not granted by this slice, so generic document permissions cannot expose medical records. Parent/Guardian and Player visibility remains unavailable until relationship and branch policy is approved. Retired, expired, quarantined, and unverified documents remain unavailable through normal download/preview paths.

## Portal session scope (user-provided direction — not client approval)

Portal users do not receive staff permissions and do not use the staff dashboard. The `portal` route filter requires a valid portal account session. Student access is server-scoped to its linked active Player; Parent access is server-scoped to active Players linked through guardian `player_contacts` relationships. No new staff RBAC grant is introduced. Portal activation, reset, consent, and detailed document visibility remain NEEDS_DECISION.

## Portal mobile OTP direction (user-provided direction — not client approval)

Student and Parent login requests use verified mobile ownership and OTP verification, not staff credentials or staff permissions. Portal sessions remain separate from staff sessions; branch and guardian relationship scope is resolved server-side. No new staff permission is granted. Mobile verification ownership, account activation, consent, and detailed document visibility remain NEEDS_DECISION.

Portal development OTP display direction (2026-10-02; user-provided direction — not client approval): the local OTP warning is not a staff permission and grants no portal or staff access. Server-side environment/database/flag gates apply independently of RBAC, and the display is never available through staff pages.
### Tournament fixture and standings slice — user-provided direction

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-03).** Existing `tournament.view` and `tournament.update` permissions are reused. Every fixture read and write is authorized against the Tournament branch through the central authorization service. No new permission is granted automatically. Completion, cancellation, postponement, rescheduling, and corrections require `tournament.update`, CSRF-protected routes, branch validation, and append-only audit metadata.

## Match Player Performance direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Match Player Performance uses existing `match.view` for read access, `match.update` for active Match entry, and `performance.update` for completed Match corrections. Services enforce branch authorization and Match Player membership; route visibility does not replace service checks. No new permission is granted by this slice. Any future dedicated permission remains NEEDS_DECISION.

## Match Player Fielding Details direction (2026-10-03)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Fielding uses the existing `match.update` and completed-Match `performance.update` permissions. No new permission is added. Match membership, branch authorization, CSRF, correction reasons, and audit records remain enforced by the existing service workflow.

## Tournament Phase 1 reconciliation — T-01 through T-04

**Recorded:** 2026-10-04  
**Status:** USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.  

The user selected the recommended secure defaults for the first four Tournament decision areas. These records authorize only the bounded implementation described below and do not approve T-05 through T-20.

- **T-01:** Keep the existing Tournament type allowlist; standalone branch-owned Tournaments; explicitly authorized Match links only; no parent competition hierarchy.
- **T-02:** Allow `planned → active → completed`, `planned → cancelled`, and `active → cancelled`; completed and cancelled are terminal; no reopening; exceptional changes require a reason; record transitions in append-only audit history.
- **T-03:** One owning branch; no implicit cross-branch access; Grounds, Teams, Players, Coaches, Fixtures, Matches, and reports must match the owning branch; no branch transfer workflow.
- **T-04:** Separate MCC-local date/time inputs; service-layer conversion; UTC storage where timestamps are required; no visible timezone suffix; fixtures must fall within Tournament start/end boundaries; use existing Match/Scheduling conventions.

**Approver identity:** User-provided direction; client approver not recorded.  
**Approval date:** Not client-approved.  
**Effective date:** 2026-10-04 for the bounded implementation only.

## Opponent catalog direction (2026-10-05)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Opponent records use branch-scoped `opponent.view`, `opponent.create`, and `opponent.update` permissions. Match selection remains subject to branch authorization.

Role mapping for this user-provided direction: Super Admin and Branch Admin receive `opponent.view`, `opponent.create`, and `opponent.update`; Coach receives `opponent.view` only. This is recorded as user-provided direction, not client approval, and must be reviewed before production deployment.
## Achievements permission gate (2026-10-06)

No Achievement permission is client-approved. The bounded catalog direction defines only `achievement.view` and `achievement.create`; all recipient, approval, certificate, portal, report/export, notification, and retention permissions remain `NEEDS_DECISION`. Future permissions must be least-privilege, centrally enforced, and branch-scoped independently of menu visibility.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** Future Achievement permissions should separate view, create, update, approve, revoke, and archive actions where required, with certificate/report/export permissions separated if approved. No `achievement.*` permission is created or granted by this direction.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The bounded catalog foundation defines only `achievement.view` and `achievement.create`, granted by its forward-only migration to Super Admin and Branch Admin. Services recheck permission and authorized branch scope. No update, approval, recipient, certificate, portal, report, export, notification, or retention permission is added.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06):** The selected draft-only award slice uses `achievement.view`, `achievement.create`, and `achievement.update` for Super Admin and Branch Admin draft operations only. No approval, publication, recipient-automation, certificate, portal, report/export, notification, retention, or archive permission is granted.

Implementation evidence: draft award routes use centralized `achievement.view`, `achievement.create`, and `achievement.update` filters; service methods repeat branch and permission checks. No publication or downstream permission exists.
## Phase 2 decision reconciliation

Reviewed 2026-10-06. No selected options, resolutions, comments, approver names, approval dates, or effective dates were recorded for APL-01 through APL-05. Blank fields are not approval. All five items remain NEEDS_DECISION; none is approved or rejected.

| ID | Workflow | Status |
| --- | --- | --- |
| APL-01 | Draft approval | NEEDS_DECISION |
| APL-02 | Publication | NEEDS_DECISION |
| APL-03 | Published lifecycle | NEEDS_DECISION |
| APL-04 | Revocation | NEEDS_DECISION |
| APL-05 | Correction after publication | NEEDS_DECISION |

Recommended defaults remain USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. No Phase 2 code, route, permission, migration, table, menu item, or test is authorized.
## Phase 2 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

On 2026-10-06, user-provided defaults were recorded for APL-01 through APL-05: explicit approval before publication using achievement.approve; branch-scoped publication of approved drafts using achievement.publish; read-only published records with reasoned achievement.update corrections; non-destructive reasoned revocation using achievement.revoke; and explicit post-publication correction preserving actor, branch, timestamp, old values, new values, and reason, with revoked-record correction restricted.

These are not client approvals. Approver, approval date, and effective date were not provided. All five items remain NEEDS_DECISION and no implementation is authorized.
## Phase 2 lifecycle implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The APL-01 through APL-05 defaults are implemented as a bounded lifecycle slice: draft approval with achievement.approve, publication with achievement.publish, read-only published records, reasoned revocation with achievement.revoke, and reasoned published corrections using achievement.update. Status changes are branch-scoped, transactional, version-checked, and audited with actor, branch, timestamp, reason, and before/after values. Revoked records are retained and no deletion workflow is introduced.

Certificates, portal visibility, reports, exports, notifications, retention, and automatic eligibility remain unavailable and unresolved.
## Phase 3 secure-default direction — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

Secure defaults were recorded for A3-01 Certificates, A3-02 Parent/Student portal visibility, A3-03 Reports and exports, A3-04 Notifications, A3-05 Retention and archival, and A3-06 Automatic eligibility. Each remains NEEDS_DECISION because no client approver, approval date, or effective date is provided. No code, route, permission, migration, table, menu, or test is authorized.
## A3-01 certificate implementation evidence — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

A bounded certificate slice is implemented for published Achievement awards only. It uses private LocalPrivateStorage, safe deterministic generated filenames, branch-scoped generate/download permissions, idempotent award-to-certificate linkage, and append-only generation audit events. No certificate portal visibility, replacement, retirement, or public URL workflow is introduced.

A3-02 portal visibility, A3-03 reports/exports, A3-04 notifications, A3-05 retention/archival, and A3-06 automatic eligibility remain NEEDS_DECISION and are not implemented.

### Achievement A3-03 RBAC note (2026-10-06)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The bounded report and CSV export reuse the existing `report.view` permission; no new report or export permission is granted. Branch filters are intersected with the session's authorized branch scope server-side.


## Director Dashboard secure-default direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** DASH-01 through DASH-20 use branch-scoped access with an explicit authorized “All Branches” option for Super Admin only; MCC-local date/month inputs with UTC conversion for stored timestamps; active, authorized source records; no sensitive medical, document, guardian, or private contact data; no invented KPI or performance formulas outside approved existing metrics; clear safe empty states; no caching until freshness and invalidation rules are defined; existing centralized permissions and branch authorization; exports inheriting dashboard filters and authorization; and audit limited to sensitive administrative actions without private data. Exact numeric formulas remain unresolved and every DASH item remains NEEDS_DECISION. No implementation, permission grant, migration, cache, export, or UI change is authorized by this direction.

## Individual Session Skill taxonomy direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Reuse existing Session permissions as the secure default; do not add skill or catalog permissions. SKILL-01 through SKILL-13 remain `NEEDS_DECISION` with blank approval fields, so no RBAC change is authorized.
## Match-Tournament ownership RBAC direction (2026-10-11)

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** Reuse existing `match.view`,
`match.create`, `match.update`, `tournament.view`, and `tournament.update`
permissions for the planning flow. Match, Fixture, Tournament, Ground,
Opponent, and Player references remain same-authorized-branch records. No
implicit cross-branch access and no new permission are granted.

Fixture status changes, Match completion, result correction, points correction,
postponement, cancellation, and rescheduling remain subject to server-side
authorization, branch checks, CSRF-protected browser POST routes, transactions,
and append-oriented audit metadata. Optimistic version checks are required by
the planning direction for corrections, but no implementation change is
authorized.

Every item remains `NEEDS_DECISION`; approver, approval date, and effective date
are blank. This direction does not grant permissions or approve routes.
## Admission PDF field capture

USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL. Existing `admission.view`,
`admission.create`, and `admission.update` permissions continue to govern all
new fields. Coach options are limited to active Coach users assigned to an
authorized Admission branch. No new permission is introduced.

## Admission Follow-ups (2026-10-16)

No new permission is introduced. `admission.view` gates follow-up list,
detail, and Admission history reads. `admission.create` gates new follow-up
creation, while `admission.update` gates edits. The service repeats the
permission check against the Admission's derived branch and ignores submitted
branch values. CSRF remains enforced by the authenticated browser form flow;
navigation visibility is only a presentation aid.
