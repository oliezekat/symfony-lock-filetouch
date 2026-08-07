<?php

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use Symfony\Component\Lock\PersistingStoreInterface;

abstract class AbstractFileBasedStoreTestCase extends AbstractStoreTestCase
{
    use TempDirectoryTrait;
    use FileBasedStoreTestTrait;
    use ExpiringStoreTestTrait;

    /**
     * @see AbstractStoreTestCase::createStoreInstance()
     */
    abstract protected function createStoreInstance(): PersistingStoreInterface;

    /* TempDirectoryTrait */

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
        self::deleteTempDirectory();
    }

    protected function getTestTempDirectoryPath(): ?string
    {
        return $this->getTempDirectoryPath() . DIRECTORY_SEPARATOR . 'locks';
    }
}
