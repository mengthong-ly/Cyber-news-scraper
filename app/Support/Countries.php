<?php

namespace App\Support;

use Locale;

class Countries
{
    /**
     * @return array<string, string> ISO code => short English name, sorted by name
     */
    public static function all(): array
    {
        $names = [];

        foreach (config('cyber.countries') as $code) {
            $names[$code] = self::name($code);
        }

        asort($names);

        return $names;
    }

    public static function name(string $code): string
    {
        // intl gives e.g. "Myanmar (Burma)", "Congo - Kinshasa", "Hong Kong SAR China"; news searches want the short form.
        $name = Locale::getDisplayRegion("-{$code}", 'en');

        return trim(str_replace(' SAR China', '', preg_split('/ \(| - /', $name)[0]));
    }
}
