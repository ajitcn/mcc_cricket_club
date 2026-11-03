# Director Dashboard Implementation Matrix

All rows are `NEEDS_DECISION`. No dashboard formulas, migrations, permissions, exports, caching, or UI changes are authorized by this matrix.

## Reconciliation review

Reviewed against the Director Dashboard decision request and the current project requirement, decision, architecture, database, and RBAC records on 2026-10-11. No item has a completed resolution, approver identity, approval date, or effective date. Recommended secure defaults remain guidance only and are not client approval. Approval fields remain blank in the decision request.

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL (2026-10-11):** Apply the following secure-default baseline to every row while retaining `NEEDS_DECISION`: authorized branch scope with Super Admin-only explicit All Branches; MCC-local date/month inputs with UTC conversion for stored timestamps; active authorized source records; explicitly documented formulas before implementation; no sensitive medical, document, guardian, or private contact data; no unapproved KPI formulas; clear safe empty states; no caching before freshness/invalidation rules; reuse existing permissions and branch authorization; exports must inherit dashboard filters and authorization; audit only sensitive administrative actions and never private data. Numeric formula details were not supplied and remain blocked for decision.

## Formula decision matrix

| Formula ID | Metric | Exact decision still required | Secure baseline | Status |
|---|---|---|---|---|
| FORM-01 | Total active Players | Source population and assignment denominator | Distinct active authorized Players | NEEDS_DECISION |
| FORM-02 | Total active Coaches | Profile/user/assignment source | Distinct active assigned Coaches | NEEDS_DECISION |
| FORM-03 | Group coaching count | Group taxonomy or roster rule and statuses | No inference without approved taxonomy | NEEDS_DECISION |
| FORM-04 | Personal coaching count | Individual taxonomy or one-Player rule | No inference without approved taxonomy | NEEDS_DECISION |
| FORM-05 | Elite coaching count | Approved Elite category and source | Omit until taxonomy exists | NEEDS_DECISION |
| FORM-06 | Branch totals | Identity and multi-branch treatment | Authorized branch-scoped distinct totals | NEEDS_DECISION |
| FORM-07 | Batch totals | Effective assignment and duplicate handling | Current effective assignment | NEEDS_DECISION |
| FORM-08 | Age groups | Categories, DOB validity, and as-of rule | Persisted category or approved derivation only | NEEDS_DECISION |
| FORM-09 | Coach workload | Sessions, hours, assignments, or composite | Defer until selected | NEEDS_DECISION |
| FORM-10 | Availability/leave | Hours, leave, coverage, or display only | Approved leave/availability only | NEEDS_DECISION |
| FORM-11 | Upcoming sessions | Period and status inclusion | Future scheduled sessions only | NEEDS_DECISION |
| FORM-12 | Attendance | Status counts and denominator | Source counts only until denominator approved | NEEDS_DECISION |
| FORM-13 | Performance/KPI | Approved source metrics and period | Existing approved KPIs only | NEEDS_DECISION |

### Formula worksheet fields

Every formula remains blocked until its numerator and denominator are explicitly selected, along with status, scope, date/timezone, duplicate, missing-data, relationship, permission, freshness, example, test, and migration fields in the decision request.

| Formula ID | Numerator | Denominator | Status |
|---|---|---|---|
| FORM-01 | Distinct active Player IDs under the selected rule | `1` for a count or approved active population | NEEDS_DECISION |
| FORM-02 | Distinct active Coach IDs under the selected rule | `1` for a count or approved Coach population | NEEDS_DECISION |
| FORM-03 | Qualifying group sessions/records | `1` or approved session population | NEEDS_DECISION |
| FORM-04 | Qualifying individual sessions/records | `1` or approved session population | NEEDS_DECISION |
| FORM-05 | Qualifying Elite records under approved taxonomy | `1` or approved Elite population | NEEDS_DECISION |
| FORM-06 | Distinct active Players and Coaches per branch | `1` per branch or approved branch population | NEEDS_DECISION |
| FORM-07 | Distinct active Players with effective Batch assignment | `1` per Batch or approved Batch population | NEEDS_DECISION |
| FORM-08 | Distinct active Players mapped from valid DOB | `1` per age category or approved DOB population | NEEDS_DECISION |
| FORM-09 | Sessions, Player-session units, hours, or weighted units | `1`, hours, or selected workload base | NEEDS_DECISION |
| FORM-10 | Available units after leave or leave units | Availability units, scheduled hours, or `1` | NEEDS_DECISION |
| FORM-11 | Distinct future sessions matching selected rule | `1` | NEEDS_DECISION |
| FORM-12 | Attendance status rows or eligible snapshot rows | Snapshot rows, recorded rows, or occurred sessions | NEEDS_DECISION |
| FORM-13 | Approved source metric result | Comparison/source population or `1` | NEEDS_DECISION |

## Completed formula worksheet — USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL

These conservative definitions are recorded for review only. They do not authorize implementation. Approver, approval date, and effective date remain blank; all rows remain `NEEDS_DECISION`.

| ID | Numerator | Denominator | Included | Excluded | Branch/date/timezone | Duplicate/missing behavior | Example | Status |
|---|---|---|---|---|---|---|---|---|
| FORM-01 | Distinct active authorized Player IDs | `1` per count | Active | Inactive/suspended/alumni/deleted | Authorized active branches; local as-of date converted from UTC timestamps | Distinct ID; invalid/missing identity excluded | 42 Players = 42 | NEEDS_DECISION |
| FORM-02 | Distinct active assigned Coach IDs | `1` per count | Active profile, enabled user | Inactive/disabled/unassigned | Authorized branches; local effective-date filtering | Distinct Coach; missing assignment excluded | 6 Coaches = 6 | NEEDS_DECISION |
| FORM-03 | Distinct sessions with >1 eligible roster Player | `1` | Scheduled/active/completed | Cancelled/postponed/missing roster | Owning branch; local session date from UTC | Session ID once; unavailable roster excluded | 12 group sessions | NEEDS_DECISION |
| FORM-04 | Distinct sessions with exactly 1 eligible roster Player | `1` | Scheduled/active/completed | Cancelled/postponed/other roster sizes | Owning branch; local session date from UTC | Session ID once; no type inference | 8 personal sessions | NEEDS_DECISION |
| FORM-05 | Distinct active explicitly classified Elite Program/Batch IDs | `1` | Active classified Elite | Inactive/unclassified | Authorized owning branch; local effective date | ID once; missing taxonomy unavailable | 2 Elite Batches | NEEDS_DECISION |
| FORM-06 | Branch-scoped distinct active Players and Coaches | `1` per branch | FORM-01/02 included states | Their excluded states | Authorized branches and local as-of date | Identity once per branch; multi-branch tested | A 20/4, B 15/3 | NEEDS_DECISION |
| FORM-07 | Distinct active Players with current effective Batch assignment | `1` per Batch | Active Player/Batch/effective assignment | Inactive/expired/future | Matching authorized branch; local effective date | Player once; overlap invalid | 18 in U-15 | NEEDS_DECISION |
| FORM-08 | Distinct active Players mapped from valid DOB | `1` per category | Active valid DOB | Invalid/missing/future DOB | Local as-of date; DOB date-only | Player once; unavailable bucket or exclusion | 2012-04-10 on 2026-10-11 = age 14 | NEEDS_DECISION |
| FORM-09 | Distinct qualifying Coach sessions | `1` per count | Scheduled/active/completed | Cancelled/postponed | Authorized branch; local start date | Session once; missing link unavailable | 10 sessions | NEEDS_DECISION |
| FORM-10 | Approved availability minus approved leave hours | Availability hours if percentage chosen | Approved availability/leave | Pending/rejected/cancelled | Authorized Coach branch; local intervals from UTC | Merge overlaps; missing availability unavailable | 40 − 8 = 32 | NEEDS_DECISION |
| FORM-11 | Distinct scheduled sessions after local now | `1` | Scheduled | Active/completed/cancelled/postponed | Authorized branch; UTC converted to MCC local time | Session once; missing start excluded | 3 upcoming | NEEDS_DECISION |
| FORM-12 | Attendance rows by status | Eligible snapshot rows if percentage chosen | Seven approved statuses, non-cancelled sessions | Cancelled/invalid/duplicate | Authorized snapshot branch; local session date | One row per snapshot member; missing = not recorded | 15/18 = 83.3% if approved | NEEDS_DECISION |
| FORM-13 | Existing approved KPI source result | `1` or approved comparison population | Approved/completed source | Draft/rejected/insufficient | Authorized source branch; local reporting period | Source ID once; insufficient = unavailable | Valid pair yields KPI | NEEDS_DECISION |

Approval fields for FORM-01 through FORM-13: Approver: ______; Approval date: ______; Effective date: ______.

| ID | Area | Existing implementation evidence | Missing/blocked work | Recommended secure default | Permission/branch dependency | Migration impact | Tests required | Status |
|---|---|---|---|---|---|---|---|---|
| DASH-01 | Branch selector | Shared branch context and authorizedBranches; no dashboard selector | Selector semantics | Explicit selected authorized active branch | Existing dashboard and source permissions; server recheck | None expected | Selector, forged branch, scope | NEEDS_DECISION |
| DASH-02 | All branches | Aggregates authorizedBranches; no approved all-branch policy | Explicit all-branch scope | No implicit all-branch view | Explicit role/permission if approved | Possible permission migration | Cross-branch and role tests | NEEDS_DECISION |
| DASH-03 | Date/month filters | Current month/day in DashboardService | User filter contract | Local calendar period with exclusive end | Existing read permissions | None expected | Boundary and invalid range tests | NEEDS_DECISION |
| DASH-04 | Timezone | Direct Asia/Kolkata use plus shared app helpers | Consistent convention | Configured application timezone | Server-side conversion | None expected | Midnight/timezone tests | NEEDS_DECISION |
| DASH-05 | Player total | Active player count exists | Approved formula label | Active distinct Players | player.view + branch | None | Status/duplicate tests | NEEDS_DECISION |
| DASH-06 | Coach total | No dashboard Coach total | Formula and source | Active assigned Coaches | coach.view + branch | None | Multi-branch/inactive tests | NEEDS_DECISION |
| DASH-07 | Coaching types | Programs/Batches/Sessions lack approved taxonomy | Group/Personal/Elite rules | Defer until source taxonomy | Existing source permissions | Possible classification migration | Mapping tests | NEEDS_DECISION |
| DASH-08 | Branch counts | Generic branch row count exists | Metric set | Authorized branches only | Per-metric permission | None | Branch isolation | NEEDS_DECISION |
| DASH-09 | Batch distribution | Batch assignments exist | As-of/effective rule | Current effective assignment | player.view/batch.view | None | Effective-date tests | NEEDS_DECISION |
| DASH-10 | Age groups | DOB/age fields may exist | Cutoffs/as-of date | Persisted field only until approved | player.view | None or policy-only | Birthday boundary tests | NEEDS_DECISION |
| DASH-11 | Coach workload | KPI/session sources exist | Formula, weighting, period | Defer formula | coach.kpi.view + branch | None until approved | Cancellation/weight tests | NEEDS_DECISION |
| DASH-12 | Availability/leave | Scheduling enforces leave; Coach availability exists | Display policy | Approved leave/availability only | coach.view/update | None | Leave/status tests | NEEDS_DECISION |
| DASH-13 | Upcoming sessions | Existing upcomingSessions query | Period definition | Future scheduled, non-cancelled | session read + branch | None | Time/status tests | NEEDS_DECISION |
| DASH-14 | Attendance | Counts/status summary exists | Denominator/percentage policy | Source-backed counts only | attendance.view + branch | None | Seven-status/zero-data tests | NEEDS_DECISION |
| DASH-15 | Performance/KPI | Coach KPI and Performance services exist | Approved KPI set | Source-backed approved KPIs only | Existing KPI/performance permissions | None | Formula/correction tests | NEEDS_DECISION |
| DASH-16 | Empty states | Existing section empty states | Consistent semantics | Per-section safe empty state | Permission-aware | None | No-data/no-permission tests | NEEDS_DECISION |
| DASH-17 | Freshness/cache | Live queries; no cache policy | TTL/snapshot decision | Live initially | Cache cannot bypass RBAC | Possible cache infra | Staleness/invalidation tests | NEEDS_DECISION |
| DASH-18 | Roles/branch access | Centralized permission checks | Director visibility contract | Reuse existing permissions | AuthorizationService required | Possible permission migration | Role/route/branch tests | NEEDS_DECISION |
| DASH-19 | Export/report | Reports separate; no dashboard export | Export fields/policy | No export until approved | Explicit export permission | None expected | Filter parity/sensitive exclusion | NEEDS_DECISION |
| DASH-20 | Sensitive data | Existing aggregates and limited names | Detail visibility policy | Aggregates only | Source permissions + branch | None | Sensitive-field exclusion | NEEDS_DECISION |

## Evidence classification

- **Code evidence:** `DashboardService`, `DashboardModel`, `app/Views/dashboard.php`, existing Reports, Coach KPI, Scheduling, Attendance, Players, Programs, Batches, and Branch services.
- **Test evidence:** Existing module contract and route tests; no Director-specific decision contract exists.
- **User/client reference:** The supplied Director Dashboard image description is reference input, not approval of formulas or visibility.
- **Inferred findings:** Recommendations above are secure defaults only and must not be treated as approved decisions.

## Safe implementation slices after approval

1. Approve branch scope and date semantics.
2. Approve Player/Coach totals and source-backed distributions.
3. Approve upcoming sessions and attendance denominator.
4. Approve KPI/workload formulas and sensitive-data boundaries.
5. Implement UI and exports only after permissions, audit, and test acceptance are approved.
