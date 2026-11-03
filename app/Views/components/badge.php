<?php $tone = preg_replace('/[^a-z0-9-]/i', '', (string) ($tone ?? 'neutral')); ?>
<span class="mcc-badge mcc-badge-<?= esc($tone ?: 'neutral') ?>"><?= esc($label ?? '') ?></span>
