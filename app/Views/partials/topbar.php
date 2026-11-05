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
        <a class="mcc-topbar-notifications" href="<?= site_url('notifications') ?>" aria-label="Open notifications" title="Notifications">
            <i class="fa-regular fa-bell" aria-hidden="true"></i>
        </a>
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
            <form method="post" action="<?= site_url('auth/logout') ?>">
                <?= csrf_field() ?>
                <button class="mcc-button mcc-button-link" type="submit">
                    Log out
                </button>
            </form>
        </div>
    </details>
</header>
