<?php
namespace App\Http\Controllers;

trait LegacyWrapperTrait
{
    protected function injectLegacyBasePath(string $legacyDir, string &$html): void
    {
        $legacyDir = '/' . trim($legacyDir, '/') . '/';
        $html = '<script>window.LEGACY_BASE_PATH = ' . json_encode($legacyDir) . ';</script>' . $html;
    }
}
