<?php

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use Oliezekat\SymfonyLockFileTouch\Store as FileTouchStore;

final class StoreLifeTimedCaseTest extends AbstractFileBasedStoreTestCase
{
    use LifeTimedStoreTestTrait;

    /* ExpiringStoreTestTrait */

    protected function getClockDelay(): int
    {
        return 550000;
    }

    /* AbstractStoreTestCase */

    protected function createStoreInstance(): FileTouchStore
    {
        $clockDelay = $this->getClockDelay();
        $store = new FileTouchStore();
        $store->setDirectoryPath($this->getTestTempDirectoryPath());
        $store->setLifeTime(4 * $clockDelay / 1000000);
        return $store;
    }
}
