<?php

$catalog = is_array($catalog ?? null) ? $catalog : [];
$descriptions = [
    'players' => 'Active and historical Player records by branch and creation date.',
    'admissions' => 'Admission pipeline records with status and branch context.',
    'attendance' => 'Attendance records with Player, Session, and recorded date.',
    'sessions' => 'Scheduled and completed Session activity by branch.',
    'matches' => 'Match schedule, status, opponent, and result information.',
];
$icons = [
    'players' => 'fa-users',
    'admissions' => 'fa-user-plus',
    'attendance' => 'fa-clipboard-check',
    'sessions' => 'fa-calendar-days',
    'matches' => 'fa-baseball-bat-ball',
];
?>
<?= view('staff_header', [
    'title' => $title,
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>
<div class="mcc-reports-page">
    <?php if ($catalog === []): ?>
        <section class="mcc-card mcc-scheduling-card mcc-empty-state" role="status">
            <i class="fa-solid fa-chart-column mcc-empty-icon" aria-hidden="true"></i>
            <strong>No reports are available.</strong>
            <span>Ask an administrator to grant the report.view permission.</span>
        </section>
    <?php else: ?>
        <section class="mcc-card mcc-scheduling-card mcc-session-results-card" aria-labelledby="reports-heading">
            <div class="mcc-session-summary">
                <div>
                    <span class="mcc-eyebrow">Report center</span>
                    <h2 id="reports-heading">Operational reports</h2>
                </div>
                <div class="mcc-session-summary-meta">
                    <span><strong><?= count($catalog) ?></strong> report<?= count($catalog) === 1 ? '' : 's' ?></span>
                    <span class="mcc-muted">Authorized data only</span>
                </div>
            </div>
            <div class="mcc-report-catalog" aria-label="Available reports">
                <?php foreach ($catalog as $type => $label): ?>
                    <article class="mcc-card mcc-report-card">
                        <span class="mcc-report-card-icon">
                            <i class="fa-solid <?= esc($icons[$type] ?? 'fa-chart-column') ?>" aria-hidden="true"></i>
                        </span>
                        <div>
                            <span class="mcc-eyebrow">Operational report</span>
                            <h3><?= esc($label) ?></h3>
                            <p><?= esc($descriptions[$type] ?? 'Authorized report data.') ?></p>
                        </div>
                        <a class="mcc-button mcc-button-primary mcc-button-small" href="<?= esc(site_url('reports/' . rawurlencode((string) $type))) ?>">
                            Open report
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?= view('staff_footer') ?>
