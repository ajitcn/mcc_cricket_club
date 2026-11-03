# Achievements Phase 3 Decision Request

**Status:** NEEDS_DECISION for every item  
**Source:** docs/ACHIEVEMENTS-REMAINING-WORK-AUDIT.md  
**Classification:** Secure defaults are USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.  
**Scope:** Certificates, Parent/Student portal visibility, reports/exports, notifications, retention/archival, and automatic eligibility.

No application code, route, permission, migration, table, menu, or test change is authorized by this document. docs/SRS-original.md is unchanged.

## Decision rules

Blank fields are not approval. Each item remains NEEDS_DECISION until a selected option, resolution, comments, approver, approval date, and effective date are recorded.

---

## A3-01 — Certificates

**Status:** NEEDS_DECISION

**Current implementation evidence:** No Achievement certificate template, numbering, approval, private-storage, replacement, cancellation, download, scanning, encryption, or retention workflow exists.

**Secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Certificates require an approved published Achievement, a controlled template, server-generated filenames, private storage, explicit issue permission, and audited issue/replacement/retirement actions. No public document URLs.

**Decision choices:**

- [ ] A — Manual issue from approved published awards only.
- [ ] B — Automatic generation after publication.
- [ ] C — Manual issue followed by separate approval.
- [ ] D — Do not generate certificates; store award records only.

**Permission and branch scope:** Decide certificate issue, preview, replace, retire, and download permissions. Every action must be scoped to the Achievement branch; central access requires explicit authorization.

**Database and migration impact:** Template/version metadata, certificate number, private storage key, issue/replace/retire timestamps, actor IDs, and status may require a forward-only migration.

**Audit requirements:** Record actor, branch, award ID, certificate version/number, action, timestamp, reason, previous/new values, and storage reference without exposing document contents.

**Test acceptance criteria:** Unauthorized access denied; private storage enforced; safe filenames; branch isolation; duplicate numbering prevented; replacement/retirement audited; no public URLs.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## A3-02 — Parent/Student portal visibility

**Status:** NEEDS_DECISION

**Current implementation evidence:** No Achievement query or portal view exposes awards. Draft, revoked, and unpublished records have no portal route.

**Secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Show only approved published awards, filtered by the authenticated Player or verified Parent relationship. Draft, revoked, and archived records remain hidden.

**Decision choices:**

- [ ] A — Published awards visible to the Player only.
- [ ] B — Published awards visible to the Player and verified Parent contacts.
- [ ] C — Staff-only; no portal visibility.
- [ ] D — Player visibility with separate Parent opt-in.

**Permission and branch scope:** Keep staff and portal permissions separate. Portal reads must enforce Player/Parent relationship, owning branch, status, and revocation visibility.

**Database and migration impact:** Visibility flags, publication status queries, relationship checks, consent fields, or portal indexes may require a forward-only migration.

**Audit requirements:** Record visibility changes, consent/revocation decisions, actor, branch, timestamp, and reason. Do not audit or expose sensitive relationship data unnecessarily.

**Test acceptance criteria:** Draft/revoked exclusion; Player/Parent isolation; cross-branch denial; relationship enforcement; safe empty states; no sensitive metadata leakage.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## A3-03 — Reports and exports

**Status:** NEEDS_DECISION

**Current implementation evidence:** No Achievement report, export route, formula, filter contract, output format, or export permission exists.

**Secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Provide read-only branch-scoped reports from approved published records only. Exports remain disabled until fields, formats, privacy, and retention are approved.

**Decision choices:**

- [ ] A — Staff-only on-screen reports; no exports.
- [ ] B — Staff reports with CSV export.
- [ ] C — Staff reports with CSV and PDF export.
- [ ] D — No reports or exports.

**Permission and branch scope:** Decide separate achievement.report and achievement.export permissions. Reports and exports must enforce authorized branch filters server-side.

**Database and migration impact:** Add only approved indexes or reporting projections through forward-only migrations. Do not duplicate sensitive data without a documented need.

**Audit requirements:** Record report/export actor, branch filters, time, format, record scope, and reason where required. Do not log raw sensitive output.

**Test acceptance criteria:** Formula/source correctness; branch isolation; permission filtering; empty states; export authorization; no raw JSON or unauthorized fields.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## A3-04 — Notifications

**Status:** NEEDS_DECISION

**Current implementation evidence:** No Achievement notification event, recipient policy, template, channel, consent rule, retry strategy, or outbox integration exists.

**Secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Do not send Achievement notifications until recipients, consent, templates, channels, delivery, retry, and privacy rules are approved. If later enabled, use an idempotent outbox and fail-closed delivery.

**Decision choices:**

- [ ] A — In-app notifications to authorized staff only.
- [ ] B — In-app notifications to staff and verified portal recipients.
- [ ] C — In-app plus email/WhatsApp after explicit consent.
- [ ] D — No Achievement notifications.

**Permission and branch scope:** Decide event creation and delivery permissions. Recipients must be limited to authorized users and the Achievement branch.

**Database and migration impact:** Event/outbox, template, delivery-attempt, consent, and idempotency fields may require a forward-only migration.

**Audit requirements:** Record event type, actor, branch, recipient scope, delivery status, retry outcome, and failure reason without logging sensitive award content or tokens.

**Test acceptance criteria:** Consent enforcement; recipient scope; branch isolation; idempotency; retry/failure behavior; no real external delivery in tests; no sensitive content leakage.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## A3-05 — Retention and archival

**Status:** NEEDS_DECISION

**Current implementation evidence:** No Achievement retention period, archive state, legal hold, purge authority, archival job, restore workflow, or deletion policy exists.

**Secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Retain award and audit history; use logical archival only; prohibit physical deletion until an approved retention schedule and legal-hold policy exist.

**Decision choices:**

- [ ] A — Retain indefinitely; allow logical archive.
- [ ] B — Fixed retention period followed by reviewed logical archive.
- [ ] C — Archive after a business-defined period; deletion requires separate approval.
- [ ] D — No archival workflow yet.

**Permission and branch scope:** Decide archive, restore, legal-hold, and purge permissions. Actions remain branch-scoped and auditable.

**Database and migration impact:** Archive state, archive actor/time/reason, legal-hold marker, retention dates, and indexes may require a forward-only migration.

**Audit requirements:** Record archive/restore/hold/purge decisions, actor, branch, timestamp, reason, and prior/new state. Audit history must not be deleted.

**Test acceptance criteria:** No destructive deletion by default; legal hold prevents purge; branch and permission checks; archived records excluded from normal views; history retained.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## A3-06 — Automatic eligibility

**Status:** NEEDS_DECISION

**Current implementation evidence:** No automatic source integration or eligibility formula exists. Awards are manually created only.

**Secure default — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL:** Keep automatic eligibility disabled. If approved later, use only explicitly approved source data and formulas, produce nominations for human review, and never publish automatically.

**Decision choices:**

- [ ] A — No automatic eligibility; manual nomination only.
- [ ] B — Automatic nominations from approved source rules; human approval required.
- [ ] C — Automatic creation of drafts from approved source rules.
- [ ] D — Automatic approval/publication after source evaluation.

**Permission and branch scope:** Decide source-read, nomination, approval, and correction permissions. Source and recipient branches must match.

**Database and migration impact:** Rule versions, source references, evaluation periods, idempotency keys, and nomination metadata may require a forward-only migration.

**Audit requirements:** Record rule version, source IDs, evaluation time, actor/job identity, branch, candidate outcome, and later approval/correction reason. Do not log sensitive source payloads.

**Test acceptance criteria:** Deterministic formulas; source authorization; branch isolation; inactive recipient exclusion; duplicate/idempotency protection; rollback; human approval gate; no fabricated values.

**Resolution:** [ ] NEEDS_DECISION — option: ______  
**Comments:** ______________________________________________  
**Approver:** ____________________  **Approval date:** __________  **Effective date:** __________

---

## Consolidated decision record

| ID | Item | Selected option | Resolution | Comments | Approver | Approval date | Effective date | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A3-01 | Certificates |  |  |  |  |  |  | NEEDS_DECISION |
| A3-02 | Portal visibility |  |  |  |  |  |  | NEEDS_DECISION |
| A3-03 | Reports and exports |  |  |  |  |  |  | NEEDS_DECISION |
| A3-04 | Notifications |  |  |  |  |  |  | NEEDS_DECISION |
| A3-05 | Retention and archival |  |  |  |  |  |  | NEEDS_DECISION |
| A3-06 | Automatic eligibility |  |  |  |  |  |  | NEEDS_DECISION |

## Implementation gate

Do not implement any Phase 3 item until its decision fields are completed explicitly. Recommended defaults are not approval. docs/SRS-original.md must remain unchanged.## Applied secure defaults — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

Reviewed 2026-10-06. The following secure defaults are recorded as planning direction only. No client approver, approval date, or effective date was provided, so every item remains NEEDS_DECISION.

### A3-01 — Certificates

- **Selected secure default:** Manual certificate issue only after an approved published Achievement.
- **Resolution:** Use controlled templates, server-generated filenames, private storage, explicit issue/preview/replace/retire permissions, and audited actions. No public document URLs.
- **Implementation scope if later approved:** Certificate metadata, private document service integration, template/version handling, issue, preview, replacement, and retirement.
- **Permissions and branch rules:** Separate certificate permissions; every action must match the Achievement owning branch and authorized staff scope.
- **Migration impact:** Certificate number, template/version, storage key, status, actor, and timestamps may require a forward-only migration.
- **Audit requirements:** Actor, branch, award, certificate version/number, action, timestamp, reason, and previous/new values; never document contents or secrets.
- **Test criteria:** Private storage, authorization, branch isolation, safe filenames, duplicate numbering prevention, replacement/retirement audit, and no public URLs.

### A3-02 — Parent/Student portal visibility

- **Selected secure default:** Show only approved published awards to the Player and verified Parent relationship.
- **Resolution:** Draft, revoked, and archived awards remain hidden. Portal access is read-only and relationship-scoped.
- **Implementation scope if later approved:** Portal query, status filtering, Player/Parent relationship checks, branch isolation, and safe empty states.
- **Permissions and branch rules:** Keep portal permissions separate from staff permissions; enforce authenticated relationship and owning branch server-side.
- **Migration impact:** Visibility, consent, publication, or relationship indexes may require a forward-only migration.
- **Audit requirements:** Visibility and consent changes record actor, branch, timestamp, reason, and prior/new values without exposing sensitive relationship data.
- **Test criteria:** Player/Parent isolation, draft/revoked exclusion, cross-branch denial, relationship enforcement, and no sensitive metadata leakage.

### A3-03 — Reports and exports

- **Selected secure default:** Staff-only, branch-scoped read-only reports; exports remain disabled until separately approved.
- **Resolution:** Use approved published records only, with no fabricated formulas or unauthorized fields.
- **Implementation scope if later approved:** Read-only report queries, approved filters, empty states, and separately approved export formats.
- **Permissions and branch rules:** Separate achievement.report and achievement.export permissions; all filters and output queries enforce branch authorization server-side.
- **Migration impact:** Only approved indexes or reporting projections may be added through forward-only migrations.
- **Audit requirements:** Actor, branch filter, timestamp, report type, format, and record scope; never log raw sensitive output.
- **Test criteria:** Source correctness, branch filtering, permission enforcement, empty states, export authorization, and no raw JSON/sensitive leakage.

### A3-04 — Notifications

- **Selected secure default:** Keep Achievement notifications disabled until recipients, consent, channels, templates, delivery, and retry policy are approved.
- **Resolution:** If later approved, use an idempotent outbox and fail-closed delivery with no sensitive award content in unapproved messages.
- **Implementation scope if later approved:** Event definitions, recipient resolver, templates, consent checks, outbox, retry, failure handling, and audit.
- **Permissions and branch rules:** Separate event and delivery permissions; recipients must be authorized users in the Achievement branch.
- **Migration impact:** Outbox, template, delivery-attempt, consent, and idempotency fields may require a forward-only migration.
- **Audit requirements:** Event, actor, branch, recipient scope, delivery state, retry outcome, and failure reason; never OTPs, tokens, or sensitive payloads.
- **Test criteria:** Consent, scope, branch isolation, idempotency, retry/failure behavior, and no real external delivery in tests.

### A3-05 — Retention and archival

- **Selected secure default:** Retain awards and audit history indefinitely until a retention policy is approved; allow logical archival only after approval.
- **Resolution:** No physical deletion, purge, or destructive cleanup is allowed by this direction.
- **Implementation scope if later approved:** Archive state, legal hold, retention schedule, restore controls, and separately authorized purge.
- **Permissions and branch rules:** Separate archive, restore, legal-hold, and purge permissions; actions remain branch-scoped and audited.
- **Migration impact:** Archive state, archive actor/time/reason, legal-hold marker, retention dates, and indexes may require a forward-only migration.
- **Audit requirements:** Archive, restore, hold, or purge actor, branch, timestamp, reason, and prior/new state; audit history itself remains protected.
- **Test criteria:** No deletion by default, legal hold prevents purge, branch/RBAC checks, archived records excluded from normal views, and history retained.

### A3-06 — Automatic eligibility

- **Selected secure default:** Keep automatic eligibility disabled; manual nomination remains the only creation source.
- **Resolution:** If later approved, evaluate only explicitly approved source data, create nominations or drafts for human review, and never publish automatically.
- **Implementation scope if later approved:** Versioned source rules, evaluator, candidate records, idempotency, review queue, and correction handling.
- **Permissions and branch rules:** Separate source-read, nomination, approval, and correction permissions; source and recipient branches must match.
- **Migration impact:** Rule versions, source references, evaluation periods, idempotency keys, and nomination metadata may require a forward-only migration.
- **Audit requirements:** Rule version, source IDs, evaluation time, actor/job identity, branch, candidate outcome, and later approval/correction reason; no sensitive source payloads.
- **Test criteria:** Deterministic rules, source authorization, branch isolation, inactive-recipient exclusion, duplicate prevention, rollback, human approval gate, and no fabricated values.

## Approval state

All A3-01 through A3-06 remain NEEDS_DECISION. Client approver, approval date, and effective date are not provided. These defaults do not authorize implementation.
