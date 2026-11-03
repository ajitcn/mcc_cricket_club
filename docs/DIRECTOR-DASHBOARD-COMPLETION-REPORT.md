# Director Dashboard Completion Report

**Report date:** 2026-10-11  
**Scope:** Bounded, read-only Director Dashboard foundation  
**Direction record:** `USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL`

## Implemented formulas

The following source-backed formulas are implemented using existing records and the documented branch and date rules:

| Formula | Implemented behavior |
|---|---|
| FORM-01 | Counts distinct active Players in the authorized branch scope. |
| FORM-02 | Counts distinct active, enabled, assigned Coaches in the authorized branch scope. |
| FORM-03 | Counts distinct Sessions with more than one active, same-branch roster Player. Included statuses are scheduled, active, and completed. |
| FORM-04 | Counts distinct Sessions with exactly one active, same-branch roster Player. Included statuses are scheduled, active, and completed. |
| FORM-06 | Shows active Player and Coach totals per authorized active branch. |
| FORM-07 | Counts distinct active Players with a current effective active Batch assignment. |
| FORM-11 | Counts scheduled Sessions strictly after the current MCC-local time within the selected day/month period. |

## Authorization and branch scope

- Every dashboard query is restricted to branches in the server-side authorized branch session context.
- A requested branch is accepted only when it is authorized.
- Super Admin users receive an explicit All Branches option only when the existing `rbac.manage` authorization and Super Admin role checks both pass.
- No client-side selector, URL value, or hidden field grants additional branch access.
- Player, Coach, Batch, Session, and branch relationships are checked server-side.

## Date and time behavior

- Dashboard day and month inputs are interpreted in the configured MCC application timezone.
- Day filters use a half-open local interval: start inclusive, next day exclusive.
- Month filters use a half-open local interval: first day inclusive, next month exclusive.
- Stored UTC timestamps are compared only after local boundaries are converted to UTC.
- Upcoming sessions use a strict current-time boundary and include only scheduled records.
- No timezone suffix is displayed in the dashboard UI.

## Status, duplicate, and missing-data rules

- Only active Players, active Coach profiles, enabled Coach users, active Batches, and valid effective assignments contribute to the implemented counts.
- FORM-03 and FORM-04 exclude cancelled, postponed, invalid, and incomplete rosters.
- Scheduled, active, and completed Sessions are eligible for FORM-03 and FORM-04.
- FORM-11 includes scheduled Sessions only; active, completed, cancelled, and postponed Sessions are excluded.
- Queries count distinct identity or Session IDs and group roster rows before counting, preventing duplicate roster rows from inflating totals.
- Missing branch, identity, assignment, roster, status, or start-time data is excluded or shown through a safe empty/unavailable state.
- Zero qualifying records are shown as zero or a clear empty state, never fabricated values.

## Sensitive-data exclusions

The dashboard does not query or display medical conditions, medical documents, guardian details, private contact information, document contents, or audit internals. The safe unavailable label is:

`Medical metrics — Not available on this dashboard`

## Empty and unavailable states

The dashboard provides section-level empty states when no authorized records are available. Unsupported metrics remain unavailable rather than being inferred from names, counts, programs, batches, or unrelated fields.

## Deferred and blocked formulas

The following formulas remain unavailable or blocked:

| Formula | Reason |
|---|---|
| FORM-05 — Elite coaching | No approved Elite taxonomy or source classification field exists. |
| FORM-08 — Age groups | Age categories and DOB boundary rules are unresolved. |
| FORM-09 — Coach workload | Workload unit, weighting, and denominator are unresolved. |
| FORM-10 — Availability/leave | Availability denominator and leave interval policy are unresolved. |
| FORM-12 — Attendance | The approved denominator and percentage semantics are unresolved. |
| FORM-13 — Performance/KPI | The approved KPI sources, status rules, and calculation are unresolved. |

No deferred formula has been inferred or implemented.

## UI completion update

The Director Dashboard presentation now uses a compact MCC-local filter disclosure, a responsive Director hero, KPI cards, branch summary cards, batch distribution, source-backed coaching-type distribution, a branch-scoped Coach profile list, upcoming session cards, safe empty states, and explicit unavailable states for unresolved age-group, availability, workload, Elite, and KPI formulas. Coach workload is not calculated because no approved workload unit or denominator exists.

The Coach list exposes only display name, authorized branch, role/profile specialization, and profile status from existing sources. No medical, document, guardian, private-contact, availability, or workload data is rendered.

## Test result

The current focused Dashboard result is **14 tests, 172 assertions passed**. The broader dashboard command also includes two legacy `DashboardIntegrationTest` setup errors caused by its FK-invalid table cleanup before the dashboard request runs.

Coverage includes formula contracts, route/filter wiring, branch and status boundaries, safe unavailable states, and medical-data protection assertions.

## Migration and permission status

- No migration was required or added for the implemented formulas.
- No migration was applied for this dashboard scope.
- No new permissions were added.
- Existing dashboard, module, branch, and centralized RBAC permissions are reused.
- No caching, exports, or additional persistence layer was introduced.

## Scope protection

No additional formulas, migrations, permissions, caching, exports, or unrelated UI changes were implemented as part of this report. `docs/SRS-original.md` was not modified.
