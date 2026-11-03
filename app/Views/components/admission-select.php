<?php

declare(strict_types=1);

$id = (string) ($id ?? 'admission-select');
$name = (string) ($name ?? $id);
$label = (string) ($label ?? 'Select option');
$options = is_array($options ?? null) ? $options : [];
$selected = $selected ?? null;
$multiple = (bool) ($multiple ?? false);
$forceBtSelect = (bool) ($forceBtSelect ?? false);
$forceNativeSelect = (bool) ($forceNativeSelect ?? false);
$requestedPlaceholder = (string) ($placeholder ?? '');
$extraAttributes = is_array($attributes ?? null) ? $attributes : [];

$fieldName = str_ends_with($name, '[]')
    ? substr($name, 0, -2)
    : $name;
$placeholderMap = [
    'age_group_id' => 'Choose group',
    'age_group' => 'Choose group',
    'gender' => 'Choose gender',
    'branch_id' => 'Choose branch',
    'batch_id' => 'Choose batch',
    'coach_user_id' => 'Choose coach',
    'personal_coach_user_id' => 'Choose coach',
    'lead_id' => 'Choose lead',
    'trial_id' => 'Choose trial',
    'batting_order' => 'Choose order',
    'installment_plan' => 'Choose plan',
    'hostel_installment_plan' => 'Choose plan',
    'coaching_delivery' => 'Choose coaching mode',
    'induction_plan' => 'Choose option',
    'elite_camp' => 'Choose option',
    'student_diary' => 'Choose option',
    'ability_overall' => 'Choose rating',
    'ability_batting' => 'Choose rating',
    'ability_bowling' => 'Choose rating',
    'ability_wicketkeeping' => 'Choose rating',
    'ability_fitness' => 'Choose rating',
    'playing_role' => 'Choose playing role',
    'batting_style' => 'Choose batting style',
    'bowling_style' => 'Choose bowling style',
    'strong_hand' => 'Choose strong hand',
    'personal_coaching_days' => 'Choose coaching days',
    'personal_coaching_duration' => 'Choose coaching duration',
    'group_coaching_days' => 'Choose group coaching days',
    'fitness_plan' => 'Choose fitness plan',
    'gym_fitness_session' => 'Choose gym fitness session',
    'match_program' => 'Choose match program',
    'review_frequency' => 'Choose review frequency',
    'progress_report_frequency' => 'Choose report frequency',
    'feedback_call_frequency' => 'Choose feedback frequency',
    'meditation_sessions' => 'Choose meditation frequency',
    'elite_camp_days' => 'Choose camp days',
    'elite_camp_duration' => 'Choose camp duration',
    'welcome_kit' => 'Choose welcome kit',
    'document_type' => 'Choose document type',
    'status' => 'Choose status',
];
$placeholder = $placeholderMap[$fieldName]
    ?? ($requestedPlaceholder !== '' ? $requestedPlaceholder : 'Choose ' . strtolower(trim($label)));

$selectedValues = is_array($selected)
    ? array_map('strval', $selected)
    : ($selected === null || $selected === '' ? [] : [(string) $selected]);

$realOptionCount = 0;
foreach ($options as $option) {
    if (! is_array($option)) {
        continue;
    }

    $value = (string) ($option['value'] ?? '');
    if ($value === '' || ! empty($option['disabled']) || ! empty($option['placeholder'])) {
        continue;
    }

    $realOptionCount++;
}

$useBtSelect = ! $forceNativeSelect && ($forceBtSelect || $multiple || $realOptionCount >= 10);
$attributesHtml = '';
foreach ($extraAttributes as $attribute => $value) {
    if ($value === null || $value === false) {
        continue;
    }

    $attributesHtml .= ' ' . esc((string) $attribute);
    if ($value !== true) {
        $attributesHtml .= '="' . esc((string) $value) . '"';
    }
}

$inputName = $multiple && ! str_ends_with($name, '[]') ? $name . '[]' : $name;
$controlAttributes = $useBtSelect
    ? ' data-bt-select data-bt-select-clear data-bt-select-placeholder="' . esc($placeholder) . '" title="Select ' . esc($label) . '"'
    : '';
?>
<select class="form-control" id="<?= esc($id) ?>" name="<?= esc($inputName) ?>"<?= $controlAttributes ?><?= $attributesHtml ?><?= $multiple ? ' multiple' : '' ?>>
    <?php if (! $multiple): ?>
        <option value="" disabled<?= $selectedValues === [] ? ' selected' : '' ?>><?= esc($placeholder) ?></option>
    <?php endif; ?>
    <?php foreach ($options as $option): ?>
        <?php
        if (! is_array($option)) {
            continue;
        }

        $value = (string) ($option['value'] ?? '');
        $optionLabel = (string) ($option['label'] ?? $value);
        $isSelected = in_array($value, $selectedValues, true);
        $optionAttributes = '';
        foreach (($option['attributes'] ?? []) as $attribute => $attributeValue) {
            if ($attributeValue === null || $attributeValue === false) {
                continue;
            }

            $optionAttributes .= ' ' . esc((string) $attribute);
            if ($attributeValue !== true) {
                $optionAttributes .= '="' . esc((string) $attributeValue) . '"';
            }
        }
        ?>
        <option value="<?= esc($value) ?>"<?= $isSelected ? ' selected' : '' ?><?= ! empty($option['disabled']) ? ' disabled' : '' ?><?= $optionAttributes ?>><?= esc($optionLabel) ?></option>
    <?php endforeach; ?>
</select>
