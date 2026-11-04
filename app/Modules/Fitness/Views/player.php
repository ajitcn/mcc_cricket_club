<?php

$player = is_array($result['player'] ?? null) ? $result['player'] : [];
$assessments = is_array($result['assessments'] ?? null) ? $result['assessments'] : [];
$trend = is_array($result['monthly_trend'] ?? null) ? $result['monthly_trend'] : [];
$kpis = is_array($result['kpis'] ?? null) ? $result['kpis'] : [];
$canEdit = (bool) ($result['can_edit'] ?? false);
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

?>
<?= view('staff_header', [
    'title' => 'Player Fitness',
    'pageActions' => '<div class="mcc-page-actions">
        <a class="mcc-button mcc-button-secondary" href="' . site_url('fitness') . '">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back to Fitness</span>
        </a>
        ' . ($canEdit ? '<a class="mcc-button mcc-button-primary" href="' . site_url('fitness/new?player_id=' . (int) ($player['id'] ?? 0)) . '">
            <i class="fa-solid fa-plus" aria-hidden="true"></i><span>New Assessment</span>
        </a>' : '') . '
    </div>',
]) ?>

<div class="mcc-fitness-player-page">
    <section class="mcc-card mcc-detail-hero" aria-labelledby="fitness-player-heading">
        <div>
            <span class="mcc-eyebrow">Fitness profile</span>
            <h1 id="fitness-player-heading"><?= esc($player['full_name'] ?? 'Player') ?></h1>
            <p class="mcc-muted"><?= esc($player['branch_name'] ?? 'Branch not available') ?> <span aria-hidden="true">·</span> Player #<?= (int) ($player['id'] ?? 0) ?></p>
        </div>
        <span class="mcc-badge mcc-badge-info">Latest BMI: <?= esc($kpis['latest_bmi'] ?? 'Not available') ?></span>
    </section>

    <section class="mcc-card mcc-fitness-summary" aria-label="Fitness summary">
        <div class="mcc-stat-card"><span class="mcc-eyebrow">Assessments</span><strong><?= (int) ($kpis['assessment_count'] ?? 0) ?></strong><span class="mcc-muted">Recorded results</span></div>
        <div class="mcc-stat-card"><span class="mcc-eyebrow">Latest assessment</span><strong><?= esc($assessments[0]['assessment_date'] ?? '—') ?></strong><span class="mcc-muted">Most recent entry</span></div>
    </section>

    <?php if ($canEdit): ?>
        <section class="mcc-card mcc-detail-card" aria-labelledby="fitness-assessment-form-heading">
            <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Record results</span><h2 id="fitness-assessment-form-heading">New fitness assessment</h2></div></div>
            <form method="post" action="<?= site_url('fitness/players/' . (int) ($player['id'] ?? 0) . '/assessments') ?>" class="row">
                <?= csrf_field() ?>
                <div class="col-12 col-md-4 mb-3"><label for="assessment-date">Month</label><input class="form-control" id="assessment-date" name="assessment_date" type="text" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" placeholder="DD-MM-YYYY" required></div>
                <div class="col-12 col-md-4 mb-3"><label for="age-group">Age Group</label><select class="form-control" id="age-group" name="age_group"><option value="">Choose age group</option><?php foreach (($age_groups ?? []) as $ageGroup): ?><option value="<?= esc($ageGroup['name']) ?>"><?= esc($ageGroup['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12 col-md-4 mb-3"><label for="bmi">BMI</label><input class="form-control" id="bmi" name="bmi" type="number" min="0" max="99.99" step="0.01"></div>
                <?php foreach ($metricFields as $field => $label): ?>
                    <div class="col-12 col-md-4 mb-3"><label for="<?= esc($field) ?>"><?= esc($label) ?></label><input class="form-control" id="<?= esc($field) ?>" name="<?= esc($field) ?>" type="number" min="0" step="0.01"></div>
                <?php endforeach; ?>
                <div class="col-12 mb-3"><label for="coach-notes">Notes</label><textarea class="form-control" id="coach-notes" name="coach_notes" rows="3"></textarea></div>
                <div class="col-12 mcc-form-actions"><button class="mcc-button mcc-button-primary" type="submit">Save assessment</button></div>
            </form>
        </section>
    <?php endif; ?>

    <section class="mcc-card mcc-detail-card" aria-labelledby="fitness-trend-heading">
        <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Progress</span><h2 id="fitness-trend-heading">Monthly trend</h2></div></div>
        <?php if ($trend === []): ?>
            <div class="mcc-empty-state" role="status">No monthly fitness trend is available yet.</div>
        <?php else: ?>
            <div class="mcc-table-wrap"><table class="mcc-table"><thead><tr><th>Month</th><th>Assessments</th><th>Average BMI</th></tr></thead><tbody>
                <?php foreach ($trend as $month): ?><tr><td><?= esc($month['month'] ?? '—') ?></td><td><?= (int) ($month['assessment_count'] ?? 0) ?></td><td><?= esc($month['average_bmi'] ?? 'Not available') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

    <section class="mcc-card mcc-detail-card" aria-labelledby="fitness-history-heading">
        <div class="mcc-card-heading"><div><span class="mcc-eyebrow">Recorded results</span><h2 id="fitness-history-heading">Assessment history</h2></div></div>
        <?php if ($assessments === []): ?>
            <div class="mcc-empty-state" role="status">No fitness assessments have been recorded.</div>
        <?php else: ?>
            <div class="mcc-table-wrap"><table class="mcc-table mcc-fitness-assessment-table"><thead><tr><th>Date</th><th>Age Group</th><?php foreach ($metricFields as $label): ?><th><?= esc($label) ?></th><?php endforeach; ?><th>BMI</th><th>Notes</th></tr></thead><tbody>
                <?php foreach ($assessments as $assessment): ?><tr><td><span class="mcc-muted mcc-meta-text"><?= esc(mcc_user_date($assessment['assessment_date'] ?? null)) ?></span></td><td><?= esc($assessment['age_group'] ?? '—') ?></td><?php foreach (array_keys($metricFields) as $metric): ?><td><?= esc($assessment[$metric] ?? '—') ?></td><?php endforeach; ?><td><?= esc($assessment['bmi'] ?? 'Not available') ?></td><td><?= esc(trim((string) ($assessment['coach_notes'] ?? '')) !== '' ? $assessment['coach_notes'] : 'Not provided') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

</div>

<?= view('staff_footer') ?>
