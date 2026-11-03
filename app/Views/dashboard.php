<?php

$sections = $sections ?? [];
$analytics = $analytics ?? [];
$attention = $sections['attention'] ?? [];
$sessions = $sections['sessions'] ?? [];
$trials = $sections['trials'] ?? [];
$activity = $sections['activity'] ?? [];
$matches = $activity['matches'] ?? [];
$tournaments = $activity['tournaments'] ?? [];
$coachingAnalytics = $analytics['coaching'] ?? [];
$coachDirectory = $analytics['coaches'] ?? [];

$formatDate = static function (
    ?string $value,
    string $format = 'd-m-Y',
    bool $storedUtc = false
): string {
    if ($value === null || $value === '') {
        return 'Not scheduled';
    }

    try {
        $date = new DateTimeImmutable(
            $value,
            new DateTimeZone($storedUtc ? 'UTC' : config('App')->appTimezone)
        );
        if ($storedUtc) {
            $date = $date->setTimezone(new DateTimeZone(config('App')->appTimezone));
        }
    } catch (Throwable) {
        return 'Date unavailable';
    }

    return $date->format($format);
};

$statusLabel = static function (string $value): string {
    return ucwords(str_replace('_', ' ', strtolower($value)));
};

$attentionCount = count($attention['followUps'] ?? [])
    + count($attention['trials'] ?? [])
    + array_sum($attention['admissions'] ?? []);

$upcomingCount = count($sessions)
    + count($trials)
    + count($matches)
    + count($tournaments);

$recentTotal = count($activity['leads'] ?? [])
    + count($activity['admissions'] ?? [])
    + count($activity['followUps'] ?? []);

$directorCardLabels = [
    'Total leads',
    'Open admissions',
    'Active players',
    'Active Coaches',
    'Group coaching sessions',
    'Personal coaching sessions',
    'Elite Coaching',
    'Total Branches',
];
$cards = array_values(array_filter(
    $cards ?? [],
    static fn (array $card): bool => in_array((string) ($card['label'] ?? ''), $directorCardLabels, true),
));
?>
<?= view('staff_header', [
    'title' => 'Director Dashboard',
    'pageSubtitle' => 'Complete overview of Players, Coaches, and coaching activity across authorized branches.',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>

<div class="mcc-dashboard">
    <header class="dashboard-hero">
        <div>
            <p class="mcc-eyebrow">Director view</p>
            <h1>Director Dashboard</h1>
            <p class="dashboard-hero-copy">
                A source-backed view of Players, Coaches, coaching activity, and upcoming sessions.
            </p>
        </div>
        <form method="get" action="<?= site_url('/') ?>" class="dashboard-hero-filters">
            <label>
                <span>Month</span>
                <input name="month" type="month" value="<?= esc((string) ($selectedMonth ?? '')) ?>">
            </label>
            <label>
                <span>Branch</span>
                <select name="branch_id">
                    <?php if (! empty($canAllBranches)): ?>
                        <option value="0">All Branches</option>
                    <?php endif; ?>
                    <?php foreach (($branchOptions ?? []) as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= (int) ($selectedBranch ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Day</span>
                <input name="date" type="date" value="<?= esc((string) ($selectedDate ?? '')) ?>">
            </label>
            <?php if (! empty($canAllBranches)): ?>
                <input type="hidden" name="scope" value="<?= ! empty($allBranchesSelected) ? 'all' : 'branch' ?>">
            <?php endif; ?>
            <button type="submit">Apply</button>
            <a href="<?= site_url('/') ?>">Clear</a>
        </form>
        <span class="dashboard-hero-date"><?= esc((string) ($contextDate ?? '')) ?></span>
    </header>

    <details class="dashboard-surface dashboard-filters" aria-labelledby="dashboard-filters-heading">
        <summary class="dashboard-section-heading dashboard-filter-summary">
            <span>
                <span class="mcc-eyebrow">Scope</span>
                <strong id="dashboard-filters-heading">Dashboard filters</strong>
            </span>
            <span class="dashboard-section-note">MCC local time · Select to adjust</span>
        </summary>
        <p class="dashboard-filter-helper">Month, branch, and selected day controls are available in the header above. All date boundaries use MCC local time.</p>
    </details>
    <?php if (($unavailableMetrics ?? []) !== []): ?>
        <section class="dashboard-surface dashboard-unavailable" aria-labelledby="dashboard-unavailable-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="mcc-eyebrow">Formula status</p>
                    <h2 id="dashboard-unavailable-heading">Metrics not yet available</h2>
                </div>
            </div>
            <div class="dashboard-status-list">
                <?php foreach ($unavailableMetrics as $metric): ?>
                    <div class="dashboard-status-row">
                        <span><?= esc($metric) ?></span>
                        <strong class="mcc-muted">Not available</strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($cards !== []): ?>
        <section class="dashboard-surface dashboard-summary" aria-labelledby="overview-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="mcc-eyebrow">At a glance</p>
                    <h2 id="overview-heading">Operational overview</h2>
                </div>
                <span class="dashboard-section-note">Authorized branch view</span>
            </div>
            <div class="dashboard-kpi-grid">
                <?php foreach ($cards as $card): ?>
                    <?php $cardTag = ($card['url'] ?? null) !== null ? 'a' : 'div'; ?>
                    <<?= $cardTag ?> class="dashboard-kpi<?= ! empty($card['unavailable']) ? ' dashboard-kpi-unavailable' : '' ?>"<?= ($card['url'] ?? null) !== null ? ' href="' . esc(site_url($card['url'])) . '"' : '' ?>>
                        <span class="dashboard-kpi-icon" aria-hidden="true">
                            <i class="fa-solid <?= esc((string) ($card['icon'] ?? 'fa-chart-simple')) ?>"></i>
                        </span>
                        <span class="dashboard-kpi-label"><?= esc($card['label']) ?></span>
                        <strong><?= esc((string) $card['value']) ?></strong>
                        <span class="dashboard-kpi-description"><?= esc($card['description']) ?></span>
                    </<?= $cardTag ?>>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (($analytics['branchesVisible'] ?? false) === true): ?>
        <section class="dashboard-surface dashboard-branch-summary" aria-labelledby="branch-summary-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="mcc-eyebrow">Authorized branch scope</p>
                    <h2 id="branch-summary-heading">Branch summary</h2>
                </div>
                <span class="dashboard-section-note">Players, Coaches, coaching type, and current Batches</span>
            </div>
            <?php if (($analytics['branches'] ?? []) === []): ?>
                <div class="dashboard-empty dashboard-empty-inline">
                    <p>No branch summary data is available for this scope.</p>
                </div>
            <?php else: ?>
                <div class="dashboard-branch-card-grid">
                    <?php foreach ($analytics['branches'] as $branch): ?>
                        <?php
                        $branchCoaching = $analytics['coachingByBranch'][(int) $branch['branch_id']] ?? null;
                        $branchBatchRows = array_values(array_filter(
                            $analytics['batches'] ?? [],
                            static fn (array $batch): bool => (string) ($batch['branch_name'] ?? '') === (string) $branch['branch_name'],
                        ));
                        $branchGroup = (int) ($branchCoaching['group'] ?? 0);
                        $branchPersonal = (int) ($branchCoaching['personal'] ?? 0);
                        $branchCoachingTotal = $branchGroup + $branchPersonal;
                        $groupPercent = $branchCoachingTotal > 0
                            ? (int) round($branchGroup / $branchCoachingTotal * 100)
                            : 0;
                        $personalPercent = $branchCoachingTotal > 0 ? 100 - $groupPercent : 0;
                        $maxBatchTotal = 0;
                        foreach ($branchBatchRows as $batchRow) {
                            $maxBatchTotal = max($maxBatchTotal, (int) ($batchRow['total'] ?? 0));
                        }
                        ?>
                        <article class="dashboard-branch-card">
                            <div class="dashboard-branch-card-heading">
                                <span class="dashboard-branch-avatar" aria-hidden="true">
                                    <i class="fa-solid fa-building"></i>
                                </span>
                                <h3><?= esc((string) $branch['branch_name']) ?></h3>
                            </div>
                            <div class="dashboard-branch-card-metrics">
                                <span><strong><?= esc((string) $branch['players']) ?></strong><small>Players</small></span>
                                <span><strong><?= esc((string) $branch['coaches']) ?></strong><small>Coaches</small></span>
                            </div>
                            <div class="dashboard-branch-card-detail">
                                <strong>Coaching type</strong>
                                <?php if (is_array($branchCoaching)): ?>
                                    <div class="dashboard-branch-coaching-chart" style="--group-percent: <?= $groupPercent ?>%; --personal-percent: <?= $personalPercent ?>%;">
                                        <span class="dashboard-branch-donut" aria-hidden="true"></span>
                                        <span class="dashboard-branch-legend">
                                            <span><i class="dashboard-legend-dot dashboard-legend-group"></i>Group <?= esc((string) $branchGroup) ?></span>
                                            <span><i class="dashboard-legend-dot dashboard-legend-personal"></i>Personal <?= esc((string) $branchPersonal) ?></span>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span>Not available on this dashboard</span>
                                <?php endif; ?>
                            </div>
                            <div class="dashboard-branch-card-detail">
                                <strong>Batch-wise distribution</strong>
                                <?php if ($branchBatchRows !== []): ?>
                                    <?php foreach ($branchBatchRows as $batch): ?>
                                            <span class="dashboard-branch-batch-row">
                                                <em><?= esc((string) $batch['batch_name']) ?></em>
                                                <i><b style="width: <?= $maxBatchTotal > 0 ? (int) round((int) $batch['total'] / $maxBatchTotal * 100) : 0 ?>%;"></b></i>
                                                <strong><?= esc((string) $batch['total']) ?></strong>
                                            </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span>No active batch assignments.</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="dashboard-primary-grid">
        <?php if (($sections['attentionVisible'] ?? false) === true): ?>
            <section class="dashboard-surface dashboard-attention" aria-labelledby="attention-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Staff action</p>
                        <h2 id="attention-heading">Needs attention</h2>
                    </div>
                    <?php if ($attentionCount > 0): ?>
                        <span class="dashboard-count">
                            <?= esc((string) $attentionCount) ?> open item<?= $attentionCount === 1 ? '' : 's' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($attentionCount === 0): ?>
                    <div class="dashboard-empty dashboard-empty-inline">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <p>No follow-ups or workflow items require attention.</p>
                    </div>
                <?php else: ?>
                    <div class="dashboard-attention-list">
                        <?php foreach ($attention['followUps'] ?? [] as $item): ?>
                            <a class="dashboard-action-item" href="<?= site_url('crm/leads/' . (int) $item['lead_id']) ?>">
                                <span>
                                    <strong>Overdue follow-up</strong>
                                    <small>
                                        <?= esc($item['full_name']) ?> · <?= esc($formatDate($item['follow_up_at'])) ?>
                                    </small>
                                </span>
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>

                        <?php foreach ($attention['trials'] ?? [] as $item): ?>
                            <a class="dashboard-action-item" href="<?= site_url('crm/trials/' . (int) $item['id']) ?>">
                                <span>
                                    <strong>Trial awaiting selection</strong>
                                    <small>
                                        <?= esc($item['full_name']) ?> · <?= esc($formatDate($item['trial_date'])) ?>
                                    </small>
                                </span>
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>

                        <?php foreach ($attention['admissions'] ?? [] as $status => $total): ?>
                            <?php if ((int) $total > 0): ?>
                                <a class="dashboard-action-item" href="<?= site_url('admissions') ?>">
                                    <span>
                                        <strong><?= esc($statusLabel((string) $status)) ?> admissions</strong>
                                        <small>
                                            <?= esc((string) $total) ?> workflow item<?= (int) $total === 1 ? '' : 's' ?> to review
                                        </small>
                                    </span>
                                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($quickActions !== []): ?>
            <section class="dashboard-surface dashboard-quick-actions" aria-labelledby="quick-actions-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Shortcuts</p>
                        <h2 id="quick-actions-heading">Quick actions</h2>
                    </div>
                </div>
                <div class="dashboard-quick-action-list">
                    <?php foreach ($quickActions as $action): ?>
                        <a class="mcc-button mcc-button-secondary" href="<?= site_url($action['url']) ?>">
                            <i class="fa-solid <?= esc($action['icon']) ?>" aria-hidden="true"></i>
                            <span><?= esc($action['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="dashboard-analytics-grid">
        <?php if (($analytics['playersVisible'] ?? false) === true): ?>
            <section class="dashboard-surface" aria-labelledby="player-analytics-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Player base</p>
                        <h2 id="player-analytics-heading">Player status</h2>
                    </div>
                    <a href="<?= site_url('players') ?>">View players</a>
                </div>
                <?php if (($analytics['players'] ?? []) !== []): ?>
                    <div class="dashboard-status-list">
                        <?php foreach ($analytics['players'] as $status => $total): ?>
                            <div class="dashboard-status-row">
                                <span><?= esc($statusLabel((string) $status)) ?></span>
                                <strong><?= esc((string) $total) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty">
                        <p>No player status data is available.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (($analytics['batchesVisible'] ?? false) === true): ?>
            <section class="dashboard-surface" aria-labelledby="batch-analytics-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Batch distribution</p>
                        <h2 id="batch-analytics-heading">Active Players by Batch</h2>
                    </div>
                </div>
                <?php if (($analytics['batches'] ?? []) === []): ?>
                    <div class="dashboard-empty dashboard-empty-inline">
                        <p>No active batch assignments are available for this scope.</p>
                    </div>
                <?php else: ?>
                    <div class="dashboard-status-list">
                        <?php foreach ($analytics['batches'] as $batch): ?>
                            <div class="dashboard-status-row">
                                <span><?= esc((string) $batch['batch_name']) ?> · <?= esc((string) $batch['branch_name']) ?></span>
                                <strong><?= (int) $batch['total'] ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (($analytics['branchesVisible'] ?? false) === true): ?>
            <section class="dashboard-surface dashboard-branch-totals" aria-labelledby="branch-analytics-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Branch distribution</p>
                        <h2 id="branch-analytics-heading">Branch totals</h2>
                    </div>
                </div>
                <?php if (($analytics['branches'] ?? []) === []): ?>
                    <div class="dashboard-empty dashboard-empty-inline">
                        <p>No active branch totals are available for this scope.</p>
                    </div>
                <?php else: ?>
                    <div class="dashboard-branch-card-grid">
                        <?php foreach ($analytics['branches'] as $branch): ?>
                            <?php
                            $branchCoaching = $analytics['coachingByBranch'][(int) $branch['branch_id']] ?? null;
                            $branchBatchRows = array_values(array_filter(
                                $analytics['batches'] ?? [],
                                static fn (array $batch): bool => (string) ($batch['branch_name'] ?? '') === (string) $branch['branch_name'],
                            ));
                            ?>
                            <article class="dashboard-branch-card">
                                <div class="dashboard-branch-totals-heading">
                                    <span class="dashboard-branch-totals-marker" aria-hidden="true"></span>
                                    <h3><?= esc((string) $branch['branch_name']) ?></h3>
                                </div>
                                <div class="dashboard-branch-card-metrics">
                                    <span>
                                        <strong><?= esc((string) $branch['players']) ?></strong>
                                        <small>Active Players</small>
                                    </span>
                                    <span>
                                        <strong><?= esc((string) $branch['coaches']) ?></strong>
                                        <small>Active Coaches</small>
                                    </span>
                                </div>
                                <div class="dashboard-branch-card-detail">
                                    <strong>Coaching type</strong>
                                    <?php if (is_array($branchCoaching)): ?>
                                        <span>Group <?= esc((string) $branchCoaching['group']) ?> · Personal <?= esc((string) $branchCoaching['personal']) ?></span>
                                    <?php else: ?>
                                        <span>Not available on this dashboard</span>
                                    <?php endif; ?>
                                </div>
                                <div class="dashboard-branch-card-detail">
                                    <strong>Batch-wise distribution</strong>
                                    <?php if ($branchBatchRows !== []): ?>
                                        <?php foreach ($branchBatchRows as $batch): ?>
                                            <span><?= esc((string) $batch['batch_name']) ?>: <?= esc((string) $batch['total']) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span>No active batch assignments.</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (($analytics['trialsVisible'] ?? false) === true): ?>
            <section class="dashboard-surface" aria-labelledby="trial-analytics-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Pipeline</p>
                        <h2 id="trial-analytics-heading">Trial activity</h2>
                    </div>
                    <a href="<?= site_url('crm/trials') ?>">View trials</a>
                </div>
                <?php $trialAnalytics = $analytics['trials'] ?? []; ?>
                <?php if (array_sum($trialAnalytics) > 0): ?>
                    <div class="dashboard-status-list">
                        <?php foreach ($trialAnalytics as $status => $total): ?>
                            <div class="dashboard-status-row">
                                <span><?= esc($statusLabel((string) $status)) ?></span>
                                <strong><?= esc((string) $total) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty">
                        <p>No trial activity is recorded yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (($analytics['attendanceVisible'] ?? false) === true): ?>
            <section class="dashboard-surface" aria-labelledby="attendance-analytics-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Participation</p>
                        <h2 id="attendance-analytics-heading">Attendance activity</h2>
                    </div>
                    <a href="<?= site_url('attendance') ?>">Open attendance</a>
                </div>
                <?php $attendanceAnalytics = $analytics['attendance'] ?? []; ?>
                <?php if (($attendanceAnalytics['recorded'] ?? 0) > 0): ?>
                    <div class="dashboard-attendance-summary">
                        <strong><?= esc((string) $attendanceAnalytics['recorded']) ?></strong>
                        <span>records captured</span>
                    </div>
                    <div class="dashboard-attendance-breakdown">
                        <?php foreach (['present', 'late', 'absent', 'leave', 'medical', 'rain_cancelled', 'coach_cancelled'] as $status): ?>
                            <span>
                                <b><?= esc((string) ($attendanceAnalytics[$status] ?? 0)) ?></b>
                                <?= esc($statusLabel($status)) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty">
                        <p>No attendance records have been captured.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <div class="dashboard-director-grid">
        <?php if (($analytics['coachingVisible'] ?? false) === true): ?>
            <section class="dashboard-surface" aria-labelledby="coaching-distribution-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Selected period</p>
                        <h2 id="coaching-distribution-heading">Coaching-type distribution</h2>
                    </div>
                    <span class="dashboard-section-note">Session roster rule</span>
                </div>
                <?php if ((int) ($coachingAnalytics['group'] ?? 0) + (int) ($coachingAnalytics['personal'] ?? 0) === 0): ?>
                    <div class="dashboard-empty dashboard-empty-inline">
                        <p>No group or personal coaching sessions are available for this period.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $coachingTotal = (int) ($coachingAnalytics['group'] ?? 0)
                        + (int) ($coachingAnalytics['personal'] ?? 0);
                    ?>
                    <div class="dashboard-distribution-list">
                        <?php foreach ([
                            'group' => ['label' => 'Group coaching', 'tone' => 'info'],
                            'personal' => ['label' => 'Personal coaching', 'tone' => 'success'],
                        ] as $key => $item): ?>
                            <div class="dashboard-distribution-row">
                                <span class="dashboard-distribution-label">
                                    <?= view('components/badge', ['tone' => $item['tone'], 'label' => $item['label']]) ?>
                                </span>
                                <span class="dashboard-distribution-value">
                                    <i aria-hidden="true"><b style="width: <?= $coachingTotal > 0 ? (int) round((int) ($coachingAnalytics[$key] ?? 0) / $coachingTotal * 100) : 0 ?>%;"></b></i>
                                    <strong><?= esc((string) ($coachingAnalytics[$key] ?? 0)) ?></strong>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (($analytics['coachesVisible'] ?? false) === true): ?>
            <section class="dashboard-surface dashboard-coach-directory" aria-labelledby="coach-directory-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Authorized active staff</p>
                        <h2 id="coach-directory-heading">Coach list</h2>
                    </div>
                    <a href="<?= site_url('coaches') ?>">View coaches</a>
                </div>
                <?php if ($coachDirectory === []): ?>
                    <div class="dashboard-empty dashboard-empty-inline">
                        <p>No active Coaches are available in this branch scope.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table dashboard-coach-table">
                            <caption class="mcc-sr-only">Authorized active Coach profiles</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Coach</th>
                                    <th scope="col">Branch</th>
                                    <th scope="col">Profile</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coachDirectory as $coach): ?>
                                    <tr>
                                        <th scope="row"><?= esc((string) ($coach['display_name'] ?? 'Coach')) ?></th>
                                        <td><?= esc((string) ($coach['branch_name'] ?? 'Not available')) ?></td>
                                        <td>
                                            <span class="dashboard-table-stack">
                                                <span><?= esc((string) ($coach['specialization'] ?? 'Profile specialization not available')) ?></span>
                                                <?= view('components/badge', [
                                                    'tone' => (int) ($coach['profile_active'] ?? 0) === 1 ? 'success' : 'neutral',
                                                    'label' => (int) ($coach['profile_active'] ?? 0) === 1 ? 'Active profile' : 'Profile not available',
                                                ]) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <div class="dashboard-reference-grid">
        <section class="dashboard-surface" aria-labelledby="age-distribution-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="mcc-eyebrow">Player base</p>
                    <h2 id="age-distribution-heading">Age-wise Player distribution</h2>
                </div>
            </div>
            <div class="dashboard-unavailable-panel">
                <strong>Not available on this dashboard</strong>
                <p>Age categories and DOB boundary rules are not approved for this view.</p>
            </div>
        </section>

        <section class="dashboard-surface" aria-labelledby="coaching-batch-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="mcc-eyebrow">Session roster projection</p>
                    <h2 id="coaching-batch-heading">Coaching type vs batch</h2>
                </div>
            </div>
            <?php if (($analytics['coachingByBatch'] ?? []) === []): ?>
                <div class="dashboard-empty dashboard-empty-inline">
                    <p>No coaching-type and batch records are available for this period.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table dashboard-coaching-batch-table">
                        <caption class="mcc-sr-only">Coaching type versus batch distribution</caption>
                        <thead>
                            <tr>
                                <th scope="col">Batch</th>
                                <th scope="col">Group</th>
                                <th scope="col">Personal</th>
                                <th scope="col">Elite</th>
                                <th scope="col">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['coachingByBatch'] as $batch): ?>
                                <tr>
                                    <th scope="row"><?= esc((string) $batch['batch_name']) ?></th>
                                    <td><?= esc((string) $batch['group']) ?></td>
                                    <td><?= esc((string) $batch['personal']) ?></td>
                                    <td><span class="mcc-muted">Not available</span></td>
                                    <td><strong><?= esc((string) $batch['total']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if (($analytics['coachingVisible'] ?? false) === true): ?>
            <section class="dashboard-surface" aria-labelledby="coach-schedule-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="mcc-eyebrow">Selected MCC day</p>
                        <h2 id="coach-schedule-heading">Coach schedule</h2>
                    </div>
                    <a href="<?= site_url('sessions') ?>">View full schedule</a>
                </div>
                <?php if ($sessions === []): ?>
                    <div class="dashboard-empty dashboard-empty-inline">
                        <p>No scheduled Coach sessions are available for the selected day or period.</p>
                    </div>
                <?php else: ?>
                    <div class="dashboard-schedule-list">
                        <?php foreach ($sessions as $session): ?>
                            <div class="dashboard-schedule-row">
                                <strong><?= esc($formatDate($session['starts_at'] ?? null, 'h:i A', true)) ?></strong>
                                <span>
                                    <b><?= esc((string) ($session['coach_name'] ?? 'Coach')) ?></b>
                                    <small><?= esc((string) ($session['batch_name'] ?? 'Batch not assigned')) ?> · <?= esc((string) ($session['branch_name'] ?? 'Branch not available')) ?></small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <section class="dashboard-surface dashboard-batch-distribution" aria-labelledby="batch-distribution-heading">
        <div class="dashboard-section-heading">
            <div>
                <p class="mcc-eyebrow">Active assignments</p>
                <h2 id="batch-distribution-heading">Batch-wise distribution</h2>
            </div>
            <a href="<?= site_url('batches') ?>">View batches</a>
        </div>
        <?php if (($analytics['batches'] ?? []) === []): ?>
            <div class="dashboard-empty dashboard-empty-inline">
                <p>No active batch assignments are available for this scope.</p>
            </div>
        <?php else: ?>
            <div class="dashboard-batch-list">
                <?php foreach ($analytics['batches'] as $batch): ?>
                    <div class="dashboard-batch-row">
                        <span>
                            <strong><?= esc((string) $batch['batch_name']) ?></strong>
                            <small><?= esc((string) $batch['branch_name']) ?></small>
                        </span>
                        <b><?= esc((string) $batch['total']) ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-surface dashboard-workload-unavailable" aria-labelledby="workload-heading">
        <div class="dashboard-section-heading">
            <div>
                <p class="mcc-eyebrow">Coach operations</p>
                <h2 id="workload-heading">Coach workload overview</h2>
            </div>
        </div>
        <div class="dashboard-unavailable-panel">
            <strong>Not available on this dashboard</strong>
            <p>No approved workload unit, weighting, or denominator exists for this view.</p>
        </div>
    </section>

    <section class="dashboard-surface dashboard-upcoming" aria-labelledby="upcoming-heading">
        <div class="dashboard-section-heading">
            <div>
                <p class="mcc-eyebrow">Schedule overview</p>
                <h2 id="upcoming-heading">Upcoming activity</h2>
            </div>
            <div class="dashboard-calendar-actions" aria-label="Calendar summary">
                <span class="dashboard-section-note">Calendar</span>
                <?php if ($upcomingCount > 0): ?>
                    <span class="dashboard-section-note">
                        <?= esc((string) $upcomingCount) ?> scheduled items
                    </span>
                <?php else: ?>
                    <span class="dashboard-section-note">No scheduled items</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($upcomingCount === 0): ?>
            <div class="dashboard-empty">
                <p>No scheduled items.</p>
            </div>
        <?php else: ?>
            <div class="dashboard-upcoming-groups">
                <div class="dashboard-upcoming-group">
                    <div class="dashboard-subsection-heading">
                        <h3>Sessions</h3>
                        <?php if ($sessions !== []): ?>
                            <a href="<?= site_url('sessions') ?>">View schedule</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($sessions === []): ?>
                        <p class="dashboard-muted-empty">No upcoming sessions.</p>
                    <?php else: ?>
                        <div class="dashboard-activity-list">
                            <?php foreach ($sessions as $item): ?>
                                <div class="dashboard-activity-row dashboard-activity-card">
                                    <span class="dashboard-activity-date">
<?= esc($formatDate($item['starts_at'], 'd-m-Y', true)) ?>
                                        <b><?= esc($formatDate($item['starts_at'], 'h:i A', true)) ?></b>
                                    </span>
                                    <span class="dashboard-activity-copy">
                                        <strong><?= esc($item['batch_name']) ?></strong>
                                        <small><?= esc($item['branch_name']) ?> · <?= esc($item['coach_name']) ?></small>
                                    </span>
                                    <span class="dashboard-activity-type">Session</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-upcoming-group">
                    <div class="dashboard-subsection-heading">
                        <h3>Trials</h3>
                        <?php if ($trials !== []): ?>
                            <a href="<?= site_url('crm/trials') ?>">View trials</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($trials === []): ?>
                        <p class="dashboard-muted-empty">No upcoming trials.</p>
                    <?php else: ?>
                        <div class="dashboard-activity-list">
                            <?php foreach ($trials as $item): ?>
                                <div class="dashboard-activity-row dashboard-activity-card">
                                    <span class="dashboard-activity-date">
<?= esc($formatDate($item['trial_date'], 'd-m-Y')) ?>
                                        <b><?= esc(substr((string) $item['trial_time'], 0, 5)) ?></b>
                                    </span>
                                    <span class="dashboard-activity-copy">
                                        <strong><?= esc($item['full_name']) ?></strong>
                                        <small><?= esc($item['branch_name']) ?></small>
                                    </span>
                                    <span class="dashboard-activity-type">Trial</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-upcoming-group">
                    <div class="dashboard-subsection-heading">
                        <h3>Matches</h3>
                        <?php if ($matches !== []): ?>
                            <a href="<?= site_url('matches') ?>">View matches</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($matches === []): ?>
                        <p class="dashboard-muted-empty">No upcoming matches.</p>
                    <?php else: ?>
                        <div class="dashboard-activity-list">
                            <?php foreach ($matches as $item): ?>
                                <a class="dashboard-activity-row dashboard-activity-card dashboard-activity-link" href="<?= site_url('matches') ?>">
                                    <span class="dashboard-activity-date">
<?= esc($formatDate($item['starts_at'] ?? null, 'd-m-Y', true)) ?>
                                        <b><?= esc($formatDate($item['starts_at'] ?? null, 'h:i A', true)) ?></b>
                                    </span>
                                    <span class="dashboard-activity-copy">
                                        <strong><?= esc($item['opponent'] ?? 'Scheduled match') ?></strong>
                                        <small><?= esc($item['match_type'] ?? 'Match') ?></small>
                                    </span>
                                    <span class="dashboard-activity-type">Match</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-upcoming-group">
                    <div class="dashboard-subsection-heading">
                        <h3>Tournament activity</h3>
                        <?php if ($tournaments !== []): ?>
                            <a href="<?= site_url('tournaments') ?>">View tournaments</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($tournaments === []): ?>
                        <p class="dashboard-muted-empty">No upcoming tournament activity.</p>
                    <?php else: ?>
                        <div class="dashboard-activity-list">
                            <?php foreach ($tournaments as $item): ?>
                                <a class="dashboard-activity-row dashboard-activity-card dashboard-activity-link" href="<?= site_url('tournaments') ?>">
                                    <span class="dashboard-activity-date">
<?= esc($formatDate($item['starts_on'] ?? null, 'd-m-Y')) ?>
                                        <b><?= esc($statusLabel((string) ($item['status'] ?? 'planned'))) ?></b>
                                    </span>
                                    <span class="dashboard-activity-copy">
                                        <strong><?= esc($item['name'] ?? 'Scheduled tournament') ?></strong>
                                        <small><?= esc($item['tournament_type'] ?? 'Tournament') ?></small>
                                    </span>
                                    <span class="dashboard-activity-type">Tournament</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-surface dashboard-recent" aria-labelledby="recent-heading">
        <div class="dashboard-section-heading">
            <div>
                <p class="mcc-eyebrow">What changed</p>
                <h2 id="recent-heading">Recent activity</h2>
            </div>
        </div>

        <?php if ($recentTotal === 0): ?>
            <div class="dashboard-empty">
                <p>No recent activity is available.</p>
            </div>
        <?php else: ?>
            <div class="dashboard-activity-list dashboard-recent-list">
                <?php foreach ($activity['leads'] ?? [] as $item): ?>
                    <div class="dashboard-activity-row">
                        <span class="dashboard-activity-copy">
                            <strong>New lead · <?= esc($item['full_name']) ?></strong>
                            <small><?= esc($item['branch_name']) ?> · <?= esc($formatDate($item['created_at'])) ?></small>
                        </span>
                        <span class="dashboard-activity-type"><?= esc($statusLabel((string) $item['status'])) ?></span>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($activity['admissions'] ?? [] as $item): ?>
                    <div class="dashboard-activity-row">
                        <span class="dashboard-activity-copy">
                            <strong>Admission · <?= esc($item['full_name'] ?? 'Applicant') ?></strong>
                            <small><?= esc($item['branch_name']) ?> · <?= esc($formatDate($item['updated_at'])) ?></small>
                        </span>
                        <span class="dashboard-activity-type"><?= esc($statusLabel((string) $item['status'])) ?></span>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($activity['followUps'] ?? [] as $item): ?>
                    <div class="dashboard-activity-row">
                        <span class="dashboard-activity-copy">
                            <strong>Follow-up · <?= esc($item['full_name']) ?></strong>
                            <small><?= esc($item['branch_name']) ?> · <?= esc($statusLabel((string) $item['status'])) ?></small>
                        </span>
                        <span class="dashboard-activity-type"><?= esc($formatDate($item['follow_up_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?= view('staff_footer') ?>
