# Client Reference Decision Request

**Date:** 2026-10-11  
**Status:** Every item is NEEDS_DECISION until all resolution fields are completed.

The supplied reference descriptions establish desired workflow coverage and presentation. They do not approve formulas, authorities, finance rules, privacy, retention, or notification behavior.

## Decision fields

For each item record:

- **Resolution:** APPROVED, REJECTED, or NEEDS_DECISION
- **Selected option:**
- **Comments:**
- **Approver identity:**
- **Approval date:**
- **Effective date:**

Blank fields are not approval.

## Admission and trial

### CR-01 — Admission UI and existing lifecycle

**Evidence:** Admissions create/edit/detail/complete/status/checklist/convert routes and services exist with branch checks, CSRF, history/audit, and Player conversion.

**Question:** May the existing Admission/Trial data be presented in a reference-aligned UI without adding payment, meeting, hostel, scoring, or media fields?

**Options:** A. UI/read-only summary only; B. UI plus existing-field edits; C. Defer.

**Recommended secure default:** A.

**Requirements if approved:** Existing admission permissions, branch isolation, CSRF, audit, transaction, and conversion tests. No migration for option A.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

### CR-02 — Trial mode and assessment

**Evidence:** Trial booking, Coach/Ground options, statuses, attendance, reschedule, cancellation, and history exist. Mode, scoring, and video media are not established.

**Question:** Which trial assessment model is authoritative?

**Options:** A. On-ground only; B. Video only; C. Both with separate fields; D. Current status-only workflow.

**Recommended secure default:** D pending approval.

**Required decisions:** Scoring, evaluator, outcome, retrial cardinality, mode-specific fields, media policy, branch and audit behavior.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

### CR-03 — Trial media

**Evidence:** Documents has private storage foundation; no approved Trial media linkage, scan, retention, or visibility contract.

**Question:** Should Trial media be supported?

**Options:** A. No media; B. Private uploads only; C. Private uploads and links; D. Defer.

**Recommended secure default:** A until storage/scanner/retention owners approve.

**Required decisions:** File types/size, quarantine, encryption, download roles, replacement, retention, portal visibility, and audit fields.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

### CR-04 — Admission meeting

**Evidence:** No complete meeting entity/history is established.

**Question:** What meeting record is required?

**Options:** A. None; B. Append-only meeting record; C. Editable record with audit; D. External record only.

**Recommended secure default:** B with restricted sensitive notes.

**Required decisions:** Fields, attendees, authority, status impact, visibility, correction, retention, and permission.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

## Finance and hostel

### CR-05 — Fees and payment plan

**Evidence:** No confirmed payment ledger, gateway, receipt, refund, or reminder workflow.

**Question:** What payment scope is approved?

**Options:** A. None; B. Manual ledger; C. Gateway-backed ledger; D. Ledger plus gateway.

**Recommended secure default:** A until Finance approves amounts, currencies, statuses, payment authority, refunds, failed payments, reminders, and provider deployment.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

### CR-06 — Hostel

**Evidence:** No confirmed hostel requirement, allocation, charges, or consent workflow.

**Question:** What hostel scope is approved?

**Options:** A. None; B. Requirement flag only; C. Allocation and charges; D. Full hostel module.

**Recommended secure default:** A.

**Required decisions:** Availability, charges, consent, health/privacy fields, status, cancellation, permissions, and retention.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

## Dashboard

### CR-07 — Director dashboard selectors and formulas

**Evidence:** DashboardService provides permission-aware branch-scoped cards for Players, Leads, Trials, Admissions, Sessions, Attendance, Matches, Tournaments, and Development.

**Question:** May a Director dashboard selector be added?

**Options:** A. Existing persisted counts only; B. Add branch/date/month selectors after formula approval; C. Add all reference formulas now; D. Defer.

**Recommended secure default:** A.

**Required decisions:** Director role, branch switching, date/month boundaries, Coach workload formula, coaching taxonomy, batch/age formulas, freshness, report/notification aggregation.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

## Coaching sessions

### CR-08 — Individual session type and skill taxonomy

**Evidence:** Scheduling supports roster sessions, date/time, Coach, Batch, Ground, Players, attendance, feedback, history, and audit. No individual type or skill taxonomy is approved.

**Question:** How should individual sessions be modeled?

**Options:** A. Reuse group Session with one Player; B. Separate individual status/type; C. Separate module; D. Defer.

**Recommended secure default:** D.

**Required decisions:** Single-Player cardinality, skill/shot vocabulary, required fields, notes visibility, attendance interaction, Coach authority, media, and portal visibility.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

### CR-09 — Group eligibility and roster limit

**Evidence:** Scheduling uses Batch/Player rosters and immutable Attendance snapshots; no group entity or size policy is established.

**Question:** What defines a coaching group?

**Options:** A. Batch is the group; B. New group entity; C. Unrestricted roster; D. Defer.

**Recommended secure default:** A for verification only; no new limit inferred.

**Required decisions:** Eligibility, maximum size, roster lock/correction, Coach authority, branch scope, and audit.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

### CR-10 — Session focus, drills, plan, and notes

**Evidence:** No approved focus/drill taxonomy or plan contract is established.

**Question:** Which session-plan source is authoritative?

**Options:** A. None; B. Free text only; C. Managed taxonomy plus notes; D. Separate drill library.

**Recommended secure default:** A pending approval.

**Required decisions:** Values, required/optional fields, Coach permissions, note visibility, correction, retention, and reporting.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

## Cross-cutting policy

### CR-11 — Notification channels

Approve in-app, email, WhatsApp, SMS, recipients, consent, templates, retries/outbox, idempotency, and audit.

**Recommended secure default:** No new reference notifications; existing Notifications scope only.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

### CR-12 — Sensitive media and document deployment

Approve private storage, scanner/quarantine, encryption, retention, access roles, download/preview, replacement, and deletion/archive.

**Recommended secure default:** Private storage, fail-closed access, no public URLs, no new media linkage until deployment evidence.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

### CR-13 — Parent/Student visibility

Approve visibility of admissions, trials, sessions, attendance, performance, documents, and achievements, including verified relationship, consent, branch, and redaction rules.

**Recommended secure default:** No new visibility; reuse only explicitly implemented portal slices.

**Resolution:** NEEDS_DECISION  
**Selected option:** ____________________  
**Comments:** ____________________  
**Approver:** ____________________  
**Approval date:** __________  
**Effective date:** __________

## Exact client decisions required before coding

1. Trial mode, scoring, evaluator, outcomes, retrials, and media.
2. Admission meeting fields, authority, visibility, and retention.
3. Fee/payment/refund/reminder rules and finance authority.
4. Hostel scope, charges, consent, and privacy.
5. Director role, branch/date selectors, formulas, workload, and freshness.
6. Individual session type and skill taxonomy.
7. Group definition, eligibility, size, and roster correction.
8. Session focus/drill/plan fields and notes visibility.
9. Media storage, scanning, encryption, retention, and access.
10. Notification channels, consent, recipients, retries, and templates.
11. Parent/Student visibility and redaction.

Recommended defaults are not approval. Every item remains NEEDS_DECISION until the fields above are completed.
