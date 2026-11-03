# Admission Test Implementation Matrix

## Scope status

Status: `REFERENCE_EXTRACTION_COMPLETE_IMPLEMENTATION_VERIFIED_WITH_NEEDS_DECISION_ITEMS`

The authoritative files are now available at `docs/reference/Back Side.pdf` and `docs/reference/Front Side Selection Paper.pdf`. Both were inspected before implementation. The older `tmp/pdfs/admission-redesign-test.pdf` is non-authoritative and is not used by this module.

The authoritative extraction and the Admission comparison are recorded below.

No Admission Test scoring, payment collection, or approval rules have been invented. Fee capture is implemented as a separate, editable post-submission workflow.

## Existing module mapping

| Requested area | Existing source | Reuse/implementation decision |
| --- | --- | --- |
| Trial linkage | `trials`, `TrialService`, CRM Trial routes/views | Reuse Trial primary key and existing branch authorization. |
| Lead linkage | `trials.lead_id`, CRM Lead/Trial services | Reuse existing Lead relationship where available. |
| Branch scope | `trials.branch_id`, `BranchContextService`, centralized authorization | Reuse server-side branch isolation. |
| Applicant personal details | `AdmissionModel` and Admission form/service | Reuse matching fields only after PDF labels are verified. |
| Parent details | `AdmissionModel` and Admission form/service | Reuse matching fields only after PDF labels are verified. |
| Follow-ups | `AdmissionFollowUpService`, `admission_followups`, Admission Follow-up routes | Follow-up workflow can be adapted only after the Admission Test relationship and permissions are approved. |
| Pending Admission | Existing Trial → Pending Admission service and Admission routes | Reuse existing conversion service; do not create a second conversion workflow. |
| CRM navigation | `NavigationService`, CRM sidebar | Add Admission Test immediately after Trials only after the route and permission are approved. |

## Personal Details prefill mapping

Prefill is a read-only data projection. It does not update Lead, Trial, or Admission records.
The linked Lead is loaded first and must belong to the Trial branch. An existing Admission is
used only as a fallback and must belong to the same branch. Existing Admission Test values take
priority over source values; submitted validation input takes priority over both.

| Admission Test / Back Side.pdf field | Lead source | Admission fallback | Trial fallback | Indicator |
| --- | --- | --- | --- | --- |
| `applicant_full_name` / Name of the Player | `full_name` | `applicant_full_name` | None | Prefilled from Lead/Admission |
| `applicant_email` | `email` | `applicant_email` | None | Prefilled from Lead/Admission |
| `applicant_phone` | `phone` | `applicant_phone` | None | Prefilled from Lead/Admission |
| `applicant_date_of_birth` / Date of Birth | `date_of_birth` | `applicant_date_of_birth` | None | Prefilled from Lead/Admission |
| `father_name` / Father Name | None | `father_name` | None | Prefilled from Admission |
| `father_profession` / Father Profession | None | `father_profession` | None | Prefilled from Admission |
| `age` / Age | None | `age` | None | Prefilled from Admission |
| `residence` / Residence | None | `residence` | None | Prefilled from Admission |
| `contact_number` / Contact No 1 | `phone` | `parent_mobile` | None | Prefilled from Lead/Admission |
| `secondary_contact_number` / Contact No 2 | None | `secondary_contact_number` | None | Prefilled from Admission |
| `email` / Email ID 1 | `email` | `parent_email` | None | Prefilled from Lead/Admission |
| `secondary_email` / Email ID 2 | None | `secondary_email` | None | Prefilled from Admission |
| `represented_as` / Represented | None | `represented_as` | None | Prefilled from Admission |
| `coaching_start` / Date/Month to start coaching | None | `joining_date` | None | Prefilled from Admission |
| `coaching_type` / Which type of coaching | None | `coaching_type` | None | Prefilled from Admission |
| `playing_role` / Cricket skill / Ability | None | `playing_role` | None | Prefilled from Admission |
| `nearest_branch` / Nearest branch your Residence | None | `nearest_branch` | None | Prefilled from Admission |
| `discovery_source` / How did you come to know | None | `discovery_source` | None | Prefilled from Admission |
| `reference_details` / Reference | None | `reference_details` | None | Prefilled from Admission |
| `achievements` / Achievements | None | `achievements` | None | Prefilled from Admission |

No exact Back Side.pdf / existing Admission Test mapping is approved for gender, school/college,
previous coaching information, or cricket background, so these fields are not copied or invented.
The Trial currently has no exact matching personal-detail source field; its date, location, and
remarks are not used as personal-detail fallbacks.

## Required reference extraction

| Reference | Required extraction | Status |
| --- | --- | --- |
| `Back Side.pdf` | Exact applicant, parent, and additional field labels; field types; required/optional indicators; ordering | `NEEDS_DECISION` — file unavailable |
| `Front Side Selection Paper.pdf` | Exact Basic Test sections, questions, options, marks, result fields, and evaluator fields | `NEEDS_DECISION` — file unavailable |

## Data design pending reference review

The minimum proposed separation is:

- Admission Test header linked to Trial, Lead, Branch, and Applicant.
- Basic Test response/result table linked to Admission Test.
- Admission Test Follow-up table linked to Admission Test and retaining Trial/Branch context.

Exact columns, status transitions, scoring representation, and foreign keys remain pending the authoritative PDFs and decisions listed in the decision request.

## Security and workflow constraints

- Admission Test paper-set rating fields are legacy-readable only. New wizard, review, correction, answer persistence, and projections do not render, validate, or write them. Trial Coach ratings and Admission ratings remain separate workflows.

- Explicit routes and thin controllers.
- Centralized permissions and branch authorization on every read/write.
- CSRF on browser writes.
- Transactions, rollback, optimistic concurrency, idempotency, and append-only audit events.
- Existing Admission conversion service and canonical `admission_number` generation must be reused.
- No changes to Match/Tournament workflows or `docs/SRS-original.md`.

## Admission Test Fees implementation

| Requirement | Implementation | Storage/validation |
| --- | --- | --- |
| Coaching and Hostel Fee with payment plans | Dedicated Fees section on submitted/review pages using the Admission input-group pattern | `admission_tests.training_fee`, `installment_plan`, `hostel_charges`, `hostel_installment_plan`; non-negative decimal values and existing `Full Payment`/`Instalment Plan` options |
| Installment schedules | Editable coaching and hostel year rows with four due-date/amount pairs | `admission_test_coaching_installments` and `admission_test_hostel_installments`; server-side date, year, and amount validation |
| Full payment behavior | Schedule is hidden and marked `Installments: Not applicable` | No schedule rows are persisted for a full-payment plan |
| Total summary | Recalculated from captured coaching and hostel fees only | No Admission Fee field was introduced because it is not an approved Admission Test requirement |
| Independent save | `POST /admission-tests/{id}/fees` | Version-checked update; test responses, answers, score, result, Trial link, and follow-up history are not modified |
| Security and audit | `admission.update`, CSRF, branch authorization, transaction/rollback, and optimistic concurrency | Audit includes before/after fee and schedule values, actor, branch, Admission Test ID, required reason, and UTC timestamp |
| Pending Admission conversion | Existing conversion copies the captured fee fields and schedules into the normal Admission fee structures | Conversion remains idempotent and does not change existing Admission or Admission Follow-up behavior |

## Authoritative PDF extraction

### Back Side.pdf

The portrait, one-page form is headed exactly `ADMISSION ENQUIRY FORM (MCC)` and includes `Kindly read the full form before you fill.` It uses numbered underlined answer areas and compact option rows.

Fields and printed options:

1. `Name of the Player :-`
2. `Father Name :-`
3. `Father Profession:-`
4. `Age :-`, `Date of Birth:-`, `Residence:-`
5. `Contact No :-` with blanks `1).` and `2).`
6. `Email ID :-` with blanks `1).` and `2).`
7. `Represented:` with `School`, `Academy`, `District`, `State`, `MCA`, `None`
8. `Date/Month (Wish to start the coaching) :-`
9. `Which type of coaching would you like to join :-` with `1). Group`, `2). Personal`, `3). Short term`, `4). Advance Training`, `5). Others`
10. `Cricket skill / Ability :-` with `1). Batsman`, `2). Bowler`, `3). All-rounder`
11. `Nearest branch your Residence :-` with `1). Santa Cruz`, `2). Thane`, `3). Bhandup`
12. `How did you come to know about us :-`
12. `Reference (Including address / contact no :-`

The page also contains `Achievements` and a closing `Note:` area. The duplicate printed number `12` is preserved as part of the PDF contract. No required/optional indicators are printed.

### Admission field comparison

| PDF label | Existing Admission field reused | New field required |
| --- | --- | --- |
| Name of the Player | `applicant_full_name` | No |
| Father Name | `father_name` | No |
| Age | `age` | No |
| Date of Birth | `applicant_date_of_birth` | No |
| Contact No 1 | `parent_mobile` | No |
| Email ID 1 | `parent_email` | No |
| Date/Month to start coaching | `joining_date` | No; month-only interpretation is `NEEDS_DECISION` |
| Which type of coaching | Existing coaching-plan field | No |
| Cricket skill / Ability | `playing_role` | No |
| Achievements | `achievements` | No |
| Father Profession | None | `father_profession` |
| Residence | No exact equivalent | `residence` |
| Contact No 2 | None | `secondary_contact_number` |
| Email ID 2 | None | `secondary_email` |
| Represented | No exact equivalent | `represented_as` |
| Nearest branch your Residence | Not the same as authorized `branch_id` | `nearest_branch` |
| How did you come to know about us | No exact equivalent | `discovery_source` |
| Reference (Including address / contact no | No exact equivalent | `reference_details` |

### Front Side Selection Paper.pdf

The PDF contains three portrait Selection Test variants identified as `A`, `B`, and `C`. Each has `Selection Test`, `Please tick the correct answer given below`, `DATE: __________`, `NAME :_______________________________________________`, `AGE :___________`, and ten numbered questions. The printed assessment row is retained only as legacy reference/storage compatibility; it is not part of the Admission Test capture or display workflow.

Variant A:

1. `Who cracked the Highest score in Ranji Trophy?` — `Sachin Tendulkar`, `Virender Sehwag`, `Aakash Chopra`, `Wasim Jaffer`
2. `In which direction does the ball of left arm off spinner turn for Right Hand Batsman?` — `In`, `Out`
3. `who was the MOM for final match?` — `Sandeep Patil`, `Mohinder Amarnath`, `Kapil Dev`, `Sunil Gavaskar`; year is unclear: `NEEDS_DECISION`
4. `Who won the 2018 U-19 world cup?` — `India`, `sri-lanka`, `Pakistan`, `Australia`
5. `who bowled the fastest ball in cricket History?` — `Zaheer Khan`, `shoaib akhtar`, `Shaun Tait`, `Brett Lee`
6. Printed prompt begins `Who scored 2nd ...`; options `Virender Sehwag`, `Sunil Gavaskar`, `Karun Nair`, `Rahul Dravid`, `V.V.S Laxman`; exact prompt: `NEEDS_DECISION`
7. `What is the maximum number of overs a bowler can bowl in T-20 cricket?` — `4`, `6`, `5`, `2`
8. Power-play question describing the one-day fielding restriction and asking `In a T-20 match, how many overs of power play is allowed?` — `6`, `5`, `4`, `10`
9. `How long is the duration of one innings of a T-20 match?` — `60 minutes`, `75 minutes`, `20 minutes`, `90 minutes`
10. `In T-cricket, how many bouncers are allowed in any over?` — `2`, `0`, `3`, `1`; competition wording: `NEEDS_DECISION`

Variant B:

1. `Who got the Highest Left arm wicket in an International cricket?` — `Deniel Vettori`, `Rangana Herath`, `Pragyan Ojha`, `Ashley Giles`
2. Bowl-out question beginning `A new concept in T-20 cricket is that of the football style bowl-out. When is a bowl-out resorted to?` — `When the match is interrupted by rain`, `When the match has to be called off`, `When the result of the match is a tie`, `When there is time for only less than 5 overs to be bowled`
3. Beamer statement ending `The batsman gets a free hit the next delivery.` — `TRUE`, `False`
4. `who cracked the Highest score in Ranji Trophy?` — `Sachin Tendulkar`, `Virender Sehwag`, `Aakash Chopra`, `Wasim Jaffer`
5. `What is the name given to the biennial international Test cricket series played between England and Australia?` — `The Sheffield Shield`, `The Cricket World Cup`, `The Trans-Tasman Trophy`, `The Ashes`
6. `What does the term LBW stand for in cricket?` — `Long blue wickets`, `Lunch bought weekly`, `Leg before wicket`, `Leg before wide`
7. `What is the length of a cricket pitch?` — `20.12m`, `20.50 m`, `22.12m`, `21.20m`
8. `What is the maximum permissible length of bat in cricket?` — `38 inches`, `42 inches`, `35 inches`, `40 inches`
9. The encoded PDF text extracts literally as `Who is the youngest cricketer to score in list A in cricket?`; grammar/meaning remains `NEEDS_DECISION`. Options: `Sanju Samson`, `Virender Sehwag`, `Kumar Sangakara`, `Yashasvi Jaiswal`.
10. Free-hit question beginning `Another new concept of T-20 cricket is the free-hit... When does the free-hit come into play?` — `When the bowler bowls a no ball`, `When the bowler bowls a beamer`, `When the bowler bowls a wide`, `When the bowler sledges the batsman`

Variant C:

1. LBW question — `Long blue wickets`, `Lunch bought weekly`, `Leg before wicket`, `Leg before wide`
2. Highest left-arm wicket question — `Deniel Vettori`, `Rangana Herath`, `Pragyan Ojha`, `Ashley Giles`
3. Highest Ranji Trophy score question — `Sachin Tendulkar`, `Virender Sehwag`, `Aakash Chopra`, `Wasim Jaffer`
4. Printed prompt begins `Who scored 2...`; options `Virender Sehwag`, `Sunil Gavaskar`, `Karun Nair`, `Rahul Dravid`, `V.V.S Laxman`; exact prompt: `NEEDS_DECISION`
5. The encoded PDF text extracts literally as `Who is the youngest cricketer to score in list A in cricket?`; grammar/meaning remains `NEEDS_DECISION`. Options: `Sanju Samson`, `Virender Sehwag`, `Kumar Sangakara`, `Yashasvi Jaiswal`.
6. `How long is the duration of one innings of a T-20 match?` — `60 minutes`, `75 minutes`, `20 minutes`, `90 minutes`
7. MOM question — `Sandeep Patil`, `Mohinder Amarnath`, `Kapil Dev`, `Sunil Gavaskar`; year: `NEEDS_DECISION`
8. `For how many days is a Test match scheduled?` — `50 overs`, `Five days`, `One day`, `100 overs`
9. `Full form of ICC is:` — `India Cricket Council`, `International Cricket Committee`, `International Cricket Council`, `Intercontinental Cricket Corporation`
10. Beamer statement — `TRUE`, `False`

No question-level marks or pass threshold are printed. The visible `10` values belong to the legacy assessment row, which is excluded from current Admission Test capture and display; score calculation and approval semantics remain `NEEDS_DECISION`.

## Complete coverage gap table

Every non-ambiguous PDF item is rendered, stored, restored, and shown in review. Ambiguous literal text remains visible with `NEEDS_DECISION`; it is not silently corrected.

| PDF item | Existing implementation | Missing implementation closed | Storage field | Validation | Display location |
| --- | --- | --- | --- | --- | --- |
| Back Side: Name, Father Name, Age, Date of Birth | Admission-equivalent fields | None | Test personal fields | Existing Admission rules | Personal Details/review |
| Back Side: Father Profession, Residence | Missing | Added migration/model/view mapping | `father_profession`, `residence` | Nullable text | Personal Details/review |
| Back Side: Contact No 1/2, Email ID 1/2 | First values existed | Secondary values mapped | Contact/email fields | Nullable valid email | Personal Details/review |
| Back Side: Represented | Missing | Added selector | `represented_as` | Printed option list | Personal Details/review |
| Back Side: Date/Month, coaching type, skill | Existing equivalents | Test mapping completed | `coaching_start`, `coaching_type`, `playing_role` | Printed option lists | Personal Details/review |
| Back Side: Nearest branch your Residence | Branch was conflated risk | Separate field retained | `nearest_branch` | Printed branch list | Personal Details/review |
| Back Side: Discovery, Reference, Achievements | Partial/missing | Added and mapped | `discovery_source`, `reference_details`, `achievements` | Nullable text | Personal Details/review |
| Front page 1 / Paper Set A, A1-A10 | 10 configured questions | Rendered and persisted only when Set A is selected and locked | `admission_test_responses(variant=A)` | Configured option or null | Selected-set review/history |
| Front page 2 / Paper Set B, B1-B10 | 10 configured questions | Rendered and persisted only when Set B is selected and locked | `admission_test_responses(variant=B)` | Configured option or null | Selected-set review/history |
| Front page 3 / Paper Set C, C1-C10 | 10 configured questions | Rendered and persisted only when Set C is selected and locked | `admission_test_responses(variant=C)` | Configured option or null | Selected-set review/history |
| A6, B9, C4, C5 incomplete/blank prompts | Placeholder retained | No guessed wording | Response `prompt` snapshot | Option validation only | Basic Test/review/decision request |
| A3/C7 year and A10 T-cricket wording | Ambiguous | Literal ambiguity retained | Response `prompt` snapshot | Option validation only | Basic Test/review |
| Printed Batting, Wkt Keeper, Bowling, Fitness, Written test, overall | Legacy columns/history | No new controls, validation, writes, or rendering; columns retained | `admission_tests.*_mark` | Legacy-readable only | Storage compatibility |
| Printed assessment maximum `10` | No score rule | Excluded from current workflow; no calculation | Legacy assessment fields | No pass/weighting inference | Decision/reference only |
| DATE, NAME, AGE and instruction on pages 1-3 | Summary existed | Page instructions and three page headings rendered | Trial/Test header plus page sections | Existing Trial validation | Detail header/Basic Test |
| Score/result fields | Nullable columns existed | Remain nullable and uncalculated | `score`, `result` | Policy pending | Review/decision request |
| Correction history | Current rows were replaced | Versioned snapshots added | `admission_test_submission_history` | Unique test/version | Review history |
