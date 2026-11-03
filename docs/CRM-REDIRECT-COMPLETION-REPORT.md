# CRM Redirect Completion Report

## Scope

The legacy CRM browser exception paths were reviewed and updated so that a
failed request returns to the existing Lead or follow-up screen that owns the
form. This change is limited to redirect targets and does not change Lead,
Trial, Admission, branch, permission, CSRF, audit, transaction, or idempotency
behavior.

## Explicit return targets

| Failure path | Return target |
| --- | --- |
| Lead create | `/crm/leads/new` |
| Lead edit/update | `/crm/leads/{id}/edit` |
| Lead assignment | `/crm/leads/{id}` |
| Lead status change | `/crm/leads/{id}` |
| Follow-up create | `/crm/leads/{id}` |
| Follow-up update | `/crm/leads/{leadId}` |
| Follow-up completion | `/crm/leads/{leadId}` |
| Follow-up cancellation | `/crm/leads/{leadId}` |
| Follow-up correction | `/crm/leads/{leadId}` |

All browser exception redirects preserve submitted input with `withInput()` and
the safe flashed error message. Non-browser/API responses remain JSON responses
with their existing status handling.

## Trial review

Trial browser handlers already used explicit targets through the existing
`redirectWithTrialError()` helper and direct `/crm/trials/{id}` targets. No
Trial route or business behavior was changed.

## Changed files

- `app/Modules/CRM/Controllers/Browser.php`
- `tests/Unit/CrmRedirectContractTest.php`
- `tests/Feature/CrmRedirectRoutesTest.php`
- `docs/CRM-REDIRECT-COMPLETION-REPORT.md`

The focused tests assert that Lead routes exist, CRM browser exceptions do not
use `redirect()->back()`, Lead targets are explicit, and Trial failures retain
their existing explicit return-target helper.

## Verification

The focused CRM redirect suite passed:

- **2 tests**
- **14 assertions**
- **Exit code: 0**

The larger legacy CRM suite was not used as completion evidence because it
contains unrelated fixture and append-only audit failures.

No migrations, permissions, routes, database records, or
`docs/SRS-original.md` were changed.
