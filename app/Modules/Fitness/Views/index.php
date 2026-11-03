<?php
$fitnessFilters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$hasFitnessFilters = trim((string) ($fitnessFilters['q'] ?? '')) !== ''
    || trim((string) ($fitnessFilters['from'] ?? '')) !== ''
    || trim((string) ($fitnessFilters['to'] ?? '')) !== '';
$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button"
        data-filter-toggle data-show-label="Show filters" data-hide-label="Hide filters"
        aria-controls="fitness-filters" aria-expanded="' . ($hasFitnessFilters ? 'true' : 'false') . '">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">' . ($hasFitnessFilters ? 'Hide filters' : 'Show filters') . '</span>
    </button>
</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<main class="container py-4 mcc-fitness-page">
    <section id="fitness-filters" class="mcc-card mcc-fitness-filter-card" aria-labelledby="fitness-filter-heading" <?= $hasFitnessFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <p class="mcc-eyebrow">Search</p>
                <h2 id="fitness-filter-heading">Find assessments</h2>
            </div>
        </div>
        <form method="get" action="<?= esc(site_url('fitness')) ?>" class="mcc-fitness-filter-form">
            <div class="mcc-form-group">
                <label for="q">Player</label>
                <input class="form-control" id="q" name="q" value="<?= esc($result['filters']['q'] ?? '') ?>" placeholder="Search by Player name">
            </div>
            <div class="mcc-form-group">
                <label for="from">From</label>
                <input class="form-control" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" id="from" name="from" value="<?= esc(mcc_user_date_input($result['filters']['from'] ?? null)) ?>" placeholder="dd-mm-yyyy">
            </div>
            <div class="mcc-form-group">
                <label for="to">To</label>
                <input class="form-control" type="text" inputmode="numeric" autocomplete="off" data-mcc-date-picker data-mcc-date-picker-no-toggle data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" id="to" name="to" value="<?= esc(mcc_user_date_input($result['filters']['to'] ?? null)) ?>" placeholder="dd-mm-yyyy">
            </div>
            <div class="mcc-form-actions">
                <button class="mcc-button mcc-button-primary" type="submit">Search</button>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('fitness') ?>">Clear</a>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-fitness-results-card" aria-labelledby="fitness-results-heading">
        <div class="mcc-card-heading">
            <div>
                <p class="mcc-eyebrow">Assessment history</p>
                <h2 id="fitness-results-heading">Recorded assessments</h2>
            </div>
            <span class="mcc-muted"><?= count($result['assessments'] ?? []) ?> shown</span>
        </div>
        <?php if (($result['assessments'] ?? []) === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-solid fa-heart-pulse mcc-empty-icon" aria-hidden="true"></i>
                <h3>No fitness assessments found</h3>
                <p>Try changing the Player or date filters.</p>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap mcc-fitness-table-wrap">
                <table class="mcc-table mcc-fitness-table">
                    <caption class="visually-hidden">Fitness assessments</caption>
                    <thead>
                        <tr><th scope="col">Player</th><th scope="col">Branch</th><th scope="col">Assessment date</th><th scope="col">BMI</th><th scope="col">Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['assessments'] as $assessment): ?>
                            <tr>
                                <th scope="row" data-label="Player"><?= esc($assessment['full_name']) ?></th>
                                <td data-label="Branch"><?= esc($assessment['branch_name']) ?></td>
                                <td data-label="Assessment date"><span class="mcc-fitness-date"><?= esc(mcc_user_date($assessment['assessment_date'] ?? null)) ?></span></td>
                                <td data-label="BMI"><span class="mcc-badge mcc-badge-info"><?= esc($assessment['bmi'] ?? 'Not available') ?></span></td>
                                <td data-label="Action"><a class="mcc-button mcc-button-secondary mcc-button-sm" href="<?= site_url('fitness/players/' . (int) $assessment['player_id']) ?>">View fitness</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?= view('staff_footer') ?>
