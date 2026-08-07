<?php

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use Oliezekat\SymfonyLockFileTouch\Store as FileTouchStore;

final class StoreDefaultCaseTest extends AbstractFileBasedStoreTestCase
{
    /* ExpiringStoreTestTrait */

    protected function getClockDelay(): int
    {
        return 250000;
    }

    /* AbstractStoreTestCase */

    protected function createStoreInstance(): FileTouchStore
    {
        $store = new FileTouchStore();
        $store->setDirectoryPath($this->getTestTempDirectoryPath());
        return $store;
    }
}
