<?= view('staff_header', [
    'title' => $title,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . esc(site_url('players')) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to Players</span></a>',
]) ?>
<main class="mcc-player-360-detail" id="main-content">
    <section class="mcc-card mcc-player-360-hero" aria-labelledby="player-360-title">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Player overview</span>
                <h2 id="player-360-title"><?= esc($result['player']['profile']['full_name']) ?></h2>
                <p class="mcc-muted">
                    #<?= esc((string) ($result['player']['admission_number'] ?? $result['player']['id'])) ?>
                    · <?= esc($result['player']['branch_name']) ?>
                </p>
            </div>
            <span class="mcc-badge mcc-badge-<?= esc((string) $result['player']['status'] === 'active' ? 'success' : 'neutral') ?>">
                <?= esc(ucwords(str_replace('_', ' ', (string) $result['player']['status']))) ?>
            </span>
        </div>
    </section>

    <?php
    $attendanceSection = is_array($result['sections']['attendance'] ?? null) ? $result['sections']['attendance'] : [];
    $sessionSection = is_array($result['sections']['sessions'] ?? null) ? $result['sections']['sessions'] : [];
    $matchSection = is_array($result['sections']['matches'] ?? null) ? $result['sections']['matches'] : [];
    $tournamentSection = is_array($result['sections']['tournaments'] ?? null) ? $result['sections']['tournaments'] : [];
    $developmentSection = is_array($result['sections']['development'] ?? null) ? $result['sections']['development'] : [];
    $fitnessSection = is_array($result['sections']['fitness'] ?? null) ? $result['sections']['fitness'] : [];
    $progressSummary = is_array($result['admission']['session_progress'] ?? null) ? $result['admission']['session_progress'] : [];
    $statusBadgeClass = static function (string $status): string {
        $normalized = strtolower(trim($status));

        return match (true) {
            in_array($normalized, ['active', 'approved', 'completed', 'present', 'won'], true) => 'success',
            in_array($normalized, ['scheduled', 'pending', 'late', 'upcoming'], true) => 'warning',
            in_array($normalized, ['absent', 'cancelled', 'rejected', 'lost'], true) => 'danger',
            default => 'neutral',
        };
    };
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
            <div><dt>Admission number</dt><dd><?= esc($result['player']['admission_number'] ?? 'Not available') ?></dd></div>
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
            <div><dt>Age group</dt><dd><?= esc($result['player']['admission_age_group'] ?? 'Not available') ?></dd></div>
            <div><dt>Current batch</dt><dd><?= esc($result['assignments']['current_batch']['batch_name'] ?? 'Not available') ?></dd></div>
            <div><dt>Group coach</dt><dd><?= esc($result['player']['group_coach_name'] ?? 'Not assigned') ?></dd></div>
            <div><dt>Personal coach</dt><dd><?= esc($result['player']['personal_coach_name'] ?? 'Not assigned') ?></dd></div>
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
            <section class="mcc-card mcc-player-360-section<?= $name === 'performance' ? ' mcc-player-360-performance-section' : '' ?><?= (! is_array($section) || ! ($section['available'] ?? false)) ? ' mcc-player-360-unavailable' : '' ?>" aria-labelledby="player-360-section-<?= esc($name) ?>">
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
                    <?php if ($name === 'attendance' && ($section['rows'] ?? []) !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead><tr><th>Date</th><th>Session</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice($section['rows'], 0, 8) as $row): ?>
                                        <tr>
                                            <td><?= esc(mcc_user_datetime($row['recorded_at'] ?? $row['starts_at'] ?? null)) ?></td>
                                            <td>#<?= (int) ($row['session_id'] ?? 0) ?></td>
                                            <td>
                                                <?php $status = (string) ($row['status'] ?? 'Unknown'); ?>
                                                <span class="mcc-badge mcc-badge-<?= esc($statusBadgeClass($status)) ?>">
                                                    <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if ($name === 'sessions' && ($section['rows'] ?? []) !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead>
                                    <tr>
                                        <th>Start</th>
                                        <th>Status</th>
                                        <th>Coach</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($section['rows'], 0, 8) as $row): ?>
                                        <tr>
                                            <td><?= esc(mcc_user_datetime($row['starts_at'] ?? null)) ?></td>
                                            <?php $status = (string) ($row['status'] ?? 'Unknown'); ?>
                                            <td>
                                                <span class="mcc-badge mcc-badge-<?= esc($statusBadgeClass($status)) ?>">
                                                    <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                                                </span>
                                            </td>
                                            <td><?= esc($row['coach_name'] ?? 'Not assigned') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if ($name === 'matches' && ($section['rows'] ?? []) !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead>
                                    <tr>
                                        <th>Match</th>
                                        <th>Status</th>
                                        <th>Result / score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($section['rows'], 0, 8) as $row): ?>
                                        <tr>
                                            <td>
                                                #<?= (int) ($row['id'] ?? 0) ?><br>
                                                <span class="mcc-table-secondary-text">
                                                    <?= esc($row['opponent'] ?? 'Opponent not recorded') ?>
                                                </span>
                                            </td>
                                            <?php $status = (string) ($row['status'] ?? 'Unknown'); ?>
                                            <td>
                                                <span class="mcc-badge mcc-badge-<?= esc($statusBadgeClass($status)) ?>">
                                                    <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= esc($row['result'] ?? 'Not recorded') ?>
                                                <?php if (trim((string) ($row['score_summary'] ?? '')) !== ''): ?>
                                                    <br>
                                                    <span class="mcc-table-secondary-text">
                                                        <?= esc((string) $row['score_summary']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php elseif ($name === 'sessions' && isset($section['rows'])): ?>
                    <?php if ($section['rows'] !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead>
                                    <tr>
                                        <th>Start</th>
                                        <th>Status</th>
                                        <th>Coach</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($section['rows'], 0, 8) as $row): ?>
                                        <tr>
                                            <td><?= esc(mcc_user_datetime($row['starts_at'] ?? null)) ?></td>
                                            <?php $status = (string) ($row['status'] ?? 'Unknown'); ?>
                                            <td>
                                                <span class="mcc-badge mcc-badge-<?= esc($statusBadgeClass($status)) ?>">
                                                    <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                                                </span>
                                            </td>
                                            <td><?= esc($row['coach_name'] ?? 'Not assigned') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?><p class="mcc-empty-state">No linked sessions.</p><?php endif; ?>
                <?php elseif ($name === 'matches' && isset($section['rows'])): ?>
                    <?php if ($section['rows'] !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead>
                                    <tr>
                                        <th>Match</th>
                                        <th>Status</th>
                                        <th>Result / score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($section['rows'], 0, 8) as $row): ?>
                                        <tr>
                                            <td>
                                                #<?= (int) ($row['id'] ?? 0) ?><br>
                                                <span class="mcc-table-secondary-text">
                                                    <?= esc($row['opponent'] ?? 'Opponent not recorded') ?>
                                                </span>
                                            </td>
                                            <?php $status = (string) ($row['status'] ?? 'Unknown'); ?>
                                            <td>
                                                <span class="mcc-badge mcc-badge-<?= esc($statusBadgeClass($status)) ?>">
                                                    <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= esc($row['result'] ?? 'Not recorded') ?>
                                                <?php if (trim((string) ($row['score_summary'] ?? '')) !== ''): ?>
                                                    <br>
                                                    <span class="mcc-table-secondary-text">
                                                        <?= esc((string) $row['score_summary']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?><p class="mcc-empty-state">No linked matches.</p><?php endif; ?>
                <?php elseif ($name === 'tournaments'): ?>
                    <?php if (($section['tournaments'] ?? []) !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead>
                                    <tr>
                                        <th>Tournament</th>
                                        <th>Status</th>
                                        <th>Dates</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($section['tournaments'], 0, 8) as $row): ?>
                                        <tr>
                                            <td><?= esc($row['name'] ?? 'Unnamed tournament') ?></td>
                                            <?php $status = (string) ($row['status'] ?? 'Unknown'); ?>
                                            <td>
                                                <span class="mcc-badge mcc-badge-<?= esc($statusBadgeClass($status)) ?>">
                                                    <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= esc(mcc_user_date($row['starts_on'] ?? null)) ?>
                                                –
                                                <?= esc(mcc_user_date($row['ends_on'] ?? null)) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?><p class="mcc-empty-state">No tournament records.</p><?php endif; ?>
                <?php elseif ($name === 'development' && isset($section['plans'])): ?>
                    <?php if ($section['plans'] !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th>Status</th>
                                        <th>Coach / target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($section['plans'], 0, 8) as $row): ?>
                                        <tr>
                                            <td><?= esc($row['title'] ?? 'Untitled plan') ?></td>
                                            <?php $status = (string) ($row['status'] ?? 'Unknown'); ?>
                                            <td>
                                                <span class="mcc-badge mcc-badge-<?= esc($statusBadgeClass($status)) ?>">
                                                    <?= esc(ucwords(str_replace('_', ' ', $status))) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= esc($row['coach_name'] ?? 'Not assigned') ?><br>
                                                <span class="mcc-table-secondary-text">
                                                    Target: <?= esc(mcc_user_date($row['target_date'] ?? null)) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?><p class="mcc-empty-state">No development plans.</p><?php endif; ?>
                <?php elseif ($name === 'fitness' && (isset($section['assessments']) || isset($section['plans']))): ?>
                    <div class="mcc-player-360-fitness-summary">
                        <span>Assessments <strong><?= (int) count($section['assessments'] ?? []) ?></strong></span>
                        <span>Plans <strong><?= (int) count($section['plans'] ?? []) ?></strong></span>
                    </div>
                    <?php if (($section['assessments'] ?? []) !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead>
                                    <tr>
                                        <th>Assessment date</th>
                                        <th>Height</th>
                                        <th>Weight</th>
                                        <th>Body fat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($section['assessments'], 0, 8) as $row): ?>
                                        <tr>
                                            <td><?= esc(mcc_user_date($row['assessment_date'] ?? null)) ?></td>
                                            <td><?= esc($row['height_cm'] ?? '—') ?> cm</td>
                                            <td><?= esc($row['weight_kg'] ?? '—') ?> kg</td>
                                            <td><?= esc($row['body_fat_percent'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php elseif ($name === 'performance' && isset($section['rows'])): ?>
                    <?php if (($section['kpis'] ?? []) !== []): ?>
                        <div class="mcc-player-360-summary">
                            <?php foreach (['runs' => 'Runs', 'wickets' => 'Wickets', 'catches' => 'Catches', 'highest_score' => 'Highest score'] as $key => $label): ?>
                                <?php if (array_key_exists($key, $section['kpis'])): ?>
                                    <div>
                                        <span><?= esc($label) ?></span>
                                        <strong><?= esc((string) ($section['kpis'][$key] ?? 0)) ?></strong>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($section['rows'] !== []): ?>
                        <div class="mcc-table-wrap mcc-player-360-data-table-wrap">
                            <table class="mcc-table mcc-player-360-data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Runs / wickets</th>
                                        <th>Recorded by</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($section['rows'], 0, 8) as $row): ?>
                                        <tr>
                                            <td><?= esc(mcc_user_date($row['recorded_on'] ?? null)) ?></td>
                                            <td><?= esc(ucwords(str_replace('_', ' ', (string) ($row['record_type'] ?? 'Performance')))) ?></td>
                                            <td>
                                                <?= esc((string) ($row['runs'] ?? '—')) ?> /
                                                <?= esc((string) ($row['wickets'] ?? '—')) ?>
                                            </td>
                                            <td><?= esc($row['recorded_by_name'] ?? 'Not available') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="mcc-player-360-performance-empty">
                            <strong>No performance records yet</strong>
                            <span>Batting, bowling, and fielding entries will appear here after they are recorded.</span>
                        </div>
                    <?php endif; ?>
                <?php elseif (isset($section['rows'])): ?>
                    <p class="mcc-muted">Records: <?= esc((string) count($section['rows'])) ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</main>
<?= view('staff_footer') ?>
