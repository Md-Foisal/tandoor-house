<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Loads the interface strings sent to the browser: every file in the
 * locale's lang folder, keyed by file name.
 */
class Translations
{
    /**
     * @return array<string, mixed>
     */
    public static function forLocale(string $locale): array
    {
        $directory = lang_path($locale);

        if (! File::isDirectory($directory)) {
            return [];
        }

        $groups = [];

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $groups[$file->getBasename('.php')] = require $file->getPathname();
        }

        return $groups;
    }
}
