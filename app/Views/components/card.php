<section class="mcc-card <?= esc($class ?? '') ?>">
    <?php if (! empty($eyebrow) || ! empty($heading)): ?><div class="mcc-card-heading"><?php if (! empty($eyebrow)): ?><span class="mcc-eyebrow"><?= esc($eyebrow) ?></span><?php endif; ?><?php if (! empty($heading)): ?><h2><?= esc($heading) ?></h2><?php endif; ?></div><?php endif; ?>
    <?= $content ?? '' ?>
</section>
