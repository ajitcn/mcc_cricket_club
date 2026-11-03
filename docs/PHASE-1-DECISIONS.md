# MCC — Phase 1 Decision Sheet

Status: Prepared for client approval
Purpose: Minimum decision set required to move Phase 1 from `BLOCKED` to `READY-FOR-IMPLEMENTATION` after approval and acceptance-test confirmation.
Authority: `docs/DECISIONS.md` remains authoritative. Items below are unresolved `NEEDS_DECISION` items; recommendations remain `PROPOSED` until explicitly approved.

Resolved since preparation: `GOV-01`, `GOV-02`, `SEC-12`, `SEC-13`, and `SEC-14` were approved on 2026-08-28 and are no longer Phase 1 decision blockers.

Approved rows retained for traceability are not blockers. This sheet does not repeat already approved branch, lifecycle, lead, or duplicate-detection decisions. It excludes P2 decisions and decisions conditional on a later migration, portal, API, bulk-import, or future-module scope.

## PHASE-1-DECISION-BLOCKERS

### P0 — Foundation and Phase 1 architecture

Decision ID: `GOV-01`, `GOV-02`
Area: Governance
Question: Who approves requirements, and how are changes to policies, formulas, thresholds, and acceptance criteria controlled?
PROPOSED baseline: Named product owner approval with versioned decision records, impact assessment, effective date, and preserved history.
Options: Client-only approval; formal change-control group.
Recommendation: PROPOSED — use named owner approval with auditable change records.
Impact if approved: Provides valid approval evidence and prevents draft rules entering implementation.
Blocked until: All business implementation gates.
Approver: Authorized product owner; name not recorded.
Status: APPROVED

Decision ID: `SEC-14`
Area: Identity and account recovery
Question: How should account recovery and guardian verification protect accounts?
PROPOSED baseline: Expiring, single-use recovery with rate limits, verified relationship checks, session invalidation, and audit events.
Options: Verified email; verified phone; staff-assisted recovery; approved combination.
Recommendation: PROPOSED — require verified recovery and relationship evidence before access is granted.
Impact if approved: Enables secure account lifecycle and prevents unverified guardian access.
Blocked until: Authentication and guardian-linked access.
Approver: Authorized product owner and security approver; names not recorded.
Status: APPROVED

Decision ID: `SEC-12`
Area: File security
Question: How must uploaded files be malware-scanned?
PROPOSED baseline: Scan before availability, quarantine failures, and audit scan outcomes.
Options: Managed scanner; approved on-host scanner; manual fallback.
Recommendation: PROPOSED — use pre-availability scanning with quarantine and no unsafe fallback.
Impact if approved: Defines safe document upload and operational scanner dependency.
Blocked until: Document/media upload.
Approver: Authorized product owner and security approver; names not recorded.
Status: APPROVED

Decision ID: `SEC-13`
Area: Consent and safeguarding
Question: What consent or legal basis is required for minors, personal data, media, and documents?
PROPOSED baseline: Consent/legal-basis record with guardian authority, evidence, expiry, withdrawal, and audit.
Options: Documented consent register; policy-based legal-basis register; approved hybrid.
Recommendation: PROPOSED — require recorded authority and withdrawal handling before sensitive access or publication.
Impact if approved: Defines lawful collection, minor safeguards, and revocation behavior.
Blocked until: Sensitive records, documents, and any Phase 1 media use.
Approver: Authorized product owner and safeguarding/privacy approver; names not recorded.
Status: APPROVED

Decision ID: `DOC-01`, `DOC-02`, `DOC-03`, `DOC-05`, `DOC-06`, `DOC-07`, `DOC-08`
Area: Documents and private media
Question: Who may upload and verify documents, what may be viewed, how are objects encrypted and retained, and how are replacements handled?
PROPOSED baseline: Type-specific authorization; explicit verification states; restricted unverified/medical access; encrypted private storage; logical retirement; immutable replacement history.
Options: Broad branch-staff access; provider-managed encryption; permanent retention; replace-in-place.
Recommendation: PROPOSED — apply least privilege, private storage, version history, and controlled retirement.
Impact if approved: Enables secure Phase 1 document upload, verification, expiry tracking, download, and replacement.
Blocked until: Document management and medical-data handling.
Approver: Authorized product owner and security/privacy approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `OPS-03`
Area: Private storage
Question: Where should private document/media objects be stored?
PROPOSED baseline: Provider-neutral private storage abstraction with internal keys and request-time authorization.
Options: Local private filesystem; S3/R2; managed storage provider.
Recommendation: PROPOSED — approve the abstraction first and select a provider through deployment design.
Impact if approved: Enables reversible storage deployment without public URLs.
Blocked until: Private document/media handling.
Approver: Authorized product owner and operations/security approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `OPS-01`, `OPS-02`, `OPS-10`, `OPS-11`, `OPS-12`
Area: Audit, resilience, retention, and operations
Question: What critical actions are audited, how long is data retained, what backup/recovery objectives apply, and how are incidents and alerts handled?
PROPOSED baseline: Append-oriented audit; entity-specific retention; encrypted daily backups with quarterly restore tests; documented incident response and redacted monitoring.
Options: Minimal audit; indefinite retention; provider-managed backup; internal or managed incident response.
Recommendation: PROPOSED — approve auditable, encrypted, restore-tested operations with explicit ownership.
Impact if approved: Enables foundation release controls and protects sensitive/admin records.
Blocked until: Foundation operations and Phase 1 production readiness.
Approver: Authorized product owner and operations/security approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `REL-01`, `REL-02`
Area: Guardian/contact data
Question: Can guardians link to multiple players, and which contact/guardian types must be distinct?
PROPOSED baseline: Explicit relationship records supporting multiple guardians/children and separate guardian, emergency, and primary-contact semantics.
Options: One parent/one player; flat parent fields; one contact type.
Recommendation: PROPOSED — use explicit relationships and contact types without granting portal visibility by implication.
Impact if approved: Enables accurate Phase 1 guardian/contact records and future-safe authorization.
Blocked until: Player/guardian records.
Approver: Authorized product owner; name not recorded.
Status: APPROVED

Decision ID: `DAT-01`
Area: Data ownership and quality
Question: Who owns validation rules, duplicate review, corrections, reference data, and quality reporting?
PROPOSED baseline: Named module owners with a central data steward and audited correction authority.
Options: Module-only ownership; central steward.
Recommendation: PROPOSED — assign accountable owners before data entry and conversion workflows.
Impact if approved: Makes CRM, admission, player, and reporting data quality testable.
Blocked until: CRM, admissions, players, and reports.
Approver: Authorized product owner; name not recorded.
Status: APPROVED

Decision ID: `AT-01`–`AT-06`
Area: Attendance
Question: Which eligible sessions and statuses determine attendance, how are cancellations and late records treated, and who may correct records?
PROPOSED baseline: Attendance belongs to eligible player/session pairs; cancellation is session-level and excluded from denominators; Late is separate from Present; corrections are authorized and audited.
Options: Count cancelled sessions; player cancellation statuses; Late as absence; unrestricted corrections.
Recommendation: PROPOSED — preserve session history and define denominator, thresholds, correction window, and audit before calculation.
Impact if approved: Enables attendance marking, reports, and approved alerts without inventing formulas.
Blocked until: Attendance and attendance reporting.
Approver: Authorized product owner and academy operations approver; names not recorded.
Status: NEEDS_DECISION

### P1 — Affected Phase 1 modules

Decision ID: `SCH-01`–`SCH-05`
Area: Scheduling and resources
Question: Are sessions one-time or recurring, how are capacity and coach availability handled, and what happens on conflicts or rain shifts?
PROPOSED baseline: One-time sessions first; server-side capacity and availability checks; hard conflict prevention; auditable rescheduling.
Options: Full recurrence; warning-only conflicts; manual rescheduling.
Recommendation: PROPOSED — use the smallest schedulable model with hard safety checks and preserved history.
Impact if approved: Enables sessions, rosters, grounds, and coach assignment.
Blocked until: Sessions/grounds/batches.
Approver: Authorized product owner and academy operations approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `REP-01`–`REP-03`
Area: Reports and exports
Question: Which columns, filters, date meanings, and view/export permissions apply to each Phase 1 report?
PROPOSED baseline: Approved report catalogue, standard scope/date filters, separate export permissions, field allowlists, timestamps, and audit.
Options: Developer-defined columns; same view/export permission; no exports.
Recommendation: PROPOSED — approve report definitions before report implementation.
Impact if approved: Makes reports reproducible, branch-safe, and privacy-controlled.
Blocked until: Reports/exports.
Approver: Authorized product owner and data/privacy approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `DASH-01`–`DASH-03`
Area: Operational dashboard
Question: Which Phase 1 KPIs are approved, who sees them, and how fresh must they be?
PROPOSED baseline: Source-backed metric catalogue with formula/version, role and branch scope, freshness, and p95 measurement.
Options: Approximate counts; one dashboard; real-time or scheduled refresh.
Recommendation: PROPOSED — expose only approved source-backed metrics.
Impact if approved: Enables an honest operational dashboard without unsupported calculations.
Blocked until: Operational dashboard.
Approver: Authorized product owner and data/security approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `NOT-01`–`NOT-03`
Area: Notifications
Question: Which channels, recipients, timing, opt-out rules, retries, and delivery records are required for Phase 1?
PROPOSED baseline: Approved event templates and recipient policies, email foundation first, safe retries, delivery audit, and no source-record corruption.
Options: Both email and WhatsApp; manual messages; best-effort delivery.
Recommendation: PROPOSED — start with approved email events and defer WhatsApp provider integration.
Impact if approved: Enables attendance/trial/document notifications with controlled disclosure.
Blocked until: Notifications and approved attendance alerts.
Approver: Authorized product owner and privacy/operations approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `OPS-05`
Area: Frontend foundation
Question: Which UI framework should be used for Phase 1 workflows?
PROPOSED baseline: One maintained framework selected before major frontend implementation, with accessibility requirements.
Options: Existing/custom CSS; alternative maintained framework.
Recommendation: PROPOSED — select one supported framework and document its accessibility baseline.
Impact if approved: Prevents inconsistent UI and supports keyboard, focus, contrast, and responsive acceptance tests.
Blocked until: Phase 1 frontend work.
Approver: Authorized product owner and architecture approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `OPS-07`
Area: Search and filtering
Question: Which fields can users search, and how are branch scope and sensitive data enforced?
PROPOSED baseline: Indexed, allowlisted, role- and branch-aware search fields with sensitive fields excluded by default.
Options: Broad full-text search; module-local search; no search.
Recommendation: PROPOSED — approve allowlisted search fields and server-side scope enforcement.
Impact if approved: Enables safe Phase 1 search/filtering without record-existence leakage.
Blocked until: CRM, players, sessions, and reports requiring search.
Approver: Authorized product owner and security/data approver; names not recorded.
Status: NEEDS_DECISION

Decision ID: `OPS-08`, `OPS-09`
Area: Performance and locale
Question: What dataset/load defines the p95 target, and what locale/date/timezone rules apply to Phase 1?
PROPOSED baseline: Production-like p95 benchmark methodology and English/India locale with `Asia/Kolkata` report semantics.
Options: Average response; synthetic benchmark; multilingual MVP; alternate locale.
Recommendation: PROPOSED — approve the measurement method and retain the documented default timezone unless changed explicitly.
Impact if approved: Makes NFR-01 and date-boundary behavior testable.
Blocked until: Performance evidence and date-sensitive reports.
Approver: Authorized product owner and operations approver; names not recorded.
Status: NEEDS_DECISION

## Scope boundary

Other unresolved decisions remain in `DECISIONS.md` for later or conditional scope. They are intentionally not presented as Phase 1 approval questions and do not block Phase 1 unless the approved scope changes.

Approval of a recommendation must be recorded in `DECISIONS.md`; this sheet never changes decision status.
