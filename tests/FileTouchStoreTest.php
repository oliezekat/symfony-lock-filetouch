<?php

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use Symfony\Component\Lock\PersistingStoreInterface;
use Oliezekat\SymfonyLockFileTouch\Store as FileTouchStore;

final class FileTouchStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;

    private static ?string $testTempDirectoryPath = null;

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void
    {
        if (self::$testTempDirectoryPath !== null) {
            return;
        }
        $path = null;
        while (($path === null) || is_dir($path) || file_exists($path)) {
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_' . md5(random_bytes(32), false);
        }
        self::$testTempDirectoryPath = $path;
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$testTempDirectoryPath === null) {
            return;
        }
        if (is_dir(self::$testTempDirectoryPath) === false) {
            return;
        }
        foreach (scandir(self::$testTempDirectoryPath, SCANDIR_SORT_NONE) as $key => $filename) {
            if ($filename == '.') {
                continue;
            }
            if ($filename == '..') {
                continue;
            }
            $filepath = self::$testTempDirectoryPath . DIRECTORY_SEPARATOR . $filename;
            if (is_dir($filepath)) {
                continue;
            }
            @unlink($filepath);
        }
        @rmdir(self::$testTempDirectoryPath);
        self::$testTempDirectoryPath = null;
    }

    private function getTestTempDirectoryPath(): ?string
    {
        return self::$testTempDirectoryPath;
    }

    /* ExpiringStoreTestTrait */

    protected function getClockDelay(): int
    {
        return 250000;
    }

    /* AbstractStoreTestCase */

    protected function getStore(): PersistingStoreInterface
    {
        $store = new FileTouchStore();
        $store->setDirectoryPath($this->getTestTempDirectoryPath());
        return $store;
    }
}
