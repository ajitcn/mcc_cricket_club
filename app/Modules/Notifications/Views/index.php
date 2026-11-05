<?php

$result = is_array($result ?? null) ? $result : [];
$notifications = is_array($result['notifications'] ?? null) ? $result['notifications'] : [];
$filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
$createOptions = is_array($create_options ?? null)
    ? $create_options
    : ['branches' => [], 'users' => [], 'categories' => ['general'], 'branch_optional' => false];
$hasFilters = ($filters['state'] ?? '') !== '' || ($filters['category'] ?? '') !== '';
$pageActions = '<div class="mcc-page-actions">'
    . '<button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-expanded="'
    . ($hasFilters ? 'true' : 'false')
    . '" aria-controls="notification-filters"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>Filters</span></button>';
if ($createOptions['branches'] !== []) {
    $pageActions .= '<button class="mcc-button mcc-button-primary" type="button" data-notification-modal-open>'
        . '<i class="fa-solid fa-plus" aria-hidden="true"></i><span>New notification</span></button>';
}
$pageActions .= '</div>';
?>
<?= view('staff_header', ['title' => $title, 'pageActions' => $pageActions]) ?>

<section id="notification-filters" class="mcc-card" aria-labelledby="notification-filters-heading" <?= $hasFilters ? '' : 'hidden' ?>>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="notification-filters-heading">Notification filters</h2>
        </div>
    </div>
    <form method="get" action="<?= site_url('notifications') ?>" class="row g-3">
        <div class="col-12 col-md-4">
            <label for="notification-state">State</label>
            <select class="form-control" id="notification-state" name="state">
                <option value="">All notifications</option>
                <option value="unread" <?= ($filters['state'] ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option>
                <option value="read" <?= ($filters['state'] ?? '') === 'read' ? 'selected' : '' ?>>Read</option>
            </select>
        </div>
        <div class="col-12 col-md-5">
            <label for="notification-category">Category</label>
            <select class="form-control" id="notification-category" name="category">
                <option value="">All categories</option>
                <?php foreach ((array) ($createOptions['categories'] ?? ['general']) as $category): ?>
                    <option value="<?= esc($category) ?>" <?= ($filters['category'] ?? '') === $category ? 'selected' : '' ?>><?= esc(ucwords(str_replace(['_', '-'], ' ', $category))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex align-items-end gap-2">
            <button class="mcc-button mcc-button-primary" type="submit">Apply</button>
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('notifications') ?>">Clear</a>
        </div>
    </form>
</section>

<section class="mcc-card mcc-notification-list" aria-labelledby="notifications-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Communication</span>
            <h2 id="notifications-heading">Notifications</h2>
        </div>
        <span class="mcc-muted"><?= count($notifications) ?> result<?= count($notifications) === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($notifications === []): ?>
        <p class="mcc-empty-state">No notifications are available.</p>
    <?php else: ?>
        <div class="mcc-notification-items">
            <?php foreach ($notifications as $notification): ?>
                <?php $isRead = ($notification['read_at'] ?? null) !== null; ?>
                <article class="mcc-notification-item <?= $isRead ? 'is-read' : 'is-unread' ?>">
                    <div class="mcc-notification-icon" aria-hidden="true">
                        <i class="fa-solid <?= $isRead ? 'fa-envelope-open' : 'fa-bell' ?>"></i>
                    </div>
                    <div class="mcc-notification-content">
                        <div class="mcc-notification-heading">
                            <h3><?= esc($notification['title'] ?? 'Notification') ?></h3>
                            <span class="mcc-badge <?= $isRead ? 'mcc-badge-neutral' : 'mcc-badge-info' ?>">
                                <?= $isRead ? 'Read' : 'Unread' ?>
                            </span>
                        </div>
                        <p><?= esc($notification['body'] ?? '') ?></p>
                        <div class="mcc-notification-meta">
                            <span><?= esc($notification['category'] ?? 'General') ?></span>
                            <span><?= esc(mcc_user_datetime($notification['created_at'] ?? null)) ?></span>
                        </div>
                    </div>
                    <div class="mcc-notification-actions">
                        <?php if (! $isRead): ?>
                            <form method="post" action="<?= site_url('notifications/' . (int) ($notification['recipient_id'] ?? 0) . '/read') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="state" value="<?= esc($filters['state'] ?? '') ?>">
                                <input type="hidden" name="category" value="<?= esc($filters['category'] ?? '') ?>">
                                <button class="mcc-button mcc-button-secondary" type="submit">Mark read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($createOptions['branches'] !== []): ?>
    <div class="mcc-modal" data-notification-modal hidden aria-hidden="true">
        <div class="mcc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="notification-modal-heading">
            <div class="mcc-modal-heading">
                <div>
                    <span class="mcc-eyebrow">Communication</span>
                    <h2 id="notification-modal-heading">New notification</h2>
                </div>
                <button class="mcc-icon-button" type="button" data-notification-modal-close aria-label="Close notification dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form method="post" action="<?= site_url('notifications/create') ?>" class="mcc-form">
                <?= csrf_field() ?>
                <div class="mcc-form-field">
                    <label for="notification-branch">Branch <?= ! empty($createOptions['branch_optional']) ? '<span class="mcc-muted">(optional)</span>' : '' ?></label>
                    <select id="notification-branch" class="form-control" name="branch_id" <?= empty($createOptions['branch_optional']) ? 'required' : '' ?> data-notification-branch>
                        <option value="">All authorized branches</option>
                        <?php foreach ($createOptions['branches'] as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= (string) old('branch_id') === (string) $branch['id'] ? 'selected' : '' ?>><?= esc($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mcc-form-field">
                    <label for="notification-title">Title</label>
                    <input id="notification-title" class="form-control" name="title" maxlength="180" value="<?= esc(old('title')) ?>" required>
                </div>
                <div class="mcc-form-field">
                    <label for="notification-category-create">Category</label>
                    <select id="notification-category-create" class="form-control" name="category" required>
                        <option value="">Choose category</option>
                        <?php foreach ((array) ($createOptions['categories'] ?? ['general']) as $category): ?>
                            <option value="<?= esc($category) ?>" <?= old('category') === $category ? 'selected' : '' ?>><?= esc(ucwords(str_replace(['_', '-'], ' ', $category))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mcc-form-field">
                    <label for="notification-body">Message</label>
                    <textarea id="notification-body" class="form-control" name="body" rows="4" required><?= esc(old('body')) ?></textarea>
                </div>
                <div class="mcc-form-field">
                    <label for="notification-recipients">Recipients <span class="mcc-muted">(optional — all users in scope)</span></label>
                    <select id="notification-recipients" class="form-control selectpicker btSelect" name="recipient_user_ids[]" multiple data-bt-select data-live-search="true" data-actions-box="true" title="All users in scope">
                        <?php foreach ($createOptions['users'] as $user): ?>
                            <option value="<?= (int) $user['id'] ?>" data-branch-id="<?= (int) $user['branch_id'] ?>">
                                <?= esc(($user['display_name'] ?? '') !== '' ? $user['display_name'] : ($user['email'] ?? 'User #' . (int) $user['id'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mcc-modal-actions">
                    <button class="mcc-button mcc-button-primary" type="submit">Send notification</button>
                    <button class="mcc-button mcc-button-secondary" type="button" data-notification-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?= view('staff_footer') ?>
