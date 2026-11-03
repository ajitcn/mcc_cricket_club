# Coach and Attendance First Slice — Approval Request

**Prepared:** 2026-09-25  
**Status:** Awaiting authorized approval  
**Source brief:** `docs/COACH-ATTENDANCE-REQUIREMENTS-DECISION-BRIEF.md`  
**Scope:** First Coach and Attendance source-event slice only.  
**Approval meaning:** A response approves only the exact item and scope recorded below. It does not approve the wider requirements baseline, unresolved KPI/alert behavior, or any item marked `NEEDS_DECISION`.

## Instructions for the approver

Please select **Approved**, **Rejected**, or **Needs discussion** for every item. Record the exact resolution in the comments field. An item remains `NEEDS_DECISION` until the authorized approver, identity, and date are recorded in the project decision records. Do not infer approval from existing code or historical status tables.

**Authorized approver:** __________________________________________  
**Decision date:** ________________________________________________

## Current implementation context

- Coach profiles already use `coach_profiles` and currently store specialization, experience years, qualification, certifications, availability, and an active flag. Existing branch assignments use `user_branches`; BR-03 records that Coaches may be assigned to multiple authorized branches.
- Attendance already records source events for persisted session rosters, uses server-side branch authorization, supports correction history and audit, and currently accepts `PRESENT`, `ABSENT`, `LATE`, and `EXCUSED`.
- AT-02 records the existing four-status baseline as a proposed status vocabulary and says leave/medical treatment requires an explicit policy. AT-06 defers derived calculations, thresholds, reports, alerts, and recipients.
- Existing implementation is evidence only. It does not establish client approval for this request.

## Approval decisions

### 1. Attendance status vocabulary and cancellation labels

**Decision ID:** AT-R1 (brief item; underlying records: AT-02, AT-04, SCH-04)  
**Current status:** `NEEDS_DECISION`  
**Conflict to resolve:** The existing code uses `PRESENT`, `ABSENT`, `LATE`, and `EXCUSED`. The supplied direction additionally names Leave, Medical, Rain Cancelled, and Coach Cancelled.

Choose one exact status contract:

- [ ] Keep `PRESENT`, `ABSENT`, `LATE`, and `EXCUSED`.
- [ ] Replace or extend the vocabulary with specific additional statuses. List exact stored codes and labels below.

Decide whether Rain Cancelled and Coach Cancelled are Attendance statuses or Session cancellation states, and whether Leave and Medical are statuses or absence reasons. Confirm whether medical details may be stored; the existing AT-02 baseline says no medical detail in Attendance.

**Decision:** Approved / Rejected / Needs discussion  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

### 2. Treatment of cancelled sessions in attendance percentages

**Decision ID:** AT-R2  
**Current status:** `NEEDS_DECISION`  
**Sources:** AT-01, AT-04, SCH-04, user-provided percentage requirements.

Decide whether sessions cancelled for any approved cancellation reason are excluded from attendance percentages. If not, specify the exact numerator and denominator treatment for each cancellation state.

**Decision:** Approved / Rejected / Needs discussion  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

### 3. Treatment of Leave and Medical in denominators

**Decision ID:** AT-R2  
**Current status:** `NEEDS_DECISION`  
**Sources:** AT-01, AT-02, AT-04, user-provided status requirements.

For each of Leave and Medical, decide whether the event is excluded from the denominator, counted as non-attendance, or handled by another explicit rule. Specify how the rule applies to percentages, corrections, and future alerts.

| Status | Exclude from denominator | Count as non-attendance | Other exact rule |
|---|---|---|---|
| Leave | [ ] | [ ] | ______________________________ |
| Medical | [ ] | [ ] | ______________________________ |

**Decision:** Approved / Rejected / Needs discussion  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

### 4. Percentage denominator and date boundaries

**Decision ID:** AT-R4  
**Current status:** `NEEDS_DECISION`  
**Sources:** AT-01, AT-03, AT-06, OPS-09, `docs/REQUIREMENTS.md` calculation rule.

Define the exact denominator, numerator, timezone, inclusivity, and date boundaries for each period. Specify week start day, calendar versus rolling periods, handling of no eligible records, rounding/precision, and whether corrections recalculate historical results.

| Period | Date boundary and timezone | Denominator | Numerator | Empty-period result | Rounding/version rule |
|---|---|---|---|---|---|
| Daily | __________________ | __________________ | __________________ | __________________ | __________________ |
| Weekly | __________________ | __________________ | __________________ | __________________ | __________________ |
| Monthly | __________________ | __________________ | __________________ | __________________ | __________________ |
| Yearly | __________________ | __________________ | __________________ | __________________ | __________________ |

**Decision:** Approved / Rejected / Needs discussion  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

### 5. Attendance authority and audit access

**Decision ID:** AT-R3 / AT-R8  
**Current status:** `NEEDS_DECISION`  
**Sources:** AT-03, AT-05, BR-03, BR-04, BR-06, existing `attendance.view`, `attendance.record`, and `attendance.correct` permissions.

Specify which existing roles or exact permissions may perform each action. Do not grant access merely because a user has a role label.

| Action | Authorized role(s) or exact permission | Branch scope / limits |
|---|---|---|
| Mark attendance | ______________________________ | ______________________________ |
| Correct attendance | ______________________________ | ______________________________ |
| Approve attendance or corrections | ______________________________ | ______________________________ |
| View attendance history | ______________________________ | ______________________________ |
| Audit attendance changes | ______________________________ | ______________________________ |

Specify required reason, actor, timestamp, previous/new values, source version, and audit/history behavior for each correction or approval action.

**Decision:** Approved / Rejected / Needs discussion  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

### 6. Coach branch ownership and multi-branch assignment

**Decision ID:** CM-02  
**Current status:** `NEEDS_DECISION` for this scope; BR-03 currently records multi-branch Coach assignment as an approved constraint.

Confirm the exact branch policy for Coach profiles, assignments, sessions, leave, and KPI visibility. Confirm whether Coaches may be assigned to multiple branches, and whether profile data is global or branch-specific. Confirm who may add/remove branch assignments and how inactive or closed branches are handled.

- [ ] Coach may be assigned to one branch only.
- [ ] Coach may be assigned to multiple authorized branches.
- [ ] Other exact rule: ____________________________________________

**Decision:** Approved / Rejected / Needs discussion  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

### 7. Coach field definitions

**Decision ID:** CM-01  
**Current status:** `NEEDS_DECISION`  
**Sources:** User-provided Coach Management list; existing `coach_profiles` migration and `CoachManagementService::saveProfile()`; PA-02; SCH-05.

Define the type, units, required/optional status, maximum length, allowed values, structure, search behavior, and history for each field. Decide whether Leave is a separate dated/branch-scoped record rather than a profile field, and define availability structure and timezone.

| Field | Type / units | Required? | Allowed values or structure | Branch scope | History/audit rule |
|---|---|---|---|---|---|
| Experience | __________________ | __________________ | __________________ | __________________ | __________________ |
| Qualification | __________________ | __________________ | __________________ | __________________ | __________________ |
| Certifications | __________________ | __________________ | __________________ | __________________ | __________________ |
| Availability | __________________ | __________________ | __________________ | __________________ | __________________ |
| Specialization | __________________ | __________________ | __________________ | __________________ | __________________ |
| Leave | __________________ | __________________ | __________________ | __________________ | __________________ |

**Decision:** Approved / Rejected / Needs discussion  
**Exact resolution:**

____________________________________________________________________

**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

## Explicitly deferred items

The following remain `NEEDS_DECISION` and are not approved by this request:

- Below-75% attendance alerts: period, formula, recipients, channel, deduplication, visibility, and correction behavior.
- Continuous-absence alerts: consecutive-event definition, thresholds, windows, recipients, and recalculation.
- Late-arrival alerts: late definition, grace period, threshold period, recipients, and correction behavior.
- Player feedback rating: instrument, source, formula, scale, visibility, and correction rules.
- Performance improvement index: source data, formula, period, scale, visibility, and recalculation rules.
- Coach KPI visibility, aggregation, rounding, missing-data behavior, historical versioning, and audit policy.
- Any new migrations, permissions, notification jobs, reports, dashboards, or alert delivery mechanisms for those items.

**Deferred-item confirmation:** Approved / Rejected / Needs discussion  
**Comments:**

____________________________________________________________________

**Approver:** ______________________________  **Date:** ______________

## Next step after approval

Reconcile the completed responses into `docs/REQUIREMENTS.md`, `docs/DECISIONS.md`, `docs/RBAC.md`, `docs/ARCHITECTURE.md`, and `docs/DATABASE.md`, preserving unresolved items as `NEEDS_DECISION`. Only then define the smallest approved source-event change, its acceptance criteria, focused tests, and any required migration.

## Later user-direction record (2026-09-25)

The subsequent pasted direction authorizes implementation of the defined Coach and Attendance slice as user direction, not client approval. It supersedes the implementation hold for the seven attendance statuses, existing-source percentage calculations, branch-scoped report-ready alerts, and the defined Coach profile/KPI scope. The deferred items above remain `NEEDS_DECISION` for client reconciliation and are not treated as approved by this record.
