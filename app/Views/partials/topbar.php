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
    <?= view('partials/active_branch_selector') ?>
    <div class="mcc-topbar-spacer"></div>
    <span class="mcc-topbar-date"><?= esc(date('D, d-m-Y')) ?></span>
    <details class="mcc-account">
        <summary>
            <span class="mcc-avatar" aria-hidden="true">
                <?= esc(strtoupper(substr($email, 0, 1))) ?>
            </span>
            <span class="mcc-account-email"><?= esc($email) ?></span>
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
