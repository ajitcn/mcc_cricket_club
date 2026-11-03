# MCC Cricket Club Management System — Requirements Review

Status: Draft for client and architecture review  
Source: `docs/SRS-original.md`, SRS Version 1.0  
Purpose: Convert the original SRS into an implementation-ready requirements specification without modifying the original SRS.

This document is analysis, not implementation. It does not approve database tables, modules, permissions, workflows, calculations, integrations, or UI decisions.

Reconciliation note (2026-08-28): `docs/DECISIONS.md` is the current authoritative decision register. Its explicit APPROVED records supersede the corresponding historical NEEDS_DECISION entries in this analysis. The unresolved items added by the current review remain NEEDS_DECISION in `DECISIONS.md`; this document is retained as the analysis/audit trail and is not itself approval evidence.

## 1. Scope and traceability

The SRS describes a centralized responsive web and mobile-ready platform for a multi-branch cricket academy. Its goals are to digitize admissions, coaching, attendance, performance, fitness, tournaments, communication, reporting, and administration while maintaining centralized player records. (SRS 1.1, 2)

The SRS contains twenty named functional modules (SRS 4, Modules 1–20), non-functional requirements (SRS 5), future integrations (SRS 6), and a final "Lead management" heading without additional detail (SRS 7).

Traceability convention:

- SRS 1–3: purpose, objectives, and roles
- SRS 4, Module N: functional requirements
- SRS 5: non-functional requirements
- SRS 6: future integrations
- SRS 7: lead-management heading with no detailed requirements

The original source contains formatting and wording ambiguities. This review preserves the intent where clear and marks unresolved behavior as `NEEDS_DECISION`.

## 2. Current technical boundary

The current MCC foundation uses CodeIgniter 4.7+, PHP 8.2+, MySQL/MariaDB, PHPUnit 10, PSR-4, explicit routes, disabled auto-routing, shared application layers, and an empty `app/Modules` boundary.

The future business modules should use:

```
app/Modules/ModuleName/
├── Config/Routes.php
├── Controllers/
├── Models/
├── Services/
└── Views/
```

No business module, migration, or business workflow is approved by this document.

## 3. Functional module catalogue

### 3.1 Lead Management / CRM

Source: SRS Module 2, SRS 7, marketing requirements in Modules 2 and 19.

Required capabilities:

- Create and maintain leads.
- Record lead source: Google, Meta Ads, Instagram, Website, Walk-In, Referral, School, WhatsApp, or Other.
- Associate a campaign where available.
- Assign or record a counselor.
- Record lead status.
- Maintain follow-up history.
- Record next follow-up date.
- Record remarks.
- Record admission probability.
- Book trials.
- Record trial attendance.
- Record selection.
- Convert a lead through admission into an active player.
- Handle lost leads.
- Report admissions, conversions, funnel stages, campaigns, sources, branches, and trends.
- Support future campaign ROI and cost-per-admission reporting.

The SRS does not define the lead status vocabulary, ownership rules, duplicate policy, reassignment policy, probability semantics, lost-lead reasons, or conversion rules. See NEEDS_DECISION LD-01 through LD-10.

### 3.2 Admission Management

Source: SRS Module 2.

Required flow stated by the SRS:

```
Lead
→ Trial Booked
→ Trial Attended
→ Selected
→ Admission Pending
→ Admission Completed
→ Active Player
```

Required information includes lead source, campaign-related reporting, counselor, follow-up history, next follow-up, remarks, probability, and conversion reporting.

Follow-Up behavior is defined by the single authoritative contract recorded in `DECISIONS.md` as approved decision `LD-13`. This document does not duplicate Follow-Up policy.

Dependencies: Lead, Trial, Branch, Player, User/RBAC, notifications, and reporting.

Admission Elite Camp implementation note (2026-10-13): the existing `High Performance Elite Camp`
choice remains `Yes` or `No`. When it is `Yes`, `elite_camp_days` reuses the approved coaching-day
values `1 Day`, `2 Days`, `3 Days`, `4 Days`, and `5 Days`; `elite_camp_duration` reuses the approved
coaching-duration values `1 hour`, `1.5 hours`, and `2 hours`. Both values are nullable, are required
only for `Yes`, and are cleared server-side for `No`. No new option vocabulary or authorization rule
is introduced.

### 3.3 Player Management

Source: SRS Module 1 and Module 20.

Player records must support:

- Automatically generated Player ID
- Photo
- Full name
- Date of birth
- Gender
- Age group
- Branch
- Batch
- Joining date
- Blood group
- Medical conditions
- Jersey size
- Playing role
- Batting style
- Bowling style
- Parent information
- Emergency contacts
- Documents
- Player status: Lead, Trial, Selected, Active, Inactive, Suspended, Left Academy, Alumni
- Duplicate detection using mobile number plus date of birth
- Chronological player timeline
- QR code on profile
- Tags such as Scholarship, Elite, Fast Bowler, or Rehab

Dependencies: Admission, Branch, Batch, Parent/Player accounts, Documents, Attendance, Performance, Fitness, Matches, Development, and Audit Logs.

The SRS does not define whether Lead and Trial are player statuses or pre-player admission stages. See PL-01.

### 3.4 Coach Management

Source: SRS Module 3.

Store:

- Experience
- Qualification
- Certifications
- Availability
- Specialization
- Leave
- Assigned branch
- Assigned sessions

Coach KPIs:

- Sessions conducted
- Attendance compliance
- Player feedback rating
- Assigned players
- Performance improvement index

Dependencies: Users, Branches, Sessions, Attendance, Player Development, Performance, Fitness, and Leave/Availability.

KPI formulas and coach access boundaries are undefined.

### 3.5 Attendance

Source: SRS Module 4.

Player attendance statuses:

- Present
- Absent
- Leave
- Medical
- Late
- Rain Cancelled
- Coach Cancelled

The system must calculate daily, weekly, monthly, and yearly percentages and provide alerts for:

- Attendance below 75%
- Continuous absence
- Late arrivals

Dependencies: Sessions, Players, Batches, Branches, Coaches, holidays/cancellations, and Notifications.

Cancellation denominator and Late treatment are undefined. See AT-01 through AT-06.

### 3.6 Performance Tracking

Source: SRS Module 5.

Batting:

- Runs
- Balls
- Strike rate
- Average
- Boundary count
- Highest score

Bowling:

- Overs
- Wickets
- Economy
- Dot balls
- Best figures

Fielding:

- Catches
- Run outs
- Stumpings
- Drop catches

Coach ratings:

- Technique
- Discipline
- Attitude
- Communication
- Leadership

Monthly progress graphs are required. Calculation formulas, source events, correction policy, and review ownership are undefined. See PF-01 through PF-06.

### 3.7 Fitness Management

Source: SRS Module 6 and fitness fields in Module 5.

Store:

- Height
- Weight
- BMI
- Body fat
- Vertical jump
- Sprint time
- Agility
- Flexibility
- Yo-Yo
- Endurance

Generate:

- Monthly fitness reports
- Fitness trend graphs
- Coach notes
- Fitness risk alerts
- Fitness programs

Dependencies: Player, Fitness Coach, Coach, Development, Notifications, Reports, and sensitive-data controls.

BMI formula, measurement units, risk thresholds, visibility, and correction policy are undefined.

### 3.8 Session Management

Source: SRS Module 7.

Session focus options:

- Batting
- Bowling
- Match Simulation
- Throwdowns
- Video Analysis
- Mental Training
- Fitness
- Fielding

Additional fields:

- Duration
- Ground
- Equipment
- Session rating
- Weather
- Attendance
- Coach notes

Dependencies: Branch, Batch, Player, Coach, Ground, Equipment, Scheduling, Attendance, and Notifications.

The SRS does not define recurring schedules, capacity, booking ownership, rescheduling, or session status.

### 3.9 Ground Management

Source: SRS Module 8.

Required capabilities:

- Manage indoor nets
- Manage outdoor nets
- Ground allocation
- Slot booking
- Coach assignment
- Rain shift
- Equipment availability
- Ground maintenance calendar

Dependencies: Branch, Sessions, Coaches, Equipment, Scheduling, Weather, and Maintenance.

Booking conflict rules, overbooking, priority, and cross-branch use are undefined.

### 3.10 Match Management

Source: SRS Module 9.

Store:

- Opponent
- Venue
- Tournament
- Date
- Playing XI
- Result
- Scorecard
- Player performance
- Coach remarks
- MVP
- Photo gallery
- Video highlights

Dependencies: Players, Coaches, Branches, Tournaments, Performance, Documents/Media, and Notifications.

The relationship between a match and tournament, scoring depth, editing rights, and selection rules are undefined.

### 3.11 Tournament Management

Source: SRS Module 10.

Tournament categories include:

- School
- Club
- District
- MCA
- State
- National
- International
- England Tour

Track:

- Fixtures
- Performance
- Awards
- Certificates
- Selection status

Dependencies: Matches, Players, Branches, Documents, Performance, Awards, Certificates, and Notifications.

Tournament hierarchy, shared tournaments, selection approval, and certificate ownership are undefined.

### 3.12 Player Development

Source: SRS Module 11.

Required information:

- Monthly reviews
- Coach ratings
- Goals
- Improvement percentage
- Skill development
- Mental strength
- Leadership
- Discipline
- Attendance
- Recommendations

Dependencies: Player, Coach, Attendance, Performance, Fitness, Goals, and High Performance.

Review cadence, scoring scales, improvement formula, approval, and visibility are undefined.

### 3.13 High Performance Program

Source: SRS Module 12.

Required information:

- Video analysis
- Coach reviews
- Individual Development Plan
- Monthly targets
- Skill videos
- Selection readiness
- Performance benchmarks

Recommended boundary: this should be a specialized program/tracking layer that consumes normal Performance, Fitness, Development, and Media data. It should not duplicate those records.

The SRS does not define eligibility, enrollment, exit, selection authority, or benchmark formulas. See HP-01 through HP-05.

### 3.14 Parent Portal

Source: SRS Module 13.

Parents may access:

- Attendance
- Performance
- Homework
- Announcements
- Photos
- Videos
- Upcoming matches
- Holiday notices
- Coach feedback
- Fitness reports

Visibility must be limited to authorized linked children. Parent account relationships are unresolved. See REL-01 through REL-05.

### 3.15 Player Portal

The SRS explicitly lists Player as a role and says players can view:

- Attendance
- Performance
- Goals
- Match statistics
- Coach recommendations

The portal should be treated separately from the Parent Portal at the access-policy level, even if the frontend later shares components.

### 3.16 Documents

Source: SRS Modules 1 and 14.

Document types:

- Aadhaar
- Birth Certificate
- School ID
- MCA Card
- Medical Certificate
- Passport
- Visa
- Consent Forms
- Signed Forms
- Player photo

Required capabilities:

- Secure storage
- Upload
- Verification
- Expiry tracking
- Controlled download
- Replacement
- Deletion or retirement
- Audit history

Sensitive-data handling is governed by `AGENTS.md`. Visibility and verification ownership require decisions. See DOC-01 through DOC-08.

### 3.17 Reports

Source: SRS Module 15.

Reports named by the SRS:

- Attendance
- Admissions
- Coach
- Branch
- Ground
- Fitness
- Performance
- Tournament
- Marketing
- Player
- Session

Formats:

- PDF
- Excel
- CSV

Each report requires defined audience, filters, columns, branch scope, permissions, date semantics, and data sources. These are not yet specified.

### 3.18 Dashboard

Source: SRS Module 16.

KPIs:

- Players today
- New admissions
- Active players
- Trials scheduled
- Attendance percentage
- Sessions running
- Coaches available
- Grounds occupied
- Upcoming tournaments
- Top performers
- Players needing attention
- Expiring documents
- Birthday alerts
- Recently joined players

Charts:

- Monthly admission trends
- Branch comparison
- Attendance heatmap
- Coach workload
- Fitness improvement
- Performance trends

Dashboard data must be role-aware and branch-scoped. KPI definitions and refresh expectations are undefined.

### 3.19 Notifications

Source: SRS Module 17.

Channels:

- WhatsApp
- Email

Events:

- Birthdays
- Sessions
- Trials
- Events
- Document expiry
- Attendance warning
- Tournament selection

Separate core notification events from future channel integrations. The SRS does not define templates, recipients, timing, opt-out, retries, or delivery logs.

### 3.20 Branch Management

Source: SRS Module 18.

Initial examples:

- Santacruz
- Thane
- Bhandup

Every record belongs to one branch, Branch Admins only see their own branch, and Super Admin sees all authorized cross-branch data.

The branch relationship must be analyzed per entity. Shared entities and transfers require decisions.

### 3.21 Marketing Dashboard

Source: SRS Module 19.

Track:

- Lead source
- Campaign
- Conversion
- ROI
- Admissions
- Trials
- Branch comparison
- Monthly trends
- Google versus Meta
- Cost per admission

Currently available operational data can support source, campaign, trial, admission, conversion, and branch reporting. ROI, ad spend, and cost-per-admission require an approved cost data source or future advertising integration. See MKT-01 through MKT-04.

### 3.22 360° Player Profile

Source: SRS Module 20.

A single aggregation/view layer should present:

- Personal information
- Attendance history
- Fitness
- Match statistics
- Videos
- Photos
- Coach feedback
- Goals
- Tournament history
- Achievements
- Certificates
- Selection history
- Injury history
- Development timeline

The view should read from owning modules and must not duplicate authoritative data.

## 4. Supporting capabilities and dependencies

These are implied by the SRS and should be analyzed before becoming modules:

| Capability | Responsibility | Depends on |
|---|---|---|
| Authentication | Identity, login, sessions, recovery | Users, security, notifications |
| User Management | Staff, parent, and player accounts | RBAC, branches, audit |
| Role/Permission Management | Centralized access policies | Users, roles, permissions, branch scope |
| Branch Management | Branch lifecycle and access scope | Users, operational entities |
| Batch Management | Group players for sessions and attendance | Players, branches, schedules |
| Scheduling | Recurring sessions, trials, slots, fixtures | Sessions, grounds, coaches |
| Audit Logs | Append-oriented critical-change history | All sensitive/administrative modules |
| Media/File Storage | Private documents, photos, and videos | Documents, authorization, storage integration |
| Reporting | Shared filters and exports | All operational modules |
| Notification Orchestration | Events, recipients, templates, delivery status | Users, sessions, trials, documents |
| Settings/Reference Data | Approved configurable lists and thresholds | Administration, audit |

These should not be created automatically. Their ownership and boundaries require architecture review.

## 5. User-role requirements matrix

The SRS provides broad capabilities but not a complete CRUD/approval matrix. The table records explicit access, reasonable structural implications, and unresolved actions.

| Role | View | Create | Update | Delete | Approve | Branch/data scope |
|---|---|---|---|---|---|---|
| Super Admin | All listed administration, operational, reporting, and settings data | Users, branches, coaches, players, reports, notifications, settings as stated | Same areas as authorized | NEEDS_DECISION | Permissions and administrative changes implied; final approvals NEEDS_DECISION | All branches, subject to sensitive-data policy |
| Branch Admin | Players, admissions, attendance, sessions, reports | Same areas as stated | Same areas as stated | NEEDS_DECISION | NEEDS_DECISION | Own branch only |
| Admission Team | Leads, trials, admissions, related player intake data | Leads, follow-ups, trials, admission records | Lead and admission records | NEEDS_DECISION | Selection/admission completion NEEDS_DECISION | Assigned branch or all branches NEEDS_DECISION |
| Coach | Assigned players, sessions, attendance, performance, feedback, videos | Attendance, session reports, performance, feedback, videos | Own submitted records; exact scope NEEDS_DECISION | NEEDS_DECISION | Performance/development review approval NEEDS_DECISION | Assigned branch/players; cross-branch access NEEDS_DECISION |
| Fitness Coach | Assigned player fitness data and programs | Fitness tests, measurements, programs | Fitness records and programs | NEEDS_DECISION | Fitness risk/report approval NEEDS_DECISION | Assigned branch/players |
| Nutritionist | Nutrition-related information is implied by role but not specified | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION | Branch and player scope NEEDS_DECISION |
| Reception | Operational intake, trials, schedules, and basic player information NEEDS_DECISION | Leads/trials/basic administrative records NEEDS_DECISION | NEEDS_DECISION | NEEDS_DECISION | None stated; NEEDS_DECISION | Branch only unless approved otherwise |
| Parent | Attendance, reports, feedback, events, homework, announcements, videos, photos, fitness reports | None explicitly stated | None explicitly stated | None explicitly stated | None stated | Linked children only; relationship model NEEDS_DECISION |
| Player | Attendance, performance, goals, match statistics, recommendations | None explicitly stated | None explicitly stated | None explicitly stated | None stated | Own data only; age/consent rules NEEDS_DECISION |

Global unresolved items:

- Whether “manage” includes create, update, delete, and approve.
- Whether staff can see sensitive medical and document records.
- Whether a user can hold multiple roles.
- Whether a user can belong to multiple branches.
- Whether Parent and Player accounts are mandatory.
- Whether Coach access is restricted to assigned players or branch players.
- Whether Super Admin can override all restrictions or only branch restrictions.

## 6. Player lifecycle

### 6.1 Stated lifecycle

```
Lead
→ Trial
→ Selected
→ Admission Pending
→ Admission Completed
→ Active
→ Inactive / Suspended / Left Academy / Alumni
```

The authoritative Lead/Admission pipeline is `Lead → Trial Booked → Trial Attended → Selected → Admission Pending → Admission Completed → Active Player`. Admission is the final conversion stage of the Lead/Admission pipeline; the subsequent Inactive, Suspended, Left Academy, and Alumni states belong to the Player lifecycle. The shorter lifecycle notation above is retained as historical analysis only.

### 6.2 Transition analysis

| Transition | Trigger/data | Likely responsible role | Status |
|---|---|---|---|
| New lead → Lead | Lead capture | Admission Team, Reception, approved intake users | Explicit capability; detailed rules NEEDS_DECISION |
| Lead → Trial Booked | Trial booking | Admission Team; possibly Reception | Explicit flow; booking rules NEEDS_DECISION |
| Trial Booked → Trial Attended | Attendance at trial | Admission Team/Reception/Coach NEEDS_DECISION | NEEDS_DECISION |
| Trial Attended → Selected | Trial assessment | Coach and/or Admission Team | NEEDS_DECISION |
| Selected → Admission Pending | Selection recorded | Admission Team/Branch Admin | NEEDS_DECISION |
| Admission Pending → Admission Completed | Required admission information/documents/payment if applicable | Admission Team/Branch Admin | Fees/payment and approval NEEDS_DECISION |
| Admission Completed → Active | Activation | Branch Admin/Super Admin | NEEDS_DECISION |
| Active → Inactive | Change in participation | Branch Admin/Super Admin | Reason and authority NEEDS_DECISION |
| Active → Suspended | Administrative/disciplinary decision | Branch Admin/Super Admin | Grounds, duration, appeal NEEDS_DECISION |
| Active → Left Academy | Departure | Branch Admin/Super Admin | Reason and reactivation NEEDS_DECISION |
| Active → Alumni | Alumni classification | Super Admin/Branch Admin | Criteria and timing NEEDS_DECISION |
| Any state → previous state | Correction | Authorized administrator | Whether reversals are allowed NEEDS_DECISION |

Player status history should be append-oriented for auditability. A player timeline should aggregate status, admission, attendance, performance, fitness, documents, matches, and development events rather than duplicate them.

## 7. Branch requirements

### 7.1 Branch ownership assessment

| Area | Branch relationship | Analysis |
|---|---|---|
| Players | Required | SRS says every record belongs to one branch |
| Leads | Required or intake branch | Needed for branch conversion/reporting; assignment timing NEEDS_DECISION |
| Admissions | Required | Must retain originating/current branch semantics |
| Coaches | Required | Assigned Branch is explicit; multi-branch assignment NEEDS_DECISION |
| Batches | Required | Batch belongs to a branch unless shared batches are approved |
| Sessions | Required | Ground, coach, and player branch conflicts must be controlled |
| Attendance | Inherited and/or explicit | Session branch should be authoritative; policy NEEDS_DECISION |
| Grounds | Required | Grounds belong to a branch unless shared |
| Matches | Required or multi-branch | Shared match representation NEEDS_DECISION |
| Tournaments | Required, shared, or central | Tournament may span branches |
| Reports | Filtered by branch | Branch Admin own branch; Super Admin cross-branch |
| Documents | Inherited from player/owner | Access must follow subject and document policy |

### 7.2 Cross-branch operations

The SRS implies but does not define:

- Player transfer between branches
- Coach transfer or multi-branch assignment
- Shared tournaments
- Shared matches
- Shared grounds
- Cross-branch batches
- Super Admin reporting
- Historical branch ownership
- Whether existing records move or retain origin branch
- Whether branch administrators can see transferred history

Branch scope should be enforced centrally in services/repositories/query policies and verified by tests. Frontend filtering is never sufficient.

## 8. Parent and player relationships

The SRS identifies Parent and Player roles but does not define the relationship model.

Possible models:

- One parent to one player
- One parent to multiple players
- Multiple parents to one player
- Parent account with guardian relationship type
- Player account linked directly to the player record
- Staff-managed records without portal accounts

Recommended analysis model: separate user accounts from person/player records, with an explicit relationship entity supporting multiple guardians and multiple children, relationship type, primary-contact flag, consent, and effective dates. This is a recommendation only.

Decisions required:

- Whether both parents can have accounts.
- Whether a parent can view all linked children.
- Whether a parent can view sensitive medical data.
- Whether a player can view medical, document, or parent data.
- Whether coach visibility is limited to assigned players.
- How parental consent and minor accounts work.
- Whether player accounts are available by age or approval.
- What happens when custody/relationship changes.

## 9. Attendance requirements

### 9.1 Status semantics

The SRS lists Present, Absent, Leave, Medical, Late, Rain Cancelled, and Coach Cancelled.

The system must distinguish:

- Player attendance status
- Session cancellation status
- Absence reason
- Excused versus unexcused absence

“Rain Cancelled” and “Coach Cancelled” appear to describe a session rather than an individual player status. This must be clarified.

### 9.2 Calculation analysis

The SRS requires daily, weekly, monthly, and yearly percentages but provides no formula.

A calculation requires:

- Included sessions
- Denominator treatment
- Treatment of Leave and Medical
- Treatment of Late
- Treatment of cancelled sessions
- Treatment of trial attendance
- Treatment of holidays
- Rounding
- Time zone and date boundary
- Historical corrections

No formula should be implemented before approval.

### 9.3 Alerts

Below 75%, continuous absence, and late-arrival alerts require:

- Threshold definitions
- Lookback period
- Recipient
- Deduplication
- Escalation
- Notification channel
- Branch scope

## 10. Performance requirements

Performance data should be separated into batting, bowling, fielding, and coach ratings.

The SRS does not state whether figures are entered per match, innings, session, tournament, or manually aggregated. Recommended approach: record raw event/match values and derive aggregates, subject to approval.

Required decisions:

- Strike-rate formula and zero-ball handling.
- Batting-average formula and not-out handling.
- Economy formula and legal-ball/over representation.
- Best-figures comparison.
- Boundary definition.
- Whether coach ratings use a fixed scale.
- Who may create, edit, verify, or lock results.
- Whether players/parents see raw or summarized performance.
- How corrections affect historical graphs.
- Whether performance is branch-scoped, team-scoped, or player-scoped.

## 11. Fitness requirements

Fitness records should be time-series measurements linked to a player and assessment date.

Required data includes height, weight, BMI, body fat, vertical jump, sprint, agility, flexibility, Yo-Yo, and endurance, plus programs, notes, reports, trends, and risk alerts.

Recommended separation:

- Measurement record
- Fitness assessment
- Fitness program
- Program assignment
- Coach note
- Risk alert
- Monthly report

This recommendation requires approval.

Decisions required:

- Units and accepted ranges.
- BMI calculation and whether it is stored or derived.
- Risk thresholds and who defines them.
- Visibility to parents, players, coaches, and nutritionists.
- Whether medical information is included.
- Who may edit historical measurements.
- Whether a fitness record can be deleted or only corrected.

## 12. Sessions, scheduling, and grounds

Sessions require focus, coach, players/batch, ground, duration, equipment, weather, rating, attendance, and notes.

Scheduling must resolve:

- One-time versus recurring sessions
- Batch capacity
- Player overlap
- Coach availability and leave
- Ground slot conflicts
- Equipment conflicts
- Rain shifts and rescheduling
- Cancellation reasons
- Notification timing
- Time zones and calendar dates
- Whether sessions can span branches

Ground management requires resource inventory, slot booking, allocation, coach assignment, equipment availability, and maintenance periods.

No booking conflict rule is defined. At minimum, overlapping bookings for the same ground slot, coach, or capacity-limited resource should be rejected or require explicit override. This is a recommendation requiring approval.

## 13. Matches and tournaments

A Match should own match-specific details: opponent, venue, date, playing XI, result, scorecard, player performance, coach remarks, MVP, and media.

A Tournament should own competition-level details: name/category, dates, fixtures, awards, certificates, and selection status.

Recommended relationship: Tournament aggregates Fixtures/Matches; a Match may optionally belong to a Tournament. Shared matches and cross-branch participation require explicit rules.

Decisions required:

- Whether a match can exist outside a tournament.
- Whether one match can involve multiple branches.
- Who selects the Playing XI.
- Who confirms results and scorecards.
- Whether MVP is selected by a coach or administrator.
- Whether media is public, portal-only, or private.
- Whether tournament certificates belong to players, tournament records, or documents.
- Selection approval and notification rules.

## 14. Player development

The SRS requires monthly reviews, ratings, goals, improvement percentage, skill development, mental strength, leadership, discipline, attendance, and recommendations.

Recommended model: monthly review periods with immutable submitted versions, coach authorship, optional reviewer approval, goals with status and target dates, and derived improvement metrics. This is not approved.

Dependencies include attendance, performance, fitness, coach assignment, player status, and high-performance eligibility.

Scoring scales, formulae, review locking, visibility, and editing rights are NEEDS_DECISION.

## 15. High Performance Program

This should differ from normal development by being an explicitly managed program with eligibility, enrollment, benchmarks, targets, specialized review, and selection-readiness tracking.

It should consume:

- Performance statistics
- Fitness assessments
- Development reviews
- Video analysis
- Coach reviews
- Match history

It should not duplicate those sources.

Required decisions:

- Eligibility criteria
- Enrollment authority
- Program branch scope
- Exit/suspension rules
- Selection-readiness definition
- Benchmark ownership
- Visibility to parents and players
- Whether multiple programs can overlap

## 16. Documents and sensitive data

### 16.1 Ownership

Likely ownership is subject-based:

- Player documents belong to a player.
- Consent documents may also identify a parent/guardian.
- Coach/administrative documents, if later required, belong to their user/person record.
- Photos and videos require an explicit subject and consent policy.

### 16.2 Required lifecycle

Document handling should support:

1. Upload
2. Malware/type/size validation
3. Safe private storage
4. Metadata capture
5. Verification
6. Expiry monitoring
7. Controlled download
8. Replacement/version history
9. Retirement/deletion policy
10. Audit event

### 16.3 Decisions

- Who may upload each document type.
- Who verifies it.
- Whether unverified documents are visible.
- Whether parents may view, upload, or download.
- Whether players may view documents.
- Whether medical documents are restricted to specific roles.
- Whether documents are encrypted at rest and how keys are managed.
- Retention and deletion periods.
- Whether replacement preserves old versions.
- Whether deletion means logical retirement only.
- Whether photos/videos use the same storage policy.

## 17. Notifications

### 17.1 Core event catalogue

Potential core events:

- Birthday
- Session created/changed/cancelled
- Trial booked/changed
- Event announcement
- Document nearing expiry
- Attendance threshold warning
- Tournament selection
- Match or fixture update
- Admission follow-up reminder

Only events explicitly required by the SRS should be MVP candidates; the rest require prioritization.

### 17.2 Channels

Email and WhatsApp are named. WhatsApp Business API is a future integration under SRS 6. Therefore, the notification domain should be channel-ready, but WhatsApp delivery should not be assumed to be available in MVP.

Required decisions include templates, consent, recipient selection, timing, retries, delivery status, opt-out, language, and failure handling.

## 18. Reports

| Report | Audience | Likely data source | Required filters/scope |
|---|---|---|---|
| Attendance | Branch Admin, Coach, Parent, Player, Super Admin | Sessions, attendance, players | Date, branch, batch, player, status; role scope NEEDS_DECISION |
| Admissions | Admission Team, Branch Admin, Super Admin | Leads, trials, admissions | Date, branch, status, counselor, source |
| Coach | Branch Admin, Super Admin | Coaches, sessions, attendance, feedback | Branch, coach, date |
| Branch | Super Admin, Branch Admin | Cross-module aggregates | Branch/date; visibility NEEDS_DECISION |
| Ground | Branch Admin, Super Admin | Grounds, bookings, maintenance | Branch, ground, date, status |
| Fitness | Fitness Coach, Coach, Parent, Player, Admin | Fitness records | Player, branch, date, metric; sensitive visibility |
| Performance | Coach, Player, Parent, Admin | Matches, performance | Player, branch, tournament, date |
| Tournament | Coach, Admin, Player, Parent | Tournaments, matches, awards | Tournament, branch, player, date |
| Marketing | Admission Team, Branch Admin, Super Admin | Leads, campaigns, admissions | Source, campaign, branch, date |
| Player | Authorized staff, Parent, Player | Player aggregate | Player, branch, status |
| Session | Branch Admin, Coach, Super Admin | Sessions, grounds, coaches | Branch, coach, batch, date |

All reports require PDF, Excel, and CSV behavior to be defined separately. Export authorization must be at least as strict as screen authorization.

## 19. Dashboard

Dashboard data sources include Players, Leads/Admissions, Trials, Attendance, Sessions, Coaches, Grounds, Tournaments, Documents, Fitness, Performance, and Development.

Role-specific dashboards are required conceptually:

- Super Admin: cross-branch management
- Branch Admin: own branch
- Admission Team: lead/admission funnel
- Coach: assigned sessions, attendance, and development
- Fitness Coach: fitness trends and risks
- Parent: linked-child summary
- Player: own performance and goals
- Reception: intake and scheduling summary, if approved
- Nutritionist: nutrition/fitness view, if approved

KPI definitions, date windows, refresh rate, missing-data behavior, and branch filtering are NEEDS_DECISION.

## 20. Marketing

Operationally available data:

- Lead source
- Campaign identifier
- Counselor
- Trial
- Selection
- Admission
- Branch
- Dates
- Conversion stages

Future/integration-dependent data:

- Advertising spend
- Google/Meta API data
- Cost per admission
- True ROI
- Campaign attribution across multiple touches

The SRS's Google versus Meta comparison can begin as source-based operational reporting, but must not be represented as financial ROI without approved cost data.

## 21. 360° Player Profile

The 360° profile should aggregate links/cards from:

| Profile area | Owning source |
|---|---|
| Personal information | Player |
| Attendance | Attendance |
| Fitness | Fitness |
| Match statistics | Match/Performance |
| Videos/photos | Media/Documents |
| Coach feedback | Development/Session/Coach |
| Goals | Development |
| Tournament history | Tournament/Match |
| Achievements | Tournament/Player Development |
| Certificates | Documents/Tournament |
| Selection history | Admission/Tournament/High Performance |
| Injury history | Medical/Fitness/Player; exact owner NEEDS_DECISION |
| Timeline | Audit/domain events aggregation |

The profile must enforce the viewer's authorization and must not duplicate authoritative fields.

## 22. Cross-module dependency matrix

| Upstream | Downstream | Dependency |
|---|---|---|
| Authentication/User | All modules | Identity and session |
| RBAC | All protected modules | Server-side permissions |
| Branch | All operational modules | Scope and reporting |
| Lead | Trial, Admission, Marketing | Funnel and attribution |
| Trial | Admission, Notifications, Reports | Attendance and conversion |
| Admission | Player | Conversion and lifecycle |
| Player | Batch, Attendance, Performance, Fitness, Documents | Core subject |
| Batch | Sessions, Attendance, Reports | Group scheduling |
| Coach | Sessions, Attendance, Performance, Development | Ownership and assignment |
| Fitness | Development, High Performance, Dashboard, Reports | Progress/risk |
| Session | Attendance, Ground, Coach KPIs, Notifications | Operational event |
| Ground | Sessions, Scheduling, Reports | Resource allocation |
| Match | Performance, Tournament, Player Profile | Competition data |
| Tournament | Match, Awards, Certificates, Notifications | Competition hierarchy |
| Development | Player Profile, High Performance | Review and goals |
| Documents | Player Profile, Notifications, Reports | Compliance and expiry |
| Notifications | All event-producing modules | Communication |
| Audit Logs | All critical modules | Traceability |
| Reporting | Dashboard, exports | Aggregation |

## 23. Likely business entities

This is an entity inventory, not a database design.

| Entity | Purpose | Owner | Relationships | Branch scope | Sensitivity |
|---|---|---|---|---|---|
| User Account | Authentication identity | Identity | Roles, branches, person | Usually scoped/assigned | High |
| Role | RBAC grouping | Access Control | Permissions, users | Global | Medium |
| Permission | RBAC capability | Access Control | Roles | Global | Low |
| Branch | Academy location/access boundary | Branch | Users, players, coaches, batches | Global root | Medium |
| Person/Guardian | Human contact record | Identity/Relations | Users, players | Usually branch-associated | High |
| Parent-Player Relationship | Guardian/child access | Relations | Parents, players | Inherited | High |
| Lead | Prospect and CRM history | Lead/CRM | Branch, counselor, campaign, trials | Yes | High |
| Campaign | Marketing grouping | Marketing | Leads, source | Global or branch NEEDS_DECISION | Medium |
| Lead Source | Attribution category | Marketing | Leads | Global | Low |
| Follow-up | CRM interaction | Lead/CRM | Lead, user | Inherits lead | High |
| Trial | Trial booking/attendance | Admission | Lead, player candidate, branch, coach | Yes | High |
| Admission | Conversion process | Admission | Lead, trial, player | Yes | High |
| Player | Academy participant | Player | Branch, batch, guardians, records | Yes | High |
| Player Status History | Lifecycle audit | Player | Player, user | Inherits player | High |
| Player Tag | Categorization | Player | Player | Inherits player | Medium |
| Batch | Player grouping | Scheduling | Branch, players, sessions | Yes | Medium |
| Coach Profile | Coaching data | Coach | User, branch, sessions | Yes | High |
| Coach Availability/Leave | Scheduling constraints | Coach | Coach, branch | Yes | Medium |
| Session | Training event | Session | Batch, players, coach, ground | Yes | Medium |
| Attendance Record | Player participation | Attendance | Session, player | Inherits session | High |
| Fitness Assessment | Fitness measurements | Fitness | Player, coach | Inherits player | High |
| Fitness Program | Prescribed training | Fitness | Player, coach | Yes | High |
| Fitness Risk Alert | Risk notification | Fitness | Player, assessment | Inherits player | High |
| Performance Record | Match/session performance | Performance | Player, match, coach | Inherits event | High |
| Coach Rating | Qualitative evaluation | Development | Player, coach, review | Inherits player | High |
| Development Review | Periodic review | Development | Player, coach, goals | Yes | High |
| Goal/Target | Development target | Development | Player, review/program | Inherits player | Medium |
| High Performance Enrollment | Program membership | High Performance | Player, program, coach | Yes | High |
| Video Analysis | Review/media annotation | High Performance/Media | Player, coach, video | Inherits subject | High |
| Ground | Training resource | Ground | Branch, slots, maintenance | Yes | Medium |
| Equipment | Training resource | Ground/Session | Branch, sessions | Yes | Medium |
| Ground Booking | Resource reservation | Scheduling | Ground, session, coach | Yes | Medium |
| Match | Competitive event | Match | Tournament, players, performance | Yes/shared NEEDS_DECISION | Medium |
| Tournament | Competition | Tournament | Matches, branches, awards | Shared/central NEEDS_DECISION | Medium |
| Fixture | Tournament schedule | Tournament | Tournament, match | Shared/branch NEEDS_DECISION | Medium |
| Award/Achievement | Recognition | Tournament/Player | Player, tournament | Inherits event | Medium |
| Certificate | Award/document proof | Documents/Tournament | Player, tournament | Inherits player | High |
| Player Document | Compliance record | Documents | Player, verifier | Inherits player | Very high |
| Media Asset | Photo/video | Media/Documents | Player, match, session | Inherits subject | High |
| Notification | Communication event | Notifications | User, event, channel | Inherits event | Medium |
| Report Definition/Export | Generated report | Reporting | User, filters, source | Scope-dependent | High |
| Audit Event | Critical-change history | Audit | User, target record | Inherits target | High |
| Setting/Reference Value | Configurable options | Administration | Modules | Global/branch NEEDS_DECISION | Medium |

## 24. Requirements gap inventory (historical analysis)

The following list records ambiguity identified during the original review. Current status must be checked in `DECISIONS.md`; approved records there resolve the corresponding gap, while unapproved items remain NEEDS_DECISION. This section is not itself the authoritative decision register.

### High priority: security, access, and sensitive data

- **SEC-01 — Authentication provider and account model.** The SRS requires secure authentication but does not define whether staff, parent, and player accounts share one identity model. This affects users, relationships, sessions, and recovery.
- **SEC-02 — Multi-role users.** It is unclear whether one user may hold multiple roles.
- **SEC-03 — Branch assignment.** It is unclear whether users may belong to one branch, multiple branches, or have temporary assignments.
- **SEC-04 — RBAC approval authority.** It is unclear who can create/change roles and permissions and whether Super Admin actions require audit approval.
- **SEC-05 — Sensitive medical visibility.** It is unclear which roles may view medical conditions, medical certificates, fitness risks, and injury history.
- **SEC-06 — Document visibility.** It is unclear whether parents, players, coaches, reception, or Branch Admins may view each document type.
- **SEC-07 — Document verification.** It is unclear who verifies documents and what verification states exist.
- **SEC-08 — Document retention/deletion.** It is unclear how long documents remain and whether deletion means logical retirement.
- **SEC-09 — Parent consent/minor accounts.** It is unclear how consent and player accounts are handled for minors.
- **SEC-10 — Media consent.** It is unclear whether photos and videos require explicit consent and who can publish them.
- **SEC-11 — Export security.** It is unclear which roles may export sensitive reports and documents.

### High priority: lifecycle and branch isolation

- **PL-01 — Lead/Trial as player statuses.** It is unclear whether Lead and Trial are pre-player admission stages or values in the Player status field.
- **PL-02 — Transition authorities.** It is unclear who may perform each lifecycle transition.
- **PL-03 — Transition prerequisites.** It is unclear what is required before Selection, Admission Completed, and Active.
- **PL-04 — Reversal policy.** It is unclear whether transitions can be reversed and how corrections are audited.
- **PL-05 — Suspension rules.** It is unclear who can suspend, for what reasons, for how long, and whether appeal exists.
- **PL-06 — Left Academy and Alumni rules.** It is unclear when a player becomes Left Academy or Alumni and whether reactivation is allowed.
- **BR-01 — Lead branch assignment.** It is unclear whether a lead belongs to the inquiry branch, trial branch, conversion branch, or multiple branches.
- **BR-02 — Player transfer.** It is unclear whether transfers change ownership, preserve history, require approval, or create effective dates.
- **BR-03 — Coach multi-branch assignment.** It is unclear whether coaches can work across branches.
- **BR-04 — Shared sessions/batches/grounds.** It is unclear whether resources may be shared across branches.
- **BR-05 — Shared matches/tournaments.** It is unclear whether competition records are central or branch-owned.
- **BR-06 — Cross-branch visibility.** It is unclear whether Branch Admins can see historical records after transfer.
- **BR-07 — Branch deletion/closure.** It is unclear how records are handled when a branch closes.

### High priority: CRM, admission, and money

- **LD-01 — Lead status vocabulary.** The SRS says to track lead status but gives no values.
- **LD-02 — Lead ownership/reassignment.** It is unclear who owns a lead and who may reassign it.
- **LD-03 — Duplicate leads.** It is unclear how duplicate leads are detected, merged, or retained.
- **LD-04 — Lead source attribution.** It is unclear whether one lead has one source or multiple touchpoints.
- **LD-05 — Campaign definition.** It is unclear whether campaigns are global, branch-specific, dated, or externally integrated.
- **LD-06 — Probability semantics.** It is unclear whether admission probability is manual, calculated, or a fixed percentage scale.
- **LD-07 — Trial rules.** It is unclear how many trials a lead may have, who books them, and how rescheduling works.
- **LD-08 — Lost lead handling.** It is unclear which lost reasons exist, whether lost leads can reopen, and who can close them.
- **LD-09 — Selection criteria.** It is unclear who selects a trialist and what evidence is required.
- **LD-10 — Conversion definition.** It is unclear what event counts as conversion and how conversion percentage is calculated.
- **LD-11 — Fees and payments.** The SRS mentions admission but does not define fees, discounts, payments, refunds, or financial records.
- **LD-12 — Counselor KPI rules.** It is unclear how counselor performance is measured.

### High priority: calculations and workflows

- **AT-01 — Attendance denominator.** It is unclear whether percentages use scheduled, attended, non-cancelled, or eligible sessions.
- **AT-02 — Leave and Medical treatment.** It is unclear whether they are excluded, counted as absence, or separately reported.
- **AT-03 — Late treatment.** It is unclear whether Late counts as Present, partial attendance, or absence.
- **AT-04 — Cancellation treatment.** It is unclear how Rain Cancelled and Coach Cancelled affect attendance.
- **AT-05 — Continuous absence.** It is unclear what number of absences and time window trigger the alert.
- **AT-06 — Attendance correction.** It is unclear who can edit attendance, until when, and whether changes require approval.
- **PF-01 — Performance source grain.** It is unclear whether performance is captured per innings, match, session, tournament, or manual aggregate.
- **PF-02 — Batting formulas.** Strike rate, average, boundaries, and highest score rules are not defined.
- **PF-03 — Bowling formulas.** Overs representation, economy, best figures, and dot-ball rules are not defined.
- **PF-04 — Performance correction/locking.** It is unclear who verifies or locks scorecards and records.
- **PF-05 — Coach rating scale.** It is unclear whether ratings use a fixed numeric scale, text, or both.
- **PF-06 — Graph aggregation.** It is unclear which date periods, missing values, and corrections graphs use.
- **FIT-01 — Fitness units/formulas.** Units, ranges, BMI derivation, and measurement precision are not defined.
- **FIT-02 — Fitness risk rules.** Thresholds, severity, ownership, and alerts are not defined.
- **FIT-03 — Fitness visibility.** Role-specific visibility is not defined.
- **DEV-01 — Development scoring.** Scales and improvement percentage formulas are not defined.
- **DEV-02 — Review workflow.** Authoring, review, approval, locking, and correction rules are not defined.
- **HP-01 — High-performance eligibility.** Entry criteria and authority are not defined.
- **HP-02 — Selection readiness.** The meaning and calculation of readiness are not defined.
- **HP-03 — Benchmark ownership.** It is unclear who sets and changes benchmarks.
- **HP-04 — Program lifecycle.** Enrollment, suspension, completion, and exit rules are not defined.
- **HP-05 — Normal versus high-performance data.** It is unclear which records are shared and which are specialized.

### Scheduling, grounds, matches, and tournaments

- **SCH-01 — Recurring sessions.** It is unclear whether recurrence is required.
- **SCH-02 — Booking conflict policy.** It is unclear whether conflicts are blocked, warned, or overridden.
- **SCH-03 — Capacity.** Batch, ground, and session capacity rules are not defined.
- **SCH-04 — Rain shift.** It is unclear who reschedules, how alternatives are selected, and how notifications work.
- **SCH-05 — Coach availability.** Availability and leave precedence are not defined.
- **MT-01 — Match/tournament relationship.** It is unclear whether every match belongs to a tournament.
- **MT-02 — Shared competition scope.** Cross-branch match and tournament ownership is not defined.
- **MT-03 — Playing XI authority.** Selection and approval roles are not defined.
- **MT-04 — Scorecard authority.** Creation, verification, correction, and locking are not defined.
- **MT-05 — MVP and selection status.** Rules and approvers are not defined.
- **MT-06 — Tournament hierarchy.** Tournament, fixture, tour, and event relationships are not defined.

### Relationships and portals

- **REL-01 — Parent cardinality.** One-to-many parent/child and multiple-parent relationships are not specified.
- **REL-02 — Guardian types.** Primary guardian, emergency contact, and billing contact semantics are not specified.
- **REL-03 — Parent visibility.** Child data visibility, especially medical and document data, is not specified.
- **REL-04 — Player visibility.** Own-data access and age/consent rules are not specified.
- **REL-05 — Relationship changes.** Custody, removal, and account deactivation rules are not specified.

### Notifications, reports, and integrations

- **NOT-01 — Required MVP channels.** It is unclear whether email is MVP and WhatsApp is future-only.
- **NOT-02 — Recipients/templates/timing.** Event recipients, message content, scheduling, retries, and opt-out are not specified.
- **NOT-03 — Delivery audit.** Delivery status and provider failure handling are not specified.
- **REP-01 — Report columns.** Required columns for each report are not specified.
- **REP-02 — Report filters/date semantics.** Filter definitions, timezone, and date boundaries are not specified.
- **REP-03 — Report authorization.** Role-specific report access and export authority are not specified.
- **DASH-01 — KPI formulas.** Dashboard metric formulas are not specified.
- **DASH-02 — Dashboard role variants.** Exact role dashboards are not specified.
- **DASH-03 — Refresh/performance.** Refresh frequency, caching, and the three-second target interpretation are not specified.
- **MKT-01 — ROI data source.** Ad-spend data is not available in the SRS.
- **MKT-02 — Attribution model.** First-touch, last-touch, or multi-touch attribution is not specified.
- **MKT-03 — Campaign ownership.** Campaign branch/global ownership is not specified.
- **MKT-04 — Cost per admission.** Cost fields and integration timing are not specified.

### Architecture, compliance, and operations

- **OPS-01 — Audit scope.** Exact auditable actions, retention, and access are not specified.
- **OPS-02 — Backup requirements.** “Daily automated backups” lacks retention, restore testing, encryption, and ownership.
- **OPS-03 — Storage provider.** Private document/video storage provider is not selected.
- **OPS-04 — API scope.** The SRS says web and mobile platform but does not define API consumers or endpoints.
- **OPS-05 — UI framework.** The final frontend framework is not selected.
- **OPS-06 — Bulk import/export.** File formats, validation, error reporting, and permissions are not defined.
- **OPS-07 — Search.** Search fields, ranking, branch scope, and sensitive-data behavior are not defined.
- **OPS-08 — Performance target.** “Under 3 seconds for standard operations” lacks measurement conditions and percentile.
- **OPS-09 — Internationalization.** Language, timezone, locale, and formatting requirements are not defined.
- **OPS-10 — Data retention.** Retention/deletion rules for player, medical, document, media, and audit data are not defined.

## 25. Recommended decisions register

Recommendations below are proposals, not approvals.

| ID | Current SRS wording | Why ambiguous | Recommended option | Alternatives | Impact |
|---|---|---|---|---|---|
| SEC-01 | Secure authentication with RBAC | Account types and identity boundary absent | One database-backed identity model with linked staff/parent/player profiles | Separate staff and portal identities; external identity provider | Determines users, sessions, recovery, and portal architecture |
| SEC-02 | Multiple named roles | Multi-role behavior absent | Allow multiple roles with least-privilege union and explicit branch scope | One role per user; prioritized role | Affects permission evaluation and UI |
| SEC-03 | Branch Admin limited to own branch | User multi-branch assignment absent | Store explicit branch assignments and effective dates | One branch only; global staff exception | Affects every protected query |
| SEC-04 | Super Admin manages permissions | Approval/audit absent | Central RBAC with audited permission changes and no controller hardcoding | Static permissions; two-person approval | Affects governance and audit |
| SEC-05 | Medical conditions/fitness data listed | Sensitive visibility absent | Restrict medical detail to explicitly authorized admin/fitness roles; parent/player visibility configurable | All branch staff; parent-only; no portal visibility | High privacy and safeguarding impact |
| SEC-06 | Secure document storage | Per-type access absent | Define a document-type policy matrix before implementation | Uniform player-level access; role-only access | Affects storage authorization and portals |
| SEC-07 | Documents need secure storage/download | Verification absent | Statuses: pending, verified, rejected, expired, retired; verifier and audit required | Boolean verified flag; no verification | Affects compliance workflow |
| SEC-08 | Download anytime | Retention absent | Logical retirement with configurable retention; physical deletion restricted | Permanent retention; hard deletion | Affects privacy, storage, and audit |
| SEC-09 | Parent and Player roles | Minor consent absent | Require guardian consent and configurable player-account eligibility | No player accounts; all players accounts | Affects portal rollout and legal safeguards |
| SEC-10 | Photos/videos | Consent absent | Track media consent and restrict publication by subject/role | Treat media as ordinary documents; global consent | Affects media storage and notifications |
| SEC-11 | Reports/export formats | Sensitive export scope absent | Export permissions separate from view permissions and audited | Same as view; exports disabled for sensitive data | Affects data leakage risk |
| PL-01 | Status list includes Lead/Trial | Stage versus status unclear | Keep Lead/Trial in CRM/admission; Player status begins at Selected or Admission Pending | One unified lifecycle field | Affects schema and workflow clarity |
| PL-02 | Admission flow listed | Transition actors absent | Admission Team records intake; Coach recommends selection; Branch Admin approves activation | Branch Admin owns all; Coach owns selection | Affects RBAC and audit |
| PL-03 | Admission Completed → Active | Prerequisites absent | Require approved admission checklist; do not assume payment until decided | Minimal manual activation; payment-gated activation | Affects onboarding |
| PL-04 | Statuses listed | Reversal absent | Allow controlled correction/reversal with reason and audit | Immutable forward-only transitions | Affects data integrity |
| PL-05 | Suspended | Rules absent | Require reason, actor, start/end dates, and audit; approval by Branch/Super Admin | Immediate indefinite suspension | Affects safeguarding and operations |
| PL-06 | Left Academy/Alumni | Criteria absent | Separate departure event from alumni classification; explicit reactivation policy | Treat Alumni as terminal status | Affects reporting and lifecycle |
| BR-01 | Every record belongs to branch | Lead timing absent | Assign lead to intake branch; preserve transfer history | Assign only at trial/admission | Affects CRM reporting |
| BR-02 | Branch Admin own branch | Transfer absent | Explicit approved transfer with effective date and historical ownership | Update branch in place; duplicate record | Affects audit and historical reporting |
| BR-03 | Coach assigned branch | Multi-branch absent | Support multiple authorized branches if operationally required | One branch only | Affects scheduling |
| BR-04 | Grounds/sessions/batches listed by branch implication | Shared resources absent | Default to branch-owned; model explicit shared access later | Fully shared resources | Affects conflict detection |
| BR-05 | Branch comparison/tournaments | Shared competition absent | Tournament global, fixtures/matches linked to participating branches | Tournament branch-owned | Affects reporting |
| LD-01 | Track lead status | Vocabulary absent | Use configurable approved statuses with ordered funnel and terminal lost state | Hardcoded status enum | Affects CRM workflow |
| LD-02 | Counselor field | Ownership absent | One accountable owner plus audited reassignment | Multiple owners; branch queue | Affects workload and follow-up |
| LD-03 | Duplicate player rule only | Lead duplicate absent | Duplicate warning using configurable contact/DOB rules; no automatic merge | Hard block; allow duplicates | Affects data quality |
| LD-04 | Lead source list | Attribution absent | Store primary source plus optional campaign/touchpoint history | One source only | Affects marketing accuracy |
| LD-05 | Campaign mentioned | Definition absent | Campaign has name, source, dates, branch scope, active state | Free-text campaign | Affects reporting |
| LD-06 | Admission probability | Formula absent | Manual probability on approved scale, clearly labeled non-authoritative | Calculated model; omit field | Affects dashboards |
| LD-07 | Trial booked/attended | Booking policy absent | Trial is schedulable event with reschedule/cancel reason and attendance record | Simple date field | Affects scheduling |
| LD-08 | Lost lead handling | Reasons/reopen absent | Required lost reason, closed date, optional reopen with audit | Permanent lost state | Affects funnel |
| LD-09 | Selected stage | Criteria absent | Coach recommendation plus authorized admission/admin confirmation | Admission-only selection | Affects workflow |
| LD-10 | Conversion percentage | Formula absent | Define conversion as approved admission completion or Active; report both if needed | Trial-to-active only | Affects KPI consistency |
| LD-11 | Admission management | Fees absent | Keep payments out of scope until approved; do not infer financial tables | Add billing/payment module | Major scope/security impact |
| AT-01 | Attendance percentages | Denominator absent | Calculate over eligible non-cancelled scheduled sessions | Count all scheduled sessions | Affects alerts/reports |
| AT-02 | Leave/Medical | Treatment absent | Exclude excused Leave/Medical from denominator and report separately | Count as absent | Affects fairness |
| AT-03 | Late | Treatment absent | Count as attended with separate late metric unless approved otherwise | Partial attendance; absent | Affects thresholds |
| AT-04 | Cancelled statuses | Session/player distinction absent | Store cancellation on session; exclude cancelled sessions from denominator | Keep as player statuses | Clarifies data model |
| AT-05 | Continuous absence | Window absent | Configurable consecutive eligible absences, initially decision-only | Fixed count | Affects alerts |
| AT-06 | Attendance correction | Editing absent | Coach edits within period; Branch Admin approves late corrections; audit all changes | Admin-only editing | Affects trust |
| PF-01 | Performance fields | Grain absent | Raw records per match/innings with derived aggregates | Manual aggregate fields | Affects calculation integrity |
| PF-02 | Batting metrics | Formulas absent | Adopt standard cricket formulas after domain approval | Manual values | Affects reports |
| PF-03 | Bowling metrics | Over notation absent | Store legal balls and derive overs/economy | Store decimal overs | Affects precision |
| PF-04 | Scorecard | Verification absent | Coach enters, authorized reviewer verifies/locks | Any admin edits | Affects integrity |
| PF-05 | Coach ratings | Scale absent | Fixed documented scale with optional comments | Free text; 1–10 | Affects comparability |
| PF-06 | Monthly graphs | Aggregation absent | Derive from locked raw records by calendar month and branch/player scope | Snapshot manual values | Affects trends |
| FIT-01 | Measurements | Units/formulas absent | Store units explicitly; derive BMI from approved height/weight values | Fixed metric units | Affects interoperability |
| FIT-02 | Risk alerts | Threshold absent | Versioned thresholds owned by approved fitness authority; alerts audited | Manual alerts only | Affects safeguarding |
| FIT-03 | Fitness visibility | Role absent | Fitness Coach/Coach access by assignment; parent/player summary only unless approved | Full detail to parents | Privacy impact |
| DEV-01 | Improvement % | Formula absent | Do not calculate until baseline, period, and scale approved | Manual percentage | Affects KPI trust |
| DEV-02 | Monthly reviews | Workflow absent | Draft → submitted → reviewed → locked with audit | Editable indefinitely | Affects historical integrity |
| HP-01 | High Performance Program | Eligibility absent | Explicit enrollment criteria approved by coaching leadership | No formal enrollment | Affects scope |
| HP-02 | Selection readiness | Meaning absent | Store assessment plus rationale; avoid opaque automatic score initially | Calculated score | Affects selection fairness |
| SCH-01 | Sessions | Recurrence absent | Support one-time first; add recurrence only if required | Full recurring scheduler | Scope impact |
| SCH-02 | Slot booking | Conflict absent | Block hard resource conflicts; allow audited admin override | Warning only | Prevents double booking |
| SCH-03 | Sessions/batches | Capacity absent | Capacity belongs to batch/ground/session and is validated server-side | Unlimited capacity | Affects scheduling |
| SCH-04 | Rain shift | Mentioned only | Reschedule same session with reason and notifications | Cancel and recreate | Affects history |
| MT-01 | Tournament field on match | Relationship absent | Optional Tournament parent; standalone matches allowed | Mandatory tournament | Affects data flexibility |
| MT-02 | Branch comparison | Shared event absent | Match supports participating branches; tournament is global when shared | Branch-owned match only | Affects reports |
| MT-03 | Playing XI | Authority absent | Coach proposes; authorized admin/coach confirms | Coach finalizes | Affects selection |
| MT-04 | Scorecard | Authority absent | Draft and locked states with correction audit | Editable forever | Affects statistics |
| REL-01 | Parent role | Cardinality absent | Many-to-many parent/player relationship with relationship type | One parent only | Affects family support |
| REL-02 | Parent information | Guardian semantics absent | Separate guardian, emergency, and primary contact concepts | Flat parent fields | Affects data quality |
| REL-03 | Parent portal | Data scope absent | Linked-child allowlist with per-data-category policy | All child data | Privacy impact |
| REL-04 | Player portal | Own view listed | Own record only, with age/consent and sensitive-data restrictions | No player login | Affects portal |
| NOT-01 | WhatsApp/Email | Delivery phase absent | Email foundation first; WhatsApp adapter future-ready | Both MVP; email only | Integration cost |
| NOT-02 | Notifications | Timing/recipients absent | Event templates, recipient policies, preferences, retries | Manual messages | Affects operations |
| REP-01 | Reports | Columns absent | Approve report definitions before implementation | Developer-defined columns | Prevents rework |
| REP-02 | Reports | Filters absent | Standard branch/date/player/batch filters where meaningful | Per-report custom only | Affects UX/API |
| REP-03 | Reports | Authorization absent | Dedicated report/export permissions | Role-wide reports | Security impact |
| DASH-01 | KPIs/charts | Formula absent | Define metric catalog with formula, source, scope, refresh | Approximate counts | Affects trust |
| MKT-01 | ROI | Spend absent | Label operational conversion reports separately from ROI | Integrate ad platforms | Prevents false ROI |
| OPS-01 | Audit logs | Exact scope absent | Audit authentication, lifecycle, RBAC, documents, sensitive changes, admin changes | Audit every read | Storage/privacy impact |
| OPS-02 | Daily backups | Detail absent | Define encrypted daily backups, retention, restore testing, owner | Provider-managed backup | Resilience impact |
| OPS-03 | Storage | Future providers listed | Private storage abstraction with approved provider | Local private filesystem; S3/R2 | Deployment/security impact |
| OPS-04 | Web/mobile | API absent | Define API only for approved mobile/integration use cases under /api/v1/ | Server-rendered web first | Scope impact |
| OPS-05 | Responsive app | UI absent | Approve one UI framework before major frontend work | Existing CDN mix; custom CSS | Consistency/maintenance |
| OPS-06 | Bulk import/export | Detail absent | Template-based import with preview, validation errors, permissions, audit | Direct upload | Data quality |
| OPS-07 | Search/filtering | Broad requirement | Define indexed fields and role/branch-aware search | Module-local search | Performance/security |
| OPS-08 | Under 3 seconds | Measurement absent | Define p95 standard operation target with dataset and environment | Average response target | Testability |
| OPS-09 | Web/mobile platform | Locale absent | Confirm English/India timezone first; document localization extension | Multi-language MVP | Product scope |
| OPS-10 | Data retention | Absent | Approve retention by entity and legal need before deletion features | Indefinite retention | Compliance/storage |

## 26. MVP versus future scope

This classification is a planning recommendation, not a removal of SRS requirements.

### MVP candidate scope

- Database-backed authentication and secure sessions
- Centralized RBAC
- Branch management and branch isolation
- User and account management
- Lead/CRM capture and follow-up
- Trial booking and attendance
- Admission lifecycle
- Player records and lifecycle history
- Batch management
- Coach profiles and assignments
- Session scheduling
- Ground and slot allocation
- Attendance and approved calculations
- Basic performance capture
- Basic fitness assessments
- Secure document metadata and private access
- Core audit logging
- Core dashboards
- Core reports with CSV/Excel first if PDF is not immediately required
- Parent and player read-only portals if relationships and consent are approved

### Future phase

- Advanced player timeline and 360° profile
- QR profile identification
- Player tags
- Coach KPI index
- Advanced development reviews
- High Performance Program
- Video analysis
- Skill video library
- Advanced tournament and award management
- Fitness risk automation
- Advanced dashboard heatmaps and trend analytics
- PDF report generation
- Bulk import/export
- Advanced notifications
- Marketing attribution and campaign analytics
- Advanced media galleries

### Integration/future-ready

- WhatsApp Business API
- Google Calendar
- Google Maps
- SMTP provider integration
- Firebase push notifications
- AWS S3 or Cloudflare R2 media storage
- Ad-spend and Google/Meta reporting
- AI-powered performance analytics

Nothing in the original SRS is deleted by this classification. All items remain requirements or future decisions.

## 27. Traceability and implementation readiness criteria

Before implementation of a business module, its requirements should have:

- SRS references
- Approved scope and ownership
- Approved branch behavior
- Approved roles and permissions
- Approved sensitive-data visibility
- Defined lifecycle/workflow
- Defined calculations
- Defined validation rules
- Defined entities and relationships
- Defined migration impact
- Defined API/report impact
- Defined tests
- Documented decisions for all ambiguous items

The original SRS remains unchanged. Any decision that changes interpretation should be recorded in separate project documentation, especially `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/RBAC.md`, or a future requirements decision log.

## Admission Follow-ups module (2026-10-16)

USER-PROVIDED IMPLEMENTATION DIRECTION. Admission Follow-ups are a separate,
branch-scoped history of one-to-many conversation/action records linked to an
Admission. The module supports list, create, view, edit, filtering, next-due
tracking, and Admission detail history. Existing CRM Lead follow-ups remain a
separate source and are not merged. Follow-up type and status use controlled
application options; notes are escaped and bounded; no delete workflow exists.
### Achievement schema reconciliation direction (2026-10-07)

**USER-PROVIDED DIRECTION - NOT CLIENT APPROVAL.** Reconcile Achievement schema drift using the canonical `achievement_awards` and `notification_recipients` tables. Preserve data and migration history; add only missing archival indexes and `archived_by`/`restored_by` foreign keys through a forward-only, idempotent migration. A3-06 automatic eligibility remains unimplemented.

### Achievement A3-06 implementation direction (2026-10-07)

**USER-PROVIDED DIRECTION - NOT CLIENT APPROVAL.** Automatic eligibility is bounded to three explicit rules: `attendance.v1` accepts a same-branch active Player with a `present` or `late` attendance source; `performance.v1` accepts a same-branch active Player with an approved performance source reaching 50 runs, 3 wickets, or 2 catches; and `coach.v1` accepts a same-branch active Coach on a completed session source. Each evaluation creates only a draft, records source/rule/time metadata, prevents duplicate active drafts, and supports auditable retry. Certificates, portal visibility, reports, notifications, and archival behavior are unchanged.
