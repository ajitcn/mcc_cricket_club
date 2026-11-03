# MCC Client Decision Workshop

Status: Prepared for client/architecture workshop
Source: `docs/DECISIONS.md`, `docs/REQUIREMENTS-REVIEW.md`

This checklist contains the workshop decision set and historical rows. `GOV-01`, `GOV-02`, `SEC-12`, `SEC-13`, `SEC-14`, and the password-recovery scope of `INT-01` are approved in `DECISIONS.md` as of 2026-08-28. The remaining rows are unresolved; every unresolved baseline below is labelled `PROPOSED` and is not an approved requirement. Until unresolved decisions are approved, the affected implementation remains blocked; P2 decisions block only their later-phase capability, not Phase 1.

## Workshop priority order

The detail rows remain grouped by subject below. Use this priority index to run the workshop in decision order. IDs shown here are references to the single detailed row below, not additional decisions.

### P0 — Must decide before Phase 1 architecture (27)

`DAT-01`, `DAT-02`, `DOC-01`–`DOC-08`, `REL-01`–`REL-05`, `AT-01`–`AT-06`, `OPS-01`, `OPS-02`, `OPS-03`, `OPS-10`, `OPS-11`, `OPS-12`.

These establish identity/recovery, consent and safeguarding, guardian relationships, sensitive documents, retention, audit, operations, data ownership, and attendance foundations.

### P1 — Must decide before the affected Phase 1 module (21)

`SCH-01`–`SCH-05`, `REP-01`–`REP-03`, `DASH-01`–`DASH-03`, `NOT-01`–`NOT-03`, `OPS-04`–`OPS-09`, `LD-13`.

These do not authorize implementation of the affected module until resolved, but they do not reopen already approved lifecycle or branch decisions.

### P2 — Future / later (27)

`PF-01`–`PF-06`, `FIT-01`–`FIT-03`, `DEV-01`–`DEV-02`, `HP-01`–`HP-05`, `MT-01`–`MT-06`, `MKT-01`–`MKT-04`, `INT-01`.

These block only their future capabilities. They must not be used to add performance, fitness, portals, tournaments, advanced media, marketing, AI, or integrations to Phase 1.

### Overlap handling

- `SEC-13`, `REL-01`–`REL-05`, and `SEC-14` should be resolved together because consent, relationship verification, recovery, and revocation interact, but they remain separate decisions.
- `DOC-07` and `OPS-10` both concern retention; `DOC-07` covers document-specific behavior and `OPS-10` covers the wider entity schedule.
- `DOC-06` and `OPS-03` both affect storage; `DOC-06` covers encryption/key ownership and `OPS-03` covers provider/deployment abstraction.
- `DASH-03` and `OPS-08` both affect performance evidence; `DASH-03` covers dashboard freshness and `OPS-08` defines the NFR-01 measurement method.

## 1. Identity / RBAC

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|

## 2. Branch isolation

No unresolved branch decisions remain: `BR-01`–`BR-07` are recorded as APPROVED in `DECISIONS.md`. Workshop confirmation is optional; implementation still requires server-side tests and evidence.

## 3. Lead → Admission → Player lifecycle

`PL-01`–`PL-06`, `LD-01`–`LD-12`, and `LD-13` are recorded as APPROVED. Follow-Up implementation must conform exactly to the approved `LD-13` contract.

### Approved Follow-Up decision

The complete Follow-Up question set, approved baseline, alternatives, consequences, acceptance impact, and approval are defined once in `DECISIONS.md` under `LD-13`. This workshop section is intentionally a cross-reference, not a second policy definition.

## 4. Attendance

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| AT-01 | Which sessions count in an attendance percentage? | PROPOSED: eligible, scheduled, non-cancelled sessions only. | Count all scheduled; count only completed. | Defines denominator and prevents cancelled sessions distorting results. | NEEDS_DECISION | Attendance reports/alerts. | Date range, branch, denominator, timezone. |
| AT-02 | How should approved leave and medical absence count? | PROPOSED: exclude from denominator and report separately. | Count as absence; include as attended. | Affects fairness and exposure of medical information. | NEEDS_DECISION | Attendance calculations. | Status and privacy tests. |
| AT-03 | Is Late attendance still attended? | PROPOSED: Present plus separate Late metric. | Partial attendance; absent. | Affects alerts and player records. | NEEDS_DECISION | Attendance reports/alerts. | Included statuses and rounding. |
| AT-04 | What happens when a session is cancelled? | PROPOSED: cancellation belongs to Session, requires reason/audit, and creates no player cancellation status. | Player-level cancellation statuses; delete session. | Preserves history and prevents denominator errors. | NEEDS_DECISION | Session/attendance workflow. | Cancellation and audit tests. |
| AT-05 | What counts as continuous absence? | PROPOSED: configurable consecutive eligible absences with versioned threshold. | Fixed count; rolling time window. | Affects safeguarding alerts and notification volume. | NEEDS_DECISION | Attendance alerts. | Threshold, window, deduplication. |
| AT-06 | Who may correct attendance and when? | PROPOSED: author correction window plus authorized late correction with reason/audit. | Admin-only; unlimited correction. | Protects historical integrity and accountability. | NEEDS_DECISION | Attendance editing. | Conflict and audit tests. |

## 5. Sensitive data / documents

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| DOC-01 | Who may upload each document type? | PROPOSED: type-specific role policy with server enforcement. | Any branch staff; guardian upload. | Limits unauthorized collection and records actor. | NEEDS_DECISION | Document upload. | Authorization and audit. |
| DOC-02 | Who verifies documents and which states exist? | PROPOSED: pending, verified, rejected, expired, retired with verifier history. | Boolean verified; no verification. | Determines trust and visibility of identity/medical records. | NEEDS_DECISION | Verification/expiry. | State-transition tests. |
| DOC-03 | Can unverified documents be viewed? | PROPOSED: restricted to authorized verification users. | Visible to linked users; visible to all branch staff. | Reduces exposure of untrusted sensitive data. | NEEDS_DECISION | Document views/downloads. | Category visibility. |
| DOC-04 | Can parents or players view, upload, or download documents? | PROPOSED: category-level policy, default deny. | Player-level access; no portal access. | Affects privacy and support workload. | NEEDS_DECISION | Portal/document access. | File authorization tests. |
| DOC-05 | Which roles may view medical documents? | PROPOSED: least privilege with explicit medical-detail policy. | All branch admins; parent-only; no portal visibility. | High safeguarding and privacy impact. | NEEDS_DECISION | Medical views/exports. | Sensitive-data tests. |
| DOC-06 | Is encryption at rest required and who manages keys? | PROPOSED: encrypted private storage with managed key ownership. | Provider-managed encryption; application encryption. | Affects deployment, recovery, and key-loss risk. | NEEDS_DECISION | Storage deployment. | Security evidence. |
| DOC-07 | How long should documents be retained, and what does deletion mean? | PROPOSED: entity/legal-need retention with logical retirement first. | Permanent retention; approved hard deletion. | Affects privacy, storage cost, and audit history. | NEEDS_DECISION | Document retirement/deletion. | Retention and deletion tests. |
| DOC-08 | Should replacement preserve old versions and media follow the same rules? | PROPOSED: immutable version history and separate media consent policy. | Replace in place; common policy. | Preserves evidence but increases storage and review duties. | NEEDS_DECISION | Replacement/media. | History and access tests. |
| OPS-03 | Where will private objects be stored? | PROPOSED: provider-neutral private storage abstraction. | Local private filesystem; S3/R2. | Determines deployment, backup, and availability controls without changing public exposure. | NEEDS_DECISION | Production storage. | Private URL/download tests. |

## 6. Guardian / player relationships

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| REL-01 | Can a player have multiple guardians and can one guardian link to multiple players? | PROPOSED: explicit many-to-many relationship with effective dates. | One parent/one child; one parent/many children. | Supports families but requires relationship authorization. | NEEDS_DECISION | Guardian records/portal. | Link and scope tests. |
| REL-02 | Which relationship types matter? | PROPOSED: guardian, emergency, and primary-contact semantics are separate. | Flat parent fields; one contact type. | Prevents incorrect authority and contact use. | NEEDS_DECISION | Player/contact model. | Validation and visibility. |
| REL-03 | What may a verified parent see for a linked child? | PROPOSED: per-category allowlist, default deny. | All linked-child data; limited attendance only. | Prevents overbroad access to medical/documents/private data. | NEEDS_DECISION | Parent portal. | Category-level privacy. |
| REL-04 | When may a player have an account and what may they see? | PROPOSED: own record only, age/consent controlled, sensitive categories excluded by default. | No player accounts; all players eligible. | Affects minor safety and support. | NEEDS_DECISION | Player portal. | Account eligibility/access. |
| REL-05 | What happens when custody or a relationship changes? | PROPOSED: effective-dated revocation, session invalidation, and audit. | Manual support action; immediate unlink only. | Prevents former guardians retaining access. | NEEDS_DECISION | Portal revocation. | Revocation/audit tests. |

## 7. Consent / safeguarding

`SEC-13` is the primary consent/legal-basis decision and is listed in the sensitive-data section because it affects documents and media. It must also cover minors, withdrawal, publication, and guardian authority before portal or media release.

## 8. Data retention

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| OPS-10 | How long should each entity type be retained, and who may retire it? | PROPOSED: retention schedule by entity/legal need, logical retirement, controlled deletion. | Indefinite retention; hard deletion schedule. | Determines privacy, storage, audit, and recovery behavior. | NEEDS_DECISION | Deletion/retention features. | Data lifecycle tests. |

## 9. Data quality / migration

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| DAT-01 | Who owns data-quality rules, duplicate review, corrections, and reference data? | PROPOSED: named module owners plus central data steward. | Module-only ownership; central steward. | Enables accountable corrections without silent merges. | NEEDS_DECISION | CRM/player/reporting. | Validation and reconciliation. |
| DAT-02 | Will existing data be migrated, and how will it be reconciled? | PROPOSED: staged mapping, validation, duplicate review, reconciliation, sign-off, rollback. | No migration; direct import. | Affects cutover risk, privacy, and data completeness. | NEEDS_DECISION | Production onboarding/import. | Migration evidence. |

## 10. KPI / calculation definitions

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| PF-01 | At what level is performance recorded? | PROPOSED: raw match/innings/source records, aggregates derived later. | Manual aggregate; session-level only. | Preserves traceability but needs source ownership. | NEEDS_DECISION | Performance. | Source and correction tests. |
| PF-02 | Which batting formulas and edge cases apply? | PROPOSED: domain-approved standard formulas with zero/not-out rules documented. | Manual values; alternate formulas. | Determines player reports and historical comparability. | NEEDS_DECISION | Performance dashboards/reports. | Formula/version tests. |
| PF-03 | How are overs, economy, dot balls, and best figures represented? | PROPOSED: legal balls as source; derived overs/economy. | Decimal overs; manual economy. | Prevents calculation and precision errors. | NEEDS_DECISION | Bowling metrics. | Units/rounding tests. |
| PF-04 | Who verifies and locks performance records? | PROPOSED: author → reviewer → locked, with correction audit. | Editable indefinitely; admin-only lock. | Protects statistics from silent changes. | NEEDS_DECISION | Scorecards/graphs. | Lock/correction tests. |
| PF-05 | What scale is used for coach ratings? | PROPOSED: fixed documented scale plus optional comments. | Free text; alternate numeric scale. | Enables comparable trends and avoids false precision. | NEEDS_DECISION | Ratings/development. | Scale validation. |
| PF-06 | How are monthly graphs aggregated and corrected? | PROPOSED: locked source records by calendar month, player, and branch scope. | Snapshot values; rolling periods. | Defines historical reporting and missing-data behavior. | NEEDS_DECISION | Trend graphs. | Aggregation tests. |
| FIT-01 | Which fitness units, ranges, and precision apply? | PROPOSED: explicit units and accepted ranges per measurement. | Fixed metric units; free-form values. | Affects safety and interoperability. | NEEDS_DECISION | Fitness records. | Range/unit tests. |
| FIT-02 | What triggers a fitness risk alert? | PROPOSED: versioned thresholds owned by approved fitness authority. | Manual alerts; provider standards. | High safeguarding impact and notification consequences. | NEEDS_DECISION | Fitness alerts. | Threshold/version tests. |
| FIT-03 | Which roles see fitness detail? | PROPOSED: assignment-based staff access; portal summary only until approved. | Full parent visibility; coach-only. | Controls medical-adjacent exposure. | NEEDS_DECISION | Fitness views/reports. | Privacy tests. |
| DEV-01 | How is improvement measured? | PROPOSED: no percentage until baseline, period, scale, and missing-data rules are approved. | Manual percentage; alternate score. | Prevents misleading rankings and coaching decisions. | NEEDS_DECISION | Development KPIs. | Formula tests. |
| DEV-02 | Who submits, reviews, locks, and corrects development reviews? | PROPOSED: draft → submitted → reviewed → locked. | Editable indefinitely; admin-only approval. | Preserves historical coaching evidence. | NEEDS_DECISION | Development reviews. | Workflow/audit tests. |
| HP-01 | Who qualifies for the High Performance Program? | PROPOSED: approved eligibility criteria and enrollment authority. | Coach discretion; open enrollment. | Affects fairness and sensitive selection data. | NEEDS_DECISION | HPP. | Eligibility tests. |
| HP-02 | What does selection readiness mean? | PROPOSED: assessment plus rationale; no opaque automatic score initially. | Calculated score; coach-only label. | Prevents unexplained selection outcomes. | NEEDS_DECISION | HPP/readiness. | Explainability/audit. |
| HP-03 | Who owns and changes benchmarks? | PROPOSED: named coaching authority with versioned approvals. | Branch coach; vendor benchmarks. | Changes affect historical comparisons. | NEEDS_DECISION | HPP/fitness/performance. | Version tests. |
| HP-04 | What are enrollment, suspension, completion, and exit rules? | PROPOSED: explicit lifecycle with reasons and audit. | Informal membership; permanent enrollment. | Affects access, reporting, and safeguarding. | NEEDS_DECISION | HPP lifecycle. | Transition tests. |
| HP-05 | Which data is shared with normal coaching and which is specialized? | PROPOSED: consume authoritative source records without duplication. | Duplicate HPP records; isolated data. | Avoids conflicting sources of truth. | NEEDS_DECISION | HPP data model. | Ownership/consistency tests. |
| MKT-01 | Where does ad-spend data for ROI come from? | PROPOSED: operational conversion reporting only until an approved source exists. | Manual spend; Google/Meta integration. | Prevents false ROI and credential-sharing risk. | NEEDS_DECISION | Marketing ROI. | Source/reconciliation tests. |
| MKT-02 | Which touchpoint gets conversion credit? | PROPOSED: record source/touchpoints first; defer ROI attribution. | First-touch; last-touch; multi-touch. | Affects campaign decisions and privacy scope. | NEEDS_DECISION | Marketing dashboard. | Attribution tests. |
| MKT-03 | Are campaigns global or branch-scoped? | PROPOSED: explicit branch scope and active dates. | Global only; branch-only. | Affects branch isolation and reporting. | NEEDS_DECISION | Campaign management. | Scope tests. |
| MKT-04 | How is cost per admission calculated and when? | PROPOSED: no metric until cost source, denominator, and period are approved. | Manual cost; integrated spend. | Prevents unsupported financial claims. | NEEDS_DECISION | Marketing KPI. | Formula/version tests. |

## 11. Reports / exports / dashboards

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| REP-01 | What columns must each report contain? | PROPOSED: approve a report catalogue before implementation. | Developer-defined columns; one generic report. | Controls sensitive exposure and user expectations. | NEEDS_DECISION | Reports/exports. | Field allowlist. |
| REP-02 | What filters and date meanings apply? | PROPOSED: standard branch/date/player/batch filters with Asia/Kolkata semantics. | Per-report custom filters. | Affects scope and reproducibility. | NEEDS_DECISION | Reports/dashboards. | Filter/timezone tests. |
| REP-03 | Who may view versus export each report? | PROPOSED: dedicated report/export permissions and audit. | Same as view; exports disabled. | Limits bulk data leakage. | NEEDS_DECISION | Reporting/exports. | Authorization/export tests. |
| DASH-01 | Which dashboard KPIs and formulas are approved? | PROPOSED: metric catalogue with formula, source, scope, refresh, and version. | Approximate counts; manual dashboard. | Prevents authoritative-looking unsupported metrics. | NEEDS_DECISION | Operational dashboard. | KPI formula tests. |
| DASH-02 | Which dashboard does each role see? | PROPOSED: category-level, branch-scoped views using least privilege. | One dashboard; role-specific variants. | Affects sensitive-data minimization. | NEEDS_DECISION | Dashboards. | Scope/privacy tests. |
| DASH-03 | How fresh must dashboards be and how is performance measured? | PROPOSED: define refresh/cache behavior and use NFR-01 p95 measurement. | Real-time; scheduled refresh. | Affects cost, consistency, and user expectations. | NEEDS_DECISION | Dashboards/ops. | Freshness/load tests. |

## 12. Notifications

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| NOT-01 | Which notification channels are required in each phase? | PROPOSED: email foundation first; WhatsApp only after integration approval. | Both in MVP; manual messages. | Determines credentials, privacy, cost, and outage behavior. | NEEDS_DECISION | Notifications. | Delivery/channel tests. |
| NOT-02 | Who receives which message, when, and with what opt-out? | PROPOSED: event templates, recipient policy, preferences, retry policy. | Fixed recipients; no opt-out. | Prevents unwanted disclosure and notification spam. | NEEDS_DECISION | Notifications. | Recipient/privacy tests. |
| NOT-03 | How are delivery status and provider failures recorded? | PROPOSED: delivery log, safe retry, no corruption of source records. | Best effort; synchronous failure. | Affects reliability and audit without changing authoritative data. | NEEDS_DECISION | Notifications. | Failure/idempotency tests. |

## 13. Operations / incident response

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| OPS-01 | Which actions are audited, for how long, and who can view audit data? | PROPOSED: critical auth, lifecycle, RBAC, document, sensitive, export, and admin events; append-oriented storage. | Audit every read; minimal audit. | Balances evidence, privacy, and storage. | NEEDS_DECISION | Audit release. | Actor/action/entity/time/branch. |
| OPS-02 | What backup retention, recovery objectives, and restore ownership apply? | PROPOSED: encrypted daily backups and quarterly restore tests with approved RPO/RTO. | Provider-managed backup; different cadence. | Affects resilience and operational cost. | NEEDS_DECISION | Backup/restore. | Restore evidence. |
| OPS-04 | Which API consumers and endpoints are actually needed? | PROPOSED: only approved consumers under `/api/v1/`. | Server-rendered web first; mobile API. | Limits attack surface and contract burden. | NEEDS_DECISION | API work. | API auth/failure tests. |
| OPS-05 | Which UI framework should be approved? | PROPOSED: one maintained framework before major frontend work. | Existing/custom CSS; alternative framework. | Affects accessibility, dependencies, and maintenance. | NEEDS_DECISION | Frontend implementation. | Accessibility tests. |
| OPS-06 | How should bulk imports/exports work? | PROPOSED: templates, preview, validation errors, permissions, audit, and reconciliation. | Direct upload; no bulk import. | Affects data quality and leakage risk. | NEEDS_DECISION | Bulk operations. | Validation/idempotency. |
| OPS-07 | Which fields are searchable and how is sensitive data handled? | PROPOSED: indexed, branch-aware allowlisted search fields. | Broad full-text search; module-local search. | Affects inference risk and performance. | NEEDS_DECISION | Search. | Scope/privacy/load tests. |
| OPS-08 | What dataset, concurrency, and environment define the 3-second target? | PROPOSED: production-like benchmark with p95 and recorded methodology. | Average response; synthetic dataset. | Makes NFR-01 testable and comparable. | NEEDS_DECISION | Performance evidence. | Load-test evidence. |
| OPS-09 | What language, locale, and date formatting are required? | PROPOSED: English/India locale with Asia/Kolkata initially. | Multi-language MVP; alternate locale. | Affects validation, reports, and date interpretation. | NEEDS_DECISION | UX/reporting. | Locale/timezone tests. |
| OPS-11 | What is the incident response process? | PROPOSED: severity, containment, notification, evidence, recovery, review, ownership. | Internal runbook; managed response. | Required for sensitive-data and availability incidents. | NEEDS_DECISION | Production operations. | Incident exercise evidence. |
| OPS-12 | What monitoring and alerting is required? | PROPOSED: redacted metrics/logs, auth/upload/backup alerts, escalation and retention. | Platform monitoring; managed monitoring. | Detects failures without exposing secrets. | NEEDS_DECISION | Production operations. | Alert/redaction tests. |

### Scheduling and competition decisions

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| SCH-01 | Are recurring sessions required in the first release? | PROPOSED: one-time sessions first; recurrence only after approval. | Full recurrence in MVP; manual session creation. | Limits scheduling complexity and preserves clear session history. | NEEDS_DECISION | Sessions/scheduling. | Schedule lifecycle tests. |
| SCH-02 | What should happen when resources conflict? | PROPOSED: block hard conflicts, with audited authorized override. | Warn only; allow all overlaps. | Prevents double booking and unsafe operational assumptions. | NEEDS_DECISION | Ground/coach/equipment booking. | Concurrency/conflict tests. |
| SCH-03 | Where is capacity defined? | PROPOSED: validate capacity at batch, ground, and session boundaries. | Unlimited capacity; batch-only capacity. | Protects roster integrity and operational planning. | NEEDS_DECISION | Roster/session assignment. | Capacity validation. |
| SCH-04 | How should rain shifts and rescheduling work? | PROPOSED: preserve the same session history, record reason, and notify approved recipients. | Cancel/recreate; manual note only. | Preserves audit trail and prevents duplicate sessions. | NEEDS_DECISION | Session rescheduling. | Idempotency/history tests. |
| SCH-05 | How do coach availability and leave affect scheduling? | PROPOSED: approved availability and leave are checked before booking. | Availability advisory only; admin override. | Prevents unauthorized workload commitments. | NEEDS_DECISION | Coach/session scheduling. | Conflict/authorization tests. |
| MT-01 | Must every match belong to a tournament? | PROPOSED: allow standalone matches and optional tournament parent. | Tournament mandatory. | Preserves source flexibility and avoids false relationships. | NEEDS_DECISION | Match management. | Relationship validation. |
| MT-02 | Can a match or tournament involve multiple branches? | PROPOSED: explicit participating branches for shared competition. | Branch-owned only; globally visible events. | Affects branch isolation and reporting. | NEEDS_DECISION | Match/tournament access. | Cross-branch tests. |
| MT-03 | Who proposes and confirms the Playing XI? | PROPOSED: coach proposes; authorized reviewer confirms. | Coach finalizes; admin finalizes. | Makes selection accountable and auditable. | NEEDS_DECISION | Match selection. | Authorization/workflow tests. |
| MT-04 | Who verifies, locks, and corrects scorecards? | PROPOSED: draft → verified → locked with correction audit. | Editable indefinitely; admin-only lock. | Protects statistics from silent changes. | NEEDS_DECISION | Scorecards/performance. | Lock/correction tests. |
| MT-05 | Who selects MVP and updates selection status? | PROPOSED: named authorized role with recorded rationale. | Coach discretion; admin discretion. | Affects fairness, notifications, and auditability. | NEEDS_DECISION | Match/tournament awards. | Approval/audit tests. |
| MT-06 | How do tournaments, fixtures, tours, and events relate? | PROPOSED: explicit hierarchy approved before schema creation. | Flat tournament records; fixture-only model. | Prevents duplicate sources and migration rework. | NEEDS_DECISION | Tournament model. | Relationship/ownership tests. |

## 14. Integrations

| ID | Question for client | PROPOSED baseline | Alternatives | Consequences / security, data, operations | Approver | Blocked implementation | Acceptance affected |
|---|---|---|---|---|---|---|---|
| INT-01 | Which approved recovery-delivery mechanism will MCC use? | APPROVED: Option B — self-managed SMTP/relay hosted and operated by MCC infrastructure, subject to the authoritative decision record. | Managed email provider; staff-assisted recovery. | Preserves enumeration resistance, token secrecy, safe retries, outage auditability, credential control, and privacy review. | Product owner + Security/Operations approver | SMTP implementation and operational deployment evidence; unrelated future integrations remain separately governed. | Database/integration tests for delivery, failure, retry/idempotency, audit, and session invalidation. |

#### INT-01 client decision summary — Password Recovery Delivery

**Status:** APPROVED on 2026-08-28.

**Selected option:** Option B — self-managed SMTP/relay hosted and operated by MCC infrastructure.

MCC owns and operates the SMTP infrastructure. Credentials/secrets must never be committed to source control and must be stored only in environment/secret configuration. SMTP authentication and TLS are mandatory where supported. Recovery responses must remain enumeration-resistant. Reset tokens must be short-lived, single-use, securely generated, and never logged. Application logs must not contain SMTP credentials, reset tokens, or sensitive recovery data. Delivery failures must produce safe generic responses. Retries must be bounded and idempotent. SMTP outages must be observable and auditable without exposing secrets. Credential rotation, SMTP availability, queue/delivery failures, and operational ownership must be documented. Production mail-server configuration remains an operational deployment responsibility.

No SMTP host, port, credential, domain, TLS mode, retry value, provider SDK, email configuration, route, migration, or delivery implementation is selected or added by this documentation update.

**Approvers:** Product owner; Security/Operations approver.

**Remaining blockers:** SMTP implementation and operational deployment evidence for `REQ-AUTH-001`; recovery-dependent portal behavior remains subject to `REQ-PORTAL-001`; unrelated future integrations under `REQ-ADV-001` remain separately governed.

## Workshop closeout

For each decision, record the client answer, approver, approval date, rationale, and any superseded decision without deleting history. Then update `DECISIONS.md`, `REQUIREMENTS.md`, and `MODULE-STATUS.md`. The project remains `FOUNDATION-ONLY` until the technical foundation has evidence; it remains blocked from Phase 1 until all relevant business decisions and acceptance criteria are approved.
