<?php

$detail = is_array($detail ?? null) ? $detail : [];
$child = is_array($detail['child'] ?? null) ? $detail['child'] : [];
$visibility = is_array($detail['visibility'] ?? null) ? $detail['visibility'] : [];
?>
<?= view('staff_header', [
    'title' => $title,
    'nav' => $nav ?? null,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageActions' => '<a class="mcc-button mcc-button-secondary" href="' . site_url('parent-portal') . '">'
        . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="mcc-button-label">Back to parent portal</span></a>',
]) ?>
<main class="container py-4 mcc-parent-portal-page">
    <section class="mcc-card" aria-labelledby="parent-child-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Parent portal</span>
                <h1 id="parent-child-heading"><?= esc((string) ($child['full_name'] ?? 'Child details')) ?></h1>
                <p class="mcc-card-subtitle">Authorized information for the linked player.</p>
            </div>
            <span class="mcc-status-badge mcc-status-badge-success"><?= esc(ucfirst((string) ($child['status'] ?? 'active'))) ?></span>
        </div>
        <dl class="mcc-detail-grid">
            <div><dt>Branch</dt><dd><?= esc((string) ($child['branch_name'] ?? 'Not available')) ?></dd></div>
            <div><dt>Date of birth</dt><dd><?= esc(mcc_user_date($child['date_of_birth'] ?? null)) ?></dd></div>
        </dl>
    </section>

    <section class="mcc-card" aria-labelledby="parent-visibility-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Visibility</span>
                <h2 id="parent-visibility-heading">Available portal information</h2>
            </div>
        </div>
        <div class="mcc-detail-grid">
            <?php foreach ($visibility as $category => $value): ?>
                <div>
                    <dt><?= esc(ucwords(str_replace('_', ' ', $category))) ?></dt>
                    <dd><span class="mcc-status-badge mcc-status-badge-neutral"><?= esc((string) $value) ?></span></dd>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?= view('staff_footer') ?>
