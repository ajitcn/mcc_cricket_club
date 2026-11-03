# Achievements Requirements Decision Questionnaire

**Document status:** `NEEDS_DECISION` for every item below  
**Purpose:** Capture requirements decisions before any Achievements code, route, permission, migration, table, menu entry, or placeholder page is created.

**Response reconciliation (2026-10-06):** No option, resolution, approver, approval date, or effective date has been completed for any item. Blank fields are not approval. All items therefore remain `NEEDS_DECISION`.

## Evidence and decision rules

- **Code evidence:** `docs/ACHIEVEMENTS-READ-ONLY-AUDIT.md` reports no Achievements routes, controllers, services, models, views, migrations, permissions, tables, or tests.
- **Document evidence:** `docs/REQUIREMENTS.md` treats Achievements as future scope. No dedicated approved Achievements decision record was found in `docs/DECISIONS.md`, `docs/RBAC.md`, `docs/ARCHITECTURE.md`, or `docs/DATABASE.md`.
- **Client approval:** No Achievements item is client-approved. Existing Tournament, Match, Attendance, Performance, Documents, and Portal directions remain separate dependencies and do not approve an Achievements workflow.
- **User direction:** This questionnaire request is a request to decide requirements, not implementation approval.
- **Authoritative source limitation:** `docs/SRS-original.md` was requested for review and was not modified; it was not present at the expected path in this workspace during preparation.

Each item is intentionally left unresolved. A recommendation is a secure starting point only and is not an approval.

## Secure-default reconciliation

The following defaults are recorded at the user's direction as **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL**. They do not change the `NEEDS_DECISION` status and do not authorize implementation.

## Explicit next-scope selection

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-06).** The selected next scope is **Manual award creation (draft-only)**, supported by **Player recipient assignment** and **Coach recipient assignment**. This selection does not approve automatic eligibility, approval/publication, certificates, portal visibility, reports/exports, notifications, retention, or archival workflows.

### Selected scope contract

- **Exact fields:** `achievement_id`, `recipient_type` (`player` or `coach`), exactly one of `player_id` or `coach_id`, `branch_id`, `status` (`draft` only), optional `nomination_note` bounded to 2,000 characters, `created_by`, `created_at`, and `updated_at`.
- **Allowed statuses:** `draft` only. No `approved`, `revoked`, `archived`, or published state is available in this slice.
- **Authorized roles and permissions:** Existing `achievement.view` for reads and a new documented `achievement.create`/`achievement.update` mapping for draft creation/editing only, granted only to Super Admin and Branch Admin after migration review. No approval permission is granted.
- **Branch visibility:** The award draft and recipient must belong to one owning branch. No implicit cross-branch access; every selector and service query revalidates branch authorization.
- **Validation:** Achievement must exist and be active; recipient type must be allowed; exactly one valid active Player or Coach ID must be submitted; recipient branch must equal the Achievement branch; forged, inactive, deleted, duplicate, or cross-branch IDs are rejected; notes are bounded and escaped.
- **Approval authority:** Not applicable to draft-only records. Approval workflow remains `NEEDS_DECISION` and is not implemented.
- **Correction/revocation:** Draft edits require `achievement.update`; no revocation or correction of approved records exists in this slice. Draft changes record before/after values and a reason when an existing draft is edited.
- **Audit fields:** Actor, branch, timestamp, entity, recipient type/ID, previous values, new values, and reason where applicable. No passwords, tokens, or sensitive data may be logged.
- **Portal visibility:** None. Drafts are staff-only and never exposed to Parent/Student portal users.
- **Migration impact:** A forward-only award-draft table or equivalent recipient columns, foreign keys, indexes, status, notes, and audit linkage are required; no migration is applied by this documentation update.
- **Test acceptance criteria:** Unit validation for recipient exclusivity and draft status; Feature permission/CSRF/branch tests; insert-only database tests for Player and Coach drafts, cross-branch rejection, duplicate rejection, idempotent draft update, rollback, and audit metadata; no portal visibility.

### Explicitly unselected and unresolved

Automatic award creation, eligibility automation, approval authority, publication, lifecycle transitions beyond `draft`, duplicate-award policy for published awards, certificates, Parent/Student visibility, reports, exports, notifications, retention, and archival remain `NEEDS_DECISION`.

| # | Selected default | Resolution | Comments | Approver | Approval date | Effective date | Migration impact | Permission impact | Security requirements | Test acceptance criteria |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | Controlled active/inactive category catalog | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** No authoritative free text. | Secure default pending client approval. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Future category migration only after approval. | Future category permission; no grant now. | Branch scope, validation, escaped output, audit. | Invalid/inactive/cross-branch categories reject. |
| 2 | Required bounded title; optional bounded plain-text description | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | No unbounded HTML. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Future award fields only after approval. | Create/update permissions undefined. | CSRF, validation, escaping, length limits. | Blank/oversize values reject; errors persist. |
| 3 | Explicit active Player/Coach selection in owning branch | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** No inferred KPI eligibility. | Automatic eligibility off. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Recipient foreign keys require approval. | Recipient permissions undefined. | Revalidate recipient, branch, status, relationship. | Inactive/forged/cross-branch recipients reject. |
| 4 | One owning branch; no implicit cross-branch visibility | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Central access requires explicit authorization. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Branch key/index requires approval. | Central branch authorization required. | Safe denial/not-found; no client trust. | Cross-branch access is denied. |
| 5 | Manual source references only until policies are approved | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Tournament, Match, Attendance, Performance, milestone automation disabled. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Source constraints deferred. | Reuse source permissions; no grants. | Validate source ownership, branch, status, access. | Unauthorized/deleted/cross-branch sources reject. |
| 6 | Manual award creation; no automatic creation | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Automation requires separate approval. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Automation metadata deferred. | Create/approve separation required later. | No unapproved background writes. | Repeated submissions are idempotent. |
| 7 | Explicit branch-authorized approver; no self-approval | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Approver role remains unselected. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Approval metadata deferred. | Approval separate from create/update. | Server-side branch/CSRF/audit checks. | Unauthorized/self approval follows approved rule. |
| 8 | `draft → approved`; `approved → revoked` or `archived`; no reopening | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Terminal behavior needs approval. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Status/transition metadata deferred. | Transition permissions undefined. | Published records lock; reasoned changes. | Invalid transitions/reopening reject. |
| 9 | Unique recipient/category/source/effective-period combination | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Duplicate override off. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Unique indexes deferred. | Override needs separate authorization. | Transactional duplicate prevention. | Repeated/concurrent submissions cannot duplicate. |
| 10 | Reason required for correction, revocation, replacement, archival | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Preserve before/after append-only. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | History/reason storage deferred. | Correction/revoke/archive permissions undefined. | No secrets in reasons; audit actor/branch/time. | Missing reasons reject; prior values recoverable. |
| 11 | No certificates until template, numbering, approval, private storage, scanning, retention, replacement are approved | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Private storage remains required if later approved. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Certificate schema deferred. | Issue/replace/retire/download permissions undefined. | No public URLs; safe filenames; authorized access. | Unauthorized/unsafe generation rejects. |
| 12 | Staff-only visibility; portal exposure off by default | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Parent/Student access requires approved relationship/consent. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Portal visibility fields deferred. | Staff/portal permissions remain separate. | No draft/revoked/archive leakage. | Portal access is denied until approved. |
| 13 | Future least-privilege `achievement.view/create/update/approve/revoke/archive` permissions | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** No grants now. | Certificate/report/export permissions may be separate. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Permission migration deferred. | Central RBAC; no automatic grants. | Menu never substitutes authorization. | Missing permission/branch denies action. |
| 14 | Append-only audit events with actor, branch, timestamp, reason, before/after | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Separate history table not assumed. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Audit/history storage deferred. | Audit visibility restricted. | Never log passwords, tokens, document content. | Events complete, immutable, scoped, idempotent. |
| 15 | MCC-local date/time input; service conversion; UTC storage where required; no timezone suffix | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Reuse existing application timezone. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Date/time schema deferred. | Backdating authority undefined. | Validate boundaries server-side. | Invalid/boundary cases reject; UI omits suffix. |
| 16 | Retain history; logical archive only; no physical deletion without approved schedule | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Retention period unresolved. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Archive/retention metadata deferred. | Archive/restore/delete permissions undefined. | No destructive cleanup; history protected. | Retention cannot delete protected history. |
| 17 | No reports/exports until formulas, fields, filters, privacy, formats approved | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Report behavior remains blocked. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Report indexes deferred. | Report/export permissions undefined. | Branch filters; no raw JSON/sensitive fields. | Results match approved sources and scope. |
| 18 | No notifications until recipients, channels, consent, templates, delivery, retry, retention approved | **USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** | Existing notification infrastructure unchanged. | User-provided direction; client approver not recorded. | Not client-approved. | Not effective for implementation. | Outbox/template storage deferred. | Delivery permissions undefined. | Fail closed; no sensitive/unapproved delivery. | Recipient scope, idempotency, failure audit tested later. |

---

## 1. Achievement categories

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No category model, catalog, route, permission, migration, or test exists.  
**Related requirement/decision ID:** Achievements future-scope entry in `REQUIREMENTS.md`; no dedicated approved ID exists.

**Recommended secure default:** Use a centrally managed, finite category catalog with active/inactive state. Do not accept authoritative free-text categories.

**Decision choices:**

- [ ] A — Fixed system categories defined in code.
- [ ] B — Branch-scoped admin-managed categories.
- [ ] C — Centrally managed categories with optional branch visibility.
- [ ] D — Free-text category per award.

**Resolution:** `[ ] NEEDS_DECISION — select one option: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Category storage and seed/reference migration may be required; no migration is authorized now.  
**Permission impact:** Requires a documented category-management permission if categories are managed in-app; no permission is granted now.  
**Test acceptance criteria:** Authorized users see only active authorized categories; invalid, inactive, and cross-branch categories are rejected server-side; category changes are audited.

## 2. Award title and description

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No Achievement form, fields, validation, or persistence exists.  
**Related requirement/decision ID:** Achievements future-scope entry in `REQUIREMENTS.md`; no dedicated approved ID exists.

**Recommended secure default:** Require a bounded, escaped title; allow an optional bounded description; preserve plain text safely and reject blank titles.

**Decision choices:**

- [ ] A — Title required; description optional plain text.
- [ ] B — Title and description both required.
- [ ] C — Title required; description and evidence attachment optional.

**Resolution:** `[ ] NEEDS_DECISION — select one option: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** New Achievement title/description columns would be required; no schema change is authorized now.  
**Permission impact:** Create/update authorization must be decided; no permission is granted now.  
**Test acceptance criteria:** Required and length rules are enforced server-side; output is escaped; submitted values and field errors are preserved.

## 3. Player and Coach eligibility

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** Player and Coach modules exist, but no Achievement eligibility service or relationship exists. No formula or milestone rule is approved.  
**Related requirement/decision ID:** Player/Coach requirements and future Achievements scope in `REQUIREMENTS.md`; no approved eligibility ID.

**Recommended secure default:** Permit only explicitly selected active Players or Coaches in the authorized branch; do not infer eligibility from unapproved KPI or performance formulas.

**Decision choices:**

- [ ] A — Manual eligibility by authorized staff.
- [ ] B — Eligibility from approved source rules only.
- [ ] C — Manual nomination plus source-evidence review.
- [ ] D — Allow both Player and Coach recipients under separate rules.

**Resolution:** `[ ] NEEDS_DECISION — select one option: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Recipient type and recipient foreign-key strategy must be decided; no migration is authorized now.  
**Permission impact:** Eligibility review and recipient selection permissions must be defined; no grants now.  
**Test acceptance criteria:** Ineligible, inactive, forged, and cross-branch recipients are rejected; approved recipient relationships are enforced and audited.

## 4. Branch ownership and visibility

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No Achievement branch column or access query exists. Existing modules use server-side branch authorization.  
**Related requirement/decision ID:** Branch-isolation baseline in `REQUIREMENTS.md`, `RBAC.md`, and `ARCHITECTURE.md`; no Achievement-specific decision.

**Recommended secure default:** One owning branch per Achievement, with no implicit cross-branch access; Super Admin access remains explicit and audited.

**Decision choices:**

- [ ] A — Single owning branch; branch-only visibility.
- [ ] B — Multi-branch Achievement with explicit authorized branch list.
- [ ] C — Owning branch plus read-only central visibility.

**Resolution:** `[ ] NEEDS_DECISION — select one option: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Requires branch ownership or authorization mapping and indexes; no migration now.  
**Permission impact:** All list/detail/write permissions must enforce branch scope server-side.  
**Test acceptance criteria:** Cross-branch IDs return safe denial/not-found behavior; list, detail, selectors, reports, and exports never leak unauthorized records.

## 5. Source records and attribution

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** Tournament, Match, Attendance, Performance, and milestone-related records exist in separate modules, but no Achievement source linkage or approved calculation exists. Tournament T-01–T-20 and KPI/report policies remain separate decision areas.  
**Related requirement/decision ID:** Existing Tournament, Match, Attendance, Performance, and Development decision records; no approved Achievement source ID.

**Recommended secure default:** Store only explicitly selected source references; do not fabricate awards from unapproved formulas or copy sensitive source data into a new table.

**Decision choices:**

- [ ] A — Manual source reference only.
- [ ] B — Approved Match/Tournament results only.
- [ ] C — Approved Attendance/Performance/milestone rules only.
- [ ] D — Multiple source types after separate approval for each.

**Resolution:** `[ ] NEEDS_DECISION — select source types: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Source-type and source-ID fields, indexes, and integrity rules may be required; no migration now.  
**Permission impact:** Source visibility must be checked using the source module’s centralized permissions and branch scope.  
**Test acceptance criteria:** Only approved source types are accepted; forged, cross-branch, deleted, or unauthorized source IDs are rejected; attribution is deterministic and audited.

## 6. Manual versus automatic award creation

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No creation workflow or automation exists.  
**Related requirement/decision ID:** Achievements future scope; no approved automation decision.

**Recommended secure default:** Manual nomination/creation by authorized staff until every automatic source formula and correction policy is approved.

**Decision choices:**

- [ ] A — Manual creation only.
- [ ] B — Automatic creation from approved rules only.
- [ ] C — Automatic nomination followed by human approval.

**Resolution:** `[ ] NEEDS_DECISION — select one option: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Automation metadata, rule version, and idempotency keys may be needed; no migration now.  
**Permission impact:** Create and approve permissions must be separated where automation is used.  
**Test acceptance criteria:** Repeated source processing is idempotent; unauthorized manual creation is denied; automatic rules never create records outside approved scope.

## 7. Approval authority

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No approval route, authority mapping, or permission exists.  
**Related requirement/decision ID:** `RBAC.md` role baseline; no Achievement approval mapping.

**Recommended secure default:** Require a distinct branch-authorized approval permission and prohibit self-approval unless explicitly decided.

**Decision choices:**

- [ ] A — Branch Admin approval.
- [ ] B — Super Admin approval only.
- [ ] C — Designated Achievement approver role.
- [ ] D — Two-person approval for selected categories.

**Resolution:** `[ ] NEEDS_DECISION — select one option: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Approval actor/time/reason fields would be required; no migration now.  
**Permission impact:** Approval must be separate from create/update; no role grants now.  
**Test acceptance criteria:** Unauthorized approval is denied; self-approval behavior matches the decision; approval actor, branch, time, and reason are audited.

## 8. Lifecycle statuses

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No Achievement status field or transition service exists.  
**Related requirement/decision ID:** No Achievement lifecycle decision exists.

**Recommended secure default:** `draft → approved`; `approved → revoked` or `archived` with a required reason; no reopening without an explicit decision.

**Decision choices:**

- [ ] A — Draft, Approved, Revoked, Archived; terminal Revoked/Archived.
- [ ] B — Draft, Pending approval, Approved, Revoked, Archived.
- [ ] C — Approved records may be corrected through a reasoned version workflow.

**Resolution:** `[ ] NEEDS_DECISION — select lifecycle: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Status, transition metadata, and lock fields may be required; no migration now.  
**Permission impact:** Transition permissions and terminal-state enforcement must be documented.  
**Test acceptance criteria:** Invalid transitions, unauthorized reopening, and edits to locked records are rejected; valid transitions are transactional and audited.

## 9. Duplicate-award prevention

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No Achievement table, uniqueness rule, or deduplication service exists.  
**Related requirement/decision ID:** No Achievement duplicate policy exists.

**Recommended secure default:** Prevent duplicates for the same recipient, category, approved source, and effective period; allow correction only through an audited workflow.

**Decision choices:**

- [ ] A — One award per recipient/category/source.
- [ ] B — One award per recipient/category/effective date.
- [ ] C — Allow duplicates with an explicit reason and approval.

**Resolution:** `[ ] NEEDS_DECISION — select uniqueness rule: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Unique indexes and idempotency keys may be required; no migration now.  
**Permission impact:** Duplicate override, if allowed, requires separate authorization.  
**Test acceptance criteria:** Concurrent and repeated submissions cannot create unauthorized duplicates; approved exceptions are reasoned and audited.

## 10. Correction and revocation reasons

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No correction or revocation workflow exists.  
**Related requirement/decision ID:** Audit baseline in `REQUIREMENTS.md`/`ARCHITECTURE.md`; no Achievement-specific policy.

**Recommended secure default:** Require a non-empty reason for corrections, revocation, replacement, and archival; preserve before/after values append-only.

**Decision choices:**

- [ ] A — Reason required for every correction and revocation.
- [ ] B — Reason required only after approval.
- [ ] C — Reason plus second-person approval for revocation.

**Resolution:** `[ ] NEEDS_DECISION — select option: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** History/reason fields or an append-only history table may be required; no migration now.  
**Permission impact:** Correction and revoke permissions must be distinct from create.  
**Test acceptance criteria:** Missing/blank reasons are rejected where required; prior values remain recoverable; actor, branch, timestamp, and reason are recorded.

## 11. Certificates and document generation

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** Documents has a foundation, but no Achievement certificate template, numbering, generation, or issue workflow exists.  
**Related requirement/decision ID:** Documents policy direction is user-provided and not client approval; no certificate decision exists.

**Recommended secure default:** Do not generate or expose certificates until template, numbering, approval, private storage, scanning, retention, and replacement rules are approved.

**Decision choices:**

- [ ] A — No certificates in the first release.
- [ ] B — Generate private certificates from one approved template.
- [ ] C — Staff-uploaded private certificate only.
- [ ] D — Multiple templates with controlled numbering and approval.

**Resolution:** `[ ] NEEDS_DECISION — select option: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Template/version/number/storage metadata may be required; no migration now.  
**Permission impact:** Issue, replace, retire, preview, and download permissions must be defined; no grants now.  
**Test acceptance criteria:** Files remain private; safe filenames and authorization are enforced; issue/replacement/retirement are audited; no public URLs or raw metadata appear.

## 12. Parent/Student portal visibility

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** Portal authentication and dashboard foundations exist, but Achievement visibility, consent, relationship, and document rules are not approved.  
**Related requirement/decision ID:** Portal direction in `REQUIREMENTS.md`/`DECISIONS.md`; no Achievement portal decision.

**Recommended secure default:** Staff-only visibility; no portal exposure until recipient relationship, consent, privacy, and document rules are approved.

**Decision choices:**

- [ ] A — Staff only.
- [ ] B — Player sees own approved achievements.
- [ ] C — Parent sees linked Player achievements.
- [ ] D — Player and authorized linked Parent after explicit consent.

**Resolution:** `[ ] NEEDS_DECISION — select visibility: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Portal visibility/consent fields may be required; no migration now.  
**Permission impact:** Portal read policy must be separate from staff permissions and relationship checks.  
**Test acceptance criteria:** Portal users see only approved linked records; cross-player, cross-branch, draft, revoked, and archived records are excluded as decided.

## 13. Staff role permissions

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No `achievement.*` permissions or role mappings exist.  
**Related requirement/decision ID:** Role baseline in `RBAC.md`; no Achievement permission matrix.

**Recommended secure default:** Define least-privilege permissions such as `achievement.view`, `create`, `update`, `approve`, `revoke`, and `archive` only after workflow approval; do not grant automatically.

**Decision choices:**

- [ ] A — Reuse existing Player/Coach permissions only.
- [ ] B — Add separate least-privilege Achievement permissions.
- [ ] C — Separate create, approve, revoke, archive, and certificate permissions.

**Resolution:** `[ ] NEEDS_DECISION — select role/permission mapping: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Permission and role mapping migration may be required; no migration now.  
**Permission impact:** This item defines all Achievement RBAC; no permissions are added by this questionnaire.  
**Test acceptance criteria:** Menu visibility never substitutes for route/service authorization; each action is denied without its explicit permission and branch scope.

## 14. Audit history

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** Shared audit infrastructure exists, but no Achievement events or history model exists.  
**Related requirement/decision ID:** Audit baseline in `REQUIREMENTS.md`, `ARCHITECTURE.md`, and `RBAC.md`; no Achievement event list.

**Recommended secure default:** Append-only events for create, update, approve, revoke, archive, certificate issue/replacement, and visibility changes, recording actor, branch, timestamp, reason, previous values, and new values without secrets.

**Decision choices:**

- [ ] A — Shared audit log only.
- [ ] B — Shared audit log plus Achievement history table.
- [ ] C — History table for user-facing changes plus shared audit events.

**Resolution:** `[ ] NEEDS_DECISION — select option and event list: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** History fields/table and indexes may be required; no migration now.  
**Permission impact:** History visibility must be restricted to authorized staff/audit roles.  
**Test acceptance criteria:** Events are append-only, branch-scoped, complete, and free of passwords, tokens, and sensitive document content.

## 15. Effective dates and timezone handling

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** Existing modules use application date/time conventions, but Achievements has no date fields or service conversion.  
**Related requirement/decision ID:** Existing Match/Scheduling date/time conventions; no Achievement-specific decision.

**Recommended secure default:** Separate local date/time inputs where needed, convert in the service layer, store UTC timestamps, and omit timezone suffixes from visible UI.

**Decision choices:**

- [ ] A — Effective date only in application timezone.
- [ ] B — Effective local date and time converted to UTC.
- [ ] C — Start and end dates with explicit inclusive boundary rules.

**Resolution:** `[ ] NEEDS_DECISION — select date/time and boundary rules: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Date/time columns and indexes may be required; no migration now.  
**Permission impact:** Date corrections and backdating authority must be decided.  
**Test acceptance criteria:** Boundary and invalid-date cases are deterministic; UTC storage is consistent; UI has no visible timezone suffix.

## 16. Retention and archival

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No Achievement retention, archive job, deletion policy, or lifecycle storage exists.  
**Related requirement/decision ID:** Retention is unresolved in the reviewed requirements and documents.

**Recommended secure default:** Retain approved, revoked, and archived history; use logical archival only; do not physically delete without an approved retention schedule and owner.

**Decision choices:**

- [ ] A — Indefinite retention with logical archive.
- [ ] B — Retain for a defined period: ______.
- [ ] C — Archive to controlled private storage after a defined period.
- [ ] D — Delete after a legally approved period: ______.

**Resolution:** `[ ] NEEDS_DECISION — select retention/archive rule: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Archive markers, retention metadata, and scheduled operations may be required; no migration now.  
**Permission impact:** Archive, restore, and deletion permissions must be explicit; no destructive operation is authorized now.  
**Test acceptance criteria:** Retention jobs cannot delete protected history; archived records remain authorized and auditable; branch/privacy scope is preserved.

## 17. Reports and exports

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** No Achievement report or export exists. Existing report formulas and export policies are not an Achievement approval.  
**Related requirement/decision ID:** Reports/dashboard items remain unresolved where formulas, visibility, or export policy is unspecified.

**Recommended secure default:** No reports or exports until fields, formulas, filters, branch scope, sensitive-data handling, and allowed formats are approved.

**Decision choices:**

- [ ] A — No reports/exports.
- [ ] B — Authorized branch-scoped on-screen report only.
- [ ] C — Branch-scoped CSV/PDF export with audit event.
- [ ] D — Central report with explicit branch authorization.

**Resolution:** `[ ] NEEDS_DECISION — select option and fields: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Usually none, but report indexes/materialized data may be required after formulas are approved; no migration now.  
**Permission impact:** Report and export permissions must be separate and branch-scoped.  
**Test acceptance criteria:** Results match approved source rules; unauthorized branches and recipients are excluded; exports contain no raw JSON or sensitive fields.

## 18. Notifications

**Status:** `NEEDS_DECISION`  
**Current implementation evidence:** In-app notification foundations exist, but no Achievement recipient, channel, template, delivery, retry, or retention policy exists.  
**Related requirement/decision ID:** Notification delivery remains unresolved in the reviewed requirements and backlog.

**Recommended secure default:** No Achievement notifications until recipients, events, channels, templates, consent, failure handling, and delivery ownership are approved.

**Decision choices:**

- [ ] A — No notifications.
- [ ] B — In-app notifications only.
- [ ] C — In-app plus email/WhatsApp through approved providers.
- [ ] D — Staff-only notifications; portal delivery deferred.

**Resolution:** `[ ] NEEDS_DECISION — select channels, recipients, and events: ____`  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________  
**Migration impact:** Notification event/template/outbox data may be required; no migration now.  
**Permission impact:** Recipient visibility, notification management, and delivery permissions must be documented.  
**Test acceptance criteria:** Only approved recipients receive notifications; no sensitive content leaks; retries are idempotent; delivery failures are auditable and fail safely.

---

## Consolidated approval record

No item in this questionnaire is approved by default. Implementation remains blocked until the relevant choices are resolved and recorded by an authorized approver in the project decision records.

**Approver identity:** ______________________________  
**Approval date:** __________________  
**Effective date:** __________________  
**Scope approved (item numbers):** ______________________________  
**Conditions or exclusions:** ____________________________________
