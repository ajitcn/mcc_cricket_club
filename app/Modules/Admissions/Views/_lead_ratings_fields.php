<?php

$admissionRecord = is_array($admission ?? null) ? $admission : [];
$ratingService = new \App\Services\TrialRatingService();
$selectedTrialId = (string) old('trial_id', $admissionRecord['trial_id'] ?? '');
$ratingFields = [
    'ability_overall' => 'Overall Rating',
    'ability_batting' => 'Batting Rating',
    'ability_bowling' => 'Bowling Rating',
    'ability_wicketkeeping' => 'Wicketkeeping Rating',
    'ability_fitness' => 'Fitness Rating',
];
$ratingOptions = array_map(
    static function (int $value) use ($ratingService): array {
        $rating = number_format($value / 10, 1, '.', '');
        $display = $rating . ' - ' . $ratingService->category($rating);

        return ['value' => $rating . ' - ' . $ratingService->category($rating), 'label' => $display];
    },
    range(0, 100),
);
$fieldError = static function (string $field): string {
    if (function_exists('validation_show_error')) {
        $message = trim((string) validation_show_error($field));
        if ($message !== '') {
            return $message;
        }
    }

    $errors = session()->getFlashdata('field_errors');

    return is_array($errors) ? trim((string) ($errors[$field] ?? '')) : '';
};
?>
<section class="mcc-admission-trial-ratings" data-admission-trial-ratings>
    <h3>Ratings</h3>
    <div class="row admission-rating-grid mcc-admission-rating-grid" data-admission-rating-values>
        <?php foreach ($ratingFields as $field => $label): ?>
            <?php
            $fieldId = 'admission-' . str_replace('_', '-', $field);
            $selected = (string) old($field, $admissionRecord[$field] ?? '');
            $selectedNumeric = $selected === '' ? '' : (string) ((float) $selected);
            $input = '<select class="form-control" id="' . esc($fieldId) . '" name="' . esc($field)
                . '" data-admission-trial-rating="' . esc($field) . '" data-admission-rating-field>';
            $input .= '<option value="" disabled' . ($selected === '' ? ' selected' : '') . '>Choose rating</option>';
            foreach ($ratingOptions as $option) {
                $optionValue = (string) ($option['value'] ?? '');
                $numericOptionValue = trim((string) preg_replace('/\s*-\s*.*$/', '', $optionValue));
                $input .= '<option value="' . esc($optionValue) . '"'
                    . ((string) ((float) $numericOptionValue) === $selectedNumeric ? ' selected' : '') . '>'
                    . esc((string) ($option['label'] ?? $optionValue)) . '</option>';
            }
            $input .= '</select>';
            ?>
            <div class="col-12 col-md-4">
                <div class="mcc-form-field">
                    <label for="<?= esc($fieldId) ?>"><?= esc($label) ?></label>
                    <?= $input ?>
                    <?php if ($fieldError($field) !== ''): ?>
                        <p class="mcc-field-error" role="alert"><?= esc($fieldError($field)) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="mcc-empty-state" data-admission-rating-empty hidden role="status"></p>
</section>
