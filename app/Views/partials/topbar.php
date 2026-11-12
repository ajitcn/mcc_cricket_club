<?php

$user = session('user') ?? [];
$email = (string) ($user['email'] ?? 'Account');
?>
<header class="mcc-topbar">
    <button
        class="mcc-icon-button mcc-sidebar-toggle"
        type="button"
        data-sidebar-toggle
        aria-controls="mcc-sidebar"
        aria-expanded="false"
        aria-label="Toggle navigation"
    >
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <button
        class="mcc-sidebar-collapse mcc-topbar-collapse"
        type="button"
        data-sidebar-collapse
        aria-controls="mcc-sidebar"
        aria-expanded="true"
        aria-label="Collapse navigation"
        title="Collapse navigation"
    >
        <i class="mcc-sidebar-collapse-icon fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <div class="mcc-topbar-context" aria-label="Current workspace">
        <span class="mcc-topbar-context-icon" aria-hidden="true">
            <i class="fa-solid fa-shield-halved"></i>
        </span>
        <span>
            <strong>MCC Cricket Club</strong>
            <small>Academy workspace</small>
        </span>
    </div>
    <?= view('partials/active_branch_selector') ?>
    <div class="mcc-topbar-spacer"></div>
    <span class="mcc-topbar-date"><?= esc(date('D, d-m-Y')) ?></span>
    <?php if (service('authorization')->can('notification.view')): ?>
        <?php
        $topbarNotifications = ['notifications' => [], 'unread_count' => 0];
        try {
            $topbarNotifications = service('notification')->topbar();
        } catch (\Throwable) {
            // Keep the shared header available if notification storage is unavailable.
        }
        $topbarItems = is_array($topbarNotifications['notifications'] ?? null)
            ? $topbarNotifications['notifications']
            : [];
        $topbarUnread = (int) ($topbarNotifications['unread_count'] ?? 0);
        ?>
        <details class="mcc-topbar-notification-menu">
            <summary class="mcc-topbar-notifications" aria-label="Open notifications" title="Notifications">
                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                <?php if ($topbarUnread > 0): ?>
                    <span class="mcc-topbar-notification-count" aria-label="<?= $topbarUnread ?> unread notifications">
                        <?= $topbarUnread > 99 ? '99+' : $topbarUnread ?>
                    </span>
                <?php endif; ?>
            </summary>
            <div class="mcc-topbar-notification-panel">
                <div class="mcc-topbar-notification-heading">
                    <strong>Notifications</strong>
                    <?php if ($topbarUnread > 0): ?>
                        <span class="mcc-badge mcc-badge-info"><?= $topbarUnread ?> unread</span>
                    <?php endif; ?>
                </div>
                <?php if ($topbarItems === []): ?>
                    <p class="mcc-topbar-notification-empty">No notifications yet.</p>
                <?php else: ?>
                    <div class="mcc-topbar-notification-items">
                        <?php foreach ($topbarItems as $topbarItem): ?>
                            <?php
                            $topbarBody = (string) ($topbarItem['body'] ?? '');
                            $topbarPreview = function_exists('mb_strimwidth')
                                ? mb_strimwidth($topbarBody, 0, 80, '…')
                                : substr($topbarBody, 0, 80);
                            ?>
                            <a class="mcc-topbar-notification-item <?= ($topbarItem['read_at'] ?? null) === null ? 'is-unread' : '' ?>" href="<?= site_url('notifications') ?>">
                                <span class="mcc-topbar-notification-item-icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
                                <span>
                                    <strong><?= esc($topbarItem['title'] ?? 'Notification') ?></strong>
                                    <small><?= esc($topbarPreview) ?></small>
                                    <time datetime="<?= esc((string) ($topbarItem['created_at'] ?? '')) ?>"><?= esc(mcc_user_datetime($topbarItem['created_at'] ?? null)) ?></time>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="mcc-topbar-notification-view-all" href="<?= site_url('notifications') ?>">View all notifications</a>
            </div>
        </details>
    <?php endif; ?>
    <details class="mcc-account">
        <summary>
            <span class="mcc-avatar" aria-hidden="true">
                <?= esc(strtoupper(substr($email, 0, 1))) ?>
            </span>
            <span class="mcc-account-copy">
                <strong><?= esc($email) ?></strong>
                <small>Signed in</small>
            </span>
        </summary>
        <div class="mcc-account-menu">
            <a class="mcc-button mcc-button-link" href="<?= site_url('auth/password') ?>">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                Update password
            </a>
            <form method="post" action="<?= site_url('auth/logout') ?>">
                <?= csrf_field() ?>
                <button class="mcc-button mcc-button-link" type="submit">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    Log out
                </button>
            </form>
        </div>
    </details>
</header>
