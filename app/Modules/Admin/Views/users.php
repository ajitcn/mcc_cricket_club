<?php

$pageActions = '<div class="mcc-page-actions">
    <button class="mcc-button mcc-button-secondary" type="button" data-filter-toggle aria-controls="user-filters" aria-expanded="false">
        Filters
    </button>
    <a class="mcc-button mcc-button-primary" href="' . site_url('admin/users/new') . '">
        Create user
    </a>
</div>';

$userCount = count($users);

$formatAssignments = static function (mixed $assignments): string {
    if (! is_array($assignments) || $assignments === []) {
        return '—';
    }

    $names = [];
    foreach ($assignments as $assignment) {
        $name = is_array($assignment) ? ($assignment['name'] ?? '') : $assignment;
        if (is_string($name) && trim($name) !== '') {
            $names[] = trim($name);
        }
    }

    return $names === [] ? '—' : implode(', ', $names);
};
?>
<?= view('staff_header', [
    'title' => 'Users',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => $pageActions,
]) ?>

<section id="user-filters" class="mcc-card mcc-user-filters" aria-labelledby="user-filters-heading" hidden>
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Search and filter</span>
            <h2 id="user-filters-heading">User filters</h2>
        </div>
    </div>
    <form method="get" action="<?= site_url('admin/users') ?>" class="mcc-user-filter-form">
        <div class="mcc-user-filter-fields">
            <div class="mcc-user-filter-field">
                <label for="user-query">Name or email</label>
                <input
                    class="form-control"
                    id="user-query"
                    name="q"
                    type="search"
                    maxlength="190"
                    value="<?= esc($query ?? '') ?>"
                >
            </div>
        </div>
        <div class="mcc-user-filter-actions">
            <button class="mcc-button mcc-button-primary" type="submit">Apply filters</button>
            <?php if (! empty($query)): ?>
                <a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/users') ?>">Clear filters</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="mcc-card mcc-user-list-card" aria-labelledby="users-heading">
    <div class="mcc-card-heading mcc-user-list-heading">
        <div>
            <span class="mcc-eyebrow">Administration</span>
            <h2 id="users-heading">Users</h2>
            <p class="mcc-user-list-supporting-text">Manage authorized user accounts and access.</p>
        </div>
        <span class="mcc-muted">
            <?= $userCount ?> result<?= $userCount === 1 ? '' : 's' ?>
        </span>
    </div>

    <?php if ($users === []): ?>
        <div class="mcc-empty-state mcc-user-empty-state">
            <i class="fa-solid fa-users-slash" aria-hidden="true"></i>
            <h2>No matching users</h2>
            <p>No user accounts match the current search.</p>
        </div>
    <?php else: ?>
        <div class="mcc-table-wrap mcc-user-table-wrap">
            <table class="mcc-table mcc-user-table">
                <caption class="visually-hidden">User accounts</caption>
                <colgroup>
                    <col class="mcc-user-col-name">
                    <col class="mcc-user-col-email">
                    <col class="mcc-user-col-role">
                    <col class="mcc-user-col-branch">
                    <col class="mcc-user-col-status">
                    <col class="mcc-user-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php $enabled = (int) $user['is_enabled'] === 1; ?>
                        <tr>
                            <th scope="row">
                                <span class="mcc-user-name"><?= esc($user['display_name']) ?></span>
                            </th>
                            <td>
                                <a class="mcc-user-email" href="mailto:<?= esc($user['email']) ?>">
                                    <?= esc($user['email']) ?>
                                </a>
                            </td>
                            <td class="mcc-user-assignment">
                                <?= esc($formatAssignments($user['roles'] ?? null)) ?>
                            </td>
                            <td class="mcc-user-assignment">
                                <?= esc($formatAssignments($user['branches'] ?? null)) ?>
                            </td>
                            <td>
                                <span class="mcc-badge <?= $enabled ? 'mcc-badge-success' : 'mcc-badge-danger' ?>">
                                    <?= $enabled ? 'Enabled' : 'Disabled' ?>
                                </span>
                            </td>
                            <td>
                                <div class="mcc-user-actions mcc-lead-actions">
                                    <a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/users/' . $user['id']) ?>">
                                        View
                                    </a>
                                    <a class="mcc-button mcc-button-secondary" href="<?= site_url('admin/users/' . $user['id'] . '/edit') ?>">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?= view('staff_footer') ?>
