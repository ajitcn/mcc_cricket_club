# Marketing Dashboard Approval Request

**Prepared:** 2026-09-27  
**Status:** Approval request; unresolved items remain `NEEDS_DECISION`.  
**Authority:** This request does not claim client approval and does not replace `docs/SRS-original.md`. The original client SRS is unavailable.

## Approval statement

Please approve, reject, or return each decision below for discussion. Record the exact rule, scope, and effective date. Implementation must continue to show unsupported metrics as `Not available` until the required source data and formulas are approved.

**Approver:** ____________________________________  
**Decision date:** _________________________________  
**Overall decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Comments:**

______________________________________________________________________________

______________________________________________________________________________

## Current implementation evidence

| Area | Current evidence | Current limitation |
|---|---|---|
| Route and permission | `/marketing` uses `auth` and `permission:marketing.view`; `MarketingDashboardService` repeats the permission check. | No separate permission for exports, targets, attribution, or cost data. |
| Branch scope | The service accepts only a requested branch in `session('authorizedBranches')` and applies branch filters to Lead, Admission, Trial, option, comparison, and trend queries. | Role-specific scope and stale-session behavior are not defined for Marketing. |
| Filters | Branch, date presets/custom dates, source, Lead status, Admission status, and Lead search. | Trial status is accepted as a legacy query filter but has no visible control. |
| Metrics | Counts for Leads, Admissions, Trials, and `admissions.status = CONVERTED`; grouped source/status/funnel/trend tables. | No approved rates, targets, ROI, cost, or attribution calculation is implemented. |
| Source data | `leads.source`, `lead_sources`, Lead statuses, Admissions, Trials, and branch records. | No campaign, touchpoint, spend, cost, or persisted Marketing summary source was found. |
| Unavailable metrics | The service returns `Not available` for campaign attribution, ROI, cost per admission, and conversion rate. | These values must not be derived without approval. |
| Date handling | Presets and custom dates use `config('App')->appTimezone`; local-day boundaries are converted to UTC query bounds. | Freshness, reporting-period ownership, and historical recalculation rules are unresolved. |
| Tests | `tests/Unit/MarketingDashboardContractTest.php` and `tests/database/MarketingDashboardIntegrationTest.php` cover permission, branch scope, filters, date validation, and UTC/local boundary behavior. | No tests cover approved attribution, cost, ROI, targets, role variants, freshness, correction, or audit policy. |

**Primary sources:** `docs/MARKETING-DASHBOARD-READ-ONLY-AUDIT.md`; `app/Services/MarketingDashboardService.php`; `app/Modules/Marketing/Views/index.php`; `app/Modules/Marketing/Config/Routes.php`; `app/Database/Migrations/20260904001000_AddMarketingDashboardPermission.php`; `tests/Unit/MarketingDashboardContractTest.php`; `tests/database/MarketingDashboardIntegrationTest.php`.

## Decision response fields

For each item, mark one response and record the exact rule. Do not treat code behavior as approval.

### MKT-02 — Campaign and source attribution

**Current record:** `docs/DECISIONS.md / MKT-02`; `docs/REQUIREMENTS-REVIEW.md / MKT-02`; `docs/MARKETING-DASHBOARD-READ-ONLY-AUDIT.md / Attribution and source vocabulary`. Status: `NEEDS_DECISION`.

Decide:

- Whether attribution uses first touch, last touch, multi-touch, or another model.
- Whether a Lead has one primary source plus campaign/touchpoint history.
- Which event receives attribution credit and how reassignment or source correction is handled.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### MKT-03 — Campaign ownership and scope

**Current record:** `docs/DECISIONS.md / MKT-03`; `docs/REQUIREMENTS-REVIEW.md / MKT-03`; `LD-05`. Status: `NEEDS_DECISION`.

Decide:

- Whether campaigns are global, branch-scoped, or both.
- Who may create, edit, activate, deactivate, and report campaigns.
- Required campaign fields, date/lifecycle rules, and whether historical campaign links are immutable.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### Source vocabulary and inactive-source behavior

**Current record:** `app/Modules/CRM/Models/LeadSourceModel.php`; `MarketingDashboardService::filterOptions()`; `docs/SRS.md` source vocabulary; `docs/DECISIONS.md / LD-04`. Status: `NEEDS_DECISION` for the final reporting policy.

The current selector merges configured `lead_sources` names with distinct persisted `leads.source` values and does not filter `is_active`.

Decide:

- The authoritative allowed source vocabulary.
- Whether historical values outside the configured list remain reportable.
- Whether inactive sources remain selectable for historical reporting, disappear from new-entry controls only, or are excluded entirely.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### Conversion definition and conversion date

**Current record:** `docs/DECISIONS.md / LD-10`; `MarketingDashboardService::kpis()`; `docs/REQUIREMENTS.md / REQ-DASH-001`. Status: conversion-rate/reporting details `NEEDS_DECISION`.

The current implementation counts Admissions with status `CONVERTED`; it does not calculate a conversion rate or show active-player conversion separately.

Decide:

- The authoritative conversion event: admission completion, Admission `CONVERTED`, active-player creation, or separate measures.
- The date used for reporting conversion: Lead creation, Admission creation/completion, conversion timestamp, or another event date.
- Numerator and denominator for any conversion rate, including treatment of cancelled, withdrawn, duplicate, or reopened records.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### MKT-01 — Cost and ad-spend data source

**Current record:** `docs/DECISIONS.md / MKT-01`; `docs/REQUIREMENTS-REVIEW.md / MKT-01`; audit section `Conversion, cost-per-admission, ROI, and targets`. Status: `NEEDS_DECISION`.

No spend, cost, campaign, or advertising integration source exists in the current repository.

Decide:

- The authoritative source for ad spend and other marketing costs.
- Whether costs are entered internally, imported, or integrated from an external provider.
- Cost categories, ownership, branch/campaign allocation, currency, tax treatment, and effective dates.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### MKT-04 — ROI and cost-per-admission formulas

**Current record:** `docs/DECISIONS.md / MKT-04`; `docs/REQUIREMENTS-REVIEW.md / MKT-04`; `MarketingDashboardService` unavailable metrics. Status: `NEEDS_DECISION`.

Decide:

- ROI numerator, denominator, treatment of revenue/fees, and whether ROI is campaign, source, branch, or period based.
- Cost-per-admission numerator and admission denominator.
- Treatment of zero denominators, missing costs, refunds, duplicate admissions, withdrawn/rejected admissions, and late cost corrections.
- Precision, rounding, currency, formula version, and missing-data display.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### Targets and reporting periods

**Current record:** `docs/REQUIREMENTS-REVIEW.md / DASH-01, MKT-01–MKT-04`; `docs/REQUIREMENTS.md / Calculation rule`. Status: `NEEDS_DECISION`.

No target table, target formula, or target UI exists.

Decide:

- Which metrics have targets and whether targets are branch, campaign, source, role, or global.
- Target periods: daily, weekly, monthly, quarterly, yearly, campaign lifetime, or another period.
- Ownership, effective dates, versioning, correction behavior, and whether targets are historical snapshots.
- Whether comparisons show absolute variance, percentage variance, or another approved measure.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### DASH-02 / REQ-DASH-001 — Dashboard role visibility and branch scope

**Current record:** `docs/DECISIONS.md / DASH-02`; `docs/REQUIREMENTS.md / REQ-DASH-001`; `docs/RBAC.md / marketing.view`; route and service permission checks. Status: `NEEDS_DECISION`.

The migration grants `marketing.view` to Super Admin, Branch Admin, and Admission Team. The service uses the current session's authorized branch list for every query.

Decide:

- Which roles may view each metric, filter, source field, campaign field, and future cost field.
- Whether all permitted roles see the same dashboard or role-specific variants.
- Whether Super Admin scope is all assigned branches, all active branches, or another explicit scope.
- Whether Branch Admin and Admission Team may compare multiple authorized branches.
- Sensitive-field redaction and behavior for a stale or changed branch assignment.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### DASH-03 / OPS-09 — Data freshness and timezone behavior

**Current record:** `docs/DECISIONS.md / DASH-03`; `docs/REQUIREMENTS-REVIEW.md / DASH-03`; `OPS-09`; `MarketingDashboardService`. Status: freshness/refresh remains `NEEDS_DECISION`; timezone presentation is approved under `OPS-09`.

The current dashboard reads live source rows, uses the configured application timezone for local date ranges, converts date boundaries to UTC, and does not display an as-of timestamp or freshness indicator.

Decide:

- Required freshness: live, scheduled, cached, or a maximum age.
- Whether an as-of timestamp is required and which timezone it uses.
- Cache/snapshot invalidation and behavior after source corrections.
- Reporting-period boundaries and daylight-saving handling for deployments whose configured timezone observes DST.
- Performance and concurrent-load expectations for the live aggregation.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

### Correction, audit, and historical recalculation rules

**Current record:** `docs/DECISIONS.md / OPS-01`; `docs/REQUIREMENTS.md / Calculation rule`; audit service conventions; Marketing Dashboard is currently read-only. Status: `NEEDS_DECISION` for Marketing source corrections and derived-history policy.

Decide:

- Who may correct source, campaign, attribution, cost, target, or conversion records.
- Required reason and audit fields: actor, timestamp, branch, previous value, new value, and source entity.
- Whether corrections are append-only, versioned, or editable in place.
- Whether historical dashboards recalculate from corrected source data or preserve a prior snapshot.
- Formula/version metadata and effective dates for recalculated results.
- Retention, export, and audit-read restrictions for marketing data.

**Decision:** ☐ APPROVED  ☐ REJECTED  ☐ NEEDS_DISCUSSION  
**Resolution:** ______________________________________________________________  
**Comments:** ________________________________________________________________  
**Approver:** __________________________  **Date:** __________________________

## Conflicts requiring explicit reconciliation

1. `docs/SRS.md` and `docs/REQUIREMENTS-REVIEW.md` describe campaigns and marketing reporting, while the current implementation has only a Lead source string and no campaign workflow.
2. `LD-04`/`LD-05` are recorded as approved source/campaign decisions, but `MKT-02`/`MKT-03` remain unresolved for attribution and ownership/reporting behavior. The approver must state how these records relate.
3. `LD-10` defines admission completion as the primary conversion event, while the current Dashboard exposes an `admissions.status = CONVERTED` count and no conversion-rate contract. The date and ratio semantics require explicit confirmation.
4. `OPS-09` defines timezone behavior, but freshness, aggregation, and historical recalculation rules remain unresolved under `DASH-03` and the correction policy.

## Approval record

**Approver name:** ____________________________________________________________  
**Role/authority:** __________________________________________________________  
**Decision date:** ___________________________________________________________  
**Approved scope/version:** __________________________________________________  
**Follow-up decision-record ID(s):** _________________________________________  
**Additional comments:**

______________________________________________________________________________

______________________________________________________________________________

