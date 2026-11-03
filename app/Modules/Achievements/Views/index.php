<?php
$achievements = is_array($achievements ?? null) ? $achievements : [];
?>
<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4 mcc-achievements-page">
    <div class="mcc-page-heading">
        <div>
            <span class="mcc-eyebrow">Achievements</span>
            <h1>Achievement catalog</h1>
            <p class="mcc-card-subtitle">Branch-scoped titles and categories available for future award workflows.</p>
        </div>
        <div class="mcc-page-actions">
            <?php if (service('authorization')->can('achievement.create')): ?>
                <a class="mcc-button mcc-button-primary" href="<?= site_url('achievements/new') ?>">Create Achievement</a>
            <?php endif; ?>
        </div>
    </div>
    <section class="mcc-card" aria-labelledby="achievement-list-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Authorized catalog</span>
                <h2 id="achievement-list-heading">Achievements</h2>
            </div>
            <span class="mcc-muted"><?= count($achievements) ?> configured</span>
        </div>
        <?php if ($achievements === []): ?>
            <div class="mcc-empty-state">
                <strong>No achievements configured</strong>
                <span>Create a branch-scoped Achievement title to begin the catalog.</span>
            </div>
        <?php else: ?>
            <div class="mcc-table-wrap">
                <table class="mcc-table">
                    <caption class="sr-only">Authorized Achievement catalog</caption>
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">Category</th>
                            <th scope="col">Branch</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($achievements as $achievement): ?>
                            <tr>
                                <th scope="row"><?= esc($achievement['title']) ?></th>
                                <td data-label="Category"><?= esc($achievement['category']) ?></td>
                                <td data-label="Branch"><?= esc($achievement['branch_name']) ?></td>
                                <td data-label="Status"><span class="mcc-badge mcc-badge-<?= (int) $achievement['is_active'] === 1 ? 'success' : 'neutral' ?>"><?= (int) $achievement['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= view('staff_footer') ?>
