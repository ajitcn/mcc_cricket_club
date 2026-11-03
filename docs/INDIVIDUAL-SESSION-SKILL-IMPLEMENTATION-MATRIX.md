# Individual Coaching Session Skill Implementation Matrix

**Date:** 2026-10-11  
**Status:** Planning only; all rows `NEEDS_DECISION`  
**Direction record:** User reference intent; not client approval

| ID | Area | Existing evidence | Missing decision or implementation | Secure default | Permissions and branch rules | Migration impact | Test acceptance | Status |
|---|---|---|---|---|---|---|---|---|
| SKILL-01 | Skill areas | Scheduling has no skill field or catalog | Approve six-area taxonomy or alternative | Controlled six-area catalog | Existing Session permissions; authorized branch | Catalog/enum only after approval | Allowed and unknown-area validation | NEEDS_DECISION |
| SKILL-02 | Skill/shot options | No approved options | Define options and ownership | Versioned managed catalog | Active options visible only in authorized branch | Option table/indexes may be required | Search, filtering, inactive-option rejection | NEEDS_DECISION |
| SKILL-03 | Custom Coach skills | No custom workflow | Decide proposal or prohibition | No custom values | No new permission by default | None unless proposal workflow approved | Unknown values rejected | NEEDS_DECISION |
| SKILL-04 | Catalog scope | Branch isolation exists; catalog scope absent | Global versus branch catalog | Global definitions, branch-authorized usage | Session branch must match option scope | Branch key/uniqueness if scoped | Cross-branch denial | NEEDS_DECISION |
| SKILL-05 | Required selection | Existing Session has no skill requirement | Optional, primary, or required selection | Optional until approved | Existing session create/update | Nullable relation if added | Empty and valid selection paths | NEEDS_DECISION |
| SKILL-06 | Performance/Development effect | Modules are separate | Linkage and write policy absent | No automatic mutation | No cross-module writes | Link table only if approved | Session save leaves both modules unchanged | NEEDS_DECISION |
| SKILL-07 | Notes/feedback | Existing bounded notes and feedback | Separate observation field and limits | Reuse existing fields | Existing branch/feedback permissions | None if reused | Length, correction, audit, safe output | NEEDS_DECISION |
| SKILL-08 | Media/documents | Private Documents foundation; no Session linkage | Storage, scanning, retention, visibility | No upload initially | Existing document and branch checks | Link table/metadata if approved | Private access and quarantine tests | NEEDS_DECISION |
| SKILL-09 | Portal visibility | Portal relationship checks exist; no skill policy | Staff, Player, Parent, approval state | Staff only | Relationship and branch checks | Visibility/status fields if approved | Portal denial and sensitive-field exclusion | NEEDS_DECISION |
| SKILL-10 | Correction/audit | Existing transactions, audit, concurrency | Skill-specific correction policy | Reuse existing rules; reason on correction | Existing update permission | Version/reason only if needed | Before/after audit and stale-version tests | NEEDS_DECISION |
| SKILL-11 | Reports/dashboard | No approved skill KPI | Reporting and formula definitions | No metrics initially | Existing report/dashboard scope | Reporting indexes only if approved | Source-only, branch-safe empty states | NEEDS_DECISION |
| SKILL-12 | Permissions | Centralized Session/Attendance RBAC | Reuse versus skill permissions | Reuse existing Session permissions | Server-side branch enforcement | Permission rows only if approved | Role, CSRF, forged-ID tests | NEEDS_DECISION |
| SKILL-13 | Retention/archival | General audit/document policy; no skill policy | Retention duration and archive behavior | Retain with Session history; manual archive | Authorized branch-scoped archive access | Fields/jobs only if approved | No deletion, audit retention, idempotency | NEEDS_DECISION |

## Shared implementation gates

No route, controller, service, model, view, permission, migration, or test implementation should be added until the relevant row has an explicit resolution. Any approved slice must preserve:

- Centralized RBAC and server-side branch isolation.
- CSRF, server-side validation, transactions, and append-oriented audit history.
- Existing Session roster, attendance snapshot, feedback, correction, and concurrency behavior.
- Configured MCC-local date/time handling and UTC persistence.
- Private document storage when documents are eventually approved.
- No raw JSON, sensitive data, or unapproved portal visibility.

**Approval fields for every row:** Selected option: ______; Resolution: ______; Comments: ______; Approver: ______; Approval date: ______; Effective date: ______.

## Reconciled secure defaults

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The secure defaults in the matrix are recorded as planning direction only. All rows remain `NEEDS_DECISION`; no approver, approval date, or effective date has been supplied. No code, schema, route, permission, media, report, or portal implementation is authorized.

| ID | Recorded direction | Status | Approver | Approval date | Effective date |
|---|---|---|---|---|---|
| SKILL-01 | Use the six controlled areas listed in the decision request. | NEEDS_DECISION |  |  |  |
| SKILL-02 | Use a versioned centrally managed option catalog. | NEEDS_DECISION |  |  |  |
| SKILL-03 | Do not allow Coach-created taxonomy values. | NEEDS_DECISION |  |  |  |
| SKILL-04 | Keep definitions global and enforce branch-authorized usage. | NEEDS_DECISION |  |  |  |
| SKILL-05 | Keep skill selection optional. | NEEDS_DECISION |  |  |  |
| SKILL-06 | Do not automatically write Performance or Development records. | NEEDS_DECISION |  |  |  |
| SKILL-07 | Reuse existing bounded notes and feedback. | NEEDS_DECISION |  |  |  |
| SKILL-08 | Do not add Individual Session media/document upload. | NEEDS_DECISION |  |  |  |
| SKILL-09 | Keep visibility staff-only. | NEEDS_DECISION |  |  |  |
| SKILL-10 | Reuse correction rules and require reasoned append-only audit values. | NEEDS_DECISION |  |  |  |
| SKILL-11 | Keep skill metrics out of reports and dashboards. | NEEDS_DECISION |  |  |  |
| SKILL-12 | Reuse existing Session permissions and add no taxonomy permissions. | NEEDS_DECISION |  |  |  |
| SKILL-13 | Retain records with Session history and avoid automatic deletion. | NEEDS_DECISION |  |  |  |

### SKILL-02 catalog supplied for decision review

**USER-PROVIDED DIRECTION — NOT CLIENT APPROVAL.** The proposed global catalog is active by default in the listed order. Coaches cannot add options. Authorized administrators may retire options for future use; historical Sessions retain the original option reference and label. All options and rules remain `NEEDS_DECISION` with blank approval fields.

| Area | Ordered options |
|---|---|
| Batting | Front-foot drive; Back-foot defence; Pull shot; Cut shot; Sweep shot; Running between wickets |
| Bowling | Line and length; Seam movement; Swing control; Spin control; Yorker; Bouncer |
| Fielding | Ground fielding; Catching; Throwing accuracy; High catches; Boundary fielding; Direct hit |
| Wicketkeeping | Stance and footwork; Taking behind the stumps; Glovework; Standing up to the stumps; Standing back; Stumping technique |
| Fitness | Speed and agility; Strength; Endurance; Mobility; Core stability; Recovery |
| Game awareness | Shot selection; Strike rotation; Field placement; Bowling plans; Match awareness; Decision-making |

| Rule | Recorded direction | Status |
|---|---|---|
| Default state | Every listed option is active by default. | NEEDS_DECISION |
| Ownership | Definitions are global; use is branch-authorized. | NEEDS_DECISION |
| Custom options | Coaches may not add custom options. | NEEDS_DECISION |
| Retirement | Authorized administrators may retire options for future use. | NEEDS_DECISION |
| History | Existing Sessions retain the original option reference and label after retirement or catalog changes. | NEEDS_DECISION |

Approver: ______; Approval date: ______; Effective date: ______.
