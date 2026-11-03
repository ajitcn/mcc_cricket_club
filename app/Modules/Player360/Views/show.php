<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . esc(site_url('players')) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to Players</span></a>',
]) ?>
<main class="container py-4 mcc-player-360-detail" id="main-content">
    <section class="mcc-card mcc-player-360-hero" aria-labelledby="player-360-title">
        <div>
            <span class="mcc-eyebrow">Player overview</span>
            <h1 id="player-360-title"><?= esc($result['player']['profile']['full_name']) ?> <span>· Player 360°</span></h1>
            <p class="mcc-muted">Player #<?= esc((string) $result['player']['id']) ?> · <?= esc($result['player']['branch_name']) ?></p>
        </div>
        <span class="mcc-badge mcc-badge-<?= esc((string) $result['player']['status'] === 'active' ? 'success' : 'neutral') ?>">
            <?= esc(ucwords(str_replace('_', ' ', (string) $result['player']['status']))) ?>
        </span>
    </section>

    <?php
    $attendanceSection = is_array($result['sections']['attendance'] ?? null) ? $result['sections']['attendance'] : [];
    $sessionSection = is_array($result['sections']['sessions'] ?? null) ? $result['sections']['sessions'] : [];
    $matchSection = is_array($result['sections']['matches'] ?? null) ? $result['sections']['matches'] : [];
    $progressSummary = is_array($result['admission']['session_progress'] ?? null) ? $result['admission']['session_progress'] : [];
    ?>
    <section class="mcc-player-360-kpi-grid" aria-label="Player summary">
        <article class="mcc-card mcc-player-360-kpi-card">
            <span class="mcc-eyebrow">Attendance</span>
            <strong><?= (int) count($attendanceSection['rows'] ?? []) ?></strong>
            <span>recorded sessions</span>
        </article>
        <article class="mcc-card mcc-player-360-kpi-card">
            <span class="mcc-eyebrow">Sessions</span>
            <strong><?= (int) count($sessionSection['rows'] ?? []) ?></strong>
            <span>linked sessions</span>
        </article>
        <article class="mcc-card mcc-player-360-kpi-card">
            <span class="mcc-eyebrow">Matches</span>
            <strong><?= (int) count($matchSection['rows'] ?? []) ?></strong>
            <span>match records</span>
        </article>
        <article class="mcc-card mcc-player-360-kpi-card mcc-player-360-kpi-accent">
            <span class="mcc-eyebrow">Personal / Elite</span>
            <strong><?= (int) ($progressSummary['personal']['completed'] ?? 0) ?> / <?= (int) ($progressSummary['elite']['completed'] ?? 0) ?></strong>
            <span>completed sessions</span>
        </article>
    </section>

    <section class="mcc-card" aria-labelledby="player-profile-heading">
        <div class="mcc-card-heading"><h2 id="player-profile-heading">Profile</h2></div>
        <dl class="mcc-detail-grid">
            <div><dt>Email</dt><dd><?= esc($result['player']['profile']['email'] ?? 'Not available') ?></dd></div>
            <div><dt>Phone</dt><dd><?= esc($result['player']['profile']['phone'] ?? 'Not available') ?></dd></div>
            <div><dt>Date of birth</dt><dd><?= esc(mcc_user_date($result['player']['profile']['date_of_birth'] ?? null)) ?></dd></div>
            <div><dt>Branch</dt><dd><?= esc($result['player']['branch_name'] ?? 'Not available') ?></dd></div>
        </dl>
    </section>

    <section class="mcc-card" aria-labelledby="player-assignment-heading">
        <div class="mcc-card-heading"><h2 id="player-assignment-heading">Admission and assignments</h2></div>
        <dl class="mcc-detail-grid">
            <div><dt>Admission status</dt><dd><?= esc($result['admission']['status'] ?? 'Not available') ?></dd></div>
            <div><dt>Current batch</dt><dd><?= esc($result['assignments']['current_batch']['batch_name'] ?? 'Not available') ?></dd></div>
            <div><dt>Current coach</dt><dd><?= esc($result['assignments']['current_coach']['coach_name'] ?? 'Not available') ?></dd></div>
            <?php foreach (['personal' => 'Personal Session', 'elite' => 'Elite Session'] as $progressKey => $progressLabel): ?>
                <?php $progress = is_array($result['admission']['session_progress'][$progressKey] ?? null) ? $result['admission']['session_progress'][$progressKey] : []; ?>
                <div>
                    <dt><?= esc($progressLabel) ?></dt>
                    <dd>
                        <span class="mcc-session-progress-badge mcc-session-progress-<?= esc($progressKey) ?>">
                            <strong><?= (int) ($progress['completed'] ?? 0) ?>/<?= ($progress['target'] ?? null) === null ? '—' : (int) $progress['target'] ?></strong>
                            <span>completed</span>
                        </span>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

    <div class="mcc-player-360-section-grid">
        <?php foreach ($result['sections'] as $name => $section): ?>
            <section class="mcc-card mcc-player-360-section" aria-labelledby="player-360-section-<?= esc($name) ?>">
                <div class="mcc-card-heading">
                    <h2 id="player-360-section-<?= esc($name) ?>"><?= esc(ucwords(str_replace('_', ' ', $name))) ?></h2>
                </div>
                <?php if (! is_array($section) || ! ($section['available'] ?? false)): ?>
                    <p class="mcc-empty-state">
                        <?= esc((string) ($section['reason'] ?? 'Not available for this Player view.')) ?>
                    </p>
                <?php elseif (isset($section['summary'])): ?>
                    <div class="mcc-player-360-summary">
                        <?php foreach ($section['summary'] as $status => $total): ?>
                            <div><span><?= esc(ucwords(str_replace('_', ' ', $status))) ?></span><strong><?= esc((string) $total) ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                    <p class="mcc-muted">Attendance records: <?= esc((string) count($section['rows'] ?? [])) ?></p>
                <?php elseif (isset($section['rows'])): ?>
                    <p class="mcc-muted">Records: <?= esc((string) count($section['rows'])) ?></p>
                <?php elseif (isset($section['plans'])): ?>
                    <p class="mcc-muted">Plans: <?= esc((string) count($section['plans'])) ?></p>
                <?php elseif (isset($section['assessments'])): ?>
                    <p class="mcc-muted">Assessments: <?= esc((string) count($section['assessments'])) ?></p>
                    <p class="mcc-muted">Fitness KPI formulas: Not available</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</main>
<?= view('staff_footer') ?>
