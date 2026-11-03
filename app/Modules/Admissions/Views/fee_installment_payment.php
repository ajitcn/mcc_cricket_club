<?php

$installment = is_array($installment ?? null) ? $installment : [];
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$paymentHistory = is_array($installment['payment_history'] ?? null) ? $installment['payment_history'] : [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$type = (string) ($installment['installment_type'] ?? '');
$typeLabel = $type === 'hostel' ? 'Hostel' : 'Coaching';
$admissionId = (int) ($installment['admission_id'] ?? 0);
$installmentId = (int) ($installment['installment_id'] ?? 0);
$paymentAction = $installmentId > 0
    ? site_url('admissions/fee-installments/' . rawurlencode($type) . '/' . $installmentId . '/payment')
    : site_url('admissions/fee-installments');
$fieldValue = static function (string $field, mixed $fallback = '') use ($installment): string {
    return (string) old($field, $fallback);
};
?>
<?= view('staff_header', [
    'title' => 'Record Payment',
    'nav' => $nav,
    'message' => $message ?? null,
    'error' => $error ?? null,
    'pageSubtitle' => 'Capture a manual receipt against an existing ' . $typeLabel . ' installment.',
    'pageActions' => '<div class="mcc-page-actions"><a class="mcc-button mcc-button-secondary" href="' . site_url('admissions/fee-installments') . '">Fee Installments</a></div>',
]) ?>

<section class="mcc-card mcc-payment-details-card" aria-labelledby="payment-installment-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow"><?= esc($typeLabel) ?> installment</span>
            <h2 id="payment-installment-heading">Installment details</h2>
        </div>
        <span class="mcc-badge mcc-installment-payment-status"><?= esc((string) ($installment['payment_status'] ?? 'Not captured')) ?></span>
    </div>
    <dl class="mcc-payment-summary-grid">
        <div>
            <dt>Applicant</dt>
            <dd><?= esc((string) ($installment['applicant_name'] ?? 'Not provided')) ?></dd>
        </div>
        <div>
            <dt>Admission number</dt>
            <dd>#<?= esc((string) ($installment['admission_number'] ?? 'Not provided')) ?></dd>
        </div>
        <div>
            <dt>Branch</dt>
            <dd><?= esc((string) ($installment['branch_name'] ?? 'Not provided')) ?></dd>
        </div>
        <div>
            <dt>Year / installment</dt>
            <dd>
                <?= esc((string) ($installment['year_label'] ?? 'Not provided')) ?>
                /
                <?= esc((string) ($installment['installment_number'] ?? 'Not provided')) ?>
            </dd>
        </div>
        <div>
            <dt>Due date</dt>
            <dd><?= esc(mcc_user_date($installment['due_date'] ?? null, 'Not provided')) ?></dd>
        </div>
        <div>
            <dt>Installment amount</dt>
            <dd class="mcc-payment-amount">
                &#8377;<?= esc(number_format((float) ($installment['amount'] ?? 0), 2)) ?>
            </dd>
        </div>
        <div>
            <dt>Total received</dt>
            <dd class="mcc-payment-amount">
                &#8377;<?= esc(number_format((float) ($installment['total_received'] ?? 0), 2)) ?>
            </dd>
        </div>
        <div>
            <dt>Balance</dt>
            <dd class="mcc-payment-amount">
                &#8377;<?= esc(number_format((float) ($installment['balance'] ?? 0), 2)) ?>
            </dd>
        </div>
    </dl>
</section>

<section class="mcc-card mcc-payment-form-card" aria-labelledby="payment-form-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Manual receipt capture</span>
            <h2 id="payment-form-heading">Payment receipt</h2>
        </div>
    </div>
    <form
        method="post"
        action="<?= esc($paymentAction) ?>"
        class="mcc-payment-form"
    >
        <?= csrf_field() ?>
        <?php if ($installmentId > 0): ?>
            <input type="hidden" name="installment_id" value="<?= $installmentId ?>">
        <?php endif; ?>
        <div class="mcc-payment-form-grid">
            <div class="mcc-form-field">
                <label for="payment-amount-received">Amount received</label>
                <input class="form-control" id="payment-amount-received" name="amount_received" type="number" min="0.01" step="0.01" required value="<?= esc($fieldValue('amount_received')) ?>">
                <?php if (($fieldErrors['amount_received'] ?? '') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['amount_received']) ?></p>
                <?php endif; ?>
            </div>
            <div class="mcc-form-field">
                <label for="payment-date">Payment date</label>
                <input class="form-control" id="payment-date" name="payment_date" type="text" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off" data-mcc-date-picker data-mcc-date-format="d-m-Y" data-mcc-date-submit-format="Y-m-d" required value="<?= esc($fieldValue('payment_date')) ?>">
                <?php if (($fieldErrors['payment_date'] ?? '') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['payment_date']) ?></p>
                <?php endif; ?>
            </div>
            <div class="mcc-form-field">
                <label for="payment-method">Payment method</label>
                <select class="form-control" id="payment-method" name="payment_method" required>
                    <option value="" disabled <?= $fieldValue('payment_method') === '' ? 'selected' : '' ?>>Choose method</option>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?= esc($method) ?>" <?= $fieldValue('payment_method') === $method ? 'selected' : '' ?>><?= esc($method) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (($fieldErrors['payment_method'] ?? '') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['payment_method']) ?></p>
                <?php endif; ?>
            </div>
            <div class="mcc-form-field">
                <label for="payment-receipt-number">Receipt number</label>
                <input class="form-control" id="payment-receipt-number" name="receipt_number" type="text" maxlength="80" required value="<?= esc($fieldValue('receipt_number')) ?>">
                <?php if (($fieldErrors['receipt_number'] ?? '') !== ''): ?>
                    <p class="mcc-field-error" role="alert"><?= esc($fieldErrors['receipt_number']) ?></p>
                <?php endif; ?>
            </div>
            <div class="mcc-form-field">
                <label for="payment-reference-number">Reference number <span class="mcc-form-optional">(optional)</span></label>
                <input class="form-control" id="payment-reference-number" name="reference_number" type="text" maxlength="120" value="<?= esc($fieldValue('reference_number')) ?>">
            </div>
            <div class="mcc-form-field mcc-payment-notes-field">
                <label for="payment-notes">Notes <span class="mcc-form-optional">(optional)</span></label>
                <textarea class="form-control" id="payment-notes" name="notes" rows="3" maxlength="5000"><?= esc($fieldValue('notes')) ?></textarea>
            </div>
        </div>
        <div class="mcc-form-actions mcc-payment-actions">
            <a class="mcc-button mcc-button-secondary" href="<?= site_url('admissions/fee-installments') ?>">Cancel</a>
            <button class="mcc-button mcc-button-primary" type="submit">Record Payment</button>
        </div>
    </form>
</section>

<section class="mcc-card mcc-payment-history-card" aria-labelledby="payment-history-heading">
    <div class="mcc-card-heading">
        <div>
            <span class="mcc-eyebrow">Immutable ledger</span>
            <h2 id="payment-history-heading">Payment history</h2>
        </div>
    </div>
    <?php if ($paymentHistory === []): ?>
        <p class="mcc-empty-state mcc-payment-empty-state">No payment receipts have been recorded.</p>
    <?php else: ?>
        <div class="mcc-table-responsive">
            <table class="mcc-table mcc-payment-history-table">
                <caption class="visually-hidden">Payment receipt history</caption>
                <thead>
                    <tr>
                        <th scope="col">Receipt</th>
                        <th scope="col">Date</th>
                        <th scope="col">Amount</th>
                        <th scope="col">Method</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentHistory as $payment): ?>
                        <tr>
                            <th scope="row"><?= esc((string) ($payment['receipt_number'] ?? 'Not provided')) ?></th>
                            <td><?= esc(mcc_user_date($payment['payment_date'] ?? null, 'Not provided')) ?></td>
                            <td class="mcc-payment-amount">&#8377;<?= esc(number_format((float) ($payment['amount_received'] ?? 0), 2)) ?></td>
                            <td><?= esc((string) ($payment['payment_method'] ?? 'Not provided')) ?></td>
                            <td><?= esc((string) ($payment['reference_number'] ?? 'Not provided')) ?></td>
                            <td><?= esc((string) ($payment['notes'] ?? 'Not provided')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?= view('staff_footer') ?>
