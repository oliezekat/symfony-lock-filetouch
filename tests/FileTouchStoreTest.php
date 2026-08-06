<?php

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use Symfony\Component\Lock\PersistingStoreInterface;
use Oliezekat\SymfonyLockFileTouch\Store as FileTouchStore;

final class FileTouchStoreTest extends AbstractStoreTestCase
{
    use TempDirectoryTrait;
    use ExpiringStoreTestTrait;

    /* TempDirectory */

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void
    {
        self::createTempDirectory();
    }

    /**
     * This method is called after the last test of this test class is run.
     */
    public static function tearDownAfterClass(): void
    {
        //self::deleteTempDirectory();
    }

    private function getTestTempDirectoryPath(): ?string
    {
        return $this->getTempDirectoryPath() . DIRECTORY_SEPARATOR . 'locks';
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
