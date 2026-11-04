<?php

$assessment = is_array($assessment ?? null) ? $assessment : [];
$metricFields = [
    'squats' => 'Squats', 'broad_jump' => 'Broad Jump', 'shoulder_tap' => 'Shoulder Tap',
    'bronco_test' => 'Bronco Test', 'running_between_wickets' => 'Running Between Wickets',
    'plank' => 'Plank', 'crunches' => 'Crunches', 'thirty_meter_timing' => '30 Meter Timing',
];

?>
<?= view('staff_header', [
    'title' => 'Edit Fitness Assessment',
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . site_url('fitness/players/' . (int) ($assessment['player_id'] ?? 0)) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Player Fitness</a>',
]) ?>

<main class="mcc-page-container">
    <section class="mcc-card" aria-labelledby="fitness-edit-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Fitness assessment</span>
                <h1 id="fitness-edit-heading">Edit assessment</h1>
                <p class="mcc-muted"><?= esc($assessment['full_name'] ?? 'Player') ?> · <?= esc($assessment['branch_name'] ?? 'Branch') ?></p>
            </div>
        </div>
        <form method="post" action="<?= site_url('fitness/assessments/' . (int) ($assessment['id'] ?? 0)) ?>" class="row">
            <?= csrf_field() ?>
            <div class="col-12 col-md-4 mb-3"><label for="assessment-date">Month</label><input class="form-control" id="assessment-date" name="assessment_date" type="text" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" value="<?= esc($assessment['assessment_date'] ?? '') ?>" required></div>
            <div class="col-12 col-md-4 mb-3"><label for="age-group">Age Group</label><select class="form-control" id="age-group" name="age_group"><option value="">Choose age group</option><?php foreach (($age_groups ?? []) as $ageGroup): ?><option value="<?= esc($ageGroup['name']) ?>" <?= (string) ($assessment['age_group'] ?? '') === (string) $ageGroup['name'] ? 'selected' : '' ?>><?= esc($ageGroup['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12 col-md-4 mb-3"><label for="bmi">BMI</label><input class="form-control" id="bmi" name="bmi" type="number" min="0" max="99.99" step="0.01" value="<?= esc($assessment['bmi'] ?? '') ?>"></div>
            <?php foreach ($metricFields as $field => $label): ?>
                <div class="col-12 col-md-4 mb-3"><label for="<?= esc($field) ?>"><?= esc($label) ?></label><input class="form-control" id="<?= esc($field) ?>" name="<?= esc($field) ?>" type="number" min="0" step="0.01" value="<?= esc($assessment[$field] ?? '') ?>"></div>
            <?php endforeach; ?>
            <div class="col-12 mb-3"><label for="coach-notes">Notes</label><textarea class="form-control" id="coach-notes" name="coach_notes" rows="4"><?= esc($assessment['coach_notes'] ?? '') ?></textarea></div>
            <div class="col-12 mcc-form-actions"><button class="mcc-button mcc-button-primary" type="submit">Save changes</button><a class="mcc-button mcc-button-secondary" href="<?= site_url('fitness/players/' . (int) ($assessment['player_id'] ?? 0)) ?>">Cancel</a></div>
        </form>
    </section>
</main>

<?= view('staff_footer') ?>
