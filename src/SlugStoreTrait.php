<?php

namespace Oliezekat\SymfonyLockFileTouch;

use Symfony\Component\Lock\Key;

trait SlugStoreTrait
{
    private function generateSlugAlphaNumDashed(Key $key): string
    {
        // Alphanumeric dashed lowercase
        $slug = (string) $key;
        $slug = str_replace(
            array(
                '://',
                ":\\",
                DIRECTORY_SEPARATOR,
                '/',
                "\\",
                ),
            ' ',
            $slug
        );
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug);
        $slug = strtolower($slug);
        $slug = str_replace(' ', '-', $slug);
        return $slug;
    }

    private function generateSlugTrunkedWithSha256(Key $key): string
    {
        // trunked key with trunked sha256 hash
        // from Symfony\Component\Lock\Store\FlockStore::lock()
        return \sprintf(
            'sf.%s.%s',
            substr(preg_replace('/[^a-z0-9\._-]+/i', '-', $key), 0, 50),
            strtr(substr(base64_encode(hash('sha256', $key, true)), 0, 7), '/', '_')
        );
    }
}
