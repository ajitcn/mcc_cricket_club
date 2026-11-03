<?php

declare(strict_types=1);

/**
 * Repairs legacy mojibake tokens emitted by older UTF-8 view literals.
 *
 * Only tokens carrying common mojibake markers are considered, leaving normal
 * user-entered text and already-valid UTF-8 untouched.
 */
function mcc_normalize_mojibake(string $value): string
{
    if (!preg_match('/(?:Ã(?:ƒ|¢|‚|Æ)|â(?:€|‚))/u', $value)) {
        return $value;
    }

    $normalized = preg_replace_callback(
        '/(?<![\p{L}\d])(?:Ã(?:ƒ|¢|‚|Æ)|â(?:€|‚))[^\s<>"\']*/u',
        static function (array $match): string {
            $candidate = $match[0];

            for ($attempt = 0; $attempt < 4; $attempt++) {
                $decoded = mb_convert_encoding($candidate, 'Windows-1252', 'UTF-8');
                if ($decoded === $candidate || !is_string($decoded)) {
                    break;
                }
                $candidate = $decoded;
                if (!preg_match('/(?:Ã(?:ƒ|¢|‚|Æ)|â(?:€|‚))/u', $candidate)) {
                    break;
                }
            }

            return $candidate;
        },
        $value
    );

    return is_string($normalized) ? $normalized : $value;
}
