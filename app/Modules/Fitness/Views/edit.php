<?php

$assessment = is_array($assessment ?? null) ? $assessment : [];
$metricFields = [
    'squats' => 'Squats',
    'broad_jump' => 'Broad Jump',
    'shoulder_tap' => 'Shoulder Tap',
    'bronco_test' => 'Bronco Test',
    'running_between_wickets' => 'Running Between Wickets',
    'plank' => 'Plank',
    'crunches' => 'Crunches',
    'thirty_meter_timing' => '30 Meter Timing',
];
$playerId = (int) ($assessment['player_id'] ?? 0);
$assessmentId = (int) ($assessment['id'] ?? 0);
?>
<?= view('staff_header', [
    'title' => 'Edit Fitness Assessment',
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . site_url('fitness/players/' . $playerId) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to Player Fitness</span></a>',
]) ?>

<main class="mcc-page-container mcc-fitness-edit-page" id="main-content">
    <form method="post" action="<?= site_url('fitness/assessments/' . $assessmentId) ?>" class="mcc-form-stack">
        <?= csrf_field() ?>

        <section class="mcc-card mcc-form-section" aria-labelledby="fitness-assessment-details-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Assessment details</span>
                    <h2 id="fitness-assessment-details-heading">Measurement period</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-4 mb-3">
                    <label for="assessment-date">Month <span aria-hidden="true">*</span></label>
                    <input class="form-control" id="assessment-date" name="assessment_date" type="text" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc($assessment['assessment_date'] ?? '') ?>" required>
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label for="age-group">Age Group</label>
                    <select class="form-control" id="age-group" name="age_group">
                        <option value="">Choose age group</option>
                        <?php foreach (($age_groups ?? []) as $ageGroup): ?>
                            <option value="<?= esc($ageGroup['name']) ?>" <?= (string) ($assessment['age_group'] ?? '') === (string) $ageGroup['name'] ? 'selected' : '' ?>><?= esc($ageGroup['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label for="bmi">BMI</label>
                    <input class="form-control" id="bmi" name="bmi" type="number" min="0" max="99.99" step="0.01" value="<?= esc($assessment['bmi'] ?? '') ?>">
                </div>
            </div>
        </section>

        <section class="mcc-card mcc-form-section" aria-labelledby="fitness-metrics-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Fitness metrics</span>
                    <h2 id="fitness-metrics-heading">Performance measurements</h2>
                </div>
            </div>
            <div class="row">
                <?php foreach ($metricFields as $field => $label): ?>
                    <div class="col-12 col-md-4 mb-3">
                        <label for="<?= esc($field) ?>"><?= esc($label) ?></label>
                        <input class="form-control" id="<?= esc($field) ?>" name="<?= esc($field) ?>" type="number" min="0" step="0.01" value="<?= esc($assessment[$field] ?? '') ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mcc-card mcc-form-section" aria-labelledby="fitness-notes-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Coach notes</span>
                    <h2 id="fitness-notes-heading">Observations</h2>
                </div>
            </div>
            <label for="coach-notes">Notes</label>
            <textarea class="form-control" id="coach-notes" name="coach_notes" rows="4"><?= esc($assessment['coach_notes'] ?? '') ?></textarea>
        </section>

        <div class="mcc-form-actions">
            <button class="mcc-button mcc-button-primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Save changes</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('fitness/players/' . $playerId) ?>">Cancel</a>
        </div>
    </form>
</main>

<?= view('staff_footer') ?>
