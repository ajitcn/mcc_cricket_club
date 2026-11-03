<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4">
    <h1><?= esc(ucfirst($record['record_type'])) ?> record</h1>
    <p><?= esc($record['player_name']) ?> · <?= esc(mcc_user_date($record['recorded_on'] ?? null)) ?></p>
    <dl class="mcc-definition-list">
        <?php foreach (['runs', 'balls_faced', 'dismissals', 'fours', 'sixes', 'wickets', 'runs_conceded', 'legal_balls', 'catches', 'stumpings', 'run_outs'] as $metric): ?>
            <?php if ($record[$metric] !== null): ?><dt><?= esc(ucwords(str_replace('_', ' ', $metric))) ?></dt><dd><?= (int) $record[$metric] ?></dd><?php endif; ?>
        <?php endforeach; ?>
    </dl>
    <p><?= nl2br(esc($record['notes'] ?? '')) ?></p>
    <p>Status: <?= (int) ($record['approved'] ?? 0) === 1 ? 'Approved' : 'Pending approval' ?></p>
    <p>
        <a class="mcc-button" href="<?= site_url('performance/' . (int) $record['id'] . '/edit') ?>">Edit</a>
        <?php if ((int) ($record['approved'] ?? 0) !== 1): ?>
            <form method="post" action="<?= site_url('performance/' . (int) $record['id'] . '/approve') ?>">
                <?= csrf_field() ?>
                <label for="performance-approval-reason">Approval note (optional)</label>
                <input id="performance-approval-reason" name="reason" maxlength="255">
                <button class="mcc-button mcc-button-primary" type="submit">Approve</button>
            </form>
        <?php endif; ?>
    </p>
</main>
<?= view('staff_footer') ?>
