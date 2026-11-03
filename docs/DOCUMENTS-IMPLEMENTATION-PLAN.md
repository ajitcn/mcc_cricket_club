# Documents Implementation Plan

**Prepared:** 2026-09-27  
**Source:** `docs/DOCUMENTS-POLICY-APPROVAL-REQUEST.md`  
**Status:** Planning only. No item in the approval request is explicitly marked `APPROVED`; every response checkbox is blank. Therefore, there is no authorized Documents policy implementation slice in this plan.

## Authority and guardrails

- `docs/SRS-original.md` is unavailable and is not reconstructed.
- Existing code, tests, historical audits, and user direction are implementation evidence only. They are not client approval.
- Blank items remain `NEEDS_DECISION`; a rejected item would remain `REJECTED` and would not be implemented.
- Do not change application code, tests, migrations, database records, permissions, storage behavior, or existing decision documents until explicit approvals are reconciled with approver identity, approval date, and effective date.
- Any future implementation must preserve private storage, server-side branch isolation, CSRF protection, centralized authorization, safe error normalization, output escaping, and append-oriented audit logging.

## Approval status review

| Approval request item | Explicit response found | Current status | Implementation disposition |
|---|---|---|---|
| DOC-01 categories, upload authority, MIME types | None | `NEEDS_DECISION` | Leave unchanged |
| DOC-02 verification states and verifier roles | None | `NEEDS_DECISION` | Leave unchanged |
| DOC-03 expiry rules and enforcement | None | `NEEDS_DECISION` | Leave unchanged |
| DOC-04 Parent/Guardian and Player visibility | None | `NEEDS_DECISION` | Leave unchanged |
| DOC-05 medical-document visibility | None | `NEEDS_DECISION` | Leave unchanged |
| DOC-06 replacement and version retention | None | `NEEDS_DECISION` | Leave unchanged |
| DOC-07 retirement and deletion | None | `NEEDS_DECISION` | Leave unchanged |
| SEC-12 / DOC-09 scanning and quarantine | None | `NEEDS_DECISION` | Leave unchanged |
| OPS-03 private-storage deployment boundary | None | `NEEDS_DECISION` | Leave unchanged |
| DOC-10 / OPS-10 retention and audit | None | `NEEDS_DECISION` | Leave unchanged |
| SEC-10 / SEC-13 consent and legal basis | None | `NEEDS_DECISION` | Leave unchanged |

## Authorized implementation slices

**None at this time.** Because no response is explicitly marked `APPROVED`, the following are not authorized:

- verification states or verifier workflows;
- expiry validation, read-time expiry blocking, or expiry jobs;
- medical or category-specific permissions;
- Parent/Guardian or Player document portal access;
- version tables, restore, retention, legal holds, or physical deletion;
- scanner/quarantine integration;
- production storage, encryption, key-management, backup, or provider changes;
- consent/legal-basis fields or visibility enforcement.

No affected service, controller, route, view, migration, permission, or test should be changed under the current approval record.

## Conditional implementation map after explicit approval

The following is a dependency map for later planning. It is not authorization to implement.

| Policy approval | Likely affected areas | Migration likely? | Focused tests required after reconciliation |
|---|---|---:|---|
| DOC-01 | `DocumentManagementService`, upload controllers/views, route filters, RBAC records | Maybe, if category metadata or permission mapping changes | Unit allowlist/MIME tests; feature upload authorization; database branch/type permission tests |
| DOC-02 | Document service state machine, Browser routes/controllers, detail/list views, history/audit helpers | Yes, if state/verifier/reason fields are added | Unit transition tests; feature verifier authorization/CSRF tests; database state/history/audit tests |
| DOC-03 | Upload validation, `find()`/`read()`, preview/download controllers, expiry display | Maybe, if policy needs new metadata or job state | Unit date/time boundary tests; feature response/visibility tests; database expired-row tests |
| DOC-04 | Portal or Player/Guardian controllers, relationship authorization, document views, RBAC | Likely, for consent/visibility metadata | Unit relationship policy tests; feature branch/relationship/consent tests; database revocation tests |
| DOC-05 | Document authorization service, route filters, medical views, RBAC | Maybe, if a dedicated permission/policy table is approved | Unit type-role matrix tests; feature cross-role denial tests; database branch/medical access tests |
| DOC-06 | Replacement workflow, document history/version service, detail/restore views | Yes, for immutable versions and retention metadata | Unit version selection tests; feature restore authorization/CSRF tests; database lineage/version/audit tests |
| DOC-07 | Retirement service/routes/views, storage cleanup boundary, retention jobs | Maybe, depending on retention/legal-hold design | Unit lifecycle tests; feature retirement/restore tests; database soft-retirement and hold tests |
| SEC-12 / DOC-09 | Upload service, scanner adapter boundary, quarantine state, audit events, deployment config | Likely, for scan state/results | Unit scanner boundary tests; feature unavailable-until-clean tests; database quarantine/audit tests |
| OPS-03 | `LocalPrivateStorage` adapter/configuration and deployment integration | Not necessarily; deployment configuration may suffice | Unit provider boundary tests; feature private response tests; deployment-owner verification rather than repository-only tests |
| DOC-10 / OPS-10 | Audit/retention services, scheduled jobs, storage cleanup, policy configuration | Likely, for retention/legal-hold metadata | Unit retention calculation tests; feature audit access tests; database retention/hold/expiry tests |
| SEC-10 / SEC-13 | Consent/legal-basis service, document upload/display authorization, guardian relationship views | Yes, if consent records are persisted | Unit consent-state tests; feature minor/guardian authorization tests; database expiry/withdrawal/audit tests |

Every future slice must use the existing `DocumentManagementService`, `LocalPrivateStorage`, centralized authorization, branch checks, CSRF filters, and audit boundary unless an approved architecture decision changes that design.

## Deployment-owner evidence required before operational implementation

No deployment evidence was supplied or inferred. Before any approved storage, scanning, retention, or expiry behavior is released, the deployment owner must provide evidence for the exact approved environment covering:

1. **Private storage:** provider/adapter, root outside the public web root, network exposure, access controls, encryption at rest, key ownership, rotation, recovery, and secret management.
2. **Scanning:** scanner/provider identity, clean/failure/quarantine behavior, timeout and retry policy, quarantine access, result retention, and audited outcomes.
3. **Retention:** category/lifecycle retention schedules, legal holds, archival and physical deletion jobs, backup retention, restore test, and owner-approved recovery objectives.
4. **Expiry:** configured application timezone, date boundary, scheduler or read-time enforcement, clock source, monitoring, and evidence that expired files cannot be downloaded or previewed contrary to the approved policy.
5. **Audit and operations:** log redaction, audit retention/access, alerting, incident response, backup/restore, and deployment checklist evidence.

These are evidence requests, not implementation assumptions. Provider names, credentials, keys, and personal data must not be placed in this document or source control.

## Smallest next step

Obtain completed responses in `docs/DOCUMENTS-POLICY-APPROVAL-REQUEST.md`, including approver identity, approval date, effective date, and comments for every item. Reconcile only explicit approvals into the project requirement and decision records. Then create a new, narrow coding task for the first approved policy with its exact acceptance criteria and deployment dependencies.
