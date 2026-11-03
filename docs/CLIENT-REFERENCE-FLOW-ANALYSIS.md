# Client Reference Flow Analysis

**Date:** 2026-10-11  
**Scope:** Read-only comparison of the four client reference workflows with the current MCC Cricket Club repository.

## Evidence boundary

The four reference images were not present as readable files in the workspace. This analysis uses the workflow descriptions supplied in the request and repository source evidence. It is therefore not a pixel-level image comparison. The descriptions are reference intent, not approval of business rules.

No application code, migrations, database records, permissions, tests, or docs/SRS-original.md were modified.

Evidence terms:

- **Code evidence:** route, controller, service, model, view, migration, or test found in the repository.
- **Test evidence:** focused Unit, Feature, or database coverage found.
- **User reference:** capability described in the request.
- **Inferred finding:** comparison requiring confirmation before implementation.

## Repository evidence

The project contains Admissions, CRM, Trials, Players, Coaches, Scheduling, Attendance, Documents, Reports, Dashboard, Programs, Batches, Grounds, Equipment, Matches, Tournaments, Development, Performance, Notifications, Marketing, ParentPortal, Portal, and Achievements modules. Shared infrastructure includes centralized authorization, branch context, CSRF forms, audit persistence, transactions, datepicker attributes, btSelect controls, private document storage, staff layouts, and portal layouts.

Implementation quality is uneven. Some modules use mature service-layer workflows and tests; other views and controllers still contain compressed legacy formatting, inconsistent error redirects, or only partial reference coverage.

## 1. Admission process

### Existing implementation

- Admissions routes provide list, create, edit, detail, complete, status, checklist, convert, and document actions.
- AdmissionService supports direct and Lead-linked records, status transitions, branch checks, audit/history, transaction handling, and conversion to Player.
- CRM supplies Lead, follow-up, and Trial links. TrialService supports booked, attended, no-show, cancelled, and rescheduled states, Coach/Ground options, history, and branch authorization.
- Documents are integrated for supported private uploads.
- Focused Admission, CRM, Trial, and database tests exist.

### Gaps

The repository does not evidence a complete fee ledger, payment plan, receipts, reminders, refunds, hostel requirement/charges, formal admission meeting entity, trial video/media review, or complete notification sequence. Trial scoring and final admission gates require policy confirmation.

### Confirmed defects and risks

Some legacy Admission secondary failure paths still use back redirects, which can lose originating context. Trial browser routes are intentionally registered through CRM, so stale links to a standalone Trials route would be broken. Future trial media must use private Document service storage and scanning; public uploads are not acceptable.

### Decision dependencies

Trial scoring/outcomes, admission approval authority, fee/payment status and amount rules, hostel charges, meeting visibility, media retention, notification channels, and Player creation prerequisites are NEEDS_DECISION.

## 2. Director dashboard

### Existing implementation

DashboardService and DashboardModel provide permission-aware, branch-scoped cards and summaries for Players, Leads/Trials/Follow-ups, Admissions, Sessions, Attendance, Matches, Tournaments, Development, and related activity. Reports and Notifications are separate modules.

### Gaps

A complete Director-specific branch selector and date/month contract is not evidenced across every card. Coach totals, coaching-type summaries, Coach workload, daily Coach schedule, batch distribution, age-group distribution, and freshness labels are not consistently present.

### Confirmed risks

Permission-specific cards can produce an incomplete dashboard without an explicit explanation. Date/month behavior must use the configured Asia/Kolkata presentation convention consistently. Coach workload and coaching-type formulas are not approved.

### Decision dependencies

Director role/scope, selector behavior, date/month boundaries, Coach workload formula, coaching taxonomy, age-group formula, report definitions, and freshness rules are NEEDS_DECISION.

## 3. Individual coaching session

### Existing implementation

Scheduling routes support session list, create, edit, detail, Coach reassignment, cancel, complete, reschedule, availability, Branch/Coach/Batch/Ground/Player options, separate date/time fields, roster snapshots, attendance, feedback, history, and audit. Attendance is immutable-snapshot based and feedback is available after attendance is recorded.

### Gaps

No distinct individual-session type, single-Player route, skill/shot taxonomy, or skill assessment model is established. Optional session media/document linkage is not an approved Scheduling workflow. Coach login is not a separate authentication boundary; staff authentication and Coach permissions are reused.

### Confirmed risks

Scheduling Player/Coach options and btSelect behavior have had empty-list, clipping, and duplicate-initialization defects. A new individual workflow must not duplicate attendance or feedback records. Skill fields must not be invented from the reference.

### Decision dependencies

Session cardinality/type, skill vocabulary, notes visibility, media retention/storage, Player eligibility, attendance automation, Coach ownership, and portal visibility are NEEDS_DECISION.

## 4. Group coaching session

### Existing implementation

Scheduling supports branch, Coach, Batch, Ground, separate date/time, Player roster, status, cancellation, reschedule, completion, attendance snapshot, feedback, history, and audit. Programs/Batches and Grounds provide reference data. Attendance has approved statuses, versioning, correction reasons, and history.

### Gaps

No explicit coaching-group entity, group-size policy, focus/drill taxonomy, session plan contract, or Scheduling-owned media linkage is clearly established. Attendance summaries exist, but derived KPI/percentage rules remain limited or deferred.

### Confirmed risks

Roster snapshot timing must remain immutable. Existing branch filtering and btSelect behavior need continued regression coverage. New plan/media fields could create sensitive data and retention obligations.

### Decision dependencies

Group definition, eligibility, maximum size, roster lock/correction, focus/drill taxonomy, notes/media retention, attendance automation, and visibility are NEEDS_DECISION.

## Cross-cutting analysis

Existing security controls include centralized RBAC, server-side branch queries, CSRF, audit persistence, transactions, immutable attendance snapshots, and private document storage. New payment, media, dashboard-export, or portal features require explicit field visibility and retention decisions. Never store sensitive media in public assets or log personal data, credentials, passwords, tokens, or raw document contents.

Shared page headers, cards, tables, datepicker, btSelect, alerts, and responsive wrappers exist, but Admissions, Reports, legacy CRM, and some forms use inconsistent markup density and error presentation. New UI should copy the strongest existing detail/session patterns.

Normal PowerShell Composer/bootstrap readability is required before migrations or PHPUnit. Payment providers, notification delivery, storage/scanner, encryption, backup, and retention settings require deployment-owner evidence.

## Safe implementation slices

1. Verify and align the existing Scheduling/Attendance group-session UI without new business fields.
2. Present an Admission/Trial read-only summary from existing records without payment, hostel, scoring, or media changes.
3. Approve dashboard branch/date semantics and formulas before adding selectors or new aggregates.
4. Approve individual-session and skill taxonomy before adding fields or a new type.
5. Approve media, payment, hostel, and notification policies before building sensitive workflows.

## Summary

### Existing capabilities

Lead and follow-up flows, Trial booking/history, Admission lifecycle/conversion, Player and Coach records, Scheduling, Attendance, Documents foundation, Reports, Notifications foundation, Dashboard summaries, Programs/Batches, Grounds, Matches/Tournaments, Achievements, shared RBAC/branch/CSRF/audit patterns.

### Missing capabilities

Payment and fee management, hostel workflow, trial video/media, formal admission meetings, individual-session/skill workflow, group focus/drill/plan/media, complete Director filters/workload, and reference-specific UI consistency.

### Confirmed defects

Scheduling option loading and btSelect clipping/initialization have had defects. Some legacy error paths use back redirects. Documentation and navigation descriptions do not always match current route evidence.

### Remaining NEEDS_DECISION

Trial scoring/outcomes, admission authority, fees/payments/refunds, hostel charges, notification channels, dashboard formulas/freshness, group eligibility/limits, skill taxonomy, attendance automation, media retention, and Parent/Student visibility.
