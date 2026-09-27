<?php

namespace App\Support;

use App\Models\Translation;
use Illuminate\Translation\FileLoader;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Loads lang/{locale}.json as usual, then applies translations edited by admins
 * (Settings > Languages > Translate), which are stored in the database so they
 * survive deployments.
 */
class DatabaseTranslationLoader extends FileLoader
{
    public function load($locale, $group, $namespace = null)
    {
        $lines = parent::load($locale, $group, $namespace);

        if ($group !== '*' || ($namespace !== null && $namespace !== '*')) {
            return $lines;
        }

        try {
            if (! Schema::hasTable('translations')) {
                return $lines;
            }

            return array_merge($lines, Translation::linesFor($locale));
        } catch (Throwable) {
            return $lines;
        }
    }
}
