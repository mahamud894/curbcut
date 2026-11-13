<?php

namespace App\Support;

/**
 * Formats the small subset of ICU MessageFormat that Curbcut's translations use:
 *   {name}                                    a value
 *   {count, plural, one {# page} other {# pages}}   plural forms, with # for the number
 *   {kind, select, a {...} other {...}}        a choice by value
 * The same strings are formatted in the browser with intl-messageformat.
 */
class Messages
{
    public static function format(string $message, array $values = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if (! str_contains($message, '{')) {
            return $message;
        }

        $out = '';
        $length = strlen($message);
        for ($i = 0; $i < $length; $i++) {
            $char = $message[$i];
            if ($char === "'" && $i + 1 < $length && $message[$i + 1] === "'") {
                $out .= "'";
                $i++;
            } elseif ($char === '{') {
                $end = self::matchingBrace($message, $i);
                $out .= self::argument(substr($message, $i + 1, $end - $i - 1), $values, $locale);
                $i = $end;
            } else {
                $out .= $char;
            }
        }

        return $out;
    }

    private static function matchingBrace(string $s, int $open): int
    {
        $depth = 0;
        for ($i = $open, $n = strlen($s); $i < $n; $i++) {
            if ($s[$i] === '{') {
                $depth++;
            } elseif ($s[$i] === '}' && --$depth === 0) {
                return $i;
            }
        }

        return strlen($s) - 1;
    }

    private static function argument(string $body, array $values, string $locale): string
    {
        $parts = array_map('trim', explode(',', $body, 3));
        $name = $parts[0];
        $value = $values[$name] ?? null;

        if (count($parts) < 3) {
            if (is_int($value) || is_float($value)) {
                return self::number($value, $locale);
            }

            return $value === null ? '{'.$name.'}' : (string) $value;
        }

        $options = self::options($parts[2]);
        if ($parts[1] === 'plural') {
            $n = (int) $value;
            $choice = $options['='.$n] ?? $options[Plural::category($locale, $n)] ?? $options['other'] ?? '';

            return str_replace('#', self::number($n, $locale), self::format($choice, $values, $locale));
        }

        return self::format($options[(string) $value] ?? $options['other'] ?? '', $values, $locale);
    }

    /** @return array<string, string> */
    private static function options(string $s): array
    {
        $options = [];
        $i = 0;
        $n = strlen($s);
        while ($i < $n) {
            while ($i < $n && ctype_space($s[$i])) {
                $i++;
            }
            $start = $i;
            while ($i < $n && $s[$i] !== '{' && ! ctype_space($s[$i])) {
                $i++;
            }
            $key = substr($s, $start, $i - $start);
            while ($i < $n && $s[$i] !== '{') {
                $i++;
            }
            if ($i >= $n) {
                break;
            }
            $end = self::matchingBrace($s, $i);
            $options[$key] = substr($s, $i + 1, $end - $i - 1);
            $i = $end + 1;
        }

        return $options;
    }

    private static function number(int|float $n, string $locale): string
    {
        if (class_exists(\NumberFormatter::class)) {
            return (string) (new \NumberFormatter($locale, \NumberFormatter::DECIMAL))->format($n);
        }

        return (string) $n;
    }
}
