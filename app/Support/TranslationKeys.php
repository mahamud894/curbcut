<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Finds every translatable string in Curbcut: t()/tk()/tRich() calls in the React code,
 * tr()/tk()/__() calls in PHP and Blade, and the WCAG, conformance and dismiss-reason
 * labels. These English strings are the keys of lang/<locale>.json.
 */
class TranslationKeys
{
    /** @return list<string> */
    public static function all(): array
    {
        $keys = [];

        foreach (File::allFiles(resource_path('js')) as $file) {
            if (in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                $keys = [...$keys, ...self::calls($file->getContents(), '(?:t|tk|tRich|rich)')];
            }
        }

        foreach ([app_path(), resource_path('views'), base_path('routes')] as $dir) {
            foreach (File::allFiles($dir) as $file) {
                if (str_ends_with($file->getFilename(), '.php')) {
                    $keys = [...$keys, ...self::calls($file->getContents(), '(?:tr|tk|__)')];
                }
            }
        }

        // Labels kept in PHP constants and data.
        $wcag = File::get(app_path('Compliance/Wcag.php'));
        preg_match_all("/'\\d\\.\\d+\\.\\d+' => \\['([^']+)'/", $wcag, $m);
        $keys = [...$keys, ...$m[1]];
        preg_match_all("/'\\d' => '([^']+)'/", $wcag, $m);
        $keys = [...$keys, ...$m[1]];
        foreach ([app_path('Compliance/Conformance.php'), app_path('Models/Issue.php')] as $path) {
            preg_match_all("/'[a-z_]+' => (?:'([^']+)'|\"([^\"]+)\")/", File::get($path), $m);
            $keys = [...$keys, ...array_filter([...$m[1], ...$m[2]], fn ($k) => $k !== '' && preg_match('/^[A-Z]/', $k))];
        }

        $keys = array_values(array_unique(array_filter($keys, fn (string $k) => trim($k) !== ''
            && ! preg_match('/^[a-z_]+\.[a-z_.]+$/', $k)  // Laravel group keys such as passwords.sent
            && preg_match('/\p{L}/u', $k))));
        sort($keys);

        return $keys;
    }

    /** @return list<string> */
    private static function calls(string $source, string $functions): array
    {
        preg_match_all('/(?<![\w.$])'.$functions.'\(\s*(?:\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\])*)")/s', $source, $m, PREG_SET_ORDER);

        return array_map(fn ($match) => stripcslashes(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '')), $m);
    }
}
