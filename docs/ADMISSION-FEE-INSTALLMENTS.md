# Admission Fee Installments

The Admissions navigation now includes **Fee Installments** at
`/admissions/fee-installments`.

## Scope and authorization

- The page is protected by the existing `admission.view` permission.
- The service applies the authenticated user’s active, authorized branch IDs
  before querying or counting rows.
- The branch filter can only select one of those authorized branches.
- The canonical `admissions.admission_number` is displayed; the numeric
  Admission primary key is used only for authorized detail links.

## Data and filters

The page reads, without rewriting, from:

- `admission_coaching_installments`
- `admission_hostel_installments`

It joins the authorized Admission, Branch, and optional Lead records. Available
filters are applicant name, Admission number, branch, installment type, year,
installment number, due-date range, and due state (Upcoming, Due today, or
Overdue). Pagination uses the shared compact page-window helper.

Due-state calculations use the configured MCC application timezone and stored
calendar due dates. Display dates use the existing MCC `DD-MM-YYYY` format;
stored timestamps display as `DD-MM-YYYY, hh:mm AM/PM`.

## Manual payment receipts

Authorized staff can use the row Actions menu to record a manual receipt at:

- `GET /admissions/fee-installments/{coaching|hostel}/{installment}/payment`
- `POST /admissions/fee-installments/{coaching|hostel}/{installment}/payment`

The workflow reuses the existing `admission.update` permission because no
payment-specific permission has been approved. The service validates the
installment's Admission and branch before locking the installment and writing
to the append-only `admission_installment_payments` ledger. CSRF protection,
branch authorization, transaction rollback, and audit logging remain enabled.

The ledger stores the receipt number, received amount, date, method, optional
reference, notes, actor, and immutable entry metadata. Receipt numbers are
unique within a branch. Reposting the same receipt with the same data returns
the original receipt instead of creating a duplicate. No payment gateway,
refund, reminder, or collection workflow is introduced.

Payment status is calculated server-side from the complete ledger:

- **Not captured**: no receipt has been recorded.
- **Partially paid**: received amount is below the installment amount.
- **Paid**: received amount equals the installment amount.
- **Overpaid**: received amount exceeds the installment amount.

The list and payment detail pages show this calculated status, total received,
balance, and payment history. The migration also provides append-only
correction fields (`entry_type`, `correction_of_payment_id`, and `reason`) for
an approved reversal/correction workflow; posted payment rows are not edited or
deleted by this feature.
