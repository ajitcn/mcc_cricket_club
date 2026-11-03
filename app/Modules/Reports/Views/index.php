<?= view('staff_header', ['title' => $title]) ?>

<main class="mcc-page-container mcc-reports-page" id="main-content">
    <div class="mcc-breadcrumbs">
        <a href="<?= site_url('/') ?>">Dashboard</a>
        <span aria-hidden="true">/</span>
        <span>Reports</span>
    </div>

    <div class="mcc-page-heading">
        <div>
            <span class="mcc-eyebrow">Report center</span>
            <h1>Reports</h1>
            <p class="mcc-muted">Review authorized operational data by date, branch, and status.</p>
        </div>
    </div>

    <?php if ($catalog === []): ?>
        <section class="mcc-card mcc-empty-state" role="status">
            <strong>No reports are available.</strong>
            <span>Ask an administrator to grant the report.view permission.</span>
        </section>
    <?php else: ?>
        <section class="mcc-report-catalog" aria-label="Available reports">
            <?php
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
            <?php foreach ($catalog as $type => $label): ?>
                <article class="mcc-card mcc-report-card">
                    <span class="mcc-report-card-icon"><i class="fa-solid <?= esc($icons[$type] ?? 'fa-chart-column') ?>" aria-hidden="true"></i></span>
                    <div>
                        <span class="mcc-eyebrow">Operational report</span>
                        <h2><?= esc($label) ?></h2>
                        <p><?= esc($descriptions[$type] ?? 'Authorized report data.') ?></p>
                    </div>
                    <a class="mcc-button mcc-button-primary mcc-button-small" href="<?= site_url('reports/' . rawurlencode($type)) ?>">
                        Open report <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<?= view('staff_footer') ?>
