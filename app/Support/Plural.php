<?php

namespace App\Support;

/**
 * CLDR plural categories for whole numbers in Curbcut's languages, so PHP picks
 * the same form as Intl.PluralRules does in the browser.
 */
class Plural
{
    public static function category(string $locale, int $n): string
    {
        $n = abs($n);
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        return match (substr($locale, 0, 2)) {
            'fr', 'pt' => $n <= 1 ? 'one' : 'other',
            'bn' => $n <= 1 ? 'one' : 'other',
            'ga' => match (true) {
                $n === 1 => 'one',
                $n === 2 => 'two',
                $n >= 3 && $n <= 6 => 'few',
                $n >= 7 && $n <= 10 => 'many',
                default => 'other',
            },
            'mt' => match (true) {
                $n === 1 => 'one',
                $n === 2 => 'two',
                $n === 0 || ($mod100 >= 3 && $mod100 <= 10) => 'few',
                $mod100 >= 11 && $mod100 <= 19 => 'many',
                default => 'other',
            },
            'ro' => match (true) {
                $n === 1 => 'one',
                $n === 0 || ($mod100 >= 2 && $mod100 <= 19) => 'few',
                default => 'other',
            },
            'lv' => match (true) {
                $mod10 === 0 || ($mod100 >= 11 && $mod100 <= 19) => 'zero',
                $mod10 === 1 && $mod100 !== 11 => 'one',
                default => 'other',
            },
            'lt' => match (true) {
                $mod10 === 1 && ($mod100 < 11 || $mod100 > 19) => 'one',
                $mod10 >= 2 && ($mod100 < 11 || $mod100 > 19) => 'few',
                default => 'other',
            },
            'pl' => match (true) {
                $n === 1 => 'one',
                $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'few',
                default => 'many',
            },
            'ru' => match (true) {
                $mod10 === 1 && $mod100 !== 11 => 'one',
                $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'few',
                default => 'many',
            },
            'hr' => match (true) {
                $mod10 === 1 && $mod100 !== 11 => 'one',
                $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'few',
                default => 'other',
            },
            'cs', 'sk' => match (true) {
                $n === 1 => 'one',
                $n >= 2 && $n <= 4 => 'few',
                default => 'other',
            },
            'sl' => match (true) {
                $mod100 === 1 => 'one',
                $mod100 === 2 => 'two',
                $mod100 === 3 || $mod100 === 4 => 'few',
                default => 'other',
            },
            default => $n === 1 ? 'one' : 'other',
        };
    }
}
