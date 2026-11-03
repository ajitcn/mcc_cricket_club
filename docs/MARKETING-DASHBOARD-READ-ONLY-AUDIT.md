# Marketing Dashboard Read-Only Audit

**Review date:** 2026-09-27  
**Scope:** Marketing Dashboard routes, service, models, views, navigation, migration, tests, and current requirements/decision records.  
**Change status:** Read-only audit. No application code, tests, database records, or existing decision documents were changed.  
**Authority note:** `docs/SRS-original.md` is unavailable. `docs/SRS.md`, `docs/REQUIREMENTS.md`, and `docs/DECISIONS.md` are working records and are not treated as a replacement client approval baseline.

## Executive summary

The Marketing Dashboard is implemented at `/marketing` as a read-only, live aggregation over Leads, Admissions, Trials, branches, Lead Sources, and Lead Statuses. It has a dedicated `marketing.view` permission, branch filtering from the current session, date presets/custom ranges, source/status/search filters, operational count cards, comparison tables, and explicit `Not available` placeholders for unsupported metrics.

The repository does not contain a campaign, spend, cost, or persisted marketing-summary data model. Attribution, conversion semantics beyond the current status count, cost-per-admission, ROI, target calculations, freshness/caching, and role-specific dashboard variants remain `NEEDS_DECISION` under `MKT-01`–`MKT-04`, `DASH-01`–`DASH-03`, `REQ-DASH-001`, and `NFR-08`. No client approval is inferred from the current implementation or migration.

## Source and navigation map

| Area | Evidence | Current state |
|---|---|---|
| Route | `app/Modules/Marketing/Config/Routes.php` | `/marketing` is inside the `auth` group and has `permission:marketing.view`. |
| Controller | `app/Modules/Marketing/Controllers/Browser.php` | Thin controller passes GET filters to `MarketingDashboardService::dashboard()`. |
| Service | `app/Services/MarketingDashboardService.php` | Normalizes filters, validates date ranges, applies branch scope, queries live source tables, and returns KPI/table data. No writes. |
| View | `app/Modules/Marketing/Views/index.php` | Filter form, operational cards, unavailable-metric list, branch comparison, source/status/funnel/trend tables, and responsive empty states. |
| Navigation | `app/Services/NavigationService.php` | “Marketing Dashboard” appears in CRM when `marketing.view` is present. |
| Permission migration | `app/Database/Migrations/20260904001000_AddMarketingDashboardPermission.php` | Adds `marketing.view` to Super Admin, Branch Admin, and Admission Team when absent; `down()` is intentionally forward-only. |
| Tests | `tests/Unit/MarketingDashboardContractTest.php`, `tests/database/MarketingDashboardIntegrationTest.php` | Contract and persisted branch/filter/date/permission coverage exists. Browser rendering and deployment evidence are not present. |

## Existing source-backed metrics and filters

### Filters

`MarketingDashboardService::normalizeFilters()` and the view support:

- `branch_id`, limited to the session's `authorizedBranches`.
- Date presets: `all_time`, `today`, `this_week`, `this_month`, `last_month`.
- Custom `from` and `to` dates, requiring strict `Y-m-d` values and `from <= to`.
- Lead `source`.
- Lead `lead_status`.
- Admission `admission_status`.
- Free-text `q`, matched against Lead `full_name`, `email`, and `phone`.

The service also retains a legacy `status` filter for Trial status. The view does not expose a separate Trial status control, so a caller can supply it in the query string while the visible filter set does not explain it.

### Operational metrics and tables

The current KPI cards are direct counts over filtered, branch-scoped rows:

- `leads`: count of `leads`.
- `admissions`: count of `admissions`.
- `trials`: count of `trials`.
- `converted_admissions`: count of Admissions whose status is `CONVERTED`.

The tables are:

- Lead source performance: grouped `leads.source` counts.
- Lead lifecycle/status summary: grouped `leads.status` counts.
- Admission status counts: grouped `admissions.status` counts.
- Trial status summary: grouped `trials.status` counts.
- Branch comparison: lead, admission, and trial counts for each authorized branch.
- Created-month trends: monthly lead and admission counts from `created_at`.

All of these are read-time queries. No persisted Marketing Dashboard summary or cache table was found.

## Attribution and source vocabulary

The current source vocabulary is the union of:

1. Names from `lead_sources` through `LeadSourceModel` (`app/Modules/CRM/Models/LeadSourceModel.php`).
2. Distinct non-empty `leads.source` values for the authorized branches.

`LeadModel` stores a single `source` string and does not expose campaign, touchpoint, or attribution-history fields in the current implementation. `docs/SRS.md` lists the documented source vocabulary as Google, Meta Ads, Instagram, Website, Walk-In, Referral, School, WhatsApp, and Other. `docs/DECISIONS.md / LD-04` approves a primary source plus campaign/touchpoint history, while `LD-05` describes campaign records with name, source, dates, branch scope, and active state. No campaign table or campaign workflow was found in the reviewed source.

The service reads every `lead_sources` row and does not filter `is_active`; an inactive configured source can therefore appear as a filter option. Whether inactive sources should remain selectable for historical reporting is not specified. This is a confirmed source-option behavior to clarify before changing it, not evidence that historical data should be hidden.

The current grouping by `leads.source` is a source summary, not a documented first-touch, last-touch, or multi-touch attribution calculation. Attribution model and campaign ownership remain `NEEDS_DECISION` (`MKT-02`, `MKT-03`).

## Conversion, cost-per-admission, ROI, and targets

### Current conversion evidence

The only implemented conversion-like value is `converted_admissions`, derived from `admissions.status = 'CONVERTED'`. `docs/DECISIONS.md / LD-10` says admission completion is the primary conversion event and active-player conversion is reported separately where required. The Dashboard does not currently expose an active-player conversion measure or a ratio.

The label and source count are implementation evidence only. The approved Marketing definition of conversion rate, numerator/denominator, reporting event date, and relationship to active-player conversion is not recorded. Treat this as `NEEDS_DECISION` under the dashboard and marketing records before presenting a rate or renaming the measure.

### Unsupported metrics

`MarketingDashboardService` explicitly returns `Not available` for:

- Campaign attribution.
- ROI.
- Cost per admission.
- Conversion rate.

No ad-spend, campaign-cost, marketing-cost, target, or persisted attribution source was found in migrations, models, services, or views. `docs/REQUIREMENTS-REVIEW.md` confirms that ROI/ad spend and cost-per-admission require an approved cost source or future integration. Do not derive any of these values from counts.

The data source, timing, ownership, and formula for ROI and cost-per-admission are `NEEDS_DECISION` (`MKT-01`, `MKT-04`). Target definitions and target-period behavior are also not defined in the reviewed records.

## Date ranges, timezone, freshness, and aggregation

- Presets and custom dates are resolved with a hard-coded `Asia/Kolkata` timezone in `MarketingDashboardService`.
- Source queries compare `created_at` against `from 00:00:00` and `to 23:59:59`.
- Monthly trends group by MySQL `DATE_FORMAT(created_at, '%Y-%m')` for Leads and Admissions only.
- No explicit timezone conversion from persisted UTC timestamps to the report boundary is present.
- The service executes live queries per request; no cache, snapshot timestamp, freshness indicator, or scheduled aggregation was found.
- The view does not show the effective date range, timezone, “as of” time, or metric version.

`OPS-09` approves MCC `Asia/Kolkata` presentation with UTC database/audit timestamps and explicit timezone handling. The current hard-coded local boundary applied directly to stored `created_at` values does not demonstrate that UTC-to-MCC boundary conversion is correct. This is a confirmed implementation risk against the approved timezone contract. The first safe remediation should use the configured application timezone and explicit database-boundary conversion, with tests for date edges; it must not invent new KPI formulas.

Refresh/freshness, caching, load behavior, and aggregation versioning remain `NEEDS_DECISION` under `DASH-03` and `NFR-01`.

## Authorization, role visibility, and branch scope

- Route access requires authentication and `marketing.view`.
- The service repeats the permission check server-side.
- A requested branch is accepted only if it is in `session('authorizedBranches')`; an unauthorized branch raises `Forbidden`.
- Every Lead, Admission, Trial, option, comparison, and trend query is restricted to the authorized branch list or the selected authorized branch.
- Navigation visibility is permission-gated and does not replace server-side authorization.
- The permission migration grants the same global `marketing.view` permission to Super Admin, Branch Admin, and Admission Team.

No role-specific metric variants, field redaction policy, or separate export permission is implemented. Whether Admission Team and branch managers should see identical metrics, whether a Super Admin's scope includes all assigned branches, and whether sensitive Lead fields may appear in marketing views must be recorded in the role and field policy. These remain `NEEDS_DECISION` under `DASH-02`, `REP-03`, and `NFR-08`.

The service trusts the current session's `authorizedBranches` snapshot and rechecks the requested branch against that snapshot. The reviewed Marketing code does not independently reload current branch assignments. Session refresh behavior and stale assignment handling should follow the project's established authorization/session policy; no Marketing-specific evidence was found.

## UI findings

### Present and source-backed

- The filter form preserves selected filters and has a custom-range toggle.
- Operational totals are shown as shared metric cards.
- Unsupported metrics are visibly labeled `Not available` instead of fabricated zeros.
- Branch and analysis tables use shared cards, table wrappers, captions, and empty states.
- Responsive CSS exists in `public/assets/css/app.css` for two-column/single-column KPI grids and stacked mobile filter actions.

### Missing or potentially misleading

- No chart is rendered; monthly trends are a table only. The SRS review mentions trends/charts, but chart requirements and presentation are not approved separately.
- The dashboard does not display formula/version/scope/freshness metadata required by the working calculation rule in `docs/REQUIREMENTS.md`.
- “Converted admissions” is a status count, not a conversion-rate calculation, and the UI does not explain that distinction.
- The view exposes no campaign filter or campaign attribution table because no approved/current source exists.
- Inactive Lead Source configuration rows may appear in the source selector because the service does not apply `is_active`.
- There is no visible empty-state explanation for unsupported business metrics beyond `Not available`; this is acceptable as a safe placeholder but not a complete approved marketing contract.

## Clear defects versus NEEDS_DECISION

### Confirmed defects or implementation risks

1. Marketing date boundaries are hard-coded to `Asia/Kolkata` and are applied directly to stored timestamp strings without explicit UTC boundary conversion, despite `OPS-09`'s UTC persistence contract.
2. The source selector reads inactive `lead_sources` as well as active ones. The desired historical-reporting behavior needs confirmation before changing the query.
3. The legacy Trial `status` filter is normalized and applied by the service but has no corresponding visible control, which makes the effective filter contract unclear.
4. The dashboard has no freshness/as-of indicator, formula/version metadata, or deployment/load evidence. This is incomplete operational evidence; the required freshness contract remains decision-gated.

### NEEDS_DECISION

- `MKT-01`: ROI source, spend categories, timing, formula, missing-data behavior, and ownership.
- `MKT-02`: first-touch, last-touch, multi-touch, or other attribution model and source-of-truth history.
- `MKT-03`: campaign ownership, branch/global scope, lifecycle, active dates, and permission to manage or report campaigns.
- `MKT-04`: cost-per-admission source, cost timing, denominator, treatment of cancelled/duplicate admissions, and reporting period.
- `DASH-01`: approved metric catalogue, formulas, conversion definition, targets, source fields, denominator, versions, rounding, and unavailable-data behavior.
- `DASH-02`: role-specific cards/tables, sensitive-field visibility, branch scope, and whether role variants may differ.
- `DASH-03`: refresh/freshness, caching, aggregation boundaries, timezone presentation, and performance target interpretation.
- `REQ-REPORT-001` / `REP-03` / `NFR-08`: export formats, fields, redaction, export permission, audit event, timestamps, and failure behavior. The Marketing Dashboard currently has no export route.
- Whether campaigns and attribution are in the current replacement baseline or remain deferred, since `docs/REQUIREMENTS.md` lists Campaigns as planned while `LD-04`/`LD-05` describe approved source/campaign decisions.

## Test and deployment evidence

### Existing tests

- `tests/Unit/MarketingDashboardContractTest.php` checks permission enforcement, read-only service behavior, and `Not available` placeholders.
- `tests/database/MarketingDashboardIntegrationTest.php` checks persisted branch-scoped counts, unauthorized branch rejection, missing permission rejection, source/status/search filters, date presets, custom date ranges, and invalid date order.

### Gaps

- No test covers controller/rendered view authorization or the navigation item.
- No test verifies UTC date-boundary conversion, configured timezone changes, inactive source handling, stale session branch assignments, freshness metadata, or role variants.
- No tests cover campaigns, attribution, cost, ROI, targets, charts, exports, or formula/version metadata.
- No production deployment, load, cache, source-data freshness, or marketing integration evidence is present in the repository.

## Smallest safe next implementation task

The smallest implementation task that is clearly supported by an existing approved decision is to correct Marketing date filtering for `OPS-09`: read the configured application timezone, convert the selected MCC-local start/end boundaries to the database timestamp convention, and add focused boundary tests without adding metrics or changing branch/permission behavior.

Before implementing campaign, attribution, conversion-rate, cost, ROI, target, freshness, or role-variant work, obtain a focused approver decision covering `MKT-01`–`MKT-04` and `DASH-01`–`DASH-03`. The current source must continue to show unsupported values as `Not available` until those decisions and source data are recorded.

