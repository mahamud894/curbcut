<?php

use App\Support\Messages;

if (! function_exists('tr')) {
    /**
     * Translate an English source string (a key in lang/<locale>.json) and format it
     * as an ICU message: tr('{count, plural, one {# page} other {# pages}}', ['count' => 3]).
     */
    function tr(string $key, array $values = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return Messages::format(__($key, [], $locale), $values, $locale);
    }
}

if (! function_exists('tk')) {
    /** Marks a string for translation where it is defined; translate it with tr() where it is used. */
    function tk(string $key): string
    {
        return $key;
    }
}
