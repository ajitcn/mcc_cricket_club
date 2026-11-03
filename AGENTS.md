# AGENTS.md

## Project

Project: MCC Cricket Club Management System

This is a new multi-branch cricket academy management system based on the approved requirements in `docs/SRS-original.md`.

Technology:

- CodeIgniter 4.7+
- PHP 8.2+
- MySQL/MariaDB
- PHPUnit 10
- PSR-4

## Mandatory coding standard

All future and existing-code changes must follow [`docs/CODING-STANDARD.md`](docs/CODING-STANDARD.md).
Compressed single-line application code is prohibited. PHP, HTML, views, CSS,
JavaScript, routes, configuration, services, controllers, models, migrations,
and tests must use readable multi-line formatting. Before completion, inspect
every changed source file for compressed statements, readable control flow,
proper HTML/CSS/JavaScript structure, and available syntax checks. Do not
mass-reformat unrelated legacy code, but format any affected section safely.

The original SRS is authoritative client input and must not be modified.

## Architecture

Use the current MCC architecture as the technical source of truth:

```
app/
├── Config/
├── Controllers/
├── Database/
├── Filters/
├── Helpers/
├── Libraries/
├── Models/
├── Modules/
├── Services/
├── ThirdParty/
└── Views/
```

Business functionality should primarily live inside `app/Modules`.

Cross-cutting functionality may live in `app/Services`, `app/Helpers`, `app/Libraries`, or `app/Filters` where appropriate.

Do not introduce a second architectural pattern, unnecessary frameworks, or unnecessary dependencies.

## Module Architecture

Business modules must follow this convention:

```
ModuleName/
├── Config/
│   └── Routes.php
├── Controllers/
├── Models/
├── Services/
└── Views/
```

Create additional module directories only when there is a real requirement.

Use explicit routes. Auto-routing must remain disabled.

Do not create business modules until the relevant requirements and architecture have been reviewed.

## Controllers

Controllers must remain thin. They may:

- Receive the request
- Validate and authorize where appropriate
- Call application or domain services
- Prepare the response
- Return the response

Controllers must not contain large business workflows, complex database logic, or multi-step business transactions. Move reusable workflows to services.

## Models

Models are responsible for persistence and database interaction.

Do not place large business workflows in models. Use appropriate primary keys, foreign keys, indexes, timestamps, and soft deletes where required.

Do not create tables merely because they seem useful. Database structure must come from approved MCC requirements.

## Services

Use services for:

- Business workflows
- Multi-model operations
- Transactions
- Reusable application logic
- Integrations
- Complex operations

Do not duplicate business logic between controllers.

## Database

All schema changes must use migrations. Never manually modify production schema.

Use seeders only for approved reference, system, or development data.

Use transactions when related changes must succeed or fail together. Index frequently searched or filtered fields. Do not store sensitive information unnecessarily. Never store passwords in plain text.

## Authentication

Authentication must become a proper database-backed system.

The current generic `AuthService` is only a boundary and is not the final implementation. Authentication design must be approved before implementation.

Use secure password hashing. Implement login, logout, session handling, password reset, and account recovery securely. Password reset and recovery must use secure, expiring token-based mechanisms.

## Authorization and RBAC

Initial SRS roles are:

- Super Admin
- Branch Admin
- Admission Team
- Coach
- Fitness Coach
- Nutritionist
- Reception
- Parent
- Player

Do not hardcode business permissions in controllers. Use centralized RBAC and enforce it server-side.

Permission names should follow a consistent convention, for example:

```
player.view
player.create
player.update
player.delete

admission.view
admission.create
admission.update

attendance.view
attendance.mark
```

The final permission matrix must be derived from approved MCC requirements. Hiding buttons or menu items is not authorization.

## Branch Isolation

MCC is multi-branch. Every branch-scoped record must have an appropriate branch relationship.

Branch Admins and other branch-scoped users may access only authorized branches. Super Admin access must still follow explicit authorization rules.

Enforce branch isolation server-side through a centralized, consistently applied strategy. Never rely on frontend filters, hidden fields, URL parameters, or JavaScript for security.

## Sensitive Data

The system may contain Aadhaar details, birth certificates, passports, visas, medical certificates, medical conditions, parent contacts, and player information.

Treat these as sensitive:

- Do not expose sensitive documents through public URLs.
- Do not store sensitive documents in `public/assets`.
- Use private storage and controlled download authorization.
- Do not log sensitive personal information, passwords, tokens, or credentials.
- Do not expose sensitive data in API responses unless authorized.

## Documents

Document handling must use a service or abstraction, not direct controller file manipulation.

Validate file type, file size, filename, and upload errors. Generate safe server-side filenames. Do not trust uploaded filenames or execute uploaded files.

Document access must be authorized, and document expiry must be supported where required.

## Audit Logging

The SRS requires audit logs for critical changes. The final audit design should cover:

- Authentication events
- Player status changes
- Admission changes
- Permission changes
- Document changes
- Sensitive record changes
- Administrative changes

Audit records should be append-oriented and protected from normal modification. Never log passwords, tokens, or sensitive credentials.

## Validation

Validate all user input server-side using CodeIgniter validation mechanisms.

Do not rely only on HTML or JavaScript validation. Return clear validation errors. Keep authorization checks separate from input validation.

## Security

At minimum:

- Use CSRF protection for applicable browser forms.
- Use secure sessions.
- Use password hashing.
- Enforce authorization server-side.
- Validate input and escape output.
- Secure all uploads.
- Keep documents private.
- Store secrets in environment configuration.
- Never commit `.env` or production credentials.

## API

Keep the application API-ready, but do not create endpoints merely for architectural completeness.

If APIs are introduced, use the `/api/v1/` prefix. API authentication and authorization must be explicitly designed. Use a consistent API response structure.

## Views and Frontend

Keep business logic out of views.

Use shared layouts, partials, templates, and reusable UI components. Do not duplicate navigation, headers, footers, alerts, or common UI elements.

Select and approve the final UI framework before significant frontend implementation.

## Testing

Every meaningful business module must include tests under:

- `tests/Unit`
- `tests/Feature`
- `tests/database`

Test business rules, authorization, branch isolation, validation, critical workflows, and database behavior.

A feature is not complete merely because it works manually.

## Requirements Discipline

Read the relevant requirement in `docs/SRS-original.md` before implementing functionality.

Create separate analysis and decision documents. Do not silently invent business rules.

Mark ambiguous requirements as `NEEDS_DECISION`. Do not assume rules for:

- Permissions
- Player lifecycle
- Admission conversion
- Fees or payments
- Branch transfers
- Document visibility
- Medical information visibility
- Performance scoring
- Selection rules
- Tournament rules

without documenting the decision.

## Development Workflow

For every feature:

1. Read the relevant SRS requirement.
2. Check documented requirements decisions.
3. Inspect the existing implementation.
4. Identify affected modules.
5. Identify database changes.
6. Identify permissions.
7. Identify branch-scope requirements.
8. Implement the smallest appropriate change.
9. Add server-side validation.
10. Add tests.
11. Run relevant tests.
12. Review security.
13. Review for duplicated logic.
14. Update documentation when necessary.

## Rootins Separation

Rootins is only an architectural reference and is not a dependency of MCC.

Do not:

- Import Rootins classes.
- Reference Rootins database tables.
- Reference Rootins business modules.
- Copy Rootins business workflows.
- Copy Rootins roles or permissions.
- Copy Rootins business terminology or data.

Rootins may be consulted only when an architectural convention requires clarification.

## Change Safety

- Do not modify unrelated files.
- Do not delete existing user work.
- Do not overwrite changes without explicit approval.
- Do not perform destructive database operations without approval.
- Do not make large unrelated refactors during feature work.
- Prefer small, reviewable changes.

## Documentation

Maintain these as separate project documents:

- `docs/SRS-original.md`
- `docs/REQUIREMENTS-REVIEW.md`
- `docs/ARCHITECTURE.md`
- `docs/DATABASE.md`
- `docs/RBAC.md`

Never modify `docs/SRS-original.md`. Create or update the other documents for analysis, decisions, architecture, database, and RBAC documentation.

## Current Project Status

The project currently contains only the technical foundation.

There are no approved business modules. Do not implement Lead, Admission, Player, Attendance, Performance, Fitness, Match, Tournament, or other business functionality until the requirements and architecture have been reviewed and approved.

