<?php

/**
 * Structured printable Admission document.
 *
 * @var array<string, mixed> $admission
 */
$value = static function (string $field, mixed $fallback = 'Not provided') use ($admission): string {
    $raw = $admission[$field] ?? $fallback;

    if (is_array($raw)) {
        $raw = implode(', ', array_map(static fn (mixed $item): string => (string) $item, $raw));
    }

    $raw = trim((string) $raw);

    return $raw === '' ? (string) $fallback : $raw;
};
$ratingDisplay = static function (string $field) use ($admission): string {
    $raw = trim((string) ($admission[$field] ?? ''));
    if ($raw === '') {
        return 'Not rated';
    }

    return (new \App\Services\TrialRatingService())->formatted($raw);
};
$money = static function (mixed $raw): string {
    $raw = trim((string) ($raw ?? ''));

    return $raw === '' ? 'Not recorded' : 'INR ' . $raw;
};
$signatureValue = static function (string $field) use ($admission): string {
    return trim((string) ($admission[$field] ?? ''));
};
$signatureSource = static function (string $field) use ($signatureValue): ?string {
    $raw = $signatureValue($field);

    if ($raw === '') {
        return null;
    }

    if (str_starts_with($raw, 'data:image/')) {
        return $raw;
    }

    if (is_file($raw)) {
        return $raw;
    }

    return null;
};
$readablePlan = static function (mixed $raw): string {
    if (is_array($raw)) {
        $raw = implode(', ', array_map(static fn (mixed $item): string => (string) $item, $raw));
    }

    $text = trim((string) ($raw ?? ''));

    if ($text === '') {
        return 'Not provided';
    }

    if (ctype_digit($text)) {
        return 'Plan selected';
    }

    return str_replace(['_', '-'], ' ', $text);
};
$admissionNumber = $value('admission_number', 'Missing number');
$admissionDate = $value('admission_date_display');
$applicant = $value('applicant_full_name', $admission['lead_name'] ?? 'Not recorded');
$coachingRows = is_array($admission['coaching_installment_history'] ?? null)
    ? $admission['coaching_installment_history']
    : [];
$hostelRows = is_array($admission['hostel_installment_history'] ?? null)
    ? $admission['hostel_installment_history']
    : [];

$scheduleRows = static function (array $rows) use ($money): array {
    $result = [];

    foreach ($rows as $row) {
        if (! is_array($row)) {
            continue;
        }

        foreach (range(1, 4) as $number) {
            $date = trim((string) ($row['installment_' . $number . '_date'] ?? ''));

            if ($date === '') {
                continue;
            }

            $result[] = [
                'year' => trim((string) ($row['year_label'] ?? ('Year ' . ($row['year_number'] ?? '')))),
                'installment' => (string) $number,
                'date' => $date,
                'amount' => $money($row['installment_' . $number] ?? null),
            ];
        }
    }

    return $result;
};
$coachingSchedule = $scheduleRows($coachingRows);
$hostelSchedule = $scheduleRows($hostelRows);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admission <?= esc($admissionNumber) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 12mm 16mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: #ffffff;
            color: #17212b;
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
            margin: 0;
        }

        .pdf-header {
            border-bottom: 2px solid #d4a72c;
            margin-bottom: 12px;
            padding-bottom: 10px;
        }

        .pdf-header table,
        .pdf-details,
        .pdf-summary {
            border-collapse: collapse;
            width: 100%;
        }

        .pdf-header td {
            vertical-align: middle;
        }

        .pdf-brand-mark {
            background: #163b63;
            border: 3px solid #d4a72c;
            height: 54px;
            padding: 3px;
            text-align: center;
            width: 66px;
        }

        .pdf-logo-image {
            display: block;
            height: 48px;
            margin: 0 auto;
            max-width: 56px;
            object-fit: contain;
            width: auto;
        }

        .pdf-brand-copy {
            padding-left: 12px;
        }

        .pdf-brand-copy h1 {
            color: #163b63;
            font-size: 17pt;
            margin: 0 0 2px;
        }

        .pdf-brand-copy p {
            margin: 0;
        }

        .pdf-header-meta {
            color: #27364a;
            font-size: 8pt;
            text-align: right;
            white-space: nowrap;
        }

        .pdf-header-meta strong {
            color: #163b63;
        }

        .pdf-title {
            color: #163b63;
            font-size: 15pt;
            margin: 0 0 12px;
            text-align: center;
        }

        .pdf-section {
            margin: 0 0 12px;
        }

        .pdf-section h2 {
            background: #eaf0f6;
            border-left: 4px solid #163b63;
            color: #163b63;
            font-size: 10.5pt;
            margin: 0 0 6px;
            padding: 5px 8px;
            page-break-after: avoid;
        }

        .pdf-details {
            table-layout: fixed;
        }

        .pdf-details td {
            border: 1px solid #c4cfda;
            padding: 5px 7px;
            vertical-align: top;
            width: 50%;
        }

        .pdf-label {
            color: #536274;
            display: block;
            font-size: 7.5pt;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .pdf-value {
            overflow-wrap: anywhere;
        }

        .pdf-table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .pdf-table th,
        .pdf-table td {
            border: 1px solid #b8c4d0;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        .pdf-table th {
            background: #163b63;
            color: #ffffff;
            font-weight: 700;
        }

        .pdf-table tbody tr:nth-child(even) {
            background: #f5f8fb;
        }

        .pdf-table .amount {
            text-align: right;
            white-space: nowrap;
        }

        .pdf-fees-table th:nth-child(1),
        .pdf-fees-table td:nth-child(1) {
            width: 18%;
        }

        .pdf-fees-table th:nth-child(2),
        .pdf-fees-table td:nth-child(2) {
            width: 24%;
        }

        .pdf-fees-table th:nth-child(3),
        .pdf-fees-table td:nth-child(3) {
            width: 18%;
        }

        .pdf-fees-table th:nth-child(4),
        .pdf-fees-table td:nth-child(4) {
            width: 24%;
        }

        .pdf-fees-table th:nth-child(5),
        .pdf-fees-table td:nth-child(5) {
            width: 16%;
        }

        .pdf-declaration {
            border: 1px solid #c4cfda;
            background: #fbfcfd;
            padding: 7px 9px;
        }

        .pdf-declaration p {
            margin: 0 0 5px;
        }

        .pdf-declaration p:last-child {
            margin-bottom: 0;
        }

        .pdf-signatures {
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 18px -10px 0;
            table-layout: fixed;
            width: calc(100% + 20px);
            page-break-inside: avoid;
        }

        .pdf-signature {
            padding-top: 0;
            text-align: center;
            vertical-align: top;
        }

        .pdf-signature-label {
            color: #163b63;
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .pdf-signature-box {
            align-items: center;
            border: 1px solid #8796a6;
            display: flex;
            height: 48px;
            justify-content: center;
            overflow: hidden;
            padding: 4px;
            page-break-inside: avoid;
        }

        .pdf-signature-box img {
            display: block;
            height: 38px;
            max-width: 100%;
            object-fit: contain;
            width: auto;
        }

        .pdf-signature-box span {
            color: #536274;
            font-size: 7.5pt;
            overflow-wrap: anywhere;
        }

        .pdf-terms {
            page-break-before: always;
        }

        .pdf-terms h3 {
            color: #163b63;
            font-size: 9.5pt;
            margin: 9px 0 4px;
            page-break-after: avoid;
        }

        .pdf-terms p {
            margin: 0 0 5px;
        }

        .pdf-terms ul {
            margin: 3px 0 7px 17px;
            padding: 0;
        }

        .pdf-terms li {
            margin: 0 0 3px;
            page-break-inside: avoid;
        }

        .pdf-footer {
            border-top: 1px solid #c4cfda;
            color: #536274;
            font-size: 7.5pt;
            margin-top: 14px;
            padding-top: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="pdf-header">
        <table>
            <tr>
                <td class="pdf-brand-mark" aria-label="MCC">
                    <img
                        class="pdf-logo-image"
                        src="<?= esc(ROOTPATH . 'public/assets/img/logo.png') ?>"
                        alt="MCC logo"
                        data-pdf-image
                    >
                </td>
                <td class="pdf-brand-copy">
                    <h1>Mumbai Cricket Club</h1>
                    <p>Admission Form</p>
                </td>
                <td class="pdf-header-meta">
                    <div><strong>Admission No.:</strong> <?= esc($admissionNumber) ?></div>
                    <div><strong>Admission Date:</strong> <?= esc($admissionDate) ?></div>
                    <div><strong>Branch:</strong> <?= esc($value('branch_name')) ?></div>
                </td>
            </tr>
        </table>
    </header>

    <h1 class="pdf-title">Admission Form</h1>

    <main>
        <section class="pdf-section">
            <h2>Personal Details</h2>
            <table class="pdf-details">
                <tbody>
                    <?php foreach (array_chunk([
                        ['Applicant name', $applicant],
                        ['Lead name / reference', $value('lead_name')],
                        ['Applicant email', $value('applicant_email', $admission['lead_email'] ?? 'Not recorded')],
                        ['Applicant phone', $value('applicant_phone', $admission['lead_phone'] ?? 'Not recorded')],
                        ['Date of birth', $value('applicant_date_of_birth', $admission['lead_date_of_birth'] ?? 'Not recorded')],
                        ['Age', $value('age')],
                        ['Gender', $value('gender')],
                        ['Father name', $value('father_name')],
                        ['Mother name', $value('mother_name')],
                        ['Parent mobile', $value('parent_mobile')],
                        ['Parent email', $value('parent_email')],
                        ['Emergency contact', $value('emergency_contact')],
                        ['School / college', $value('school_college')],
                        ['Current address', $value('current_address')],
                        ['Permanent address', $value('permanent_address')],
                        ['Correspondence address', $value('correspondence_address')],
                        ['Medical conditions', $value('medical_conditions')],
                    ], 2) as $row): ?>
                        <tr>
                            <?php foreach ($row as [$label, $fieldValue]): ?>
                                <td>
                                    <span class="pdf-label"><?= esc($label) ?></span>
                                    <span class="pdf-value"><?= esc($fieldValue) ?></span>
                                </td>
                            <?php endforeach; ?>
                            <?php if (count($row) === 1): ?>
                                <td></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="pdf-section">
            <h2>Cricket Information</h2>
            <table class="pdf-details">
                <tbody>
                    <?php foreach (array_chunk([
                        ['Playing experience', $value('playing_experience')],
                        ['Previous coaching centre', $value('previous_coaching_centre')],
                        ['Playing role', $value('playing_role')],
                        ['Batting style', $value('batting_style')],
                        ['Bowling style', $value('bowling_style')],
                        ['Strong hand', $value('strong_hand')],
                        ['Jersey size', $value('jersey_size')],
                        ['Hobbies', $value('hobbies')],
                        ['Achievements', $value('achievements')],
                        ['Overall rating', $ratingDisplay('ability_overall')],
                        ['Batting rating', $ratingDisplay('ability_batting')],
                        ['Bowling rating', $ratingDisplay('ability_bowling')],
                        ['Wicketkeeping rating', $ratingDisplay('ability_wicketkeeping')],
                        ['Fitness rating', $ratingDisplay('ability_fitness')],
                        ['Batting order', $value('batting_order')],
                        ['Bowling options', $value('bowling_options')],
                    ], 2) as $row): ?>
                        <tr>
                            <?php foreach ($row as [$label, $fieldValue]): ?>
                                <td>
                                    <span class="pdf-label"><?= esc($label) ?></span>
                                    <span class="pdf-value"><?= esc($fieldValue) ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="pdf-section">
            <h2>Coaching Plan</h2>
            <table class="pdf-details">
                <tbody>
                    <?php foreach (array_chunk([
                        ['Batch', $value('batch_name')],
                        ['Batch coach', $value('assigned_coach_name')],
                        ['Personal coach', $value('personal_coach_name')],
                        ['Coaching plan', $value('coaching_plan_label', $admission['coaching_plan'] ?? 'Not applicable')],
                        ['Coaching type', $value('coaching_type')],
                        ['Training delivery', $value('coaching_delivery')],
                        ['Personal coaching', $value('personal_coaching_days') . ' / ' . $value('personal_coaching_duration') . ' / ' . $value('estimated_personal_session')],
                        ['Group coaching', $value('group_coaching_days')],
                        ['Fitness and gym', $value('fitness_plan') . ' / ' . $value('gym_fitness_session')],
                        ['Match programme', $value('match_program')],
                        ['Induction', $value('induction_plan')],
                        ['Reviews and reports', $value('review_frequency') . ' / ' . $value('progress_report_frequency')],
                        ['Feedback and meditation', $value('feedback_call_frequency') . ' / ' . $value('meditation_sessions')],
                        ['Elite camp', $value('elite_camp') . ' / ' . $value('elite_camp_days') . ' / ' . $value('elite_camp_duration') . ' / ' . $value('estimated_elite_session')],
                        ['Student diary', $value('student_diary')],
                        ['Welcome kit', $value('welcome_kit')],
                    ], 2) as $row): ?>
                        <tr>
                            <?php foreach ($row as [$label, $fieldValue]): ?>
                                <td>
                                    <span class="pdf-label"><?= esc($label) ?></span>
                                    <span class="pdf-value"><?= esc($fieldValue) ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="pdf-section">
            <h2>Fees</h2>
            <table class="pdf-table pdf-fees-table">
                <thead>
                    <tr>
                        <th>Coaching Fee</th>
                        <th>Coaching Payment Plan</th>
                        <th>Hostel Fee</th>
                        <th>Hostel Payment Plan</th>
                        <th>Total amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="amount"><?= esc($money($admission['training_fee'] ?? null)) ?></td>
                        <td><?= esc($readablePlan($admission['installment_plan'] ?? null)) ?></td>
                        <td class="amount"><?= esc($money($admission['hostel_charges'] ?? null)) ?></td>
                        <td><?= esc($readablePlan($admission['hostel_installment_plan'] ?? null)) ?></td>
                        <td class="amount"><?= esc($money($admission['total_coaching_hostel_fee'] ?? null)) ?></td>
                    </tr>
                </tbody>
            </table>
            <dl class="pdf-fees-summary">
                <dt>Taxable Amount</dt>
                <dd><?= esc($money($admission['total_taxable_fee'] ?? null)) ?></dd>
                <dt>Tax Rate</dt>
                <dd><?= ($admission['tax_rate'] ?? null) === null || ($admission['tax_rate'] ?? '') === '' ? 'Not recorded' : esc((string) $admission['tax_rate']) . '%' ?></dd>
                <dt>Calculated Tax Amount</dt>
                <dd><?= esc($money($admission['calculated_tax_amount'] ?? null)) ?></dd>
            </dl>
        </section>

        <section class="pdf-section">
            <h2>Coaching Instalments</h2>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Instalment number</th>
                        <th>Amount</th>
                        <th>Due date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($coachingSchedule === []): ?>
                        <tr><td colspan="4">Not recorded</td></tr>
                    <?php endif; ?>
                    <?php foreach ($coachingSchedule as $row): ?>
                        <tr>
                            <td><?= esc($row['year']) ?></td>
                            <td><?= esc($row['installment']) ?></td>
                            <td class="amount"><?= esc($row['amount']) ?></td>
                            <td><?= esc($row['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="pdf-section">
            <h2>Hostel Instalments</h2>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Instalment number</th>
                        <th>Amount</th>
                        <th>Due date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($hostelSchedule === []): ?>
                        <tr><td colspan="3">Not recorded</td></tr>
                    <?php endif; ?>
                    <?php foreach ($hostelSchedule as $row): ?>
                        <tr>
                            <td><?= esc($row['installment']) ?></td>
                            <td class="amount"><?= esc($row['amount']) ?></td>
                            <td><?= esc($row['date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="pdf-section">
            <h2>Declarations and Signatures</h2>
            <div class="pdf-declaration">
                <p>I have read the rules and Regulations carefully and shall be binding on me.</p>
                <p><strong>Confirmation notes:</strong> <?= esc($value('confirmation_notes')) ?></p>
            </div>
            <table class="pdf-signatures">
                <tr>
                    <td class="pdf-signature">
                        <span class="pdf-signature-label">Student signature</span>
                        <div class="pdf-signature-box">
                            <?php if ($signatureSource('student_signature') !== null): ?>
                                <img src="<?= esc($signatureSource('student_signature')) ?>" alt="Student signature" data-pdf-image>
                            <?php elseif ($signatureValue('student_signature') !== ''): ?>
                                <span><?= esc($signatureValue('student_signature')) ?></span>
                            <?php else: ?>
                                <span>Signature not provided</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="pdf-signature">
                        <span class="pdf-signature-label">Parent/Guardian signature</span>
                        <div class="pdf-signature-box">
                            <?php if ($signatureSource('parent_signature') !== null): ?>
                                <img src="<?= esc($signatureSource('parent_signature')) ?>" alt="Parent or guardian signature" data-pdf-image>
                            <?php elseif ($signatureValue('parent_signature') !== ''): ?>
                                <span><?= esc($signatureValue('parent_signature')) ?></span>
                            <?php else: ?>
                                <span>Signature not provided</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="pdf-signature">
                        <span class="pdf-signature-label">Club Authorized Person signature</span>
                        <div class="pdf-signature-box">
                            <?php if ($signatureSource('authorized_signature') !== null): ?>
                                <img src="<?= esc($signatureSource('authorized_signature')) ?>" alt="Club authorized person signature" data-pdf-image>
                            <?php elseif ($signatureValue('authorized_signature') !== ''): ?>
                                <span><?= esc($signatureValue('authorized_signature')) ?></span>
                            <?php else: ?>
                                <span>Signature not provided</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
        </section>

        <section class="pdf-section pdf-terms">
            <h2>Terms and Conditions</h2>
            <?= view('App\\Modules\\Admissions\\Views\\_terms_and_conditions', ['idPrefix' => 'pdf-terms-']) ?>
        </section>
    </main>

    <footer class="pdf-footer">Mumbai Cricket Club | Admission <?= esc($admissionNumber) ?> | Document date <?= esc(date('d-m-Y')) ?></footer>
</body>
</html>
