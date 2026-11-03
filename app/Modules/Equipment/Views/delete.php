<?php

$equipment = is_array($equipment ?? null) ? $equipment : [];
$equipmentId = (int) ($equipment['id'] ?? 0);
?>
<?= view('staff_header', ['title' => $title]) ?>
<section class="mcc-card" aria-labelledby="equipment-delete-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Grounds &amp; Equipment</span>
            <h2 id="equipment-delete-heading">Delete equipment record?</h2>
            <p class="mcc-card-subtitle">
                This hides the record from the active inventory. The record is retained in storage and audit history.
            </p>
        </div>
    </div>

    <dl class="mcc-equipment-delete-details">
        <div>
            <dt>Name</dt>
            <dd><?= esc((string) ($equipment['name'] ?? '')) ?></dd>
        </div>
        <div>
            <dt>Quantity</dt>
            <dd><?= (int) ($equipment['quantity'] ?? 0) ?></dd>
        </div>
        <div>
            <dt>Branch</dt>
            <dd><?= esc((string) ($equipment['branch_name'] ?? '')) ?></dd>
        </div>
    </dl>

    <div class="mcc-equipment-form-actions">
        <form method="post" action="<?= site_url('equipment/' . $equipmentId . '/delete') ?>">
            <?= csrf_field() ?>
            <button class="mcc-button mcc-button-primary" type="submit">
                Confirm delete
            </button>
        </form>
        <a class="mcc-button mcc-button-secondary" href="<?= site_url('equipment') ?>">
            Cancel
        </a>
    </div>
</section>
<?= view('staff_footer') ?>
