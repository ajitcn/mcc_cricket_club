<?php

$awards = is_array($awards ?? null) ? $awards : [];
$filters = is_array($filters ?? null) ? $filters : [];
$branches = is_array($branches ?? null) ? $branches : [];
$categories = is_array($categories ?? null) ? $categories : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$includeArchived = (bool) ($includeArchived ?? false);
$authorization = service('authorization');
$statusLabels = [
    'draft' => 'Draft',
    'approved' => 'Approved',
    'published' => 'Published',
    'revoked' => 'Revoked',
];
$statusTones = [
    'draft' => 'neutral',
    'approved' => 'info',
    'published' => 'success',
    'revoked' => 'danger',
];
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? 'Unavailable' : date('M j, Y', $timestamp);
};
$hasAwardFilters = trim((string) ($filters['search'] ?? '')) !== ''
    || trim((string) ($filters['category'] ?? '')) !== ''
    || trim((string) ($filters['recipient_type'] ?? '')) !== ''
    || trim((string) ($filters['status'] ?? '')) !== ''
    || trim((string) ($filters['branch_id'] ?? '')) !== ''
    || trim((string) ($filters['date_from'] ?? '')) !== ''
    || trim((string) ($filters['date_to'] ?? '')) !== ''
    || $includeArchived;
$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle
        data-show-label="Show filters" data-hide-label="Hide filters"
        aria-controls="award-filters" aria-expanded="' . ($hasAwardFilters ? 'true' : 'false') . '">
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        <span class="mcc-button-label">' . ($hasAwardFilters ? 'Hide filters' : 'Show filters') . '</span>
    </button>
    ' . ($authorization->can('achievement.create') ? '<a class="mcc-button mcc-button-primary" href="' . site_url('achievements/awards/new') . '">Create Award</a>' : '') . '
    <a class="mcc-button mcc-button-secondary" href="' . site_url('achievements') . '">Achievement Catalog</a>
</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>
<div class="mcc-achievements-page" id="main-content">
    <section id="award-filters" class="mcc-card mcc-achievement-filter-card" aria-labelledby="award-filters-heading" <?= $hasAwardFilters ? '' : 'hidden' ?>>
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Find an award</span>
                <h2 id="award-filters-heading">Filters</h2>
            </div>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements/awards') ?>">Clear filters</a>
        </div>
        <form method="get" action="<?= site_url('achievements/awards') ?>" class="mcc-form-grid mcc-achievement-filter-form mcc-award-filter-form">
            <div class="mcc-form-field">
                <label for="award-search">Search</label>
                <input class="form-control" id="award-search" name="search" type="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Title, recipient, or branch">
            </div>
            <div class="mcc-form-field">
                <label for="award-category">Category</label>
                <select class="form-control" id="award-category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= esc($category) ?>" <?= ($filters['category'] ?? '') === $category ? 'selected' : '' ?>><?= esc($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="award-recipient-type">Recipient type</label>
                <select class="form-control" id="award-recipient-type" name="recipient_type">
                    <option value="">Players and Coaches</option>
                    <option value="player" <?= ($filters['recipient_type'] ?? '') === 'player' ? 'selected' : '' ?>>Player</option>
                    <option value="coach" <?= ($filters['recipient_type'] ?? '') === 'coach' ? 'selected' : '' ?>>Coach</option>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="award-status">Status</label>
                <select class="form-control" id="award-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statusLabels as $status => $label): ?>
                        <option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="award-branch">Branch</label>
                <select class="form-control" id="award-branch" name="branch_id">
                    <option value="">All authorized branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= (string) ($filters['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mcc-form-field">
                <label for="award-date-from">Created from</label>
                <input class="form-control" id="award-date-from" name="date_from" inputmode="numeric"
                    placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-format="d-m-Y"
                    data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['date_from'] ?? null)) ?>">
            </div>
            <div class="mcc-form-field">
                <label for="award-date-to">Created to</label>
                <input class="form-control" id="award-date-to" name="date_to" inputmode="numeric"
                    placeholder="DD-MM-YYYY" data-mcc-date-picker data-mcc-date-format="d-m-Y"
                    data-mcc-date-submit-format="Y-m-d" value="<?= esc(mcc_user_date_input($filters['date_to'] ?? null)) ?>">
            </div>
            <div class="mcc-form-actions mcc-form-field-wide">
                <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
            </div>
        </form>
    </section>

    <section class="mcc-card mcc-scheduling-card mcc-achievement-results-card" aria-labelledby="award-list-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Authorized award records</span>
                <h2 id="award-list-heading"><?= $includeArchived ? 'Award history' : 'Current awards' ?></h2>
            </div>
            <span class="mcc-muted"><?= (int) ($pagination['total'] ?? count($awards)) ?> records</span>
        </div>
        <?php if ($awards === []): ?>
            <div class="mcc-empty-state" role="status">
                <strong>No Achievement Awards found</strong>
                <span>Try changing the filters or create an award for an active Player or Coach.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table mcc-achievement-awards-table">
                    <caption class="sr-only">Achievement award records</caption>
                    <thead>
                        <tr>
                            <th scope="col">Achievement</th>
                            <th scope="col">Category</th>
                            <th scope="col">Recipient</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($awards as $award): ?>
                            <?php
                            $status = strtolower((string) ($award['status'] ?? 'draft'));
                            $isArchived = (int) ($award['is_archived'] ?? 0) === 1;
                            $branchId = (string) ($award['branch_id'] ?? '');
                            $awardUrl = 'achievements/awards/' . (int) $award['id'];
                            $actionsId = 'award-actions-' . (int) $award['id'];
                            $recipientType = ($award['recipient_type'] ?? '') === 'coach' ? 'Coach' : 'Player';
                            $recipientName = $recipientType === 'Coach' ? ($award['coach_name'] ?? 'Unavailable') : ($award['player_name'] ?? 'Unavailable');
                            ?>
                            <tr>
                                <th scope="row" data-label="Achievement"><?= esc($award['achievement_title'] ?? 'Unavailable') ?></th>
                                <td data-label="Category"><?= esc($award['category'] ?? 'Uncategorized') ?></td>
                                <td data-label="Recipient"><span class="mcc-table-primary-text"><?= esc($recipientName) ?></span><span class="mcc-table-secondary-text"><?= esc($recipientType) ?></span></td>
                                <td data-label="Branch"><?= esc($award['branch_name'] ?? 'Unavailable') ?></td>
                                <td data-label="Status"><span class="mcc-badge mcc-badge-<?= esc($isArchived ? 'warning' : ($statusTones[$status] ?? 'neutral')) ?>"><?= esc($isArchived ? 'Archived' : ($statusLabels[$status] ?? 'Unknown')) ?></span></td>
                                <td data-label="Created"><time datetime="<?= esc((string) ($award['created_at'] ?? '')) ?>"><?= esc($formatDate($award['created_at'] ?? null)) ?></time></td>
                                <td data-label="Actions" class="mcc-award-actions">
                                    <div class="mcc-session-action-menu" data-session-actions>
                                        <button class="mcc-button mcc-button-secondary" type="button"
                                            data-session-actions-toggle aria-expanded="false" aria-haspopup="true"
                                            aria-controls="<?= esc($actionsId) ?>">
                                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                            Actions
                                        </button>
                                        <div class="mcc-session-action-dropdown" id="<?= esc($actionsId) ?>"
                                            data-session-actions-menu hidden>
                                    <a href="<?= esc(site_url($awardUrl)) ?>">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        View award
                                    </a>
                                    <?php if (! $isArchived && in_array($status, ['draft', 'approved'], true) && ($authorization->can('achievement.update', $branchId) || $authorization->can('achievement.create', $branchId))): ?>
                                        <a href="<?= esc(site_url($awardUrl . '/edit')) ?>">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                            Edit award
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($authorization->can('achievement.update', $branchId) || $authorization->can('achievement.create', $branchId)): ?>
                                        <form method="post" action="<?= site_url($awardUrl . '/delete') ?>" onsubmit="return confirm('Permanently delete this award? This cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <button type="submit">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                Delete award
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (! $isArchived && $status === 'draft' && $authorization->can('achievement.approve', $branchId)): ?>
                                        <form method="post" action="<?= site_url($awardUrl . '/approve') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="version" value="<?= (int) $award['version'] ?>">
                                            <input class="form-control" name="reason" required maxlength="1000" placeholder="Approval reason" aria-label="Approval reason">
                                            <button class="mcc-button mcc-button-primary" type="submit">Approve</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (! $isArchived && $status === 'approved' && $authorization->can('achievement.publish', $branchId)): ?>
                                        <form method="post" action="<?= site_url($awardUrl . '/publish') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="version" value="<?= (int) $award['version'] ?>">
                                            <input class="form-control" name="reason" required maxlength="1000" placeholder="Publication reason" aria-label="Publication reason">
                                            <button class="mcc-button mcc-button-primary" type="submit">Publish</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (! $isArchived && $status === 'published' && $authorization->can('achievement.revoke', $branchId)): ?>
                                        <form method="post" action="<?= site_url($awardUrl . '/revoke') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="version" value="<?= (int) $award['version'] ?>">
                                            <input class="form-control" name="reason" required maxlength="1000" placeholder="Revocation reason" aria-label="Revocation reason">
                                            <button class="mcc-button mcc-button-secondary" type="submit">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (! $isArchived && $status === 'published' && $authorization->can('achievement.update', $branchId)): ?>
                                        <form method="post" action="<?= site_url($awardUrl . '/correct') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="version" value="<?= (int) $award['version'] ?>">
                                            <input type="hidden" name="nomination_note" value="<?= esc((string) ($award['nomination_note'] ?? '')) ?>">
                                            <input class="form-control" name="correction_reason" required maxlength="1000" placeholder="Correction reason" aria-label="Correction reason">
                                            <button class="mcc-button mcc-button-secondary" type="submit">Correct</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (! $isArchived && $status === 'published' && ! empty($award['certificate_id']) && $authorization->can('achievement.certificate.download', $branchId)): ?>
                                        <a class="mcc-button mcc-button-secondary" href="<?= site_url($awardUrl . '/certificate/download') ?>">Download certificate</a>
                                    <?php elseif (! $isArchived && $status === 'published' && $authorization->can('achievement.certificate.generate', $branchId)): ?>
                                        <form method="post" action="<?= site_url($awardUrl . '/certificate') ?>">
                                            <?= csrf_field() ?>
                                            <button class="mcc-button mcc-button-secondary" type="submit">Generate certificate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (! $isArchived && in_array($status, ['published', 'revoked'], true) && $authorization->can('achievement.archive', $branchId)): ?>
                                        <form method="post" action="<?= site_url($awardUrl . '/archive') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="version" value="<?= (int) $award['version'] ?>">
                                            <input class="form-control" name="reason" required maxlength="1000" placeholder="Archive reason" aria-label="Archive reason">
                                            <button class="mcc-button mcc-button-secondary" type="submit">Archive</button>
                                        </form>
                                    <?php elseif ($isArchived && $authorization->can('achievement.restore', $branchId)): ?>
                                        <form method="post" action="<?= site_url($awardUrl . '/restore') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="version" value="<?= (int) $award['version'] ?>">
                                            <input class="form-control" name="reason" required maxlength="1000" placeholder="Restore reason" aria-label="Restore reason">
                                            <button class="mcc-button mcc-button-primary" type="submit">Restore</button>
                                        </form>
                                    <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (($pagination['pages'] ?? 1) > 1): ?>
                <nav class="mcc-pagination" aria-label="Achievement award pages">
                    <span>Page <?= (int) $pagination['current'] ?> of <?= (int) $pagination['pages'] ?></span>
                    <div class="mcc-pagination-actions">
                        <?php if ((int) $pagination['current'] > 1): ?>
                            <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements/awards?' . $pagination['query'] . '&page=' . ((int) $pagination['current'] - 1)) ?>">Previous</a>
                        <?php endif; ?>
                        <?php if ((int) $pagination['current'] < (int) $pagination['pages']): ?>
                            <a class="mcc-button mcc-button-secondary" href="<?= site_url('achievements/awards?' . $pagination['query'] . '&page=' . ((int) $pagination['current'] + 1)) ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?= view('staff_footer') ?>
