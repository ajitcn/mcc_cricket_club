<?php

declare(strict_types=1);

$admission = is_array($admission ?? null) ? $admission : [];
$masters = is_array($admissionMasters ?? null) ? $admissionMasters : [];
$periods = is_array($admission['periods'] ?? null) ? $admission['periods'] : [];
$current = is_array($admission['current_period'] ?? null) ? $admission['current_period'] : ($periods[0] ?? []);
$errors = session()->getFlashdata('field_errors');
$errors = is_array($errors) ? $errors : [];
$errorFor = static function (string $field) use ($errors): string {
    $message = trim((string) ($errors[$field] ?? ''));
    return $message !== '' ? $message : (function_exists('validation_show_error') ? trim((string) validation_show_error($field)) : '');
};
$valueFor = static function (string $field, mixed $fallback = '') use ($admission): mixed {
    return old($field, $admission[$field] ?? $fallback);
};
$dateValue = static function (string $field, mixed $fallback) use ($valueFor): string {
    $value = (string) $valueFor($field, $fallback);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? date('d-m-Y', strtotime($value)) : $value;
};
$masterOptions = static function (array $rows): array {
    $options = [];
    foreach ($rows as $row) {
        $options[] = ['value' => (string) ((int) ($row['id'] ?? 0)), 'label' => (string) ($row['name'] ?? '')];
    }
    return $options;
};
$selectField = static function (string $field, string $label, array $options, string $class = 'col-12 col-md-6') use ($valueFor, $errorFor): void {
    $id = 'renewal-' . str_replace('_', '-', $field);
    $input = view('components/admission-select', [
        'id' => $id,
        'name' => $field,
        'label' => $label,
        'selected' => (string) $valueFor($field),
        'options' => array_map(static fn (string $option): array => ['value' => $option, 'label' => $option], $options),
        'attributes' => [],
    ]);
    echo view('components/form-field', ['id' => $id, 'label' => $label, 'input' => $input, 'error' => $errorFor($field), 'field_class' => $class]);
};
?>
<?= view('staff_header', [
    'title' => 'Renew Admission',
    'nav' => $nav ?? [],
    'message' => $message ?? null,
    'error' => $error ?? ($renewal_error ?? null),
    'suppressGlobalAlerts' => false,
]) ?>

<main class="mcc-admission-form-shell" aria-labelledby="renew-admission-heading">
    <div class="mcc-card-heading">
        <span class="mcc-eyebrow">Admission renewal</span>
        <h2 id="renew-admission-heading">Renew Admission <?= esc((string) ($admission['admission_number'] ?? 'Not provided')) ?></h2>
        <p class="mcc-muted">The previous period remains read-only history. The fields below are the new period’s selections.</p>
    </div>

    <section class="mcc-card mcc-detail-card" aria-labelledby="previous-period-heading">
        <div class="mcc-card-heading"><span class="mcc-eyebrow">Previous period</span><h3 id="previous-period-heading">Period <?= (int) ($current['period_number'] ?? 1) ?></h3></div>
        <div class="row mcc-admission-fields">
            <div class="mcc-form-field col-12 col-md-4"><strong>Start date</strong><span><?= esc(mcc_user_date($current['start_date'] ?? null, 'Not provided')) ?></span></div>
            <div class="mcc-form-field col-12 col-md-4"><strong>End date</strong><span><?= esc(mcc_user_date($current['end_date'] ?? null, 'Not provided')) ?></span></div>
            <div class="mcc-form-field col-12 col-md-4"><strong>Age Group</strong><span><?= esc((string) ($current['age_group_label'] ?? 'Not provided')) ?></span></div>
            <div class="mcc-form-field col-12 col-md-4"><strong>Fee Plan reference</strong><span><?= esc((string) ($current['fee_plan_label'] ?? 'Not provided')) ?></span></div>
        </div>
    </section>

    <form method="post" action="<?= site_url('admissions/' . (int) ($admission['id'] ?? 0) . '/renew') ?>" class="mcc-admission-form" data-admission-five-sections>
        <?= csrf_field() ?>
        <input type="hidden" name="expected_version" value="<?= (int) ($admission['renewal_version'] ?? 0) ?>">

        <section class="mcc-card mcc-admission-section" aria-labelledby="new-period-heading">
            <div class="mcc-card-heading"><span class="mcc-eyebrow">New period</span><h3 id="new-period-heading">Admission and Age Group</h3></div>
            <div class="row mcc-admission-fields">
                <div class="mcc-form-field col-12 col-md-6"><label for="renewal-start-date">Start date</label><input class="form-control" id="renewal-start-date" name="start_date" type="text" inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc($dateValue('start_date', null)) ?>" required><?php if ($errorFor('start_date') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($errorFor('start_date')) ?></p><?php endif; ?></div>
                <div class="mcc-form-field col-12 col-md-6"><label for="renewal-end-date">End date</label><input class="form-control" id="renewal-end-date" name="end_date" type="text" inputmode="numeric" autocomplete="off" placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc($dateValue('end_date', null)) ?>" required><?php if ($errorFor('end_date') !== ''): ?><p class="mcc-field-error" role="alert"><?= esc($errorFor('end_date')) ?></p><?php endif; ?></div>
                <?php $ageInput = view('components/admission-select', ['id' => 'renewal-age-group-id', 'name' => 'age_group_id', 'label' => 'Age Group', 'selected' => (string) $valueFor('age_group_id'), 'options' => $masterOptions($masters['age_group'] ?? []), 'attributes' => []]); ?>
                <?= view('components/form-field', ['id' => 'renewal-age-group-id', 'label' => 'Age Group', 'input' => $ageInput, 'error' => $errorFor('age_group_id'), 'field_class' => 'col-12 col-md-6']) ?>
            </div>
        </section>

        <section class="mcc-card mcc-admission-section" aria-labelledby="renewal-coaching-heading">
            <div class="mcc-card-heading"><span class="mcc-eyebrow">New period selections</span><h3 id="renewal-coaching-heading">Training Selection and Coaching Options</h3></div>
            <input type="hidden" name="coaching_type[]" value="">
            <input type="hidden" name="fitness_plan[]" value="">
            <input type="hidden" name="gym_fitness_session[]" value="">
            <input type="hidden" name="match_program[]" value="">
            <input type="hidden" name="welcome_kit[]" value="">
            <input type="hidden" name="meditation_sessions" value="">
            <?= view('App\\Modules\\Admissions\\Views\\_complete_form_fields', ['admission' => $admission, 'section' => 'coaching']) ?>
        </section>

        <section class="mcc-card mcc-admission-section" aria-labelledby="renewal-fees-heading">
            <div class="mcc-card-heading"><span class="mcc-eyebrow">New period selections</span><h3 id="renewal-fees-heading">Fees and Payment Plans</h3></div>
            <div class="row mcc-admission-fields">
                <?= view('App\\Modules\\Admissions\\Views\\_fees_fields', ['admission' => $admission]) ?>
            </div>
            <h4>Coaching Instalments</h4>
            <?= view('App\\Modules\\Admissions\\Views\\_complete_form_fields', ['admission' => $admission, 'section' => 'coaching-installments']) ?>
            <h4>Hostel Instalments</h4>
            <?= view('App\\Modules\\Admissions\\Views\\_complete_form_fields', ['admission' => $admission, 'section' => 'hostel-installments']) ?>
        </section>

        <div class="mcc-admission-actions">
            <button class="mcc-button mcc-button-primary" type="submit"<?= ! empty($renewal_error) ? ' disabled' : '' ?>>Renew Admission</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/' . (int) ($admission['id'] ?? 0) . '/page') ?>">Cancel</a>
        </div>
    </form>

    <section class="mcc-card mcc-detail-card" aria-labelledby="admission-history-heading">
        <div class="mcc-card-heading"><span class="mcc-eyebrow">Read-only history</span><h3 id="admission-history-heading">Admission History</h3></div>
        <?php if ($periods === []): ?><p class="mcc-empty-state">No Admission periods have been recorded.</p><?php else: ?>
            <div class="table-responsive"><table class="table mcc-table"><thead><tr><th>Period</th><th>Start date</th><th>End date</th><th>Age Group</th><th>Fee Plan</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($periods as $period): ?><tr><td><?= (int) ($period['period_number'] ?? 0) ?></td><td><?= esc(mcc_user_date($period['start_date'] ?? null, 'Not provided')) ?></td><td><?= esc(mcc_user_date($period['end_date'] ?? null, 'Not provided')) ?></td><td><?= esc((string) ($period['age_group_label'] ?? 'Not provided')) ?></td><td><?= esc((string) ($period['fee_plan_label'] ?? 'Not provided')) ?></td><td><?= esc((string) ($period['status'] ?? 'Not provided')) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</main>

<?= view('staff_footer') ?>
