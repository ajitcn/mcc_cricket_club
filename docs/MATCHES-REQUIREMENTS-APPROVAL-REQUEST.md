# Matches Requirements Approval Request

**Prepared:** 2026-09-30  
**Status:** Decision request only. No implementation authorization is implied.  
**Source audit:** docs/MATCHES-READ-ONLY-AUDIT.md

## Decision handling

This request separates implementation evidence from authorization. Existing source, migrations, tests, and recommendations are not client approval. No new Match user direction was supplied beyond this request. Until an approver explicitly resolves an item below, its status remains NEEDS_DECISION and no related workflow should be implemented.

For every item, select one of **Approved**, **Rejected**, or **Needs discussion**, then complete resolution/comments, approver, dates, migration impact, and acceptance criteria.

## Current implementation baseline

The repository has explicit authenticated Match list, create, edit, detail, and POST save routes. The matches table stores one required branch, optional ground and Coach, opponent, type, status, start/end, result, score summary, Coach remarks, creator, and timestamps. match_players stores squad and Playing XI membership. Branch authorization, ground conflicts, Coach SchedulingService checks, transactions, resource locks, and central audit persistence exist.

The implementation has no Match scorecard, innings, per-player performance, attendance, media, export, lifecycle transition, cancellation, deletion, or Match-specific correction workflow. Current tests are one unit contract suite and one database integration suite; no Match Feature suite was found. The current forms use combined datetime text values and the service requires an explicit timezone suffix. Player and Coach option queries do not explicitly enforce active/eligible state.

---

## MT-01 — Match scope and competition types

**Status:** NEEDS_DECISION**

**Question:** Which competition types are in scope? Do friendly, practice, league, and knockout remain the complete controlled list? May a Match stand alone, belong to a Tournament, or both?

- Approved choice / types: __________________________________
- Rejected choice: __________________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: Match Management section 3.10; MT-01; MT-06; BR-05; REQ-ADV-001.
- Current evidence: MatchManagementService allows friendly, practice, league, and knockout; matches has no tournament ID.
- Migration impact: Possible competition/tournament relationship or controlled-option migration.
- Test acceptance: Approved types and standalone/parent rules are rendered and enforced server-side; unknown/rejected values fail.

## MT-02 — Match lifecycle and status transitions

**Status:** NEEDS_DECISION**

**Question:** Define statuses, allowed transitions, authority, reasons, timing, terminal behavior, and idempotency.

- Approved transitions: ______________________________________
- Rejected transitions: ______________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: MT-04; AT-04 (sessions only); requirements review Match section; REQ-ADV-001.
- Current evidence: scheduled, completed, and cancelled are accepted directly on create/update; no matrix, reason, lock, or timing rule exists.
- Migration impact: Possible status/history/lock/reason fields or tables.
- Test acceptance: Valid transitions, invalid transitions, reasons, timing, authorization, idempotency, and audit are covered.

## MT-03 — Branch ownership and multi-branch access

**Status:** NEEDS_DECISION**

**Question:** Are Matches single-branch, shared across participating branches, or both? Define current and historical visibility.

- Approved ownership/access: __________________________________
- Rejected ownership/access: __________________________________
- Needs discussion: ___________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: BR-04, BR-05, BR-06; MT-02; SEC-09/13/14.
- Current evidence: matches has one branch_id; all Match queries and audit history are branch-filtered; no participating-branches relation exists.
- Migration impact: Multi-branch access requires a forward-only relation and authorization/query changes.
- Test acceptance: Authorized, cross-branch, shared, and historical paths match the approved model.

## MT-04 — Opponent/team representation

**Status:** NEEDS_DECISION**

**Question:** Should opponents remain free text, use internal/external Team records, or support both? Define required fields and uniqueness.

- Approved representation: ____________________________________
- Rejected representation: ___________________________________
- Needs discussion: __________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: requirements review section 3.10; MT-01; MT-02; MT-06.
- Current evidence: opponent is required free-text VARCHAR(150); no Team model exists.
- Migration impact: A Team/opponent table or controlled fields may be required.
- Test acceptance: Approved representation, validation, participant authorization, and forged-ID rejection are tested.

## MT-05 — Player selection and eligibility

**Status:** NEEDS_DECISION**

**Question:** Who may select or approve Players? Which active, branch, lifecycle, age, batch, and Playing XI rules apply? May selection be corrected after the match?

- Approved authority/rules: __________________________________
- Rejected authority/rules: _________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: MT-03; MT-05; PF-01–PF-04; Player lifecycle/assignment requirements.
- Current evidence: squad and Playing XI are persisted; XI must be a squad subset and max 11; only branch equality is checked; submitted selection keys replace existing rows.
- Migration impact: Possible eligibility snapshot, approval, lock, correction, or selection-history schema.
- Test acceptance: Approved active/eligible Players only; forged/cross-branch/duplicate IDs rejected; correction history and audit preserved if approved.

## MT-06 — Coach and official assignments

**Status:** NEEDS_DECISION**

**Question:** Which Coaches and officials may be assigned? Who may assign/reassign them, and what history/reason rules apply?

- Approved roles/rules: ______________________________________
- Rejected roles/rules: _____________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: CM-02, CM-03, CM-06; MT-03; MT-04.
- Current evidence: optional Coach must be assigned to the branch and hold Coach/Fitness Coach role; availability, leave, and conflict checks run; active-user state and official fields are not established.
- Migration impact: Official assignment fields/tables or narrow permissions may be required.
- Test acceptance: Active authorized assignees, conflicts, reassignment, reasons, audit, and historical retention are covered.

## MT-07 — Venue and ground rules

**Status:** NEEDS_DECISION**

**Question:** Define venue source, branch ownership, external venues, availability, booking, overrides, and cross-branch ground use.

- Approved venue rules: ______________________________________
- Rejected venue rules: _____________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: ground/booking requirements; BR-04; BR-05; MT-02.
- Current evidence: optional active branch ground; unavailability and session/match overlap checks exist; no external venue or override policy.
- Migration impact: External/shared venue metadata may require schema changes.
- Test acceptance: Active authorized grounds, conflicts, overrides, and shared-venue behavior follow the decision.

## MT-08 — Match date/time and timezone behavior

**Status:** NEEDS_DECISION**

**Question:** Confirm local input format, storage timezone, configured timezone source, date boundaries, and same-day rules.

- Approved date/time policy: _________________________________
- Rejected date/time policy: _________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: OPS-09; Scheduling date/time conventions.
- Current evidence: service requires Z or numeric offset and stores UTC-style DATETIME; calendar filtering uses a hard-coded Asia/Kolkata constant; form uses combined Start/End inputs.
- Migration impact: Usually none; data normalization may be needed if existing timestamps are inconsistent.
- Test acceptance: Separate local inputs, configured-timezone round trip, invalid ranges, and local-calendar filtering are covered.

## MT-09 — Scorecard fields and innings structure

**Status:** NEEDS_DECISION**

**Question:** Define scorecard structure, innings count, formats, overs, teams, draft/lock states, and verification.

- Approved scorecard policy: __________________________________
- Rejected scorecard policy: _________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: MT-04; PF-01–PF-04; requirements review Match/Performance sections.
- Current evidence: only free-text score_summary and result exist; no scorecard/innings tables.
- Migration impact: New scorecard, innings, delivery, summary, and lock/version tables likely required.
- Test acceptance: Format-specific fields, draft/locked state, verification, correction, and audit are tested.

## MT-10 — Batting, bowling, fielding, and extras data

**Status:** NEEDS_DECISION**

**Question:** Which per-player and per-innings metrics are recorded, with units, precision, missing-data and edge-case rules?

- Approved metrics/formulas: __________________________________
- Rejected metrics/formulas: _________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: PF-01–PF-04; MT-04.
- Current evidence: no batting, bowling, fielding, or extras storage exists.
- Migration impact: New metric tables, units, validation, and versioning may be required.
- Test acceptance: Ranges, precision, missing/incomparable values, corrections, and approvals are enforced.

## MT-11 — Result and winner calculation

**Status:** NEEDS_DECISION**

**Question:** Are results entered manually or derived from scorecards? Define winner, draw, and verification rules.

- Approved calculation/source: _________________________________
- Rejected calculation/source: ________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: MT-04; MT-05; requirements review result/scorecard sections.
- Current evidence: result allowlist is won, lost, draw, no_result; score_summary is free text; no calculation exists.
- Migration impact: Winner, verification, and derived-result fields may be required.
- Test acceptance: Results agree with approved scorecard data and corrections are audited.

## MT-12 — Tie, abandoned, rain-cancelled, and no-result handling

**Status:** NEEDS_DECISION**

**Question:** Define status/result values, scorecard behavior, attendance impact, and audit requirements for exceptional outcomes.

- Approved exceptional-outcome rules: _________________________
- Rejected exceptional-outcome rules: ________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: MT-02; MT-04; AT-04 (sessions only).
- Current evidence: no_result exists, but no tie/abandonment/rain workflow exists; cancelled is directly editable.
- Migration impact: Additional status, reason, event-date, or weather metadata may be required.
- Test acceptance: Exceptional outcomes cannot be confused with completed wins/losses and have approved downstream effects.

## MT-13 — Player and Coach performance integration

**Status:** NEEDS_DECISION**

**Question:** Should Match source metrics feed Player Performance or Coach KPIs? Define sources, formulas, approval, visibility, correction, and branch scope.

- Approved integration/formulas: ______________________________
- Rejected integration/formulas: _____________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: PF-01–PF-06; Coach KPI decisions; PII-v1.
- Current evidence: Match stores no performance records; Player 360 lists Match participation; performance formulas remain decision-sensitive.
- Migration impact: Source links, approval markers, metrics, and recalculation/versioning may be required.
- Test acceptance: Only approved sources feed KPIs; branch/permission visibility and correction recalculation are covered.

## MT-14 — Attendance integration

**Status:** NEEDS_DECISION**

**Question:** Does a Match have attendance? Define roster source, statuses, denominator, correction, and ownership.

- Approved attendance policy: __________________________________
- Rejected attendance policy: _________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: AT-01–AT-06; MT-03; MT-05.
- Current evidence: match_players stores selection only; AttendanceService and immutable snapshots currently operate on sessions.
- Migration impact: Match attendance snapshots/tables or approved reuse of Attendance tables may be required.
- Test acceptance: Roster boundary, statuses, authorization, corrections, branch scope, and audit are verified without changing session attendance.

## MT-15 — Corrections, approval, locking, and audit history

**Status:** NEEDS_DECISION**

**Question:** Who may correct Match data, when does it lock, what requires a reason, and what append-only history is required?

- Approved correction/lock policy: ____________________________
- Rejected correction/lock policy: ___________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: MT-03–MT-05; OPS-01.
- Current evidence: all edits use match.update; central audit stores before/after summaries for create/update; no Match correction permission or lock exists.
- Migration impact: Status/history/version/correction metadata may be required.
- Test acceptance: Unauthorized/cross-branch corrections fail; approved locks, reasons, old/new values, actors, timestamps, and idempotency are tested.

## MT-16 — Reports, exports, and visibility

**Status:** NEEDS_DECISION**

**Question:** Define Match reports, filters, columns, exports, recipients, role visibility, branch scope, and export auditing.

- Approved reports/exports: ___________________________________
- Rejected reports/exports: _________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: OPS-06; DASH-01–DASH-03; MT-02; MT-13; MT-15.
- Current evidence: ReportService has a basic branch-filtered matches query; no Match-specific export route or policy was found; Dashboard shows scheduled-match activity/count.
- Migration impact: Usually none, but persisted summaries or export audit tables may be required.
- Test acceptance: Reports use authorized source rows and identical filters; sensitive fields and export visibility are controlled.

## MT-17 — Permissions and role access

**Status:** NEEDS_DECISION**

**Question:** Confirm role capabilities and whether narrow permissions are required for selection, scorecards, completion, cancellation, correction, reports, and exports.

- Approved role matrix: ______________________________________
- Rejected role matrix: _____________________________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: RBAC-SA-01; SEC-02/09/13/14; MT-03; MT-05; MT-15; MT-16.
- Current evidence: migration and AuthorizationRestoreSeeder grant match.view, match.create, and match.update to Super Admin, Branch Admin, and Coach. No narrow action permissions exist.
- Migration impact: Permission catalog and role-grant migration/seed changes may be required.
- Test acceptance: Centralized permission filters and service checks protect every action, including multi-role and cross-branch paths.

## MT-18 — Retention and historical recalculation

**Status:** NEEDS_DECISION**

**Question:** Define retention for Match, scorecard, selection, performance, attendance, media, and audit data, plus recalculation after corrections or formula changes.

- Approved retention/recalculation policy: _____________________
- Rejected retention/recalculation policy: ____________________
- Needs discussion: _________________________________________
- Resolution/comments: ______________________________________
- Approver identity: ________________________________________
- Approval date: ____________________________________________
- Effective date: ___________________________________________
- Related records: OPS-01, OPS-02, OPS-10; PF-04, PF-06; MT-04, MT-13, MT-15.
- Current evidence: Match timestamps and central audit exist; no Match retention, archive, version, lock, or recalculation policy exists.
- Migration impact: Versioned source records, derived snapshots, archive markers, or retention metadata may be required.
- Test acceptance: Historical results remain reproducible under approved versions; corrections/recalculation and retention preserve audit and branch isolation.

---

## Approval summary

| Item | Decision | Approver | Approval date | Effective date |
|---|---|---|---|---|
| MT-01 Scope/types | NEEDS_DECISION | — | — | — |
| MT-02 Lifecycle/status | NEEDS_DECISION | — | — | — |
| MT-03 Branch/multi-branch | NEEDS_DECISION | — | — | — |
| MT-04 Opponent/team | NEEDS_DECISION | — | — | — |
| MT-05 Player selection | NEEDS_DECISION | — | — | — |
| MT-06 Coach/officials | NEEDS_DECISION | — | — | — |
| MT-07 Venue/grounds | NEEDS_DECISION | — | — | — |
| MT-08 Date/time/timezone | NEEDS_DECISION | — | — | — |
| MT-09 Scorecard/innings | NEEDS_DECISION | — | — | — |
| MT-10 Batting/bowling/fielding/extras | NEEDS_DECISION | — | — | — |
| MT-11 Result/winner | NEEDS_DECISION | — | — | — |
| MT-12 Exceptional outcomes | NEEDS_DECISION | — | — | — |
| MT-13 Performance integration | NEEDS_DECISION | — | — | — |
| MT-14 Attendance integration | NEEDS_DECISION | — | — | — |
| MT-15 Corrections/locking/audit | NEEDS_DECISION | — | — | — |
| MT-16 Reports/exports/visibility | NEEDS_DECISION | — | — | — |
| MT-17 Permissions/roles | NEEDS_DECISION | — | — | — |
| MT-18 Retention/recalculation | NEEDS_DECISION | — | — | — |

**Approver identity:** ______________________________________  
**Approval date:** _________________________________________  
**Effective date:** _________________________________________  
**Overall comments:** _______________________________________

No item above authorizes code, migration, seed, permission, or database changes until the decision is explicitly recorded and reconciled into the relevant project documents.
