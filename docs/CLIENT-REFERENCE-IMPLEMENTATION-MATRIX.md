# Client Reference Implementation Matrix

**Date:** 2026-10-11  
**Status:** Read-only planning matrix. No implementation is authorized by this document.

| Workflow | Existing implementation evidence | Missing or confirmed gap | Safe slice without new policy | Permissions and tests | Status |
| --- | --- | --- | --- | --- | --- |
| Admission registration | Admissions create/edit/detail/complete, Lead linkage, branch/RBAC/CSRF/audit/transactions | Reference-specific meeting/payment/hostel sections absent | UI alignment and read-only summary | admission.view/create/update, player.convert; lifecycle, branch, CSRF, rollback tests | PARTIAL / READY |
| Trial mode and scheduling | Trial booking, Coach/Ground options, statuses, attendance, reschedule, history | On-ground/video mode, scoring, media, formal summary absent | Verify existing fields and routes | lead/trial permissions; status, branch, history tests | PARTIAL / NEEDS_DECISION |
| Trial media | Documents private storage foundation | Trial media linkage, scanner, retention, visibility absent | None until deployment/policy evidence | document permissions, quarantine/private-download tests | BLOCKED |
| Admission meeting | No complete meeting entity/history | Fields, authority, visibility, correction rules absent | None | New schema/RBAC/audit tests required | NEEDS_DECISION |
| Fees/payment plan | No confirmed payment module | Amounts, schedules, receipts, refunds, reminders absent | None | Finance authority, transaction, reconciliation tests | NEEDS_DECISION |
| Hostel | No confirmed hostel workflow | Requirement, charge, allocation, consent absent | None | Privacy/finance schema and tests | NEEDS_DECISION |
| Admission conversion | Status transitions and Player conversion exist | Full reference gates require reconciliation | Verify lineage and UI | admission/player permissions; idempotency/branch/audit tests | PARTIAL / READY |
| Director dashboard | Permission-aware DashboardService cards and analytics | Selector, Coach workload, coaching types, batches, ages, freshness not complete | Existing-data UI review | Dashboard branch/time/formula tests | PARTIAL / NEEDS_DECISION |
| Individual session | Scheduling date/time/Coach/Player/Batch/Ground, attendance/feedback/history | Individual type, skills, media absent | Shared session UI verification | session/attendance permissions; roster and validation tests | PARTIAL / NEEDS_DECISION |
| Group session | Scheduling roster sessions, snapshots, attendance, feedback, history | Group entity/size, focus/drill/plan/media absent | Existing group UI verification | session/attendance permissions; snapshot/concurrency/CSRF tests | PARTIAL / NEEDS_DECISION |
| Documents/media | Private upload/download/preview, verification/scanner/expiry foundation | Trial/session linkage and deployment evidence absent | Reuse only after policy | document permissions, private storage/scanner tests | PARTIAL / BLOCKED |
| Reports/notifications | Report Center and Notifications routes/services | Reference formulas/widgets and delivery policies unresolved | Existing module review only | report.view/notification permissions; source/delivery tests | PARTIAL / NEEDS_DECISION |
| Shared controls | Datepicker, btSelect, cards, tables, alerts, branch/RBAC/CSRF/audit | Markup and redirect drift in legacy views | Contract audit and UI normalization | Unit/Feature/UI contracts | READY |

## Workflow-specific readiness

## Affected repository surfaces

| Workflow | Routes/controllers | Services/models/views | Data and tests |
| --- | --- | --- | --- |
| Admissions and trials | app/Modules/Admissions/Config/Routes.php, Admissions Browser, CRM routes/controllers | AdmissionService, TrialService, Admission/Trial/CRM views and models | admissions, leads, trials, admission_status_history, Documents; Admission/CRM/Trial tests |
| Director dashboard | Home/index and dashboard route wiring | DashboardService, DashboardModel, shared dashboard views | Players, Leads, Admissions, Sessions, Attendance, Matches, Tournaments, Development; dashboard tests |
| Individual/group sessions | app/Modules/Scheduling/Config/Routes.php and Scheduling Browser | SchedulingService, AttendanceService, Coach feedback services, Scheduling/Attendance views | sessions, session_players, roster snapshots, attendance/history; Scheduling/Attendance tests |
| Documents/media | app/Modules/Documents/Config/Routes.php and Documents Browser | DocumentManagementService, private storage abstraction, Documents views | documents, document_history; document policy tests |
| Reports/notifications | Reports and Notifications route/controller pairs | ReportService, NotificationService, shared report/notification views | Existing report/notification tables and tests |

Any new field, service, permission, or migration must be tied to an approved decision and reviewed as a forward-only change.

### Admission

| Slice | Required database change | Required permissions | Audit/transaction | Status |
| --- | --- | --- | --- | --- |
| Existing registration UI | None | Existing admission permissions | Existing admission transaction/history | READY |
| Trial mode/media | Trial mode/media/link tables only after decision | Existing or new trial permissions only after decision | Private storage, scan, audit | NEEDS_DECISION |
| Meeting/payment/hostel | New approved schema | New approved permissions | Finance/meeting audit and rollback | NEEDS_DECISION |

### Director dashboard

| Slice | Source | Risk | Status |
| --- | --- | --- | --- |
| Existing cards | DashboardModel source tables | Permission-dependent incomplete cards | READY for verification |
| Branch/date selectors | Existing source tables | Date boundary and freshness semantics | NEEDS_DECISION |
| Workload/type/age formulas | Multiple source tables | Formula and privacy invention | NEEDS_DECISION |

### Individual session

| Slice | Existing source | Dependency | Status |
| --- | --- | --- | --- |
| Single Player selection | Scheduling roster | Cardinality and session-type decision | NEEDS_DECISION |
| Date/time/history/attendance | Scheduling and Attendance | Existing conventions | READY for verification |
| Skill/media | No approved source | Taxonomy and private-storage decisions | NEEDS_DECISION |

### Group session

| Slice | Existing source | Dependency | Status |
| --- | --- | --- | --- |
| Branch/Coach/Batch/Ground/Players | Scheduling | Existing branch/RBAC | READY for verification |
| Immutable roster and attendance | Attendance snapshots | Existing concurrency rules | READY for verification |
| Focus/drill/plan/media | No approved source | Taxonomy, retention, privacy | NEEDS_DECISION |

## Required shared acceptance

All future work must preserve centralized RBAC, server-side branch isolation, CSRF, service-layer transactions, append-only audit metadata, private document storage, configured timezone/date conventions, field-level validation, submitted-value preservation, insert-only test fixtures, responsive shared UI, and no raw JSON or sensitive data in logs/views.

## Deployment and migration gates

Verify normal PowerShell Composer/bootstrap readability before migrations or PHPUnit. Do not apply migrations automatically to production. Payment provider, notification provider, private storage/scanner, encryption, backup, and retention settings need deployment-owner evidence.
