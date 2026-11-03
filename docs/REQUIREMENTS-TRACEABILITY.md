# MCC Cricket Club — Requirements Traceability and Governance Record

Status: Controlled replacement record for missing source evidence
Review date: 2026-09-04
Source baseline: `docs/SRS.md`, `docs/REQUIREMENTS.md`, `docs/REQUIREMENTS-REVIEW.md`, `docs/DECISIONS.md`, `docs/PHASE-1-DECISIONS.md`, `docs/IMPLEMENTATION-GATE.md`, `docs/MODULE-STATUS.md`, and repository implementation evidence.

## Authority and recovery result

The repository references `docs/SRS-original.md` as the Version 1.0 client SRS, but that file is not present. A repository-wide search found no second copy, exported copy, or other source file that can be verified as the original SRS. This checkout also has no usable Git history or archive containing the missing document.

The original SRS is therefore **not recovered**. This document is a formal traceability replacement based only on verified project documentation and current repository evidence. It is not a reconstructed SRS and does not create business approval. Any requirement that depends on the missing source or an unresolved decision remains gated.

## Status vocabulary

- **Implemented** — current code and focused evidence support the documented scope.
- **Implemented with limitations** — code exists, but documented scope, evidence, policy, or production verification is incomplete.
- **Deferred by decision** — implementation is intentionally postponed by an existing decision or phase boundary.
- **Not implemented** — no verified implementation exists for the documented capability.
- **Needs business decision** — the requirement or policy cannot be completed safely from the available evidence.

## Twenty-module reconciliation

The module names below follow the SRS module sequence used by the project requests and the current `app/Modules` architecture. The status is intentionally conservative and does not infer approval from the presence of a directory alone.

| # | Module | Verified implementation state | Traceability and limitation |
|---:|---|---|---|
| 1 | Admissions | Implemented with limitations | Admission intake, lifecycle, checklist, conversion, history, branch authorization, and audit are present. Trial scheduling and unresolved policy boundaries remain separate. |
| 2 | Players | Implemented with limitations | Player identity, profile, lifecycle, assignments, contacts, branch controls, and audit are present. Relationship visibility/revocation and unresolved sensitive-data decisions remain open. |
| 3 | Coaches | Implemented with limitations | Coach records and browser views are present. Complete approved permissions, assignment policy, and production evidence remain unverified. |
| 4 | Attendance | Implemented with limitations | Source-event recording, correction, roster snapshots, history, branch controls, and audit are present. Denominators, absence formulas, alerts, and derived KPI rules remain deferred. |
| 5 | Performance | Not implemented | No verified `app/Modules/Performance` implementation is present. Performance formulas, visibility, correction, and ownership remain unresolved. |
| 6 | Fitness | Implemented with limitations | Fitness assessment/plan views and persistence are present. Metric definitions, thresholds, sensitive visibility, and production evidence remain limited or unresolved. |
| 7 | Session Management | Implemented with limitations | Scheduling, assignments, capacity/conflict controls, cancellation, history, and attendance linkage are present. Recurrence and some resource-policy decisions remain unresolved. |
| 8 | Ground Management | Implemented with limitations | Ground records, branch scope, availability/unavailability, booking/conflict handling, and detail views are present. Full operational verification and policy decisions remain limited. |
| 9 | Match Management | Implemented with limitations | Match records, ground/branch linkage, player selection, status/result fields, history, and filters are present. Scorecard ownership, lock/correction, and selection decisions remain unresolved. |
| 10 | Tournament Management | Implemented with limitations | Tournament records, branch/ground linkage, fixtures/results linkage, participation, standings, history, and filters are present. Tournament hierarchy and result-governance decisions remain unresolved. |
| 11 | Player Development | Implemented with limitations | Plans, goals, coach assignment, notes, progress/history, detail, and filters are present. Progress interpretation and sensitive visibility remain decision-controlled. |
| 12 | High Performance Program | Not implemented | No verified dedicated High Performance implementation is present. Undefined benchmarks, readiness thresholds, metrics, and visibility remain explicitly unavailable. |
| 13 | Parent Portal | Implemented with limitations | Parent dashboard/child access and a dedicated child view are present. Approved visibility is limited; unresolved relationship, consent, revocation, and sensitive-category rules remain gated. |
| 14 | Documents | Implemented with limitations | Private storage, branch/RBAC/IDOR checks, allowlisted MIME/size validation, random storage keys, filename/path safeguards, executable/script-marker rejection, attachment download headers, upload audit history, and orphan-object rollback protection are verified. Upload authority by type, verification authority, malware scanning, retention, encryption/key ownership, parent visibility, and replacement/retirement remain decision-gated. |
| 15 | Reports | Implemented with limitations | Central report catalog/results and source-backed reporting are present. Export breadth, approved columns, field policy, and report definitions remain incomplete or unresolved. |
| 16 | Dashboard | Implemented with limitations | Role/branch-scoped landing summaries, navigation, recent activity, upcoming sessions, action context, and empty states are present. The approved KPI catalogue and production evidence remain incomplete. |
| 17 | Notifications | Implemented with limitations | Foundation listing, targeting/read state, and history support are present. Triggers, delivery semantics, preferences, retries, and external providers remain deferred. |
| 18 | Branch Management | Implemented | Branch CRUD, active/inactive lifecycle, relationships, RBAC, branch assignment, audit, and safe focused verification are present. Deployment and broader cross-module evidence remain separate. |
| 19 | Marketing Dashboard | Implemented with limitations | Source-backed lead/admission summaries and available funnel views are present. Attribution, conversion definitions, targets, ROI, and unsupported KPI formulas remain unavailable. |
| 20 | Player 360° | Implemented with limitations | Read-only consolidated profile and linked source sections are present. It does not replace authoritative records; sensitive sections and undefined derived KPIs remain unavailable. |

Foundation capabilities such as authentication, authorization, audit logging, private storage, administration, trials, and operations are cross-cutting dependencies rather than additional SRS modules in this 20-module table. Their limitations remain recorded in `docs/MODULE-STATUS.md`, `docs/REQUIREMENTS.md`, and the decision register.

## Current verification evidence

The recorded current test evidence is:

| Check | Result |
|---|---|
| Unit tests | 59 tests / 837 assertions / 0 failures |
| Safe database verification | 103 tests / 1,012 assertions / 0 failures |
| PHP syntax | Passed |

These results demonstrate the executed scopes only. They do not replace unresolved business approval, production deployment evidence, load testing, backup/restore evidence, or real-browser verification.

## Preserved unresolved decision groups

The existing decision-register items remain authoritative and are not silently closed by this reconciliation. The principal unresolved groups recorded in `DECISIONS.md` and related governance documents are:

- RBAC permission matrix, identity source, account eligibility, guardian verification, recovery, and revocation.
- Branch transfer, multi-branch event ownership, and cross-branch participation rules.
- Guardian/parent relationship proof, consent, category visibility, and sensitive-data boundaries.
- Medical information and medical-document access, document categories, verification, malware scanning, retention, replacement, retirement, encryption, and provider recovery.
- Attendance timing, status semantics, roster/cancellation boundaries, denominator membership, correction policy, absence thresholds, alerts, and notification recipients.
- Performance and fitness source grain, units, metrics, thresholds, formulas, locking, visibility, and correction policy.
- Session recurrence, resource conflict overrides, capacity, coach availability, and rescheduling semantics.
- Match and tournament ownership, Playing XI confirmation, scorecard verification/locking/correction, awards, and competition hierarchy.
- Report definitions, approved columns, export formats, filters, field policy, and audit requirements.
- Dashboard KPI catalogue, role views, freshness, caching, and performance measurement.
- Notification channels, triggers, recipients, preferences, delivery state, retries, and provider integrations.
- Backup/restore, retention, incident response, monitoring, API consumers, locale/timezone semantics, bulk import/export, search policy, and production performance evidence.

The complete decision IDs and original wording remain in `docs/DECISIONS.md`; this record intentionally summarizes groups without replacing or editing that register.

## Documents security evidence

The implemented Documents controls are limited to objective security and integrity boundaries:

- Documents are stored through `LocalPrivateStorage` beneath `WRITEPATH/private`, outside `public/assets` and protected against configuration inside `FCPATH`.
- Storage keys reject absolute paths, null bytes, backslashes, dot segments, drive prefixes, and root escape attempts. Keys use a cryptographically random server-generated component and an extension derived from the trusted MIME allowlist.
- Uploads require an approved current MIME type, a non-empty file, a 10 MiB maximum, a valid upload object, and rejection of common PHP/ASP/script markers. This is content hardening, not a substitute for approved malware scanning.
- Player and branch ownership is checked server-side before metadata is stored. Listing, detail, and download paths enforce permission and authorized branch scope independently of URL or frontend filters.
- Downloads are served only by an authorized controller action, with an attachment disposition and `X-Content-Type-Options: nosniff`; no permanent public file URL is created.
- Upload metadata, document history, and centralized audit persistence are written together. If persistence fails after storage, the uncommitted private object is removed.

These controls do not decide which role may upload each document type, who verifies documents, who may view medical or parent-facing documents, retention/deletion periods, replacement/retirement semantics, encryption/key ownership, or the malware scanning provider and workflow.

## Traceability risks and governance actions

The missing client SRS is a high traceability risk: requirement IDs, acceptance criteria, priorities, and scope cannot be independently reconciled to the original client baseline. Existing project documents provide a controlled working baseline, but they cannot prove that all client requirements are represented.

Until the original SRS is supplied or formally re-approved, the following governance controls apply:

1. Treat `docs/SRS.md` and this document as controlled working evidence, not client approval.
2. Do not close `NEEDS_DECISION` items based only on implementation or passing tests.
3. Keep module status conservative where code exists without complete policy or acceptance evidence.
4. Obtain a versioned client SRS or written re-baseline before release approval or further scope expansion.
