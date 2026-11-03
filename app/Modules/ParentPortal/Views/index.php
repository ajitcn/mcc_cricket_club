<?php

$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$children = is_array($dashboard['children'] ?? null) ? $dashboard['children'] : [];
?>
<?= view('staff_header', [
    'title' => $title,
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>
<main class="container py-4 mcc-parent-portal-page">
    <section class="mcc-card" aria-labelledby="parent-portal-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Parent portal</span>
                <h1 id="parent-portal-heading">Your children</h1>
                <p class="mcc-card-subtitle">View the academy records available for your linked children.</p>
            </div>
            <span class="mcc-muted"><?= count($children) ?> linked child<?= count($children) === 1 ? '' : 'ren' ?></span>
        </div>
        <?php if ($children === []): ?>
            <div class="mcc-empty-state" role="status">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <strong>No linked children found</strong>
                <span>Contact the academy if a child should be linked to this account.</span>
            </div>
        <?php else: ?>
            <div class="mcc-portal-child-grid">
                <?php foreach ($children as $child): ?>
                    <article class="mcc-card mcc-portal-child-card">
                        <div class="mcc-portal-section-heading">
                            <div>
                                <span class="mcc-eyebrow">Player</span>
                                <h2><?= esc((string) $child['full_name']) ?></h2>
                            </div>
                            <span class="mcc-status-badge mcc-status-badge-success"><?= esc(ucfirst((string) $child['status'])) ?></span>
                        </div>
                        <dl class="mcc-detail-grid">
                            <div><dt>Branch</dt><dd><?= esc((string) $child['branch_name']) ?></dd></div>
                            <div><dt>Date of birth</dt><dd><?= esc(mcc_user_date($child['date_of_birth'] ?? null)) ?></dd></div>
                        </dl>
                        <a class="mcc-button mcc-button-primary" href="<?= site_url('parent-portal/children/' . (int) $child['id']) ?>">
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            View child details
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
