<?php
$breadcrumb = $breadcrumb ?? null;
$breadcrumbTrail = is_array($breadcrumbTrail ?? null) ? $breadcrumbTrail : [];
?>
<nav class="mcc-breadcrumbs" aria-label="Breadcrumb">
    <a href="<?= site_url('/') ?>">Dashboard</a>
    <?php if ($breadcrumbTrail !== []): ?>
        <?php foreach ($breadcrumbTrail as $item): ?>
            <span aria-hidden="true">/</span>
            <?php if (! empty($item['url'])): ?>
                <a href="<?= esc($item['url']) ?>"><?= esc((string) ($item['label'] ?? '')) ?></a>
            <?php else: ?>
                <span aria-current="page"><?= esc((string) ($item['label'] ?? '')) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php elseif (! empty($breadcrumb)): ?>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= esc($breadcrumb) ?></span>
    <?php endif; ?>
</nav>
