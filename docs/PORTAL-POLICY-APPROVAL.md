# Student/Parent Portal Policy Approval

## Approval record

- **Approver name:** Ajit
- **Approver role:** Manager
- **Approval date:** 2026-11-10
- **Effective date:** 2026-11-10
- **Scope:** Development and Production
- **Approval comments:** Not provided
- **Decision:** Approved secure defaults for all seven portal policy areas.

This document records the user-provided approval supplied in the development
conversation. The rules below are the authoritative implementation direction
for the portal, subject to the existing MCC security, branch-isolation, audit,
and deployment controls.

## Approved rules

### 1. Fees and payments

Students and parents may view only their linked fee plans, installments,
payment status, due dates, and receipts. Only authorized staff may record,
edit, refund, waive, or reconcile payments.

### 2. Documents

Students and parents may view and download only published, verified,
non-sensitive documents through controlled authorization. Aadhaar, medical,
internal review, and confidential staff documents remain staff-only.

### 3. Medical data

Students and parents may see medical submission, verification, and expiry
status only. Medical details and certificates remain restricted to authorized
staff roles.

### 4. Messaging

Portal users may submit structured support requests to the academy. Unrestricted
parent-to-coach chat is not enabled. Staff responses remain branch-scoped,
authorized, and audited.

### 5. Consent and signatures

Portal users may view signed Terms and Conditions and acknowledgement status.
New acknowledgement may be captured for a new admission or renewal. Historical
signatures and consent records are immutable and are never overwritten.

### 6. Profile and contact editing

Students and parents may submit profile or contact change requests. Official
records change only after authorized staff approval. Mobile-number changes
require OTP verification.

### 7. Hostel and transport

Students and parents may view requirement, approval, and allocation status.
Hostel charges, transport charges, routes, and allocation changes remain
staff-controlled.

## Required implementation controls

- Enforce the rules server-side; frontend visibility is not authorization.
- Scope every projection to the authenticated Student or verified guardian
  relationship and owning branch.
- Keep private documents outside public storage and use controlled downloads.
- Apply CSRF protection, validation, transactions, optimistic concurrency where
  corrections are possible, and append-only audit metadata.
- Do not expose medical details, internal notes, credentials, tokens, or other
  sensitive data in portal responses or logs.
- Add Unit, Feature, and database coverage before enabling each workflow.

## Development implementation status — 2026-11-10

- Fees: read-only linked installment and payment-status projection is enabled;
  staff-only payment mutation remains enforced.
- Documents: only active, verified, clean, non-sensitive documents are listed;
  controlled portal downloads now enforce the authenticated Student/guardian
  relationship and private storage authorization.
- Consent/signatures: acknowledgement and signature status is displayed
  read-only; historical signature values are not exposed.
- Profile/contact: authenticated student and guardian contact details are
  displayed read-only with academy-directed correction messaging; no portal
  mutation endpoint is enabled.
- Hostel/transport: admission requirement, approval/status, payment-plan, and
  allocation-contact status are displayed read-only; no portal allocation or
  charge mutation is enabled.
- Medical visibility and structured messaging remain intentionally bounded to
  status/request workflows. Medical submission, verification, scanner, and
  expiry status are now shown without exposing medical details or certificates.
- Messaging: a CSRF-protected, branch-linked structured support request form is
  available to authenticated portal users; unrestricted parent-to-coach chat is
  not enabled.

