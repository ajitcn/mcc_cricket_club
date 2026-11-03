# Achievements Read-Only Audit

Date: 2026-10-06

## Scope and evidence

This is a read-only audit of the existing application. No Achievements routes, controllers, services, models, views, migrations, permissions, or database tables were found under `app/Modules`, `app/Controllers`, `app/Services`, `app/Models`, or `app/Database/Migrations`.

The documented requirements classify Achievements as future scope (`docs/REQUIREMENTS.md`, project capability map). No approved Achievements decision, RBAC mapping, data model, lifecycle, eligibility formula, certificate relationship, retention rule, or export policy was found in the reviewed decision and architecture documents.

## Existing implementation status

| Area | Evidence | Status |
| --- | --- | --- |
| Routes | No Achievements route file or route target found | Missing; do not implement |
| Controllers/services/models/views | No implemented module found | Missing; do not implement |
| Database | No Achievements migration/table found | Missing; schema decision required |
| Permissions/RBAC | No `achievement.*` permission mapping found | Missing; RBAC decision required |
| Navigation | A former planned label was removed because it had no valid route or permission | Not advertised |
| Tests | No Achievements Unit, Feature, or database tests found | Blocked until scope exists |

## Decisions required before implementation

The client must decide, at minimum:

1. Achievement scope and categories, including whether awards and certificates are separate modules.
2. Eligibility rules and who may create, edit, approve, issue, retire, or correct an achievement.
3. Branch ownership, cross-branch visibility, and Player/Coach relationships.
4. Status lifecycle, locking, correction, replacement, and audit requirements.
5. Required fields, evidence/documents, numbering, templates, and generated artifacts.
6. Visibility, retention, archival, reporting, export, and notification behavior.
7. Permission names and role assignments, with server-side enforcement.

Until these choices are recorded as approved requirements, Achievements remains `NEEDS_DECISION` and no route or navigation link should be added.

## Related evidence classification

- **Code evidence:** no implemented Achievements module or schema was found.
- **Document evidence:** Achievements is marked future scope in `docs/REQUIREMENTS.md`.
- **User direction:** the request requires an audit before any implementation; it does not approve an Achievements workflow.
- **Client approval:** none identified.
- **Status:** `NEEDS_DECISION`; deferred from implementation.
