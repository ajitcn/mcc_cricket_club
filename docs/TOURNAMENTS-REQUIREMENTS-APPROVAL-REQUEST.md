# Tournament Requirements Approval Request

**Prepared:** 2026-10-01  
**Status:** Decision request only. All items below remain NEEDS_DECISION until an authorized approver records an explicit resolution.  
**Source:** docs/TOURNAMENTS-READ-ONLY-AUDIT.md

## Decision handling

This document separates repository evidence from authorization:

- Current code, migrations, tests, and role grants are implementation evidence only.
- User direction is not client approval.
- No Tournament policy is approved by this request itself.
- Select one choice for each item: **Approved**, **Rejected**, or **Needs discussion**.
- Complete the resolution, approver, and effective-date fields.
- Do not implement an item while it is unanswered, conflicting, or marked Rejected.

## Existing implementation baseline

The repository currently provides branch-scoped Tournament CRUD, participant rows for free-text teams and Players, optional Match-linked fixtures, simple standings, audit records, explicit permission-filtered routes, and transactional writes. It does not provide scorecards, innings, advanced registration, lifecycle transition workflows, Tournament hierarchy, attendance, performance integration, exports, notifications, or safe Tournament-specific persistent test coverage.

---

## T-01 — Tournament scope and competition types

**Status:** NEEDS_DECISION

**Decision question:** Which competition types are in scope? Should the current school, club, district, MCA, state, national, international, and England tour values remain? May a Tournament stand alone, belong to a parent competition, or both?

- Approved choice/types: ______________________________________
- Rejected choice: ____________________________________________
- Needs discussion: ___________________________________________
- Resolution/comments: ________________________________________
- Approver identity: __________________________________________
- Approval date: ______________________________________________
- Effective date: _____________________________________________
- Current implementation evidence: TournamentManagementService has a fixed type allowlist; no parent competition or hierarchy relation exists.
- Related requirements/decisions: REQ-ADV-001; MT-01; MT-06; BR-05.
- Migration impact: Possible controlled-option, parent-competition, or hierarchy schema.
- Test acceptance criteria: Approved types and standalone/parent rules render consistently and are rejected server-side when invalid.

## T-02 — Tournament lifecycle and status transitions

**Status:** NEEDS_DECISION

**Decision question:** Define statuses, valid transitions, transition authority, timing, reasons, terminal states, reopening, and idempotency.

- Approved statuses/transitions: __________________________________
- Rejected transitions: __________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: planned, active, completed, and cancelled are directly accepted on create/update; no transition matrix, reason, lock, or status history exists.
- Related requirements/decisions: MT-02; MT-04; REQ-ADV-001.
- Migration impact: Status history, transition reason, lock, or event tables may be required.
- Test acceptance criteria: Only approved transitions succeed; invalid and repeated transitions fail safely or are idempotent with complete audit metadata.

## T-03 — Branch ownership and multi-branch access

**Status:** NEEDS_DECISION

**Decision question:** Are Tournaments single-branch, global/shared, or both? Define participating branches, current visibility, historical visibility, branch transfer, and shared authorization.

- Approved ownership/access model: ______________________________
- Rejected model: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: tournaments has one branch_id and all reads/writes are branch-filtered; no participating-branches relation exists.
- Related requirements/decisions: BR-04, BR-05, BR-06; MT-02; MT-03; SEC-09/13/14.
- Migration impact: Shared access requires a forward-only branch relation and scope-aware authorization/query changes.
- Test acceptance criteria: Authorized and cross-branch reads/writes match the approved model, including historical records.

## T-04 — Tournament dates and timezone handling

**Status:** NEEDS_DECISION

**Decision question:** Confirm local input format, configured timezone source, storage timezone, inclusive date boundaries, date validation, and fixture date policy.

- Approved date/time policy: _____________________________________
- Rejected policy: ______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Tournament dates are DATE fields and use datepicker attributes; fixture scheduled_at requires an explicit timezone suffix; service uses a hard-coded Asia/Kolkata path.
- Related requirements/decisions: OPS-09; MT-08; Scheduling date/time conventions.
- Migration impact: Usually none; data normalization may be required if stored values are inconsistent.
- Test acceptance criteria: Local date/time round trips through configured timezone, rejects malformed/reversed ranges, and applies approved calendar boundaries.

## T-05 — Registration and entry rules

**Status:** NEEDS_DECISION

**Decision question:** Define registration windows, entry ownership, team/player entry limits, required documents or fees, approval states, withdrawal, duplicate entry, and late entry behavior.

- Approved rules: _______________________________________________
- Rejected rules: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: No registration entity, entry window, approval workflow, or registration route exists.
- Related requirements/decisions: REQ-ADV-001; MT-01; MT-05; REL/DOC policies where sensitive entry data is involved.
- Migration impact: Registration, approval, entry, and audit tables may be required.
- Test acceptance criteria: Entries obey approved windows, limits, permissions, branch scope, duplicate rules, and audit requirements.

## T-06 — Player eligibility and age/category rules

**Status:** NEEDS_DECISION

**Decision question:** Define active status, branch, batch/program, age cutoffs, birth-date source, category boundaries, exceptions, and eligibility snapshot timing.

- Approved eligibility rules: _____________________________________
- Rejected rules: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Participant writes check Player branch equality only; no active status, age, batch, category, or immutable eligibility snapshot is enforced.
- Related requirements/decisions: MT-03; MT-05; PF-01–PF-04; Player lifecycle requirements.
- Migration impact: Eligibility/category fields, snapshots, or approval history may be required.
- Test acceptance criteria: Forged, inactive, cross-branch, age-ineligible, and category-ineligible Players are rejected server-side with approved exceptions audited.

## T-07 — Coach and official assignments

**Status:** NEEDS_DECISION

**Decision question:** Which Coaches and officials may be assigned, who may assign/reassign them, whether assignments are branch-bound, and what correction/history rules apply.

- Approved assignment rules: ____________________________________
- Rejected rules: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Tournament schema has no Coach or official assignment fields; fixture service only links Match/Ground.
- Related requirements/decisions: CM-02, CM-03, CM-06; MT-03; MT-06.
- Migration impact: Official/Coach assignment tables or fields and audit metadata may be required.
- Test acceptance criteria: Only active, authorized assignees are accepted; reassignment, reason, branch, history, and correction rules are tested.

## T-08 — Team and squad structure

**Status:** NEEDS_DECISION

**Decision question:** Define team entity versus free text, squad size, Player roles, Playing XI, reserves, duplicates, team ownership, and selection approval.

- Approved structure: ___________________________________________
- Rejected structure: ___________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Participants store free-text teams or Player IDs; no squad, XI, reserve, team entity, or selection-history schema exists.
- Related requirements/decisions: MT-04; MT-05; MT-06.
- Migration impact: Team, squad, selection, approval, and snapshot tables may be required.
- Test acceptance criteria: Team and squad constraints, duplicate prevention, branch/eligibility validation, and corrections match the approved structure.

## T-09 — Match scheduling and fixture generation

**Status:** NEEDS_DECISION

**Decision question:** Define whether fixtures are manually entered or generated, scheduling windows, venue/Coach conflicts, Match linkage, rescheduling, and cancellation behavior.

- Approved scheduling/generation rules: __________________________
- Rejected rules: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Fixture POST service accepts manual fixture number, teams, optional Match, optional Ground, optional scheduled time, and status. No rendered fixture form or generation workflow exists.
- Related requirements/decisions: MT-01, MT-02, MT-06, MT-07, MT-08; Scheduling and Match records.
- Migration impact: Fixture generation, reschedule, conflict, and event-history fields/tables may be required.
- Test acceptance criteria: Generated/manual fixtures obey date, Ground, Match, branch, duplicate-number, conflict, and audit rules.

## T-10 — Tournament rounds and progression

**Status:** NEEDS_DECISION

**Decision question:** Define round types, ordering, bracket/group structure, qualification, byes, progression, reseeding, and incomplete-round behavior.

- Approved rounds/progression: __________________________________
- Rejected structure: ___________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: No rounds, groups, bracket, progression, or hierarchy tables exist.
- Related requirements/decisions: MT-01; MT-06; REQ-ADV-001.
- Migration impact: Round/group/bracket/progression schema is likely required.
- Test acceptance criteria: Round creation, progression, ties/byes, corrections, locking, and branch visibility follow the approved model.

## T-11 — Scorecards and result integration

**Status:** NEEDS_DECISION

**Decision question:** Define scorecard structure, innings, Match result source, verification, draft/lock state, and Tournament result integration.

- Approved scorecard/result policy: ______________________________
- Rejected policy: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Fixtures only store optional Match linkage, status, and points; Match stores free-text result/score_summary; no scorecard or verification tables exist.
- Related requirements/decisions: MT-04; MT-09; MT-11; MT-12.
- Migration impact: Scorecard, innings, metric, verification, lock, and version tables may be required.
- Test acceptance criteria: Results derive from or are entered under the approved source policy; verification, correction, locking, and audit are enforced.

## T-12 — Points table and standings formulas

**Status:** NEEDS_DECISION

**Decision question:** Define points for wins, losses, draws, ties, no-result, abandoned, rain-cancelled, bonuses, net run rate, incomplete data, and recalculation timing.

- Approved formulas: ____________________________________________
- Rejected formulas: ____________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: standings counts completed linked-Match results and adds stored home/away points; no formula/version metadata or NRR exists.
- Related requirements/decisions: MT-11; MT-12; PF-01–PF-04; REQ-ADV-001.
- Migration impact: Formula/version/configuration or materialized standings fields may be required.
- Test acceptance criteria: Approved examples, rounding, missing data, recalculation, and branch/permission visibility are covered.

## T-13 — Tie, abandoned, rain-cancelled, and no-result handling

**Status:** NEEDS_DECISION

**Decision question:** Define statuses/results, evidence, scorecard behavior, points, progression, attendance impact, correction, and audit for exceptional outcomes.

- Approved exceptional-outcome rules: ___________________________
- Rejected rules: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: no_result is accepted by Match result logic, but Tournament has no tie, abandonment, rain, weather, or exceptional-outcome workflow.
- Related requirements/decisions: MT-02; MT-11; MT-12; AT-04 for session-only cancellation policy.
- Migration impact: Outcome, reason, evidence, event date, and audit fields may be required.
- Test acceptance criteria: Exceptional outcomes cannot be treated as ordinary wins/losses and produce the approved standings/progression effects.

## T-14 — Player and Coach performance integration

**Status:** NEEDS_DECISION

**Decision question:** Should Tournament/Match source records feed Player Performance or Coach KPIs? Define source records, formulas, approval, visibility, correction, branch scope, and recalculation.

- Approved integration/formulas: __________________________________
- Rejected integration/formulas: _________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Tournament stores no performance records; Player 360 can show Match participation; Coach KPI and Performance Improvement Index policies are separately permission- and source-dependent.
- Related requirements/decisions: PF-01–PF-06; PII-v1; CM-05/CM-06; MT-13.
- Migration impact: Source links, approval markers, metrics, versioning, and recalculation may be required.
- Test acceptance criteria: Only approved source records feed KPIs; correction audit and branch/permission scope are verified.

## T-15 — Attendance integration

**Status:** NEEDS_DECISION

**Decision question:** Does Tournament participation have attendance? Define roster source, statuses, denominator, session/Match relationship, correction, and authority.

- Approved attendance policy: ___________________________________
- Rejected policy: ______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Tournament has no attendance relation, roster snapshot, or attendance route; existing Attendance is session/snapshot-based.
- Related requirements/decisions: AT-01–AT-06; MT-14; REQ-ATT-001.
- Migration impact: Tournament attendance relation or roster source may be required.
- Test acceptance criteria: Attendance reads/writes, statuses, snapshots, denominators, correction, branch scope, and audit match approved policy.

## T-16 — Corrections, approval, locking, and audit history

**Status:** NEEDS_DECISION

**Decision question:** Which Tournament, participant, fixture, result, scorecard, standings, and assignment changes require approval, lock, correction reason, append-only history, and historical recalculation?

- Approved correction/locking policy: ____________________________
- Rejected policy: ______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Tournament and fixture writes use central audit records, but participant replacement deletes/recreates rows and no status/scorecard/selection history or lock exists.
- Related requirements/decisions: MT-02, MT-03, MT-05, MT-09, MT-16; CM-06; OPS audit requirements.
- Migration impact: Append-only history, approval, lock, version, and correction metadata may be required.
- Test acceptance criteria: Actor, timestamp, branch, previous/new values, reason, approval, locking, rollback, and idempotency are tested.

## T-17 — Permissions and role visibility

**Status:** NEEDS_DECISION

**Decision question:** Define which roles may view, create, update, assign, verify, complete, cancel, correct, export, and audit Tournaments and related fixtures. Define Coach, Parent, Player, and multi-branch visibility.

- Approved role/permission matrix: ______________________________
- Rejected matrix: ______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: tournament.view/create/update are route and service gates; migration grants all three to Super Admin, Branch Admin, and Coach. No Tournament-specific verify/correct/export permission exists.
- Related requirements/decisions: RBAC.md; MT-02/03/06/16/17; BR-05/BR-06.
- Migration impact: Narrow permissions and role grants may require a forward-only migration.
- Test acceptance criteria: Every route and service action enforces the approved permission and branch scope; hidden controls never replace server authorization.

## T-18 — Reports and exports

**Status:** NEEDS_DECISION

**Decision question:** Which Tournament reports, CSV/PDF/Excel exports, columns, filters, freshness, audit events, and role/branch visibility are required?

- Approved report/export policy: __________________________________
- Rejected policy: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: No Tournament report or export route, service, view, or test exists.
- Related requirements/decisions: REQ-ADV-001; DASH-01–DASH-03; MT-18.
- Migration impact: Usually none for basic exports; audit/materialized summaries may require schema.
- Test acceptance criteria: Filters, branch scope, permission checks, field policy, timestamps, and export audit are covered.

## T-19 — Retention and historical recalculation

**Status:** NEEDS_DECISION

**Decision question:** Define retention, retirement/deletion, immutable history, source corrections, standings recalculation, KPI recalculation, backups, and legal/audit requirements.

- Approved retention/recalculation policy: ________________________
- Rejected policy: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: Foreign keys preserve some lineage; no Tournament retirement, retention, versioning, or recalculation policy exists.
- Related requirements/decisions: MT-16; MT-19; OPS audit/retention requirements; PF-05/06.
- Migration impact: Retired/versioned rows, correction events, snapshots, or recalculation metadata may be required.
- Test acceptance criteria: Historical records remain auditable, approved corrections recalculate affected outputs, and unauthorized deletion is rejected.

## T-20 — Notifications and external delivery

**Status:** NEEDS_DECISION

**Decision question:** Are Tournament registration, fixture, result, cancellation, attendance, or standings notifications required? Define recipients, consent, channels, templates, retries, failure state, retention, and external delivery boundaries.

- Approved notification policy/channels: __________________________
- Rejected policy: _______________________________________________
- Needs discussion: _____________________________________________
- Resolution/comments: __________________________________________
- Approver identity: ____________________________________________
- Approval date: ________________________________________________
- Effective date: _______________________________________________
- Current implementation evidence: No Tournament-specific notification trigger, recipient, email/WhatsApp/SMS integration, retry, or delivery audit exists.
- Related requirements/decisions: Notifications decision records; REQ-ADV-001; MT-20; consent and sensitive-data policies.
- Migration impact: Notification event/delivery state may require schema; external deployment evidence is required.
- Test acceptance criteria: No external delivery occurs without approved consent, recipient scope, channel, retry, failure, and audit behavior.

## Cross-cutting migration and deployment impact

- Migration 20260904000400_CreateTournamentManagement is the current Tournament schema dependency and must follow branches, Grounds, Players, users/roles, permissions, and Matches.
- Any shared-branch, hierarchy, registration, scorecard, selection, lifecycle, history, retention, export-audit, or notification decision may require a forward-only migration.
- Applying permission changes requires session refresh/sign-out and sign-in.
- No safe Tournament-specific persistent test seeder was found.
- TournamentManagementIntegrationTest uses destructive emptyTable() cleanup and must not run against the persistent mcc_cricket_club_test database without replacement.

## Current blockers

All T-01 through T-20 remain NEEDS_DECISION. No Tournament expansion is authorized by this request. Existing CRUD code is evidence of an implementation baseline, not approval of its broader business rules.


