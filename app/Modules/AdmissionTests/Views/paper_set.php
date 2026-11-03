<?= view('staff_header', [
    'title' => 'Select Admission Test paper set',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'breadcrumbTrail' => [
        ['label' => 'CRM', 'url' => site_url('crm')],
        ['label' => 'Trials', 'url' => site_url('crm/trials')],
        ['label' => 'Select Paper Set'],
    ],
]) ?>

<div class="mcc-admission-test-paper-set-page">
    <section class="mcc-card" aria-labelledby="paper-set-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Admission Test</span>
                <h2 id="paper-set-heading">Select Paper Set</h2>
                <p class="mcc-muted mb-0">Choose one paper set before continuing. The selection is permanently locked when saved.</p>
            </div>
            <span class="mcc-badge mcc-badge-info">Test #<?= (int) $test['id'] ?></span>
        </div>

        <form method="post" action="<?= site_url('admission-tests/' . (int) $test['id'] . '/paper-set') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="version" value="<?= (int) $test['version'] ?>">
            <fieldset>
                <legend class="form-label">Paper Set <span aria-hidden="true">*</span></legend>
                <p class="mcc-muted">Select exactly one set. Each set contains 10 questions.</p>
                <?php foreach ($paperSets as $value => $label): ?>
                    <label class="mcc-paper-set-choice mcc-admission-test-radio-label" for="paper-set-<?= esc($value) ?>">
                        <input id="paper-set-<?= esc($value) ?>" type="radio" name="paper_set" value="<?= esc($value) ?>" <?= old('paper_set') === $value ? 'checked' : '' ?> required>
                        <span>
                            <strong><?= esc($label) ?></strong>
                            <small>10 questions</small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <button class="mcc-button mcc-button-primary mt-3" type="submit">Continue Admission Test</button>
        </form>
    </section>
</div>
<?= view('staff_footer') ?>
