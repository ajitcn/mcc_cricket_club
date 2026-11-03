<?= view('staff_header', ['title' => $title]) ?>

<main class="container py-4">
    <h1>
        <?= esc($result['player']['full_name']) ?>: fitness
    </h1>
    <p>Assessments: <?= esc($result['kpis']['assessment_count']) ?>. Active plans: <?= esc($result['kpis']['active_plan_count']) ?>.</p>
    <p>
        Latest BMI: <?= esc($result['kpis']['latest_bmi'] ?? 'Not available') ?>.
        BMI change from previous stored assessment:
        <?= esc($result['kpis']['bmi_change'] ?? 'Not available') ?>.
    </p>

    <h2>Monthly trend</h2>
    <?php if (($result['monthly_trend'] ?? []) === []): ?>
        <p>No monthly fitness trend is available yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Assessments</th>
                    <th>Average BMI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['monthly_trend'] as $month): ?>
                    <tr>
                        <td><?= esc($month['month']) ?></td>
                        <td><?= esc($month['assessment_count']) ?></td>
                        <td><?= esc($month['average_bmi'] ?? 'Not available') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Assessment history</h2>
    <?php if (($result['assessments'] ?? []) === []): ?>
        <p>No fitness assessments have been recorded.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Date</th><th>Height (cm)</th><th>Weight (kg)</th><th>BMI</th><th>Body fat (%)</th><th>Notes</th></tr></thead>
                <tbody>
                    <?php foreach ($result['assessments'] as $assessment): ?>
                        <tr>
<td><?= esc(mcc_user_date($assessment['assessment_date'] ?? null)) ?></td>
                            <td><?= esc($assessment['height_cm'] ?? '') ?></td>
                            <td><?= esc($assessment['weight_kg'] ?? '') ?></td>
                            <td><?= esc($assessment['bmi'] ?? 'Not available') ?></td>
                            <td><?= esc($assessment['body_fat_percent'] ?? '') ?></td>
                            <td><?= esc($assessment['coach_notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2>Fitness plans</h2>
    <?php if (($result['plans'] ?? []) === []): ?>
        <p>No fitness plans have been recorded.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($result['plans'] as $plan): ?>
                <li>
                    <?= esc($plan['name']) ?>
                    (<?= esc($plan['status']) ?>),
<?= esc(mcc_user_date($plan['starts_on'] ?? null)) ?>
                    <?= $plan['ends_on'] ? ' to ' . esc(mcc_user_date($plan['ends_on'])) : '' ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>

<?= view('staff_footer') ?>
