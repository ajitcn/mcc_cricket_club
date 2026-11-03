# Director Dashboard Decision Request

Status: `NEEDS_DECISION` for every item. This document prepares decisions before any dashboard formula, query, migration, permission, or UI change.

## Evidence boundary

The current staff dashboard is served through `DashboardService::data()`, `DashboardModel`, and `app/Views/dashboard.php`. It already exposes permission-gated cards for active Players, Leads, Trials, Admissions, Sessions, Attendance, Matches, Tournaments, Development, and enabled staff. It uses the configured application timezone for the current date/month and intersects reads with the authenticated user's `authorizedBranches` session values. Existing Reports, Coach KPI, Scheduling, Attendance, Players, Programs, Batches, and Branch modules remain separate sources. The client reference image describes a richer Director view; it does not itself approve formulas or visibility rules.

## Decision record template

Each item below requires all of the following before implementation: selected option, resolution, comments, approver identity, approval date, and effective date. Blank fields are not approval.

### DASH-01 — Branch selector behavior

- **Existing evidence:** Shared branch context and `authorizedBranches` are used by `DashboardService`; the dashboard currently reads all session-authorized branches without a dashboard selector.
- **Recommended secure default:** A CSRF-protected selector may choose one active branch from the user's current authorized assignments; the server resolves and rechecks the branch on every request.
- **Choices:** (A) Single selected branch only; (B) Selected branch with an explicit “All authorized branches” option; (C) Fixed branch from session context; (D) No selector.
- **Permissions:** Existing dashboard/read permissions; no new permission by default.
- **Branch rules:** Never trust a posted or URL branch ID; intersect with current assignments and active-branch policy.
- **Database/migration:** None expected; selector state may remain session or query state.
- **Audit:** Audit branch-context changes only if existing branch-context audit policy requires it; never audit sensitive KPI values.
- **Acceptance:** Authorized selection works; forged/cross-branch values are denied; permission-gated cards remain filtered.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-02 — All-branches visibility

- **Existing evidence:** Dashboard aggregates `authorizedBranches`; all-branch Director policy is not established.
- **Recommended secure default:** No implicit all-branch view. Require an explicit approved role/permission and an explicit selector choice.
- **Choices:** (A) No all-branch view; (B) All currently authorized active branches; (C) All organization branches for Super Admin; (D) Separate Director permission.
- **Permissions:** Existing dashboard permissions plus an explicitly approved scope if needed.
- **Branch rules:** Only authorized branches; inactive branches excluded unless approved for historical reporting.
- **Database/migration:** None expected unless scope permissions are added.
- **Audit:** Record scope selection and actor if an all-branch view is approved.
- **Acceptance:** Cross-branch leakage tests; explicit scope authorization; stable totals.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-03 — Date/month filter semantics

- **Existing evidence:** Current service uses the current month start and current local day; no user-selectable dashboard period exists.
- **Recommended secure default:** Separate local calendar date/month filters with inclusive start and exclusive end boundaries.
- **Choices:** (A) Current day; (B) Calendar month; (C) Custom date range; (D) Day/month toggle.
- **Permissions:** Existing module read permissions.
- **Branch rules:** Filters apply only after branch authorization.
- **Database/migration:** None expected; indexes may be required after approved query design.
- **Audit:** No audit for ordinary reads; audit exports separately if approved.
- **Acceptance:** Date boundary tests in configured timezone; invalid ranges rejected; no UTC suffixes in UI.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-04 — Current-day and timezone boundaries

- **Existing evidence:** Dashboard uses `Asia/Kolkata` directly while other modules use `config('App')->appTimezone` helpers.
- **Recommended secure default:** Use the configured application timezone consistently; convert UTC timestamps at the service boundary.
- **Choices:** (A) Configured application timezone; (B) Branch timezone; (C) UTC-only; (D) User timezone.
- **Permissions:** Existing dashboard permissions.
- **Branch rules:** Branch-local policy requires explicit approval; do not infer it.
- **Database/migration:** None expected.
- **Audit:** Store audit timestamps using existing UTC convention.
- **Acceptance:** Midnight and daylight/date boundary tests; consistent formatting with Reports and Scheduling.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-05 — Total Player formula

- **Existing evidence:** Current card counts active `players` rows in authorized branches; status summary includes inactive, suspended, left academy, and alumni.
- **Recommended secure default:** “Total Players” means active Players only, counted once by Player ID in scope.
- **Choices:** (A) Active only; (B) All non-deleted; (C) Active plus suspended; (D) Current-period enrolled.
- **Permissions:** `player.view` and existing dashboard visibility.
- **Branch rules:** Player branch ownership and authorized branch scope required.
- **Database/migration:** None expected.
- **Audit:** None for read; formula version documented.
- **Acceptance:** Duplicate assignment does not double-count; inactive/cross-branch exclusion; empty state.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-06 — Total Coach formula

- **Existing evidence:** Current dashboard has no Coach total card; Coach records use active profiles, enabled users, and branch assignments.
- **Recommended secure default:** Count distinct active Coaches with an active authorized branch assignment.
- **Choices:** (A) Active assigned Coaches; (B) Enabled Coach users; (C) Coaches with upcoming sessions; (D) All historical Coaches.
- **Permissions:** `coach.view` and dashboard scope.
- **Branch rules:** Distinct Coach identity across selected branches; no unauthorized branch leakage.
- **Database/migration:** None expected.
- **Audit:** None for reads.
- **Acceptance:** Multi-branch Coach counted deterministically; inactive/unassigned excluded.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-07 — Group, Personal, and Elite coaching definitions

- **Existing evidence:** Programs, Batches, and Sessions exist, but no approved dashboard taxonomy for coaching types is documented.
- **Recommended secure default:** Do not display these categories until a source field and taxonomy are approved.
- **Choices:** (A) Program-defined type; (B) Batch-defined type; (C) Session participant count; (D) Defer category metrics.
- **Permissions:** Existing module read permissions.
- **Branch rules:** Source records must match branch scope.
- **Database/migration:** New classification field would require approval and migration.
- **Audit:** Audit classification changes if introduced.
- **Acceptance:** No fabricated categories; approved mapping tests only.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-08 — Branch-wise counts

- **Existing evidence:** Existing model can count branch rows; no Director branch comparison contract exists.
- **Recommended secure default:** Show only branches explicitly authorized to the viewer and only approved metrics.
- **Choices:** (A) Player/Coach counts; (B) All operational KPIs; (C) Sessions and attendance only; (D) Defer comparison.
- **Permissions:** Existing per-module view permissions.
- **Branch rules:** Every metric independently enforces branch scope.
- **Database/migration:** None expected unless reporting indexes are needed.
- **Audit:** Export/read audit only if approved.
- **Acceptance:** Branch isolation for every metric; no partial unauthorized totals.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-09 — Batch-wise distribution

- **Existing evidence:** Batches and Player batch assignments exist; no dashboard distribution formula is approved.
- **Recommended secure default:** Count active Players by current effective batch assignment, excluding duplicate historical assignments.
- **Choices:** (A) Active Players by current batch; (B) Sessions by batch; (C) Batch capacity/utilization; (D) Defer.
- **Permissions:** `player.view`, `batch.view` as applicable.
- **Branch rules:** Batch and Player branches must match selected scope.
- **Database/migration:** None expected.
- **Audit:** None for reads.
- **Acceptance:** Effective-date and duplicate-assignment tests; inactive/cross-branch exclusion.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-10 — Age-group categories

- **Existing evidence:** Player date of birth and age-group fields may exist; no dashboard category cutoffs are approved.
- **Recommended secure default:** Use an existing persisted age-group only; do not derive categories until cutoffs and as-of date are approved.
- **Choices:** (A) Persisted age group; (B) Age calculated as of filter date; (C) Cricket age bands; (D) Defer.
- **Permissions:** `player.view`.
- **Branch rules:** Authorized Player scope only.
- **Database/migration:** None for persisted field; derived policy may need documentation only.
- **Audit:** Formula/version documented if derived.
- **Acceptance:** Boundary birthday tests; missing DOB safe empty state; no sensitive DOB display.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-11 — Coach workload calculation

- **Existing evidence:** Sessions include Coach, dates, and roster; Coach KPI service has source-backed session and attendance summaries, but no Director workload formula.
- **Recommended secure default:** Defer workload until numerator, denominator, date window, cancellations, and group-size weighting are approved.
- **Choices:** (A) Session count; (B) Player-session count; (C) Scheduled hours; (D) Weighted composite; (E) Defer.
- **Permissions:** `coach.kpi.view` or explicit dashboard permission.
- **Branch rules:** Coach and session branch must match scope.
- **Database/migration:** None expected until formula is selected.
- **Audit:** Formula version and source date range documented.
- **Acceptance:** Cancellation/leave handling, duplicate session, and multi-branch tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-12 — Coach availability and leave handling

- **Existing evidence:** Coach availability and approved leave are used by scheduling; no dashboard display policy exists.
- **Recommended secure default:** Display only approved availability/leave summaries to authorized staff; never infer availability from missing rows.
- **Choices:** (A) Upcoming leave only; (B) Availability coverage; (C) Both; (D) Do not display.
- **Permissions:** `coach.view`/`coach.update` according to record sensitivity.
- **Branch rules:** Current authorized Coach branch scope.
- **Database/migration:** None expected.
- **Audit:** Reads need no audit; changes retain existing audit.
- **Acceptance:** Approved/pending leave and branch boundary tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-13 — Upcoming session definition

- **Existing evidence:** `DashboardModel::upcomingSessions()` returns scheduled sessions; current dashboard shows upcoming sessions under `session.update`.
- **Recommended secure default:** Scheduled sessions with start time after the current local time, excluding cancelled sessions.
- **Choices:** (A) Future scheduled; (B) Today and future; (C) Next seven days; (D) Month window.
- **Permissions:** `session.update` or a separately approved read permission.
- **Branch rules:** Session branch authorization.
- **Database/migration:** None expected.
- **Audit:** None for reads.
- **Acceptance:** Current-time boundary and cancelled/completed exclusion tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-14 — Attendance summary and denominator

- **Existing evidence:** Current dashboard counts attendance records and statuses; existing Attendance direction defines source statuses, but dashboard denominator policy is not finalized here.
- **Recommended secure default:** Show source-backed counts only; do not calculate a percentage until denominator and cancellation/leave/medical treatment are approved.
- **Choices:** (A) Recorded status counts; (B) Eligible roster denominator; (C) Occurred sessions denominator; (D) Both counts and percentage.
- **Permissions:** `attendance.view`.
- **Branch rules:** Session, snapshot, and attendance branch must match scope.
- **Database/migration:** None expected.
- **Audit:** Formula version if percentage is approved.
- **Acceptance:** Seven status, missing record, cancelled session, and zero-denominator tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-15 — Performance/KPI inclusion

- **Existing evidence:** Performance and Coach KPI services exist with permission and branch checks; dashboard currently does not include their detailed KPIs.
- **Recommended secure default:** Include only approved source-backed KPIs with labels, source, formula version, and `Not available` for insufficient data.
- **Choices:** (A) Coach KPIs; (B) Player performance summaries; (C) Both; (D) Defer.
- **Permissions:** Existing `coach.kpi.view`, `performance.view`, or explicitly approved permission.
- **Branch rules:** Source Match/Player/Coach branches must match scope.
- **Database/migration:** None expected unless materialized reporting is approved.
- **Audit:** Formula/version and correction propagation documented.
- **Acceptance:** No fabricated values; correction and zero-data tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-16 — Empty-state behavior

- **Existing evidence:** Current dashboard has empty states for cards, attention, and lists.
- **Recommended secure default:** Show a clear per-section empty state without revealing unauthorized records or treating no permission as zero data.
- **Choices:** (A) Section-specific empty text; (B) Hide unavailable sections; (C) Combined empty dashboard; (D) Permission-specific message.
- **Permissions:** Existing permission filtering.
- **Branch rules:** Do not disclose inaccessible branch existence.
- **Database/migration:** None.
- **Audit:** None.
- **Acceptance:** No-data, no-permission, and cross-branch cases are distinct and safe.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-17 — Data freshness and caching

- **Existing evidence:** Current dashboard reads live model queries on request; no cache policy is documented.
- **Recommended secure default:** Live reads initially; if caching is approved, scope cache keys by user permissions, branch, and filter period with short bounded TTL.
- **Choices:** (A) Live only; (B) Short TTL cache; (C) Scheduled snapshot; (D) Hybrid.
- **Permissions:** Cache must never bypass current authorization.
- **Branch rules:** Cache key must include authorized scope and selected branch.
- **Database/migration:** Cache infrastructure only if approved.
- **Audit:** Record refresh/export actions if applicable.
- **Acceptance:** Permission change invalidation and stale-data disclosure tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-18 — Role visibility and branch authorization

- **Existing evidence:** Current cards are independently gated by centralized permissions; `NavigationService` and `AuthorizationService` are separate from route enforcement.
- **Recommended secure default:** Keep per-metric permission checks and current authorized branch scope; Director-specific visibility requires explicit role/permission approval.
- **Choices:** (A) Existing permission-gated cards; (B) Director-only dashboard; (C) Branch Admin plus Director; (D) New dashboard permission.
- **Permissions:** Reuse existing names unless a new permission is approved.
- **Branch rules:** Server-side checks on every query and route.
- **Database/migration:** Permission migration only if selected.
- **Audit:** Audit permission changes, not ordinary reads.
- **Acceptance:** Role matrix, route, branch, and portal/staff separation tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-19 — Export and report behavior

- **Existing evidence:** Reports and Achievement exports exist separately; dashboard has no export action.
- **Recommended secure default:** No dashboard export until fields, permission, branch scope, audit, and sensitive-data policy are approved.
- **Choices:** (A) No export; (B) CSV; (C) Existing Reports handoff; (D) PDF/CSV.
- **Permissions:** Existing `report.view` is not automatically sufficient for new dashboard exports.
- **Branch rules:** Export filters must equal visible authorized scope.
- **Database/migration:** None expected; audit table reuse likely.
- **Audit:** Export actor, scope, filters, timestamp, and row count without sensitive values.
- **Acceptance:** Export authorization, filter parity, and sensitive-field exclusion tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### DASH-20 — Sensitive data shown on dashboard

- **Existing evidence:** Existing dashboard uses aggregate counts and limited names in operational lists; Admissions and Documents contain sensitive personal/document data.
- **Recommended secure default:** Aggregates only; no phone, email, medical, guardian, document filename/path, or private notes.
- **Choices:** (A) Aggregates only; (B) Names only; (C) Names plus contact; (D) Role-specific detail.
- **Permissions:** Existing source permission plus explicit sensitive-data permission for any exception.
- **Branch rules:** Apply source record authorization and branch scope.
- **Database/migration:** None expected.
- **Audit:** Access to sensitive dashboard detail must be audited if approved.
- **Acceptance:** Sensitive-field exclusion, HTML escaping, and branch-boundary tests.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

## Approval status

All DASH-01 through DASH-20 items are `NEEDS_DECISION`. Recommended secure defaults are guidance only and are not approval.

## Recorded user direction

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-11).** The project owner supplied secure defaults for DASH-01 through DASH-20. These defaults do not complete approval fields and do not authorize implementation.

- Access is branch-scoped. An explicit **All Branches** option is available only to Super Admin and only across currently authorized active branches.
- Date and month filters use MCC-local calendar inputs. Stored timestamps are converted to UTC using existing application conventions.
- Counts and distributions use active, authorized records only. Exact formulas for Players, Coaches, coaching types, batches, age groups, workload, attendance, and upcoming sessions must be written and tested before implementation; no unstated numeric formula is inferred here.
- No sensitive medical, document, guardian, or private contact data appears on the dashboard.
- No new KPI or performance formula is invented outside already approved, source-backed metrics.
- Each unavailable or empty section shows a clear safe empty state without disclosing unauthorized records.
- No caching is introduced until freshness and invalidation rules are explicitly defined.
- Existing centralized permissions and branch authorization are reused; no permission grant is implied.
- Any export must use the same filters and authorization as the dashboard.
- Only sensitive administrative actions are audited; private data is never written to audit metadata.

The recorded direction applies to DASH-01 through DASH-20 as the secure-default baseline. **Resolution, comments, approver, approval date, and effective date remain blank.**

## Numeric formula decision request

The following formula items are the remaining numeric decisions. Each remains `NEEDS_DECISION`; the secure defaults above do not select an option. For every item, complete: **Selected option, resolution, comments, approver, approval date, effective date**.

### FORM-01 — Total active Players
- **Formula choices:** (A) Numerator = distinct active `players.id`; denominator = 1 (a count); (B) active Players with an effective batch assignment; (C) active Players with a session in range.
- **Status/scope:** Define excluded statuses; authorized selected branches; local as-of date for effective timestamps.
- **Duplicates/missing/relationships:** Count each Player once; define missing assignment; require branch ownership and `player.view`.
- **Freshness/example/tests/migration:** Choose live or TTL; 42 distinct eligible Players = 42; test duplicate/inactive/cross-branch/empty cases; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-02 — Total active Coaches
- **Formula choices:** (A) distinct active Coach profiles with active branch assignments; (B) enabled Coach users; (C) Coaches with sessions in range.
- **Status/scope:** Define profile and user requirements; authorized branches and local effective date.
- **Duplicates/missing/relationships:** Multi-branch handling and unassigned behavior; require Coach profile, role, assignment, and `coach.view`.
- **Freshness/example/tests/migration:** Choose live or TTL; 6 distinct assigned Coaches = 6; test multi-branch/inactive/unassigned cases; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-03 — Group coaching count
- **Formula choices:** (A) sessions with more than one roster Player; (B) explicit group type; (C) active group Programs/Batches.
- **Status/scope:** Choose included statuses and configured local date range per authorized branch.
- **Duplicates/missing/relationships:** Distinct session IDs; missing roster/type is excluded or unavailable; require session branch and session/attendance permission.
- **Freshness/example/tests/migration:** Live preferred; 12 qualifying sessions = 12; test statuses, duplicates, missing roster, branches; taxonomy migration only if selected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-04 — Personal coaching count
- **Formula choices:** (A) sessions with exactly one roster Player; (B) explicit individual type; (C) development records.
- **Status/scope:** Choose statuses; authorized branch and local date range.
- **Duplicates/missing/relationships:** Distinct sessions; missing type/roster is not inferred; require session roster and existing permissions.
- **Freshness/example/tests/migration:** Live preferred; 8 qualifying sessions = 8; test one-vs-many, status, missing data, branch; migration only if type selected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-05 — Elite coaching count
- **Formula choices:** (A) approved Program/Batch category; (B) designated cohort; (C) no metric until taxonomy exists.
- **Status/scope:** Define active category and local effective date per branch.
- **Duplicates/missing/relationships:** Distinct records; missing category is not inferred; require Program/Batch ownership and view permissions.
- **Freshness/example/tests/migration:** Live or approved snapshot; 2 active Elite batches = 2; mapping/inactive/duplicate/unknown tests; category migration may be required.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-06 — Branch-wise Player and Coach totals
- **Formula choices:** (A) distinct active identities; (B) current assignments; (C) session participants.
- **Status/scope:** Define active/effective cutoffs; authorized branches and local as-of date.
- **Duplicates/missing/relationships:** Define multi-branch identity treatment; require branch ownership and source permissions.
- **Freshness/example/tests/migration:** Live preferred; Branch A 20/4 and B 15/3; cross-branch/multi-branch tests; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-07 — Batch-wise totals
- **Formula choices:** (A) distinct active Players with current effective assignment; (B) assignment rows; (C) sessions per Batch.
- **Status/scope:** Define active requirements and local as-of assignment time within branch.
- **Duplicates/missing/relationships:** Define overlapping assignments and missing batch behavior; require Player/Batch permissions.
- **Freshness/example/tests/migration:** Live preferred; 18 distinct active U-15 Players = 18; effective-date/duplicate/inactive/branch tests; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-08 — Age-group categories and DOB rules
- **Formula choices:** (A) persisted age group; (B) birthday-aware age on local as-of date; (C) cricket-season cutoff; (D) omit missing DOB.
- **Status/scope:** Define active status and invalid/future DOB handling; DOB is date-only and branch scoped.
- **Duplicates/missing/relationships:** One Player once; missing/invalid DOB becomes unavailable or excluded; require `player.view`.
- **Freshness/example/tests/migration:** Recalculate or persist; DOB 2012-04-10 at 2026-10-11 = age 14 under birthday-aware rules; boundary/leap/missing tests; migration only if persisted.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-09 — Coach workload calculation
- **Formula choices:** (A) distinct sessions; (B) Player-session assignments; (C) scheduled duration hours; (D) weighted composite.
- **Status/scope:** Define included statuses, cancellation/leave treatment, local date range, and duration conversion.
- **Duplicates/missing/relationships:** Distinct source IDs; missing end time unavailable/excluded; require Coach-session branch and `coach.kpi.view`.
- **Freshness/example/tests/migration:** Live preferred; 10 sessions or 35 Player-session assignments depending on formula; status/duration/leave/duplicate tests; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-10 — Coach availability and leave handling
- **Formula choices:** (A) configured hours minus approved leave; (B) upcoming approved leave; (C) coverage percentage; (D) no numeric metric.
- **Status/scope:** Approved leave only unless selected; branch, Coach, and local date/time range.
- **Duplicates/missing/relationships:** Merge overlaps; missing availability is not assumed available; require Coach view permission.
- **Freshness/example/tests/migration:** Live only unless snapshot approved; 40 hours − 8 leave = 32; overlap/status/timezone/branch tests; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-11 — Upcoming session inclusion
- **Formula choices:** (A) scheduled start after current local time; (B) today plus future; (C) next seven days; (D) selected month.
- **Status/scope:** Choose scheduled/active inclusion; cancelled/postponed excluded unless selected; authorized branch and configured timezone.
- **Duplicates/missing/relationships:** Distinct session IDs; missing start excluded; require session view permission.
- **Freshness/example/tests/migration:** Live preferred; 3 qualifying future sessions = 3; boundary/status/branch tests; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-12 — Attendance totals and denominator
- **Formula choices:** (A) status counts only; (B) denominator = eligible snapshot Player-session rows; (C) recorded rows; (D) occurred non-cancelled sessions.
- **Status/scope:** Define Leave, Medical, Late, and cancellation treatment; authorized branch and local session dates.
- **Duplicates/missing/relationships:** One row per snapshot member; define missing attendance; require snapshot and `attendance.view`.
- **Freshness/example/tests/migration:** Live preferred; 15 of 18 = 83.3% only if denominator B is approved; seven-status/duplicate/zero/cancellation tests; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

### FORM-13 — Performance/KPI inclusion
- **Formula choices:** (A) approved Coach KPI summaries; (B) approved Performance metrics; (C) both; (D) defer all dashboard KPIs.
- **Status/scope:** Define approved source status and correction handling; Match/Session/Player/Coach branch and local period.
- **Duplicates/missing/relationships:** Count source IDs once; insufficient data = `Not available`; require existing KPI/performance permissions.
- **Freshness/example/tests/migration:** Live recalculation preferred; two valid comparable records produce an approved KPI, otherwise unavailable; correction/branch/zero-data tests; no migration expected.
- **Resolution:** ______  **Comments:** ______  **Approver:** ______  **Approval date:** ______  **Effective date:** ______

## Formula worksheet

Complete the numerator and denominator explicitly for the selected option. A blank value is unresolved and is not approval.

| ID | Metric | Numerator to decide | Denominator to decide | Status |
|---|---|---|---|---|
| FORM-01 | Active Players | Distinct active Player IDs meeting the selected assignment rule | `1` for a count, or approved active population | NEEDS_DECISION |
| FORM-02 | Active Coaches | Distinct active Coach IDs meeting the selected assignment rule | `1` for a count, or approved active Coach population | NEEDS_DECISION |
| FORM-03 | Group coaching | Distinct qualifying group sessions or approved group records | `1` for a count, or approved session population | NEEDS_DECISION |
| FORM-04 | Personal coaching | Distinct qualifying individual sessions or approved individual records | `1` for a count, or approved session population | NEEDS_DECISION |
| FORM-05 | Elite coaching | Distinct active Elite Programs, Batches, sessions, or Players under the selected taxonomy | `1` for a count, or approved active Elite population | NEEDS_DECISION |
| FORM-06 | Branch totals | Distinct active Players and Coaches per authorized branch | `1` per branch count, or approved branch population | NEEDS_DECISION |
| FORM-07 | Batch totals | Distinct active Players with an effective Batch assignment | `1` per Batch count, or approved Batch population | NEEDS_DECISION |
| FORM-08 | Age groups | Distinct active Players whose DOB maps to the selected category | `1` per category count, or approved DOB population | NEEDS_DECISION |
| FORM-09 | Coach workload | Sessions, Player-session assignments, scheduled hours, or weighted units | `1`, total scheduled hours, or selected workload base | NEEDS_DECISION |
| FORM-10 | Availability/leave | Configured available units after approved leave, or approved leave units | Configured availability units, scheduled hours, or `1` for display-only counts | NEEDS_DECISION |
| FORM-11 | Upcoming sessions | Distinct sessions satisfying the selected future period/status rule | `1` for a count | NEEDS_DECISION |
| FORM-12 | Attendance | Status rows or eligible snapshot Player-session rows counted by status | Eligible snapshot rows, recorded rows, or occurred sessions | NEEDS_DECISION |
| FORM-13 | Performance/KPI | Existing approved source metric result for the selected period | Approved comparison/source population, or `1` for a stored KPI | NEEDS_DECISION |

## Completed worksheet — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

The following conservative definitions complete the worksheet for review. They are not client approval. Approver, approval date, and effective date remain blank for every item.

| ID | Numerator | Denominator | Included statuses | Excluded statuses | Branch scope | Date/month scope | MCC timezone behavior | Duplicate handling | Missing-data behavior | Example calculation | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| FORM-01 | Distinct Player IDs with `players.status = active`, active owning branch, and a current effective assignment where the selected metric requires one | `1` per count; no percentage denominator | Active | Inactive, suspended, left academy, alumni, deleted | Selected active branches authorized for the viewer | As-of selected local date/month; assignment effective at local boundary | Interpret local calendar filters in `config('App')->appTimezone`; convert stored UTC timestamps before comparison | `COUNT(DISTINCT players.id)`; never count assignment history rows twice | Exclude invalid branch or missing identity; show zero only when authorized scope has no qualifying rows | 42 distinct active Players in scope = 42 | NEEDS_DECISION |
| FORM-02 | Distinct active Coach profile/user IDs with active role, enabled user, and active branch assignment | `1` per count | Active profile, enabled user, authorized assignment | Inactive profile, disabled user, leave-only/unassigned Coach | Selected authorized active branches | As-of selected local date/month for assignment validity | Local filter boundaries converted to UTC for stored assignment timestamps | Count each Coach once per aggregate; per-branch view counts once in each owning assigned branch only if approved | Missing profile/role/assignment is excluded and not inferred | 6 distinct qualifying Coaches = 6 | NEEDS_DECISION |
| FORM-03 | Distinct Session IDs with more than one eligible roster Player, treated as group sessions until a taxonomy is approved | `1` per count | Scheduled, active, completed; cancelled/postponed excluded | Cancelled, postponed, invalid or missing roster | Session owning branch must be authorized | Session start local date within selected day/month | Convert stored UTC `starts_at` to configured MCC timezone before date filtering | Count each session ID once | Missing roster or branch makes the session unavailable and excluded | 12 qualifying Session IDs = group count 12 | NEEDS_DECISION |
| FORM-04 | Distinct Session IDs with exactly one eligible roster Player, treated as personal sessions until a taxonomy is approved | `1` per count | Scheduled, active, completed; cancelled/postponed excluded | Cancelled, postponed, zero/multiple-player roster | Session owning branch must be authorized | Session start local date within selected day/month | Use configured MCC timezone for local date boundaries | Count each session ID once | Missing roster/type is excluded; no personal classification is inferred from Coach name | 8 one-Player sessions = personal count 8 | NEEDS_DECISION |
| FORM-05 | Distinct active Program/Batch IDs explicitly classified `elite` after an approved taxonomy exists | `1` per count | Active classified Elite records | Inactive, unclassified, deleted records | Program/Batch owning branch must be authorized | Active as of selected local date/month | Apply local effective dates after converting stored timestamps | Count each Program/Batch ID once | Until taxonomy exists, display `Not available` rather than infer Elite | 2 active classified Elite Batches = 2 | NEEDS_DECISION |
| FORM-06 | Per branch: distinct active Player IDs and distinct active Coach IDs satisfying FORM-01/02 | `1` per branch count | Active source records and active assignments | Same exclusions as FORM-01/02 | Only branches currently authorized; Super Admin All Branches is explicit | As-of selected local date/month | Local branch comparison date; stored timestamps converted from UTC | Distinct identity within each branch; multi-branch treatment remains visible in test evidence | Unauthorized or inactive branch rows omitted without revealing existence | Branch A 20 Players/4 Coaches; Branch B 15/3 | NEEDS_DECISION |
| FORM-07 | Distinct active Player IDs with one current effective assignment to the Batch | `1` per Batch count | Active Player, active Batch, effective assignment | Inactive Player/Batch, expired/future assignment, deleted | Player, Batch, and assignment branch must match and be authorized | Assignment effective at selected local date/month | Compare effective timestamps using configured MCC timezone converted to UTC | Count Player once per Batch; overlapping assignments excluded as invalid | Missing/overlapping assignment produces `Not available` for that row, not a fabricated count | 18 distinct active Players in U-15 Batch = 18 | NEEDS_DECISION |
| FORM-08 | Distinct active Player IDs whose valid DOB maps to the selected category using birthday-aware age | `1` per age-group category count | Active Player with valid non-future DOB | Inactive/deleted Player, missing/invalid/future DOB | Player branch must be authorized | Age calculated as of selected MCC-local date/month end | DOB is date-only; no UTC conversion for DOB, only for record timestamps | Count each Player once in one category | Missing/invalid DOB shown under `Not available` or excluded consistently; never guess | DOB 2012-04-10 on 2026-10-11 gives age 14; category count includes that Player | NEEDS_DECISION |
| FORM-09 | Distinct scheduled/active/completed session units assigned to the Coach, using session count as the safe baseline | `1` per workload count; hours/weighted denominator not used until approved | Scheduled, active, completed | Cancelled, postponed, invalid branch; approved leave does not create workload | Session and Coach branch must be authorized | Session start local date within selected day/month | Convert stored UTC session times to MCC timezone before filtering | Count session ID once; do not multiply by roster rows | Missing Coach/session association excluded and shown unavailable | Coach with 10 qualifying sessions has workload 10 | NEEDS_DECISION |
| FORM-10 | Approved leave hours (or approved leave intervals) and configured available hours for display; no percentage by default | Configured availability hours only if a percentage is later approved | Approved availability; approved leave subtracts from availability | Pending/rejected/cancelled leave; missing availability is not assumed available | Coach and availability/leave branch must be authorized | Selected local day/month and local time interval | Convert stored interval timestamps to MCC timezone before overlap checks | Merge overlapping intervals before summing | Missing availability displays `Not available`; no inferred availability | 40 configured hours − 8 approved leave = 32 available hours | NEEDS_DECISION |
| FORM-11 | Distinct Session IDs with `status = scheduled` and local start strictly after current local time | `1` per count | Scheduled | Active, completed, cancelled, postponed | Session owning branch must be authorized | Current day or selected local date/month; exact boundary is after now | Convert UTC `starts_at` to configured MCC timezone before comparison | Count session ID once | Missing start/status excluded; empty scope shows a safe empty state | 3 scheduled sessions after 10:00 local = 3 | NEEDS_DECISION |
| FORM-12 | Attendance rows grouped by approved status; present/late counts are source counts | Percentage denominator = eligible immutable snapshot Player-session rows only if percentage is approved | Existing seven attendance statuses; session must be non-cancelled for percentage | Cancelled session rows; duplicate/invalid rows | Snapshot/session branch must be authorized | Session local date within selected day/month | Convert stored session timestamps to MCC timezone; status timestamps remain source-backed | One attendance row per snapshot member; duplicates rejected or latest valid row only per existing rule | Missing attendance remains `Not recorded`; zero denominator displays `Not available` | 15 Present/Late of 18 eligible rows = 83.3% if denominator is approved | NEEDS_DECISION |
| FORM-13 | Existing approved source KPI result for the selected period, using source record IDs once | `1` for a stored KPI, or approved comparison population for a ratio | Approved/completed source records only | Draft, rejected, unapproved, corrected-invalid, insufficient source data | Match/Session/Player/Coach branch must be authorized | Selected local day/month or approved reporting period | Convert stored UTC source timestamps to MCC timezone for period selection | Deduplicate by source record ID and stable version | Insufficient or zero denominator displays `Not available`; never fabricate values | Two comparable approved source records produce the existing KPI; otherwise `Not available` | NEEDS_DECISION |

**Approval fields for FORM-01 through FORM-13:** Approver: ______; Approval date: ______; Effective date: ______.
