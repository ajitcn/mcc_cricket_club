# Reports and Dashboards Read-Only Audit

**Review date:** 2026-09-25  
**Scope:** Current source tree, tests, and working requirements/decision records.  
**Change status:** Read-only review; no application code, tests, database records, or decision records were changed.  
**Authority note:** `docs/SRS-original.md` is not present. `docs/SRS.md` is a working specification and is not treated as client approval. Existing status tables are treated as historical when they conflict with current source evidence.

## Executive summary

The repository currently has three related read/aggregation surfaces:

1. The authenticated landing page at `/`, implemented by `DashboardService`, `DashboardModel`, and `app/Views/dashboard.php`.
2. A Reports catalog and source-backed report pages at `/reports`, `/reports/{type}`, and CSV routes at `/reports/{type}/csv`.
3. A source-backed Marketing Dashboard at `/marketing`, implemented by `MarketingDashboardService`.

The source evidence shows more implementation than the historical rows in `docs/MODULE-STATUS.md` suggest. The current Reports code supports five tabular report types and CSV output, but the report definition, approved columns, filter/date semantics, authorization policy, and export controls remain unresolved under `REP-01`–`REP-03`, `REQ-REPORT-001`, and `NFR-08`. The landing Dashboard and Marketing Dashboard provide permission- and branch-scoped summaries, but the approved operational KPI catalogue, role variants, freshness, caching, denominator/formula policy, and performance evidence remain unresolved under `DASH-01`–`DASH-03` and `REQ-DASH-001`.

No PDF, Excel/XLSX, charting, export audit, or deployment evidence was found.

## Source and navigation map

| Surface | Routes and entry points | Main implementation files | Current classification |
|---|---|---|---|
| Landing Dashboard | `/`; navigation item is the authenticated Dashboard link | `app/Controllers/Home.php`, `app/Services/DashboardService.php`, `app/Models/DashboardModel.php`, `app/Views/dashboard.php`, `app/Services/NavigationService.php` | **Implemented with limitations** for landing summaries; the separately defined operational dashboard remains decision-gated |
| Reports | `/reports`, `/reports/(:segment)`, `/reports/(:segment)/csv` | `app/Modules/Reports/Config/Routes.php`, `app/Modules/Reports/Controllers/Browser.php`, `app/Services/ReportService.php`, `app/Modules/Reports/Views/index.php`, `app/Modules/Reports/Views/show.php` | **Partially implemented / needs requirements verification** |
| Marketing Dashboard | `/marketing` | `app/Modules/Marketing/Config/Routes.php`, `app/Modules/Marketing/Controllers/Browser.php`, `app/Services/MarketingDashboardService.php`, `app/Modules/Marketing/Views/index.php` | **Implemented with limitations** |
| Shared presentation | Staff header/footer, tables, cards, buttons, dashboard and marketing rules | `app/Views/staff_header.php`, `app/Views/staff_footer.php`, `public/assets/css/app.css`, `public/assets/css/components.css`, `public/assets/css/utilities.css`, `public/assets/js/app.js` | Shared styles exist; Reports uses a much thinner UI than Dashboard/Marketing |

## Reports

### Implemented report types and data

`ReportService::CATALOG` exposes:

- `players`: player ID, person full name, status, branch ID, created timestamp.
- `admissions`: admission ID, applicant name, status, branch ID, created timestamp.
- `attendance`: attendance ID, player ID, status, recorded timestamp, joined to `sessions` for branch lineage.
- `sessions`: session ID, status, branch ID, start and end timestamps.
- `matches`: match ID, opponent, status, result, branch ID, start timestamp.

The report page renders a generic table from returned row keys. It has `from`, `to`, and `status` controls. The service also accepts `branch_id` and `player_id` filters; those controls are not exposed in the Reports view. The service orders rows by the report-specific date column descending.

### Branch scope and authorization

- Routes use `auth` and `permission:report.view` filters.
- `ReportService::authorize()` repeats the `report.view` check server-side.
- `ReportService::branches()` reads `session('authorizedBranches')` and rejects a requested branch outside that set with `Forbidden`.
- Every report query applies `whereIn()` to an appropriate branch column. Attendance uses the joined session branch (`s.branch_id`) while other reports use their owning table alias.
- The report service does not expose document tables and the existing contract test explicitly checks that.

This establishes a branch filter boundary, but the final role policy and export field policy remain `NEEDS_DECISION` under `REP-03` and `NFR-08`. The service-level permission check is global and branch filtering is a separate session-scope check; the final branch-aware permission contract has not been approved.

### Export behavior and gaps

- The only export is a CSV response generated in `Browser::csv()` with the current report rows.
- No PDF or Excel/XLSX route, renderer, library integration, or test was found.
- The CSV link in `app/Modules/Reports/Views/show.php` builds a fresh URL from the report title and does not preserve the current GET filters. A filtered screen therefore downloads an unfiltered CSV unless the user manually adds query parameters.
- The CSV response does not call the centralized audit persistence service, does not include a generated timestamp in the filename or metadata, and uses the same `report.view` permission rather than a separately approved export policy.
- The exported columns include names and applicant identity fields. The permitted fields by role, sensitivity policy, and redaction rules are not recorded.
- No export failure, authorization, timestamp, filtering, or field-restriction coverage was found.

These are confirmed implementation gaps against the working NFR/requirements record, but the required behavior cannot be completed safely until `REP-01`–`REP-03` and `NFR-08` are approved.

### Report input and UI gaps

- The Reports index is a minimal list and does not explain available columns, filters, branch scope, or export behavior.
- The report detail form uses a free-text status input rather than source-backed status options.
- No branch selector or player selector is rendered even though the service accepts those filters.
- Date values are passed to the query without the explicit date validation and range-order checks used by the Marketing Dashboard. The report service does not reject malformed dates or a `from` date after `to`.
- The table is not wrapped in the shared `mcc-table-wrap` responsive container and has no dedicated empty-state message; an empty result displays a synthetic `No data` header.
- The controller and routes are compressed into single-line legacy formatting, which conflicts with `AGENTS.md`/`docs/CODING-STANDARD.md` for future touched sections. This is a maintainability issue, not a reason to reformat unrelated code during a requirements review.

## Landing Dashboard

### Implemented cards, sections, and sources

`DashboardService::data()` builds permission-gated source summaries for the current `authorizedBranches` session scope:

- Player cards and status summary from `players`.
- Lead totals, active trials, and follow-up counts from `leads`, `trials`, and `lead_follow_ups`.
- Open admissions and admission status attention counts from `admissions`.
- Upcoming sessions from `sessions`, batches, branches, and coaches.
- Attendance record counts and status counts from `attendance`.
- Match and tournament cards/activity from `matches` and `tournaments`.
- Development plan card from `development_plans`.
- Enabled staff count from `users` and `user_branches` for `rbac.manage` users.
- Permission-gated quick actions, recent activity, upcoming activity, empty states, and responsive dashboard sections in `app/Views/dashboard.php`.

`DashboardModel` uses allowlisted table names for generic counts and applies `whereIn('branch_id', $branches)` or joined branch filters for all listed sources. This is a read/aggregation layer and does not write source data.

### Visibility and branch behavior

Each card or section is conditionally added using an existing permission such as `player.view`, `lead.view`, `admission.view`, `session.update`, `attendance.view`, `match.view`, `tournament.view`, `development.view`, or `rbac.manage`. Queries are restricted to the session's authorized branch IDs.

The landing page itself is handled by `Home::index()`, which checks login state before rendering; it does not use a route-level `auth` filter. The service repeats permission checks for each section. No separate dashboard permission exists.

### Confirmed inconsistencies and limitations

- `DashboardService` initializes its clock with a hard-coded `Asia/Kolkata` timezone instead of reading the configured application timezone. `DashboardModel::upcomingRows()` uses PHP's default `date()` timezone. Report/dashboard timezone semantics are still open under `OPS-09`/`DASH-03`, but this is an implementation inconsistency to resolve after the reporting decision is recorded.
- `DashboardModel::attendanceSummary()` exposes an `excused` bucket, and the view renders `excused`. Current attendance status direction uses `LEAVE`, `MEDICAL`, and `COACH_CANCELLED`; the dashboard does not display those current status buckets. This is a stale source/status mapping in the existing landing summary. It must not be turned into a new attendance KPI without the approved denominator/formula policy.
- The dashboard uses `session.update` to expose the upcoming-session card, while `session.view` exists as a separate permission in the migration history. Whether viewing dashboard session summaries should require `session.view` is an RBAC/role-policy question; the current source does not document that choice.
- The dashboard displays numeric counts and labels but no formula/version/scope metadata. The working requirements explicitly state that unresolved dashboard KPIs must remain unavailable or decision-gated.
- No freshness, cache, concurrent-load, or production performance evidence was found.

## Marketing Dashboard

### Implemented screens, filters, summaries, and tables

`MarketingDashboardService::dashboard()` exposes:

- Branch selector limited to `authorizedBranches`.
- Date presets: all time, today, current week, current month, previous month, and custom range.
- Custom start/end date inputs using the existing date-picker attributes.
- Lead source, lead status, admission status, and free-text search filters.
- KPI counts for leads, admissions, trials, and converted admissions.
- Lead-source performance, lead-status summary, admission-status counts, trial-status summary, branch comparison, and created-month trends.
- Explicit `Not available` values for campaign attribution, ROI, cost per admission, and conversion rate.

The view uses shared `mcc-card`, `metric-card`, `mcc-table`, `mcc-table-wrap`, `mcc-button`, empty-state, and responsive styles. No chart component or chart library is used; the trends are rendered as tables.

### Authorization, branch isolation, and date behavior

- `/marketing` is protected by `auth` and `permission:marketing.view`.
- The service repeats the permission check.
- Requested branch IDs are accepted only when present in the current authorized branch session list.
- Every source query applies the selected authorized branch set.
- Date presets and custom ranges use `Asia/Kolkata`; custom dates are validated and ordered.
- The service reads persisted Lead, Admission, Trial, Lead Source, and Lead Status values. It does not write data and deliberately leaves unsupported metrics unavailable.

The source and branch controls are the strongest verified part of this surface. The approved definitions for conversion rate, attribution, targets, ROI, cost per admission, reporting-period ownership, and role visibility remain unresolved under `MKT-01` and the broader report/dashboard decision groups.

## KPI, denominator, and formula status

| Area | Current calculation | Decision status |
|---|---|---|
| Landing player/lead/admission/trial/session/match/tournament counts | Direct counts/status summaries over authorized branch rows | Source-backed implementation exists; the approved Dashboard KPI catalogue and role variants remain `DASH-01`/`DASH-02 NEEDS_DECISION` |
| Landing attendance | Recorded count plus present/absent/late/excused buckets | Stale `EXCUSED` mapping conflicts with current status direction; denominator/percentage behavior remains controlled by Attendance decisions and is not implemented here |
| Reports | Row listings and optional status/date/player/branch filters | `REP-01` columns, `REP-02` filter/date semantics, and `REP-03` authorization remain `NEEDS_DECISION` |
| Marketing totals | Counts of Leads, Admissions, Trials, and converted Admissions | Source-backed, but conversion definition, attribution, targets, ROI, and cost formulas remain unavailable/`NEEDS_DECISION` |
| Charts/trends | Marketing created-month trend table only; no chart | No approved chart/data presentation contract found |
| Attendance percentages/alerts | Not calculated by Reports/Dashboard | Denominators, thresholds, recipients, and versioning remain decision-controlled (`AT-01`, `AT-06`) |

No report or dashboard code should invent percentages, rates, denominators, rankings, targets, or performance formulas from the current counts.

## Tests and evidence

### Existing focused coverage

- `tests/Unit/ReportContractTest.php`: report permission, catalog match coverage, and document exclusion contract.
- `tests/Unit/ReportBranchScopeContractTest.php`: requested authorized branch filter and server-side rejection contract.
- `tests/database/ReportBranchScopeIntegrationTest.php`: authorized branch filtering and cross-branch rejection.
- `tests/Unit/DashboardModuleContractTest.php`: source-backed Dashboard model/service and permission checks for matches, tournaments, and development plans.
- `tests/database/DashboardIntegrationTest.php`: landing page rendering and authentication behavior.
- `tests/Unit/MarketingDashboardContractTest.php`: permission/read-only/unavailable-metric contract.
- `tests/database/MarketingDashboardIntegrationTest.php`: branch filtering, permission denial, persisted filters, date presets, and custom date validation.

### Coverage gaps

- No test covers the Reports catalog/detail browser responses or CSV response content.
- No test verifies that CSV exports preserve filters, record an audit event, include timestamps, or enforce field restrictions.
- No PDF or Excel export tests exist.
- No tests cover malformed report dates, reversed report ranges, free-text status behavior, empty report rendering, or player/branch filter controls.
- Dashboard integration setup calls `emptyTable()` on shared tables. It is not a safe persistent-fixture pattern and should not be used as evidence for production data safety.
- No tests verify dashboard role variants, dashboard freshness/caching, configured timezone behavior, stale attendance status labels, concurrent load, or deployment configuration.
- No browser visual verification or deployment-owner evidence was found in the repository.

## Historical/document conflicts

- `docs/MODULE-STATUS.md` contains historical rows marking Reports/exports and Operational dashboard as `BLOCKED` with “No report/export code found” and “No dashboard code found.” Current source contains both implementations, so those rows are historical and stale for code inventory purposes; they still correctly identify unresolved approval gates.
- `docs/REQUIREMENTS-TRACEABILITY.md` classifies Reports, Dashboard, and Marketing Dashboard as implemented with limitations, which matches the current source more closely.
- `docs/REQUIREMENTS.md` and `docs/DECISIONS.md` retain `REQ-REPORT-001`, `REQ-DASH-001`, `REP-01`–`REP-03`, and `DASH-01`–`DASH-03` as unresolved. No client approval was inferred from implementation or migrations.
- The original client SRS remains unavailable. No replacement baseline approval was found in the reviewed records.

## Defects versus unresolved decisions

### Confirmed implementation defects or inconsistencies

1. Filtered Report pages do not preserve their current filters in the CSV link.
2. Report date input is not server-validated for format or range order.
3. Report tables lack the shared responsive wrapper and a clear empty state.
4. The landing attendance summary still labels the old `EXCUSED` bucket instead of current `LEAVE`/`MEDICAL` source statuses.
5. Dashboard time handling is split between a hard-coded timezone and PHP default timezone.
6. The Reports surface has no PDF or Excel behavior despite the working requirement listing those formats.
7. Report CSV export has no visible centralized audit/timestamp/field-policy implementation.

Items 1–5 can be corrected only after confirming the applicable report/dashboard contract where the correction changes behavior. Items 6–7 are blocked by the unresolved export policy and approval requirements.

### NEEDS_DECISION items

- `REP-01`: exact report catalog, columns, joins, sensitive fields, and redaction policy.
- `REP-02`: filters, date range semantics, timezone, status vocabulary, empty-data behavior, and whether branch/player selectors are required.
- `REP-03`: role/permission matrix, branch scope, export authority, and denial behavior.
- `DASH-01`: approved dashboard KPI catalogue, source fields, formulas, denominator rules, versions, and missing-data behavior.
- `DASH-02`: role-specific dashboard variants and branch visibility.
- `DASH-03`: refresh/freshness, caching, timezone, performance, and load expectations.
- `NFR-08`/`SEC-11`: export authorization, audit event contents, timestamp, filtering, field restriction, PDF/Excel/CSV requirements, and failure handling.
- Marketing definitions: attribution, conversion, targets, ROI, cost per admission, and reporting-period ownership (`MKT-01` and related unresolved records).
- Whether the existing landing Dashboard summaries are considered a temporary navigation overview or the approved operational Dashboard product.

## Highest-priority safe next task

No new Reports or Dashboard coding task is clearly authorized until the client/authorized approver records the missing replacement-baseline decisions. The smallest safe next step is a focused approval request for `REP-01`–`REP-03`, `DASH-01`–`DASH-03`, and `NFR-08`, with a separate conflict note for the historical `MODULE-STATUS.md` rows.

After those decisions are recorded, the first implementation slice should be the approved read-only report contract and branch/role matrix, followed by focused tests for filtered CSV behavior, export audit/timestamp/field policy, and dashboard KPI source/formula/version metadata. Do not implement PDF, Excel, new KPIs, rates, or denominator calculations from the current code alone.
