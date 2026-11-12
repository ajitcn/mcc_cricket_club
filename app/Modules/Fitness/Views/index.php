<?php
$fitnessFilters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$hasFitnessFilters = trim((string) ($fitnessFilters['q'] ?? '')) !== ''
    || trim((string) ($fitnessFilters['from'] ?? '')) !== ''
    || trim((string) ($fitnessFilters['to'] ?? '')) !== '';
$currentPage = max(1, (int) ($result['page'] ?? 1));
$pageCount = max(1, (int) ($result['pages'] ?? 1));
$pageUrl = static function (int $page) use ($fitnessFilters): string {
    $query = http_build_query(array_merge(array_filter($fitnessFilters, static fn ($value): bool => $value !== null && $value !== ''), ['page' => $page]));
    return site_url('fitness') . ($query === '' ? '' : '?' . $query);
};
$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button"
        data-filter-toggle data-show-label="Show filters" data-hide-label="Hide filters"
        aria-controls="fitness-filters" aria-expanded="' . ($hasFitnessFilters ? 'true' : 'false') . '">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">' . ($hasFitnessFilters ? 'Hide filters' : 'Show filters') . '</span>
    </button>
    <a class="mcc-button mcc-button-primary" href="' . site_url('fitness/new') . '">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <span class="mcc-button-label">New Fitness</span>
    </a>
</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<div class="mcc-fitness-page">
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
            <span class="mcc-muted"><?= (int) ($result['total'] ?? 0) ?> total assessments</span>
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
                        <tr><th scope="col">Player information</th><th scope="col">Created information</th><th scope="col">Created by</th><th scope="col">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['assessments'] as $assessment): ?>
                            <tr>
                                <th scope="row" data-label="Player">
                                    <span class="mcc-table-primary-text"><?= esc($assessment['full_name']) ?></span>
                                    <span class="mcc-table-secondary-text">Branch: <?= esc($assessment['branch_name']) ?></span>
                                    <span class="mcc-table-secondary-text">Player #<?= (int) $assessment['player_id'] ?></span>
                                    <span class="mcc-table-secondary-text">Age group: <?= esc($assessment['age_group'] ?? 'Not captured') ?></span>
                                </th>
                                <td data-label="Created information">
                                    <span class="mcc-table-primary-text"><?= esc(mcc_user_date($assessment['created_at'] ?? $assessment['assessment_date'] ?? null)) ?></span>
                                    <span class="mcc-table-secondary-text">Updated: <?= esc(mcc_user_date($assessment['updated_at'] ?? null)) ?></span>
                                    <span class="mcc-table-secondary-text">Assessment: <?= esc(mcc_user_date($assessment['assessment_date'] ?? null)) ?></span>
                                </td>
                                <td data-label="Created by">
                                    <span class="mcc-table-primary-text"><?= esc($assessment['created_by_name'] ?? 'System') ?></span>
                                </td>
                                <td data-label="Actions">
                                    <details class="mcc-detail-action-menu mcc-fitness-action-menu">
                                        <summary class="mcc-button mcc-button-secondary">
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            Actions
                                        </summary>
                                        <div class="mcc-detail-action-dropdown" role="menu" aria-label="Fitness assessment actions">
                                            <a href="<?= site_url('fitness/players/' . (int) $assessment['player_id']) ?>" role="menuitem">
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                View Player Fitness
                                            </a>
                                            <a href="<?= site_url('fitness/assessments/' . (int) $assessment['id'] . '/edit') ?>" role="menuitem">
                                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                Edit Assessment
                                            </a>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($pageCount > 1): ?>
            <nav class="mcc-pagination" aria-label="Fitness assessment pages">
                <?php for ($page = 1; $page <= $pageCount; $page++): ?>
                    <a class="<?= $page === $currentPage ? 'is-active' : '' ?>" href="<?= esc($pageUrl($page)) ?>" aria-current="<?= $page === $currentPage ? 'page' : 'false' ?>"><?= $page ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<?= view('staff_footer') ?>
