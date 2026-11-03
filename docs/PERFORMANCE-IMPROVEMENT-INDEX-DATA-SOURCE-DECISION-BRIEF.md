# Performance Improvement Index — Data-Source and Decision Brief

**Status:** `NEEDS_DECISION`

**Purpose:** Provide a source-based brief for an authorized MCC approver before any Performance Improvement Index (PII) implementation. This document does not change code, schema, database data, or existing decisions. It does not treat `docs/SRS.md` or repository code as client approval.

## 1. Current implementation evidence

| Area | Current evidence | Status |
|---|---|---|
| Performance module | `app/Services/PerformanceService.php`, `app/Modules/Performance/Controllers/Browser.php`, and the Performance views provide branch-scoped list, detail, create, edit, and soft-delete flows. | Implemented for source-record management |
| Record types | `PerformanceService::TYPES` accepts `batting`, `bowling`, and `fielding`. | Implemented |
| Batting metrics | `runs`, `balls_faced`, `dismissals`, `fours`, and `sixes`. | Available as raw fields |
| Bowling metrics | `wickets`, `runs_conceded`, and `legal_balls`. | Available as raw fields |
| Fielding metrics | `catches`, `stumpings`, and `run_outs`. | Available as raw fields |
| Record date | `recorded_on` is a required `DATE`; records are ordered by date and ID. | Available, but not an assessment marker |
| Session link | `session_id` is optional and validated against the Player, branch, and session roster. | Available relationship; not an assessment marker |
| Notes and recorder | `notes` and `recorded_by` exist. | Available context; neither defines approval |
| Existing Performance KPIs | `PerformanceService::kpis()` calculates batting record count, runs, balls faced, strike rate, average, highest score, fours, sixes, wickets, economy, catches, stumpings, and run-outs. | Existing source-backed summaries; not PII |
| Coach KPI projection | `CoachManagementService::kpis()` returns `performance_improvement_index => null`; the Coach view displays “Not yet available.” | PII blocked |

**Sources:** `app/Services/PerformanceService.php`; `app/Modules/Performance/*`; `app/Database/Migrations/20260905000100_CreatePerformanceRecords.php`; `app/Services/CoachManagementService.php`; `docs/REQUIREMENTS.md`; `docs/DECISIONS.md` (`PF-01`–`PF-06`, `DEV-01`, `CM-05`).

## 2. Baseline and latest-assessment evidence

The `performance_records` schema contains `record_type` and `recorded_on`, but it does not contain:

- a baseline flag or baseline event type;
- an assessment number or review-period identifier;
- an approved-state field;
- an approval actor or approval timestamp;
- a metric name/value structure for comparing arbitrary metrics;
- a correction version or supersession link.

The current records therefore support chronological ordering only. The repository cannot safely determine whether the earliest record is an approved baseline, whether the latest record is an approved assessment, or whether two values are comparable measurements of the same metric. A date-based inference would create a business rule that is not recorded as approved.

**Current conclusion:** baseline selection, latest-assessment selection, approval state, and comparability are `NEEDS_DECISION`.

## 3. Direction already recorded, and its limitation

The user-provided Coach Management direction recorded in `docs/REQUIREMENTS.md` and `docs/DECISIONS.md` describes a possible PII calculation: compare the earliest approved baseline with the latest approved assessment for the same Player and metric, exclude missing/invalid/incomparable metrics, and average percentage improvement across eligible metrics. It is explicitly user direction, not client approval.

The same records state that the current `performance_records` table has no approved-state or baseline/assessment marker. The direction therefore cannot be implemented safely from the current schema without an explicit source-data and approval decision.

Related records: `CM-05`, `PF-01`, `PF-02`, `PF-03`, `PF-04`, `PF-06`, `DEV-01`, `OPS-08`.

## 4. Decisions required from the authorized approver

Each unanswered item remains `NEEDS_DECISION`. The approver should provide an explicit answer, comments, identity, and date.

### PII-01 — Authoritative source and grain

**Current status:** `NEEDS_DECISION`  
**Evidence:** `performance_records` stores one row with a record type and several nullable raw metrics; `PF-01` remains unresolved.

Decide whether PII uses `performance_records` as the authoritative source, another existing assessment source, or a new approved source. Define whether a row, metric value, session, dated assessment, or review period is the comparison grain. Do not assume that a match on `player_id` and `record_type` is sufficient.

**Decision:** ________________________________  
**Comments:** ________________________________  
**Approver:** ________________________________  
**Date:** ____________________________________

### PII-02 — Baseline identification

**Current status:** `NEEDS_DECISION`  
**Evidence:** No baseline marker exists in `performance_records`; chronological order is the only available ordering.

Define the exact baseline marker and selection rule. Specify whether it is an explicit field, an approved assessment type, a review-period record, or another existing source. Define behavior when multiple candidate baselines exist.

**Decision:** ________________________________  
**Comments:** ________________________________  
**Approver:** ________________________________  
**Date:** ____________________________________

### PII-03 — Latest assessment identification

**Current status:** `NEEDS_DECISION`  
**Evidence:** `recorded_on` and ID provide chronology but no assessment-state semantics.

Define the exact latest-assessment marker and tie-breaking rule. Confirm whether later corrections replace, supersede, or remain alongside an assessment.

**Decision:** ________________________________  
**Comments:** ________________________________  
**Approver:** ________________________________  
**Date:** ____________________________________

### PII-04 — Metrics and comparability

**Current status:** `NEEDS_DECISION`  
**Evidence:** Batting, bowling, and fielding rows use different nullable metric columns; `PF-02` and `PF-03` remain unresolved.

Define which metrics participate, how metrics are paired between baseline and latest assessment, and what makes values comparable. Decide how zero baselines, missing values, invalid values, changed units, and changed metric definitions are handled.

**Decision:** ________________________________  
**Comments:** ________________________________  
**Approver:** ________________________________  
**Date:** ____________________________________

### PII-05 — Formula, rounding, and insufficient data

**Current status:** `NEEDS_DECISION`  
**Evidence:** No PII formula exists in `PerformanceService`; Coach KPI currently returns `null`.

Confirm the percentage-improvement formula, aggregation method, rounding precision, minimum number of eligible metrics, and output when no eligible comparison exists. The implementation must not fabricate zero; the current display contract is “Not yet available.”

**Decision:** ________________________________  
**Comments:** ________________________________  
**Approver:** ________________________________  
**Date:** ____________________________________

### PII-06 — Approval and correction policy

**Current status:** `NEEDS_DECISION`  
**Evidence:** `performance_records` has no approval fields; updates currently write the row and emit a basic audit event. `PF-04` remains unresolved.

Define who approves a baseline and assessment, whether unapproved records are excluded, who may correct them, whether a correction requires a reason, and whether corrections append a new version or update the existing row. Define locking, stale-write handling, audit fields, and recalculation behavior.

**Decision:** ________________________________  
**Comments:** ________________________________  
**Approver:** ________________________________  
**Date:** ____________________________________

### PII-07 — Coach visibility and branch scope

**Current status:** `NEEDS_DECISION`  
**Evidence:** `coach.kpi.view` currently gates Coach KPI display and uses the Coach’s authorized branches; `CM-05` requires permission and branch protection but does not settle aggregation details.

Confirm which roles may view PII, whether a Coach may view their own result, whether multi-branch Coaches see per-branch values or an authorized-scope aggregate, and how a Player or session’s branch controls visibility. Preserve server-side permission and branch checks.

**Decision:** ________________________________  
**Comments:** ________________________________  
**Approver:** ________________________________  
**Date:** ____________________________________

### PII-08 — Period, timezone, and version governance

**Current status:** `NEEDS_DECISION`  
**Evidence:** `OPS-09` records MCC `Asia/Kolkata` presentation and UTC persistence; `CM-05` requires documented KPI versions and configured application-timezone periods, but no PII period is defined.

Define reporting period boundaries, timezone interpretation, effective dates for formula versions, historical recalculation behavior, and whether prior published values remain reproducible under the version in effect at calculation time.

**Decision:** ________________________________  
**Comments:** ________________________________  
**Approver:** ________________________________  
**Date:** ____________________________________

## 5. Migration impact if approved

No migration is authorized by this brief. If the approver selects `performance_records` as the source, the minimum schema impact may include explicit baseline/assessment markers, approval actor/timestamp/state, correction or supersession metadata, and any approved metric-source structure. Exact fields, indexes, foreign keys, retention, and migration order require the PII decisions above.

Do not add fields merely to make chronological records appear comparable. Do not store a manually calculated PII total if the approved policy requires source-backed recalculation.

## 6. Test data and acceptance criteria after approval

Test preparation must use synthetic records only and must not alter production or unverified databases. Fixtures should cover:

- two approved comparable assessments for one Player and metric;
- multiple candidate baselines and latest assessments;
- missing, zero, invalid, and incomparable metrics;
- unapproved and corrected records;
- multiple branches and a Coach with multiple authorized branches;
- unauthorized, cross-branch, and Coach-scope visibility attempts;
- formula-version and application-timezone boundaries;
- no eligible comparison, which must display `Not yet available`.

Acceptance criteria must be written from the recorded decisions and include the exact source query, baseline/latest selection, formula, rounding, approval and correction audit fields, branch/permission behavior, version effective date, and recalculation result. Until those decisions and source markers exist, PII remains `NEEDS_DECISION` and `CoachManagementService::kpis()` must continue to report it as unavailable.

## 7. Recommendation for the next safe step

Obtain explicit answers for `PII-01` through `PII-08`, reconcile them with `PF-01`–`PF-06`, `DEV-01`, `CM-05`, and `OPS-09`, then record the approved source and formula before designing a migration or implementation. No code or database change should proceed from the current records alone.

## 8. User-provided implementation direction (2026-09-25; not client approval)

The supplied policy authorizes a source-backed PII implementation using approved `performance_records`: earliest approved record and latest approved record for the same Player, skill (`record_type`), and metric; at least two comparable records; exclusion of missing, invalid, incomparable, or zero-baseline values; percentage change using the absolute baseline denominator; preservation of negative results; and `Not yet available` when no valid comparison exists. The formula is versioned as `PII-v1` in project documentation. Existing rows receive no approval by migration and remain excluded until explicitly approved. Branch and `coach.kpi.view` checks remain mandatory, and source corrections must recalculate the read-time result with append-only audit metadata.

Implementation evidence: migration `20260925000500_AddPerformanceApprovalMarkers.php` adds `approved`, `approved_by`, and `approved_at`. Ordering uses the existing `recorded_on` date followed by record ID; no arbitrary baseline inference is used. Approval uses the existing `performance.update` permission and branch authorization. The broader client replacement baseline and any future metric/source policy remain `NEEDS_DECISION`.

