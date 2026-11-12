<?php
$achievement = is_array($achievement ?? null) ? $achievement : [];
$canEdit = service('authorization')->can('achievement.update') || service('authorization')->can('achievement.create');
?>
<?= view('staff_header', [
    'title' => 'Achievement',
    'pageActions' => '<div class="mcc-page-actions"><a class="mcc-button mcc-button-secondary" href="' . esc(site_url('achievements')) . '">Back to achievements</a>'
        . ($canEdit ? '<a class="mcc-button mcc-button-primary" href="' . esc(site_url('achievements/' . (int) $achievement['id'] . '/edit')) . '">Edit</a>' : '')
        . '</div>',
]) ?>
<div class="mcc-achievements-page">
    <section class="mcc-card mcc-scheduling-card mcc-achievement-form-card" aria-labelledby="achievement-view-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Achievement details</span>
                <h2 id="achievement-view-heading"><?= esc((string) ($achievement['title'] ?? 'Achievement')) ?></h2>
            </div>
            <span class="mcc-badge mcc-badge-<?= (int) ($achievement['is_active'] ?? 0) === 1 ? 'success' : 'neutral' ?>">
                <?= (int) ($achievement['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
            </span>
        </div>
        <dl class="mcc-detail-grid">
            <div><dt>Category</dt><dd><?= esc((string) ($achievement['category'] ?? '—')) ?></dd></div>
            <div><dt>Branch</dt><dd><?= esc((string) ($achievement['branch_name'] ?? '—')) ?></dd></div>
            <div class="mcc-detail-grid-wide"><dt>Description</dt><dd><?= nl2br(esc((string) ($achievement['description'] ?? '—'))) ?></dd></div>
        </dl>
    </section>
</div>
<?= view('staff_footer') ?>
