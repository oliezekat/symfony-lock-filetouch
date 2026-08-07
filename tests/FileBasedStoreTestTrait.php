<?php

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;

trait FileBasedStoreTestTrait
{
    /**
     * @see AbstractStoreTestCase::getStore()
     */
    abstract protected function getStore(): PersistingStoreInterface;

    /**
     * @see AbstractStoreTestCase::createStoreInstance()
     */
    abstract protected function createStoreInstance(): PersistingStoreInterface;

    /**
     * @depends testSave
     */
    public function testSaveWithIsolatedStoresOnSameResources(): void
    {
        $store1 = $this->getStore();
        $store2 = $this->createStoreInstance(); // with same settings (directory)

        $key1 = new Key(static::class . '::' . __FUNCTION__);
        $key2 = new Key(static::class . '::' . __FUNCTION__);

        $store1->save($key1);
        $this->assertTrue($store1->exists($key1));

        // 2nd store should not acquire the lock until 1st store releases it
        try {
            $store2->save($key2);
            $this->fail('The 2nd store shouldn\'t save the second key');
        } catch (LockConflictedException $e) {
        }

        $store1->delete($key1);
        $store2->save($key2);
        $this->assertFalse($store1->exists($key1));
        $this->assertTrue($store2->exists($key2));
    }
}
