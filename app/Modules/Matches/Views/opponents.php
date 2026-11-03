<?= view('staff_header', ['title' => $title]) ?>
<main class="container py-4">
    <div class="mcc-page-heading"><div><span class="mcc-eyebrow">Competitions</span><h1>Opponents</h1></div><div class="mcc-page-actions"><a class="mcc-button mcc-button-primary" href="<?= site_url('matches/opponents/new') ?>">Create opponent</a></div></div>
    <?php $result = $result ?? ['opponents' => [], 'filters' => []]; ?>
    <div class="mcc-card mcc-table-wrap">
        <table class="mcc-table"><thead><tr><th>Opponent</th><th>Branch</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($result['opponents'] as $opponent): ?><tr><th><?= esc($opponent['name']) ?></th><td><?= esc($opponent['branch_name']) ?></td><td><span class="mcc-badge <?= (int) $opponent['is_active'] === 1 ? 'mcc-badge-success' : 'mcc-badge-neutral' ?>"><?= (int) $opponent['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><?php if ($opponent['can_update']): ?><a class="mcc-button mcc-button-secondary" href="<?= site_url('matches/opponents/' . (int) $opponent['id'] . '/edit') ?>">Edit</a><?php endif ?></td></tr><?php endforeach ?>
        <?php if ($result['opponents'] === []): ?><tr><td colspan="4" class="mcc-empty-state">No opponents available.</td></tr><?php endif ?>
        </tbody></table>
    </div>
</main>
<?= view('staff_footer') ?>
