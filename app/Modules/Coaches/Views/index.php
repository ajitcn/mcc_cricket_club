<?php

$result = is_array($result ?? null)
    ? $result
    : [
        'coaches' => [],
        'total' => 0,
        'page' => 1,
        'pages' => 1,
        'filters' => [],
        'branches' => [],
    ];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$hasCoachFilters = trim((string) ($filters['q'] ?? '')) !== ''
    || trim((string) ($filters['status'] ?? '')) !== ''
    || trim((string) ($filters['branch_id'] ?? '')) !== '';
$queryFilters = array_filter(
    $filters,
    static fn ($value): bool => $value !== null && $value !== '' && $value !== 1
);
$pageUrl = static function (int $page) use ($queryFilters): string {
    $query = http_build_query(array_merge($queryFilters, ['page' => $page]));

    return site_url('coaches') . ($query === '' ? '' : '?' . $query);
};
?>
<?= view('staff_header', [
    'title' => 'Coaches',
    'nav' => $nav,
    'message' => $message,
    'error' => $error,
    'pageActions' => '<div class="mcc-page-actions">
        <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
            data-show-label="Show filters" data-hide-label="Hide filters"
            aria-controls="coach-filters" aria-expanded="' . ($hasCoachFilters ? 'true' : 'false') . '">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <span class="mcc-button-label">' . ($hasCoachFilters ? 'Hide filters' : 'Show filters') . '</span>
        </button>
        ' . (service('authorization')->can('coach.create')
            ? '<a class="mcc-button mcc-button-primary" href="' . site_url('coaches/create') . '"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add profile</a>'
            : '') . '
    </div>',
]) ?>
<div class="mcc-coach-page">
<section id="coach-filters" class="mcc-card mcc-coach-filter-card" aria-labelledby="coach-filters-heading" <?= $hasCoachFilters ? '' : 'hidden' ?>>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Coaching operations</span>
            <h2 id="coach-filters-heading">Find a coach</h2>
        </div>
        <span class="mcc-muted"><?= (int) $result['total'] ?> result<?= (int) $result['total'] === 1 ? '' : 's' ?></span>
    </div>
<form method="get" action="<?= site_url('coaches') ?>" class="mcc-coach-filter-form">
<div class="mcc-coach-field">
            <label for="coach-query">Name, email or specialization</label>
            <input id="coach-query" name="q" type="search" maxlength="100" value="<?= esc((string) ($filters['q'] ?? '')) ?>">
        </div>
<div class="mcc-coach-field">
            <label for="coach-status">Status</label>
            <select id="coach-status" name="status">
                <option value="">All statuses</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <?php if (count($result['branches']) > 1): ?>
<div class="mcc-coach-field">
                <label for="coach-branch">Authorized branch</label>
                <select id="coach-branch" name="branch_id">
                    <option value="">All authorized branches</option>
                    <?php foreach ($result['branches'] as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>>
                            <?= esc($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
<div class="mcc-coach-filter-actions">
            <button class="mcc-button mcc-button-primary" type="submit">
                <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
            </button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('coaches') ?>">Clear filters</a>
        </div>
    </form>
</section>
<section class="mcc-card mcc-detail-card mcc-coach-directory-card" aria-labelledby="coaches-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Authorized coaching staff</span>
            <h2 id="coaches-heading">Coach directory</h2>
        </div>
        <span class="mcc-muted"><?= (int) $result['total'] ?> result<?= (int) $result['total'] === 1 ? '' : 's' ?></span>
    </div>
    <div class="mcc-table-wrap">
        <table class="mcc-table mcc-coach-table">
            <caption class="visually-hidden">Authorized coach list</caption>
            <thead>
                <tr>
                    <th scope="col">Coach</th>
                    <th scope="col">Specialization</th>
                    <th scope="col">Branch</th>
                    <th scope="col">Status</th>
                    <th scope="col">Players</th>
                    <th scope="col">Sessions</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['coaches'] as $coach): ?>
                    <?php
                    $coachId = (int) $coach['id'];
                    $canUpdateCoach = service('authorization')->can(
                        'coach.update',
                        (string) ($coach['branch_id'] ?? '')
                    );
                    ?>
                    <tr>
                        <th scope="row">
                            <a href="<?= site_url('coaches/' . (int) $coach['id']) ?>"><?= esc($coach['display_name']) ?></a>
                            <span class="mcc-lead-contact"><?= esc($coach['email']) ?></span>
                        </th>
                        <td><?= esc($coach['specialization']) ?></td>
                        <td><?= esc($coach['branch_name']) ?></td>
                        <td>
                            <span class="mcc-badge <?= (int) $coach['is_active'] === 1 && (int) $coach['is_enabled'] === 1 ? 'mcc-badge-success' : 'mcc-badge-neutral' ?>">
                                <?= (int) $coach['is_active'] === 1 && (int) $coach['is_enabled'] === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= (int) $coach['assigned_players'] ?></td>
                        <td><?= (int) $coach['assigned_sessions'] ?></td>
                        <td>
                            <div
                                class="mcc-coach-action-menu"
                                data-coach-actions
                            >
                                <button
                                    class="mcc-button mcc-button-secondary"
                                    type="button"
                                    data-coach-actions-toggle
                                    aria-expanded="false"
                                    aria-haspopup="true"
                                    aria-controls="coach-actions-<?= $coachId ?>"
                                >
                                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                    Actions
                                </button>
                                <div
                                    class="mcc-coach-action-dropdown"
                                    id="coach-actions-<?= $coachId ?>"
                                    data-coach-actions-menu
                                    hidden
                                >
                                    <button
                                        type="button"
                                        data-coach-action="view"
                                        data-coach-url="<?= esc(site_url('coaches/' . $coachId)) ?>"
                                    >
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        View details
                                    </button>
                                    <?php if ($canUpdateCoach): ?>
                                        <a href="<?= esc(site_url('coaches/' . $coachId . '/edit')) ?>">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            Edit coach
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($result['coaches'] === []): ?>
                    <tr>
                        <td colspan="7" class="mcc-empty-state">No coaches match the selected filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int) $result['pages'] > 1): ?>
    <nav class="mcc-pagination mcc-coach-pagination" aria-label="Coach pages">
            <span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
            <div>
                <?php if ((int) $result['page'] > 1): ?>
                    <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] - 1)) ?>">Previous</a>
                <?php endif; ?>
                <?php if ((int) $result['page'] < (int) $result['pages']): ?>
                    <a class="mcc-button mcc-button-secondary" href="<?= esc($pageUrl((int) $result['page'] + 1)) ?>">Next</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>
</div>
<div
    class="mcc-modal mcc-coach-modal"
    data-coach-modal
    hidden
    aria-hidden="true"
>
    <div
        class="mcc-modal-dialog mcc-coach-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="coach-modal-title"
        tabindex="-1"
    >
        <div class="mcc-modal-heading">
            <div>
                <span class="mcc-eyebrow">Coach management</span>
                <h2 id="coach-modal-title" data-coach-modal-title>Edit coach</h2>
            </div>
            <button
                class="mcc-modal-close"
                type="button"
                data-coach-modal-close
                aria-label="Close coach action"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="mcc-modal-body mcc-coach-modal-body" data-coach-modal-body>
            <div class="mcc-coach-modal-loading" data-coach-modal-loading hidden>
                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                Loading...
            </div>
        </div>
    </div>
</div>
<?= view('staff_footer') ?>
