# Decision Request: Temporary Super Admin Test Account

**Status:** `NEEDS_DECISION`  
**Purpose:** Record user-provided direction for a temporary Super Admin test account and obtain explicit MCC approval before account creation or credential delivery. This request does not grant access, create an account, or generate credentials.

## Current project context

- `SEC-02` approves multiple roles per user; `SEC-03` approves multiple authorized branch assignments; `SEC-04` requires centrally controlled and audited role/permission changes. These decisions do not define the Super Admin permission set or global branch access.
- `AGENTS.md` says Super Admin access must follow explicit authorization rules and requires server-side RBAC and branch isolation. The final role-to-permission and branch-scope matrix remains `NEEDS_DECISION` (`RBAC-MATRIX` in the client approval packet).
- The current Admin routes protect user creation, role assignment, and branch assignment with `rbac.manage` (`app/Modules/Admin/Config/Routes.php`). `AdminService::createUser()` creates an account, while separate methods assign roles and branches (`app/Services/AdminService.php`). This describes current mechanics; it does not establish which person may create a Super Admin or what permissions that role should receive.
- `SEC-01` approves one database-backed identity model for staff, parents, and players. `AUTH-DESIGN` remains `NEEDS_DECISION`; that identity approval is not permission to create this account.
- `INT-01` approves MCC-operated SMTP/relay for password recovery only. It does not approve using email recovery or SMTP to deliver temporary account credentials. The replacement packet says credentials require an MCC-approved secure channel, but no channel is selected in the reviewed records.
- The requester supplied the proposed Super Admin scope and account lifecycle owners below. These are user-provided directions, not documented client approval. The approver identity and decision date are not supplied.

## Approver decisions required

Complete each item with exact approved wording. Leave unanswered rows `NEEDS_DECISION`. If decisions differ from current records, identify any superseded decision and preserve its history.

| Item | Status | User-provided direction / decision required | Decision (APPROVE / REJECT / NEEDS_DISCUSSION) | Exact resolution / comments | Approver name and authority | Date |
|---|---|---|---|---|---|---|
| Super Admin permissions | `NEEDS_DECISION` — user-provided direction, not client-approved | All permissions in the approved permission catalog in effect when this decision takes effect; permissions added to the catalog later require separate approval. The catalog version/snapshot and effective date are not identified. | __________________ | ______________________________________________ | ______________________________ | __________ |
| Branch access | `NEEDS_DECISION` — user-provided direction, not client-approved | All branches. Record the explicit server-side authorization and audit scope after approval. | __________________ | ______________________________________________ | ______________________________ | __________ |
| Account creation authority | `NEEDS_DECISION` — user-provided direction, not client-approved | MCC account owner. The current Admin routes require `rbac.manage`; the account owner’s identity and authorization for that action are not recorded. | __________________ | ______________________________________________ | ______________________________ | __________ |
| Secure credential delivery | `NEEDS_DECISION` | No channel supplied. “Secure” does not identify an MCC-approved channel. `INT-01` covers recovery SMTP/relay only, not temporary account credentials. | __________________ | ______________________________________________ | ______________________________ | __________ |
| Revocation or rotation | `NEEDS_DECISION` — user-provided direction, not client-approved | MCC account owner. The named owner, timing/trigger, credential/session revocation process, and audit evidence remain unspecified. | __________________ | ______________________________________________ | ______________________________ | __________ |

## Account request details to complete after approval

| Field | Approved value |
|---|---|
| Intended test purpose and environment | `NEEDS_DECISION` — ______________________________________________ |
| Account identifier/email | `NEEDS_DECISION` — user-provided candidate, not approved or created: `test.mcc.superadmin@example.invalid` |
| Approved role and exact permissions | `NEEDS_DECISION` — ______________________________________________ |
| Approved branch scope | `NEEDS_DECISION` — ______________________________________________ |
| Account creator | `NEEDS_DECISION` — ______________________________________________ |
| Credential delivery channel/owner | `NEEDS_DECISION` — ______________________________________________ |
| Expiry or revocation trigger | `NEEDS_DECISION` — ______________________________________________ |
| Revocation/rotation owner and evidence | `NEEDS_DECISION` — ______________________________________________ |

## Approval record

**Overall decision:** `NEEDS_DECISION`  
**Approver name and authority:**  
**Decision date:**  
**Comments / conditions:** ______________________________________________  
**Related decision updates required:** ______________________________________________

Do not create the account, assign privileges, generate credentials, or deliver credentials until the relevant decisions above are recorded. Do not treat `INT-01` recovery email delivery as approval for temporary credential delivery.

## Source records

- `AGENTS.md` — Authorization and RBAC; Branch Isolation; Authentication; Security.
- `docs/DECISIONS.md` — `SEC-01`–`SEC-04`, `SEC-14`, and `INT-01`.
- `docs/CLIENT-APPROVAL-PACKET-REPLACEMENT-BASELINE.md` — `RBAC-MATRIX`, `AUTH-DESIGN`, and secure credential handling context.
- `docs/AUTH-DESIGN-DECISION-BRIEF.md` — authentication architecture and deployment boundaries.
- `app/Modules/Admin/Config/Routes.php` and `app/Services/AdminService.php` — current user, role, and branch administration mechanics.
