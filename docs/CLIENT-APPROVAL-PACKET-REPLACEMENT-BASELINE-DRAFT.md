# MCC Cricket Club — Client Approval Packet

## Versioned Replacement Requirements Baseline

**Document status:** Draft for client review; not approved  
**Prepared:** 2026-09-20  
**Proposed baseline title:** MCC Cricket Club Management System Requirements Baseline  
**Baseline version:** `[Client to assign]`  
**Effective date:** `[Client to approve]`  
**Client approver(s):** `[Names and titles to record]`

## Purpose and authority

The project refers to `docs/SRS-original.md` as the original client SRS, Version 1.0. The file and any verifiable copy were not found in the project search. `docs/SRS.md` identifies itself as a controlled working specification and explicitly says it is not a replacement for missing client approval. This packet therefore requests approval of a **new, versioned baseline**; it does not reconstruct the missing SRS or make new business rules.

The status labels below reproduce the current record in `docs/DECISIONS.md` unless a conflict is called out. `APPROVED` decisions are carried forward as recorded. `NEEDS_DECISION` items remain unanswered. A `PROPOSED` recommendation elsewhere in the project is not an approved answer.

## Client approval statement

> I approve the MCC Cricket Club Management System Requirements Baseline, version `[version]`, effective `[date]`, as the authoritative client requirements baseline from that date. I confirm the scope and phase decisions recorded in the approved baseline and its decision register. Unresolved items remain marked `NEEDS_DECISION` and do not authorize implementation. This baseline supersedes `[identify prior baseline, or state none]` while preserving the decision history and approvals listed in the accompanying register.

**Approver name/title:** ______________________________  
**Organization:** _____________________________________  
**Signature or approval reference:** ___________________  
**Date:** ____________________________________________

## Scope and phase questions

Please answer these for the replacement baseline. No selection is implied by the existing code or by a `PROPOSED` recommendation.

| ID | Client question | Current status and source |
|---|---|---|
| SCOPE-01 | Which business capabilities are in scope for the first release, later phases, or out of scope? Please identify the capability set and priority for each phase. | `NEEDS_DECISION` — `docs/SRS.md` §§1, 7; `docs/REQUIREMENTS.md`; `AGENTS.md` currently says no approved business modules. |
| SCOPE-02 | Does the replacement baseline adopt the already recorded approvals listed below, or does the client intend to supersede any? Identify only the decisions to change; retain the remainder with their recorded status and history. | Client confirmation requested because the original baseline is missing. Existing register statuses remain as recorded pending a superseding decision — `docs/DECISIONS.md`. |
| SCOPE-03 | Does the approved exclusion of fees, payments, discounts, refunds, and financial records remain in effect? | `APPROVED` exclusion — `LD-11` in `docs/DECISIONS.md`; confirm only if the client wants to change it. |
| SCOPE-04 | Which operational, migration, security, and release requirements are acceptance gates for each phase, and who accepts each phase? | `NEEDS_DECISION` — `docs/REQUIREMENTS.md` NFRs and `REQ-OPS-001`; `OPS-02`, `OPS-08`, `OPS-11`, `OPS-12`. |
| SCOPE-05 | What existing data, if any, must be migrated, and who approves reconciliation and cutover? | Conflicting record; see `DAT-02` below. — `docs/DECISIONS.md`, `docs/CLIENT-DECISION-WORKSHOP.md`, `docs/REQUIREMENTS-TRACEABILITY.md`. |
| SCOPE-06 | Which existing code capabilities are intended to remain in the approved product scope, and which are historical, experimental, deferred, or to be excluded? | `NEEDS_DECISION` — status records conflict with each other and the current module tree; see conflict register below. |

## Existing approvals to preserve

The following statuses are recorded as approved in `docs/DECISIONS.md`. This packet preserves them; listing them does not request a new answer or expand their scope. If the client wants a change, record a separately approved superseding decision.

| Capability | Approved decisions and recorded scope |
|---|---|
| Identity, account controls, security and governance | `SEC-01`–`SEC-11`, `SEC-12`–`SEC-14`, `GOV-01`–`GOV-02`; `INT-01` for MCC-operated SMTP/relay recovery delivery only. |
| Player and admission lifecycle | `PL-01`–`PL-06`; `AD-01`–`AD-03`; `LD-01`–`LD-13`. `LD-11` excludes fees and payments. |
| Branches and player placement | `BR-01`–`BR-09`; `PA-01`–`PA-02`. |
| Scheduling and timezone | `SCH-01`–`SCH-05`; `OPS-09`. |
| Attendance source-event rules | `AT-01`–`AT-05` are recorded `APPROVED`. The exact attendance denominator is not specified in `AT-01`, and supporting documents say denominator behavior remains deferred/needs decision. `AT-06` is **APPROVED (IMPLEMENTATION DEFERRED)** for continuous-absence calculations and alerts; it does not settle the denominator discrepancy. |
| Guardian/contact foundation | `REL-01`–`REL-02`. These approvals do not grant parent/player portal visibility. |
| Audit | `OPS-01`, including its documented access, retention, branch, export, append-only, and audit-read boundaries. |
| Trials | `TRIAL-01`, approved contract in `docs/DECISIONS.md`. |

## Prioritized client decision list

Priority is based on the affected scope: **P0** decisions required for baseline/foundation acceptance; **P1** decisions required before developing the affected Phase 1 capability; **P2** decisions required only if a later capability is included. The priority labels do not change the status recorded in the decision register.

### P0 — Baseline governance, identity/access and operations

| Decision ID | Client decision required | Current status and source |
|---|---|---|
| RBAC-MATRIX | Approve the final role-to-permission matrix, including explicit Super Admin scope and branch restrictions for each role. Existing decisions approve multiple roles/branches and centralized audited RBAC, but do not supply the final permission matrix. | `NEEDS_DECISION` — `AGENTS.md` “Authorization and RBAC” and “Branch Isolation”; `REQ-RBAC-001`; broad constraints `SEC-02`–`SEC-04` are `APPROVED` in `docs/DECISIONS.md`. |
| AUTH-DESIGN | Confirm the approved authentication design boundary and whether the recorded single database-backed identity and recovery decisions are sufficient for the replacement baseline. | `NEEDS_DECISION` for architecture confirmation — `AGENTS.md` says the current `AuthService` is only a boundary and authentication design must be approved; `SEC-01`, `SEC-14`, and `INT-01` are `APPROVED` in `docs/DECISIONS.md`. |
| DAT-02 | Confirm whether business data migration is required; if so, approve the source inventory, mapping, validation, duplicate handling, reconciliation, cutover, rollback, and sign-off policy. | **Conflicting record.** `APPROVED` label in `docs/DECISIONS.md`, but the decision says “Chosen option: None” and owner/approver/date are `NEEDS_DECISION`; `docs/CLIENT-DECISION-WORKSHOP.md` and `docs/REQUIREMENTS-TRACEABILITY.md` list it unresolved. Client confirmation required; do not infer an approved migration approach. |
| OPS-02 | Approve backup owner, frequency, encryption, retention, recovery objectives, and restore-test requirements. | `NEEDS_DECISION` — `OPS-02` in `docs/DECISIONS.md`; `REQ-OPS-001`. |
| OPS-10 | Approve retention and retirement/deletion requirements by entity, including who may authorize each action. | `NEEDS_DECISION` — `OPS-10` in `docs/DECISIONS.md`. |
| OPS-11 | Approve incident detection, severity, containment, notification, evidence preservation, recovery, review, and ownership. | `NEEDS_DECISION` — `OPS-11` in `docs/DECISIONS.md`; `REQ-OPS-001`. |
| OPS-12 | Approve operational metrics/logs/traces, thresholds, sensitive-data redaction, retention, escalation, and ownership. | `NEEDS_DECISION` — `OPS-12` in `docs/DECISIONS.md`; `REQ-OPS-001`. |

### P1 — Decisions required before the affected Phase 1 capability

| Capability | Decision IDs | Client decision required | Current status and source |
|---|---|---|---|
| Attendance calculations and denominator | `AT-01`, `AT-06`, `REQ-ATT-001` | Confirm whether denominator calculations remain deferred or are included in the approved baseline; if included, record the actual rule through a separate approved decision. | `AT-01` is `APPROVED` but leaves denominator rules unspecified; denominator detail is `NEEDS_DECISION` in `docs/REQUIREMENTS.md` and deferred in `docs/MODULE-STATUS.md`. `AT-06` is `APPROVED (IMPLEMENTATION DEFERRED)` for continuous-absence calculations/alerts. |
| Documents and private media | `DOC-01`–`DOC-08` | Approve upload authority by document type, verifier and states, unverified access, parent/player access, medical visibility, encryption/key ownership, retention, and replacement/version behavior. | `NEEDS_DECISION` — unresolved decision index in `docs/DECISIONS.md`. Broad principles `SEC-05`–`SEC-08`, `SEC-10`, `SEC-12`, and `SEC-13` remain `APPROVED`; the detailed policies are separate decisions. |
| Private storage deployment | `OPS-03` | Select/approve storage provider and deployment/recovery requirements for private objects. | `NEEDS_DECISION` — `OPS-03` in `docs/DECISIONS.md`. |
| API scope | `OPS-04` | Confirm whether an API is required in the approved baseline, its consumers, and the scope of any endpoints. | `NEEDS_DECISION` — `OPS-04` in `docs/DECISIONS.md`; `AGENTS.md` says APIs should be introduced only for approved use cases. |
| Guardian/player access | `REL-03`–`REL-05` | Decide parent data categories, player account eligibility/visibility, and relationship change/revocation behavior. | `NEEDS_DECISION` — `docs/DECISIONS.md`; `REQ-PORTAL-001`. `REL-01`–`REL-02`, `SEC-09`, `SEC-13`, and `SEC-14` are recorded `APPROVED`, but do not settle these access details. |
| Reports and exports | `REP-01`–`REP-03` | Approve report catalogue/columns, filters and date semantics, and view/export authorization. | `NEEDS_DECISION` — `docs/DECISIONS.md`; `REQ-REPORT-001`. `SEC-11` separately approves export permissions distinct from view permissions. |
| Operational dashboard | `DASH-01`–`DASH-03` | Approve metric definitions/source fields, role-specific views, and refresh/freshness behavior. | `NEEDS_DECISION` — `docs/DECISIONS.md`; `REQ-DASH-001`. |
| Notifications | `NOT-01`–`NOT-03` | Approve channels and phase, recipients/templates/timing/opt-out, retries, delivery audit, and failure handling. | `NEEDS_DECISION` — `docs/DECISIONS.md`. `INT-01` approves only password-recovery SMTP/relay delivery, not general notification providers. |
| Frontend framework | `OPS-05` | Approve the UI framework before significant frontend work. | `NEEDS_DECISION` — `OPS-05` in `docs/DECISIONS.md`; `AGENTS.md` “Views and Frontend”. |
| Search and filtering | `OPS-07` | Approve searchable fields and ranking, including branch and sensitive-data behavior. | `NEEDS_DECISION` — `OPS-07` in `docs/DECISIONS.md`. |
| Bulk import/export | `OPS-06` | Approve formats, preview/validation flow, permissions, audit, and error handling. | `NEEDS_DECISION` — `OPS-06` in `docs/DECISIONS.md`. |
| Performance acceptance | `OPS-08` | Approve the dataset, load, concurrency, environment, and measurement method for performance targets. | `NEEDS_DECISION` — `OPS-08` in `docs/DECISIONS.md`; NFR-01 in `docs/MODULE-STATUS.md`. |
| Ground maintenance | `GROUND-01` | Decide whether maintenance records may be corrected, edited, cancelled, or deleted, and define authority, reasons, history, and audit. | `NEEDS_DECISION` — `GROUND-01` in `docs/DECISIONS.md`. |

### P2 — Decisions required only if later-phase capability is included

| Capability | Decision IDs | Client decision required | Current status and source |
|---|---|---|---|
| Cricket performance | `PF-01`–`PF-06` | Decide source grain, batting/bowling formulas and edge cases, correction/locking, coach rating scale, and graph aggregation. | `NEEDS_DECISION` — `docs/DECISIONS.md`; `REQ-PERF-001`. |
| Fitness | `FIT-01`–`FIT-03` | Decide units/ranges/precision, risk thresholds/severity/alerts, and role visibility. | `NEEDS_DECISION` — `docs/DECISIONS.md`; `REQ-FIT-001`. |
| Player development | `DEV-01`–`DEV-02` | Decide scales/improvement calculations and review workflow/locking. | `NEEDS_DECISION` — `docs/DECISIONS.md`. |
| High Performance Program | `HP-01`–`HP-05` | Decide eligibility, selection-readiness meaning, benchmark ownership/change control, program lifecycle, and data boundary. | `NEEDS_DECISION` — `docs/DECISIONS.md`; `REQ-ADV-001`. |
| Matches and tournaments | `MT-01`–`MT-06` | Decide match/tournament relationship, shared-competition scope, Playing XI authority, scorecard verification/locking, MVP/selection rules, and tournament hierarchy. | `NEEDS_DECISION` — `docs/DECISIONS.md`; `REQ-ADV-001`. |
| Marketing and attribution | `MKT-01`–`MKT-04` | Decide ROI source, lead attribution model, campaign ownership/scope, and cost-per-admission data/timing. | `NEEDS_DECISION` — `docs/DECISIONS.md`. |
| Counselor KPIs | `LD-12` | The KPI principle is approved; approve the exact formulas and source fields before KPI implementation. | `LD-12` is `APPROVED` with formula definitions required before implementation — `docs/DECISIONS.md`. Exact formula detail remains `NEEDS_DECISION`. |

### Approved decisions with deferred implementation

These are not open policy questions in the current register. The client should only revisit them if the replacement baseline changes the recorded scope:

| Decision ID | Current status | Scope boundary |
|---|---|---|
| `AT-06` | `APPROVED (IMPLEMENTATION DEFERRED)` | Attendance source events only; calculations, thresholds, reports, alerts, and recipients require further approved definitions. |
| `SCH-01`–`SCH-05` | `APPROVED` | Approved scheduling scope includes one-time sessions and documented ownership/conflict/cancellation/availability rules; recurrence remains deferred under `SCH-01`. |
| `LD-11` | `APPROVED` | Financial records and fee/payment workflows remain outside current scope. |

## Conflicting records for client confirmation

The following inconsistencies are retained for review. This packet does not resolve them by changing a status.

| Conflict | Records compared | Client confirmation requested |
|---|---|---|
| Attendance approval state and denominator | `docs/DECISIONS.md` marks `AT-01`–`AT-05` `APPROVED` and `AT-06` `APPROVED (IMPLEMENTATION DEFERRED)`. But `AT-01` itself says denominator rules are not inferred; `docs/REQUIREMENTS.md` both says AT-01 denominator behavior is deferred and later labels `REQ-ATT-001`/AT-01–AT-06 `NEEDS_DECISION`; `docs/MODULE-STATUS.md` also says denominator calculations remain deferred. `docs/PHASE-1-DECISIONS.md` labels the attendance package unresolved. | Preserve the recorded approvals for source-event policy. Confirm denominator treatment separately and retain the `AT-06` deferral unless superseded by an explicit approved decision. |
| Scheduling approval state | `docs/DECISIONS.md` records `SCH-01`–`SCH-05` `APPROVED`. `docs/PHASE-1-DECISIONS.md` presents them as `NEEDS_DECISION`. | Confirm the approval-register status is the intended current status. |
| Audit/operations approval state | `OPS-01` is `APPROVED` in `docs/DECISIONS.md`, but is bundled into a `NEEDS_DECISION` row in `docs/PHASE-1-DECISIONS.md`. `OPS-02`, `OPS-10`, `OPS-11`, and `OPS-12` remain `NEEDS_DECISION`. | Preserve `OPS-01` as approved; keep the other listed operational decisions open unless separately approved. |
| Data migration approval state | `DAT-02` has `APPROVED` under “Status” in `docs/DECISIONS.md`, but its chosen option is “None” and approval fields are `NEEDS_DECISION`. Workshop/traceability documents treat it as unresolved. | Confirm whether `DAT-02` was actually approved and provide the approved decision content and approver record, or retain it as `NEEDS_DECISION` through a formally recorded correction. |
| Trial approval text | `TRIAL-01` includes an earlier section phrased as requiring approvals for fields, cardinality, status transitions, branch scope, attendance source, audit, and Lead Status interaction; a later “Approved contract” defines those items and says `APPROVED_FOR_IMPLEMENTATION`. | Confirm the later approved contract supersedes the earlier open-question wording; preserve that contract without expanding it. |
| Workshop priority lists | `docs/CLIENT-DECISION-WORKSHOP.md` places already approved IDs in its P0/P1/P2 unresolved priority lists, including `DAT-01`, `AT-01`–`AT-06`, `REL-01`–`REL-02`, `OPS-01`, `LD-13`, and `INT-01`. The decision register records these as approved (with `AT-06` implementation deferred and `INT-01` limited to recovery SMTP/relay). | Confirm that the decision register governs approval state and revise priority lists to include only unresolved decisions; retain approved scope/deferrals. |
| RBAC readiness | `docs/IMPLEMENTATION-GATE.md` says there are no unresolved RBAC/branch decisions; `docs/REQUIREMENTS-TRACEABILITY.md` lists the permission matrix as unresolved; `docs/MODULE-STATUS.md` says there is no final policy matrix; `AGENTS.md` requires a final matrix derived from approved client requirements. | Confirm and approve the final role-permission and branch-scope matrix. Generic approvals `SEC-02`–`SEC-04` remain preserved but do not themselves specify the matrix. |
| Authentication architecture | `AGENTS.md` says the generic `AuthService` is only a boundary and authentication design must be approved before implementation. `docs/REQUIREMENTS.md` and traceability records describe database-backed authentication and session/recovery implementation evidence in the current tree. | Confirm whether the existing authentication architecture is approved for the replacement baseline or whether an architecture decision is still required. Preserve `SEC-01`, `SEC-14`, and `INT-01` as currently recorded unless superseded. |
| Module readiness records | `docs/MODULE-STATUS.md` explicitly calls its 20-module table a historical snapshot superseded by `docs/REQUIREMENTS-TRACEABILITY.md`; its separate readiness matrix still labels some capabilities “no code”/deferred. Traceability records code for those capabilities, and the current tree contains corresponding module directories/services. `AGENTS.md` says there are no approved business modules. | Confirm intended product scope and approval state separately from whether code exists. Code presence or historical test claims must not be treated as client approval. |
| Future integration status | The workshop lists `INT-01` among P2 decisions; `docs/DECISIONS.md` records `INT-01` `APPROVED` for the password-recovery SMTP/relay boundary and explicitly says future integrations remain separately governed. | Preserve the narrow approved recovery boundary; confirm any other integration only if selected for baseline scope and record it under a separate decision. |
| Source baseline authority | `AGENTS.md` calls `docs/SRS-original.md` authoritative. `docs/REQUIREMENTS-TRACEABILITY.md` says it is missing and `docs/SRS.md` says it is not an approved replacement. | Approve this replacement baseline with version/effective date, and state what source it supersedes. |

## Approval record handling

After client review, record each response with the decision ID, exact approved wording, named approver, approval date, affected requirements/modules, and any superseded record. Keep unanswered items `NEEDS_DECISION`; keep proposed options visibly labelled `PROPOSED`. Update the decision register and traceability documents only after the client approval is recorded. Do not infer policy from existing implementation or historical test results.

## Source documents reviewed

- `AGENTS.md`
- `docs/DECISIONS.md`
- `docs/PHASE-1-DECISIONS.md`
- `docs/CLIENT-DECISION-WORKSHOP.md`
- `docs/IMPLEMENTATION-GATE.md`
- `docs/SRS.md` (working specification; not approved)
- `docs/REQUIREMENTS.md`
- `docs/REQUIREMENTS-REVIEW.md`
- `docs/REQUIREMENTS-TRACEABILITY.md`
- `docs/MODULE-STATUS.md`
