# MCC Cricket Club Management System — System Requirements Specification

Status: Controlled working specification
REQUIREMENTS BASELINE: v1.0 — PRE-IMPLEMENTATION
Baseline date: 2026-08-28
IMPLEMENTATION GATE: READY-FOR-IMPLEMENTATION for the first vertical slice only
Source of business requirements: `docs/SRS-original.md` (Version 1.0), referenced but not present in this repository.
Authority: The original client SRS could not be recovered from the repository or available history. This document remains a controlled working specification and must not be treated as a replacement for missing client approval. Verified project documentation, decisions, and implementation evidence are reconciled in `docs/REQUIREMENTS-TRACEABILITY.md`; no missing requirements are reconstructed from memory.

Classification: `APPROVED` means supported by an explicit decision or current project instruction; `PROPOSED` is advisory; `NEEDS_DECISION` is unresolved; `IMPLEMENTED` requires code and test evidence; `NOT_IMPLEMENTED`, `BLOCKED`, and `FUTURE` describe current readiness. Functional statements from the original SRS are requirements, not evidence that the functionality exists.

## 1. Purpose and scope

MCC requires a centralized, responsive web and mobile-ready platform for multi-branch cricket-academy operations. The original SRS covers admissions, coaching, attendance, performance, fitness, tournaments, communication, reports, administration, player records, dashboards, and future integrations.

The current repository contains a partially implemented first vertical slice. No unrelated business module is approved for implementation merely because it is described here.

Explicitly outside current scope by approved decision `LD-11`: fees, payments, discounts, refunds, and financial records.

## 2. Actors

The original SRS names Super Admin, Branch Admin, Admission Team, Coach, Fitness Coach, Nutritionist, Reception, Parent, and Player. Role permissions, sensitive-data visibility, account eligibility, and branch scope are governed by the decision register and remain incomplete where no approved decision exists.

## 3. Functional requirements

The system shall support the following capability areas, subject to approved scope, permissions, data ownership, and decisions:

- CRM/admissions: lead capture, source/campaign attribution, ownership, follow-up, trials, selection, admission checklist, controlled conversion, duplicate warning, and lost-lead handling.
- Player management: generated player identity, player profile, branch/batch assignment, lifecycle history, guardian/contact relationships, documents, and audit timeline.
- Coaching operations: coach records, assignments, sessions, grounds/resources, rosters, and attendance.
- Performance and fitness: raw source records, approved derived calculations, feedback, development, and reports. No undefined score, KPI, risk, ranking, or improvement formula is authoritative.
- Matches and tournaments: event records, participation, scorecards, awards, certificates, and media, subject to unresolved ownership and approval rules.
- Documents/media: private storage, metadata, verification, expiry, replacement/version history, controlled downloads, consent, and retirement.
- Reporting/dashboards: role- and branch-scoped read/aggregation views from owning modules. Dashboards are never a source of truth.
- Notifications: event orchestration and approved channels only; provider integrations remain future scope unless separately approved.
- Portals: parent/player access only after relationship, consent, eligibility, recovery, revocation, and category visibility decisions are approved.

## 4. Lifecycle and ownership rules

CRM/admission is one pipeline that ends in controlled player conversion; the Player lifecycle begins after conversion:

`Lead → Trial Booked → Trial Attended → Selected → Admission Pending → Admission Completed → Active Player`

A lead is not automatically a player. Lead identity, source, owner, branch, status, timestamps, follow-up history, trial history, and conversion relationship must be preserved. Conversion must be controlled, auditable, idempotent, and protected against duplicate player creation. Duplicate detection warns; it does not silently merge records (`LD-03`).

Lead capability coverage includes Lead Source, Campaign, Counselor, follow-up history, next follow-up date, remarks, admission probability, status, trial information, and conversion information. Lead Sources and Lead Statuses are admin-managed configuration records; they are not separate CRM modules. The documented source vocabulary is Google, Meta Ads, Instagram, Website, Walk-In, Referral, School, WhatsApp, and Other. Current implementation status for each capability remains governed by `REQUIREMENTS.md` and `MODULE-STATUS.md`.

Authoritative ownership is:

| Data | Owning capability |
|---|---|
| Lead/follow-up | CRM/Admissions |
| Player identity/branch history | Player Management |
| Session cancellation | Session Management |
| Attendance | Attendance |
| Raw performance/source events | Match/Performance |
| Fitness measurements | Fitness |
| Document/media metadata and access policy | Document/Media |
| Dashboards/360 views | Read/aggregation layer |

Attendance belongs to Player ↔ Session. A cancelled session is a session event, requires a reason and audit record, and is excluded from attendance denominators. `Late` is normally Present plus a separate late metric. Exact formulas, thresholds, and correction policy remain decision-controlled.

## 5. Security and privacy requirements

- Deny by default. Every protected endpoint, record, and document download requires server-side authorization.
- Use one identity model with multiple roles and explicit authorized branch assignments as recorded in `DECISIONS.md`.
- Enforce branch isolation server-side; frontend filtering, hidden fields, URLs, and JavaScript are not security controls.
- Treat medical data, identity documents, passports, consent documents, photographs where applicable, guardian/private contact data, and authentication information as sensitive.
- Store sensitive documents privately. Do not expose permanent public URLs or store them in `public/assets`. Signed URLs, if later approved, must be short-lived.
- Validate file type, MIME type, size, category, upload errors, and malware scanning before accepting documents. Replacement preserves audit history.
- Never log passwords, tokens, secrets, full medical notes, identity-document numbers, private document contents, or signed URLs.
- Audit authentication, lifecycle, RBAC, document, sensitive-record, export, and administrative changes with actor, action, entity, entity ID, timestamp, branch scope, and appropriate before/after summary or reason.
- Guardian consent is required for eligible minor/player accounts; category-level portal visibility and revocation must be defined before portal release.

## 6. Non-functional requirements

The following baseline is required by the current project instruction and is not implemented unless evidence is recorded:

| ID | Requirement |
|---|---|
| NFR-01 | Approved standard reads/writes shall achieve ≤3 seconds server response time at p95 under agreed production-like data and concurrent-user load. |
| NFR-02 | All traffic shall use HTTPS; passwords shall use modern adaptive hashing; sessions rotate at login and are invalidated on logout, password reset, and account disablement. |
| NFR-03 | Authorization shall be enforced on every protected endpoint and document download. |
| NFR-04 | Production logs shall exclude passwords, tokens, full medical notes, identity-document numbers, and signed URLs. |
| NFR-05 | Backups shall be encrypted and restore-tested at least quarterly, with approved retention and recovery objectives. |
| NFR-06 | Production changes require migration guidance, automated test results, and an owner-approved release checklist. |
| NFR-07 | Default timezone is `Asia/Kolkata`; stored timestamps and report timezone semantics must be defined consistently. |
| NFR-08 | Every export shall be authorized, logged, timestamped, filtered, and restricted to fields permitted for the requesting role. |

Other original SRS NFRs include responsive operation, scalable additional branches, search/filtering, bulk Excel import/export, encrypted document storage, daily automated backups, and critical-change audit logs. Their detailed acceptance criteria are in `REQUIREMENTS.md` and unresolved policy details are in `DECISIONS.md`.

## 7. Phasing and exclusions

Phase 0 is foundation: authentication/recovery, secure sessions, account disablement, RBAC, branch authorization, authorization tests, audit, administration, reference data, private storage abstraction, backup/restore, monitoring, error logging, and deployment procedure.

Phase 1 is operational academy MVP: CRM/admissions, duplicate warning, follow-up, trials, checklist/conversion, players/guardians, branch/batch/coach assignment, one-time sessions, conflict prevention, roster, attendance, approved reports/alerts, secure documents, verification/expiry/download, audit history, and approved branch-scoped dashboards.

Performance/fitness beyond approved source capture and portals/advanced operations remain later phases unless separately approved. WhatsApp, calendar/maps, push notifications, external video storage, ad-spend integrations, and AI analytics are future scope in the original SRS.

## 8. Unresolved decisions

Every unresolved item is `NEEDS_DECISION` in `DECISIONS.md`. Major blockers include account recovery and guardian verification, permission matrix, sensitive-data visibility, malware scanning, retention, data quality/migration, attendance and KPI formulas, portal policy, report definitions, notification behavior, incident response, observability, and future integration contracts.
