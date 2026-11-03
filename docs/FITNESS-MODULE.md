# Fitness Management implementation note

This document records the implementation boundary for SRS Module 6. The
original SRS remains authoritative and is unchanged.

## Implemented scope

- Branch-owned fitness assessments with the SRS metrics and explicit metric
  units in column names.
- Stored assessment history and coach notes.
- Branch-owned fitness plans with goals, dates, status, coach, and notes.
- Search and date filtering for assessment history.
- Player fitness detail view with assessment history, plan history, monthly
  assessment trend, and real-data KPIs.
- Server-side authorization, branch scope, assigned-player restriction for
  ordinary Coaches, and record ownership checks on updates.

## Decisions still required

The requirements review and decision register still mark FIT-01, FIT-02, and
FIT-03 as `NEEDS_DECISION`. This implementation therefore does not invent
clinical ranges, risk severity thresholds, automated risk alerts, or
parent/player detail visibility. Fitness records are available only through
the new fitness permissions. Assessment corrections are permission-controlled
updates and audited until a formal correction/locking policy is approved.

BMI is derived only when a stored assessment contains positive height and
weight values. No BMI, risk, improvement, or trend value is fabricated when
the source data is absent.
