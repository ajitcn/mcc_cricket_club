<?= view('staff_header', ['title' => $title]) ?>

<main class="container py-4">
    <h1>Notifications</h1>

    <form method="get" class="row">
        <div class="col-12 col-md-6 mb-2">
            <label for="notification-state">State</label>
            <select class="form-control" id="notification-state" name="state">
                <option value="">All</option>
                <option value="unread" <?= ($result['filters']['state'] ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option>
                <option value="read" <?= ($result['filters']['state'] ?? '') === 'read' ? 'selected' : '' ?>>Read</option>
            </select>
        </div>
        <div class="col-12 col-md-6 mb-2">
            <label for="notification-category">Category</label>
            <input
                class="form-control"
                id="notification-category"
                name="category"
                value="<?= esc($result['filters']['category'] ?? '') ?>"
            >
        </div>
        <div class="col-12 mt-2 text-right">
            <button class="mcc-button mcc-button-secondary" type="submit">Filter</button>
        </div>
    </form>

    <?php if ($result['notifications'] === []): ?>
        <p class="mcc-empty-state">No notifications are available.</p>
    <?php endif ?>

    <ul>
        <?php foreach ($result['notifications'] as $notification): ?>
            <li>
                <strong><?= esc($notification['title']) ?></strong>
                <p><?= esc($notification['body']) ?></p>
                <small>
                    <?= esc($notification['category']) ?>
                    · <?= esc(mcc_user_datetime($notification['created_at'] ?? null)) ?>
                </small>
                <?php if ($notification['read_at'] === null): ?>
                    <form method="post" action="<?= site_url('notifications/' . $notification['recipient_id'] . '/read') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="state" value="<?= esc($result['filters']['state'] ?? '') ?>">
                        <input type="hidden" name="category" value="<?= esc($result['filters']['category'] ?? '') ?>">
                        <button class="mcc-button mcc-button-secondary" type="submit">Mark read</button>
                    </form>
                <?php else: ?>
                    <span>Read</span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
</main>

<?= view('staff_footer') ?>
