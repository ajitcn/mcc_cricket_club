<?= view('admission_test_public_header', [
    'title' => $title ?? 'Admission Test submitted',
]) ?>

<div class="mcc-admission-test-wizard">
    <section class="mcc-card mcc-wizard-header" aria-labelledby="admission-test-thank-you-heading">
        <div class="mcc-card-heading">
            <div>
                <span class="mcc-eyebrow">Admission Test</span>
                <h2 id="admission-test-thank-you-heading">Thank you</h2>
            </div>
        </div>
        <p class="mcc-alert mcc-alert-success" role="status">
            Thank you. Your Admission Test has been submitted successfully.
        </p>
    </section>
</div>

<?= view('admission_test_public_footer') ?>
