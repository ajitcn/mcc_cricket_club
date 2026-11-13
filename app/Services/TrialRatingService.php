<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class TrialRatingService
{
    public const FIELDS = [
        'ability_overall',
        'ability_batting',
        'ability_bowling',
        'ability_wicketkeeping',
        'ability_fitness',
    ];

    public function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value)) {
            throw new InvalidArgumentException('Invalid Trial rating.');
        }

        $value = trim((string) $value);

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(?:\s*-\s*.*)?$/', $value, $matches) === 1) {
            $value = $matches[1];
        }

        if (preg_match('/^(?:0|[1-9]\d?)(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid Trial rating.');
        }

        $numeric = (float) $value;

        if ($numeric < 0 || $numeric > 10) {
            throw new InvalidArgumentException('Invalid Trial rating.');
        }

        if (abs(($numeric * 10) - round($numeric * 10)) > 0.000001) {
            throw new InvalidArgumentException('Invalid Trial rating.');
        }

        return rtrim(rtrim(number_format($numeric, 1, '.', ''), '0'), '.');
    }

    public function category(mixed $value): ?string
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return null;
        }

        $numeric = (float) $normalized;

        return match (true) {
            $numeric <= 1.5 => 'Raw',
            $numeric <= 2.5 => 'Basic',
            $numeric <= 3.5 => 'Regular',
            $numeric <= 5.5 => 'Advanced',
            default => 'Excellent',
        };
    }

    public function numeric(mixed $value): int|float|null
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return null;
        }

        $numeric = (float) $normalized;

        return fmod($numeric, 1.0) === 0.0 ? (int) $numeric : $numeric;
    }

    /** @return array{value: string|null, category: string|null} */
    public function present(mixed $value): array
    {
        $normalized = $this->normalize($value);

        return [
            'value' => $normalized,
            'category' => $normalized === null ? null : $this->category($normalized),
        ];
    }

    public function formatted(mixed $value): string
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return 'Not rated';
        }

        return number_format((float) $normalized, 1, '.', '') . ' - ' . $this->category($normalized);
    }
}
