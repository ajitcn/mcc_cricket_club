# Statistics, Selection, Awards and Certificates Decision Brief

**Purpose:** Record the decisions required before these navigation entries or workflows can be enabled.  This is analysis and user-direction support, not client approval.

## Current gate

The codebase has no standalone Statistics, Selection, Awards, or Certificates module. The existing Trial selection outcome is an approved Trial workflow and remains unchanged. Match/Tournament selection and awards/certificates are explicitly unresolved. The existing navigation entries were misleading `Coming Soon` placeholders and have been removed until an approved permission and route exist.

## Decisions required

| ID | Decision required | Secure default until approved |
|---|---|---|
| STAT-01 | Metric catalogue, authoritative source for each metric, formulas, date boundaries, denominator/zero/missing handling, freshness, branch visibility, corrections, recalculation, and export fields | No Statistics route or menu entry; display no fabricated values |
| SEL-01 | Whether a standalone Selection workflow exists and how it relates to Trial, Match, and Tournament | Keep the existing Trial outcome only; no standalone selection tables or permissions |
| SEL-02 | Player eligibility, category/age boundary, squad/Playing XI limits, authority, approval, correction, locking, and snapshot rules | Reject unapproved workflow creation; preserve existing Match/Tournament behavior |
| AW-01 | Award criteria, source records, authority, lifecycle, correction, visibility, branch scope, and retention | No Awards workflow or permission |
| CERT-01 | Certificate templates, numbering, approval, issue/replace/retire lifecycle, private storage, visibility, retention, and audit | No Certificate generation or public links; do not reuse document permissions implicitly |
| RBAC-01 | Role-to-permission mapping and branch scope for all enabled workflows | No new grants; server-side authorization remains centralized |

## Approver record

- **Approver identity:** __________________________________________
- **Decision date:** ______________________________________________
- **Effective date:** _____________________________________________
- **Comments/constraints:** _______________________________________

Until these fields are completed in an authorized decision record, all unresolved items remain `NEEDS_DECISION` and must not be implemented.
