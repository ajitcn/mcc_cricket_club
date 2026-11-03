<?php

$report = is_array($report ?? null) ? $report : [];
$selectedPlayer = is_array($selected_player ?? null) ? $selected_player : [];
$reportPlayer = is_array($report['player'] ?? null) ? $report['player'] : [];
$player = $report !== [] ? $reportPlayer : $selectedPlayer;
$selectedYear = (int) ($report['year'] ?? $selected_year ?? date('Y'));
$playerError = (string) ($player_error ?? '');
$yearError = (string) ($year_error ?? '');
$quarters = is_array($report['quarters'] ?? null) ? $report['quarters'] : [];
$currentYear = (int) date('Y');
$yearOptions = range(2000, $currentYear + 1);
?>
<?= view('staff_header', [
    'title' => 'Annual Feedback Report',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'breadcrumbTrail' => [
        ['label' => 'Players', 'url' => site_url('players')],
        ['label' => 'Coach Feedback', 'url' => site_url('player-feedback')],
        ['label' => 'Annual report'],
    ],
    'pageActions' => '<div class="mcc-page-actions">
        <a class="mcc-button mcc-button-secondary" href="'
            . esc(site_url('player-feedback'))
            . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Feedback</a>
    </div>',
]) ?>

<div class="mcc-player-feedback-annual-page">
    <section id="annual-feedback-filters" class="mcc-card mcc-player-feedback-annual-filter"
        aria-labelledby="annual-feedback-filter-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Report filters</span>
                <h2 id="annual-feedback-filter-heading">Choose report criteria</h2>
            </div>
        </div>
        <form method="get" action="<?= esc(site_url('player-feedback/annual')) ?>"
            class="mcc-player-feedback-report-selector" data-annual-feedback-form novalidate>
            <div class="mcc-form-field mcc-player-feedback-report-player-field">
                <label for="annual-feedback-player-search">Player</label>
                <div class="mcc-player-feedback-search" data-feedback-player-search
                    data-feedback-player-search-mode="annual"
                    data-search-url="<?= esc(site_url('player-feedback/annual-player-search')) ?>">
                    <input class="form-control" id="annual-feedback-player-search" type="search"
                        autocomplete="off" minlength="1" maxlength="100"
                        placeholder="Search by Player name or ID"
                        value="<?= esc((string) ($player['player_name'] ?? '')) ?>"
                        data-feedback-player-search-input>
                    <input type="hidden" name="player_id"
                        value="<?= (int) ($player['id'] ?? 0) ?>"
                        data-feedback-player-search-id required>
                    <div class="mcc-player-feedback-search-results"
                        data-feedback-player-search-results role="listbox"
                        aria-label="Player search results" hidden></div>
                    <div class="mcc-empty-state mcc-player-feedback-search-empty"
                        data-feedback-player-search-empty role="status" hidden>
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        <strong>No authorized active Players found</strong>
                        <span>Try a different Player name or ID.</span>
                    </div>
                    <p class="mcc-muted mcc-player-feedback-search-hint"
                        data-feedback-player-search-hint>
                        Type a Player name or ID to search.
                    </p>
                    <p class="mcc-form-error" data-feedback-player-search-error
                        <?= $playerError === '' ? 'hidden' : '' ?>>
                        <?= esc($playerError) ?>
                    </p>
                    <p class="mcc-player-feedback-search-selected"
                        data-feedback-player-search-selected
                        <?= $player === [] ? 'hidden' : '' ?>>
                        <?php if ($player !== []): ?>
                            Selected: <?= esc((string) ($player['player_name'] ?? 'Player')) ?>
                            · Player ID <?= (int) ($player['id'] ?? 0) ?>
                            · <?= esc((string) ($player['branch_name'] ?? 'Authorized branch')) ?>
                        <?php endif; ?>
                    </p>
                    <button class="mcc-button mcc-button-secondary mcc-player-feedback-search-clear"
                        type="button" data-feedback-player-search-clear
                        <?= $player === [] ? 'hidden' : '' ?>>
                        Clear Player
                    </button>
                </div>
            </div>
            <div class="mcc-form-field">
                <label for="annual-feedback-year">Calendar year</label>
                <select class="form-control" id="annual-feedback-year" name="year" required>
                    <?php foreach ($yearOptions as $year): ?>
                        <option value="<?= $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>>
                            <?= $year ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mcc-form-error" data-annual-feedback-year-error
                    <?= $yearError === '' ? 'hidden' : '' ?>>
                    <?= esc($yearError) ?>
                </p>
            </div>
            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">
                    <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                    Generate Report
                </button>
            </div>
        </form>
    </section>

    <?php if ($report !== []): ?>
        <section class="mcc-card mcc-detail-card" aria-labelledby="annual-feedback-heading">
            <div class="mcc-card-heading">
                <div>
                    <span class="mcc-eyebrow">Annual report</span>
                    <h2 id="annual-feedback-heading">
                        Annual Feedback Report — <?= esc((string) $selectedYear) ?>
                    </h2>
                </div>
                <span class="mcc-badge mcc-feedback-period-badge">
                    <?= esc((string) $selectedYear) ?>
                </span>
            </div>

            <dl class="mcc-player-feedback-report-summary">
                <div>
                    <dt>Player</dt>
                    <dd><?= esc((string) ($player['player_name'] ?? 'Player')) ?></dd>
                </div>
                <div>
                    <dt>Player ID</dt>
                    <dd><?= (int) ($player['id'] ?? 0) ?></dd>
                </div>
                <div>
                    <dt>Branch</dt>
                    <dd><?= esc((string) ($player['branch_name'] ?? 'Authorized branch')) ?></dd>
                </div>
                <div>
                    <dt>Year</dt>
                    <dd><?= esc((string) $selectedYear) ?></dd>
                </div>
            </dl>
        </section>

        <section class="mcc-player-feedback-report-quarters" aria-label="Quarterly feedback">
            <?php foreach ($quarters as $quarter): ?>
                <article class="mcc-card mcc-player-feedback-report-quarter">
                    <div class="mcc-card-heading">
                        <div>
                            <span class="mcc-eyebrow">Quarterly feedback</span>
                            <h2><?= esc((string) ($quarter['period_label'] ?? 'Feedback period')) ?></h2>
                        </div>
                        <?php if ($quarter['missing'] ?? false): ?>
                            <span class="mcc-badge mcc-feedback-missing-badge">Missing</span>
                        <?php else: ?>
                            <span class="mcc-badge mcc-feedback-period-badge">Recorded</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($quarter['missing'] ?? false): ?>
                        <div class="mcc-empty-state mcc-feedback-quarter-empty" role="status">
                            <i class="fa-regular fa-comments" aria-hidden="true"></i>
                            <p>No feedback recorded</p>
                        </div>
                    <?php else: ?>
                        <div class="mcc-player-feedback-quarter-meta">
                            <strong>Coach</strong>
                            <span><?= esc((string) ($quarter['coach_name'] ?? 'Not available')) ?></span>
                        </div>
                        <dl class="mcc-player-feedback-quarter-content">
                            <div>
                                <dt>Student/Parent feedback</dt>
                                <dd><?= nl2br(esc((string) ($quarter['student_parent_feedback'] ?? ''))) ?></dd>
                            </div>
                            <div>
                                <dt>Coach feedback</dt>
                                <dd><?= nl2br(esc((string) ($quarter['coach_feedback'] ?? ''))) ?></dd>
                            </div>
                            <div>
                                <dt>Next focus/action</dt>
                                <dd><?= nl2br(esc((string) ($quarter['next_focus_action'] ?? ''))) ?></dd>
                            </div>
                        </dl>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="mcc-card mcc-player-feedback-year-summary" aria-labelledby="annual-summary-heading">
            <h2 id="annual-summary-heading">Year summary</h2>
            <p>Quarterly feedback is shown above without introducing performance scores or KPI calculations.</p>
        </section>
    <?php endif; ?>
</div>

<?= view('staff_footer') ?>
