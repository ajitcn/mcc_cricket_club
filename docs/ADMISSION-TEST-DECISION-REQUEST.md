# Admission Test Decision Request

## Reference review status

The authoritative files are now available and have been extracted into the implementation matrix. The following decisions remain unresolved and are intentionally not inferred from the PDFs.

- `Back Side.pdf`
- `Front Side Selection Paper.pdf`

## NEEDS_DECISION items

### 1. Basic Test scoring

`NEEDS_DECISION`: The paper prints `10` beside each of `Batting`, `Wkt Keeper`, `Bowling`, `Fitness`, `Written test`, and `overall`, but does not state whether these are maximum marks, entered evaluator ratings, or calculated values. Confirm whether unanswered questions contribute zero or remain null, and whether any weighting or pass threshold exists.

### 2. Test result values

`NEEDS_DECISION`: Confirm the exact allowed result values and whether the result is derived solely from the paper or may be corrected by an authorized evaluator.

### 3. Approval and rejection

`NEEDS_DECISION`: Confirm which role may move a Test to `Approved for Admission` or `Rejected/Closed`, whether rejection requires a reason, and whether either transition is reversible.

### 4. Status transitions

`NEEDS_DECISION`: Confirm the allowed transitions among Draft, Test Started, Test Submitted, Meeting Follow-up, Approved for Admission, and Rejected/Closed, including retry behavior after a failed or partial submission.

### 5. Meeting Follow-ups

`NEEDS_DECISION`: Confirm the permitted follow-up types, statuses, assignment roles, whether a follow-up is required before approval, and whether completed/closed Test records remain editable.

### 6. Pending Admission conversion

`NEEDS_DECISION`: Confirm the exact parent/student agreement action, who may perform it, and whether conversion is allowed from only `Approved for Admission` or from another Test status as well.

### 7. Applicant identity and missing Lead

`NEEDS_DECISION`: Confirm the applicant source when a Trial has no Lead and which existing Admission identity fields are authoritative when PDF labels differ.

### 8. Incomplete Selection Paper prompts

`NEEDS_DECISION`: A6 and C4 remain incomplete in the supplied PDF. B9 and C5 contain encoded text that literally extracts as `Who is the youngest cricketer to score in list A in cricket?`, but its grammar/meaning is unclear. The literal wording is preserved and all printed options remain selectable; confirm the approved editorial wording before changing it.

### 9. Printed date and competition wording

`NEEDS_DECISION`: Variant A contains visually inconsistent or incomplete wording for the final-match year and the T-cricket/T-20 wording. Confirm whether the UI should reproduce the literal printed text or a corrected editorial form. Until decided, the literal extracted labels are preserved in the reference contract.

### 10. Assessment storage and correction history

`NEEDS_DECISION`: The six printed assessment values are stored as nullable 0-10 fields and versioned with response corrections. No aggregate score, result, weighting, or pass/fail behavior is calculated until the assessment policy is approved.

## Implementation gate

Implementation may proceed for unambiguous fields, questions, options, linkage, persistence, and workflow scaffolding. It must not invent unresolved question text, score calculation, pass threshold, or approval/rejection policy.
