<?php

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockConflictedException;

trait LifeTimedStoreTestTrait
{
    /**
     * @see ExpiringStoreTestTrait::getClockDelay()
     */
    abstract protected function getClockDelay(): int;

    /**
     * @see AbstractStoreTestCase::getStore()
     */
    abstract protected function getStore(): PersistingStoreInterface;

    /**
     * @see AbstractStoreTestCase::createStoreInstance()
     */
    abstract protected function createStoreInstance(): PersistingStoreInterface;

    /**
     * Tests the store thrown exception when put off invalid expiration (TTL).
     * @depends testExpiration
     */
    public function testPutOffInvalidExpiration(): void
    {
        $this->expectException(InvalidTtlException::class);
        $clockDelay = $this->getClockDelay();
        $key = new Key(static::class . '::' . __FUNCTION__);

        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, ((4 * $clockDelay) + 1) / 1000000);
    }

    /**
     *
     * @depends testSave
     */
    public function testSaveAfterStoreLifeTimeOnSameResources(): void
    {
        $clockDelay = $this->getClockDelay();
        $store1 = $this->getStore();
        $store2 = $this->createStoreInstance(); // with same settings (life time)

        $key1 = new Key(static::class . '::' . __FUNCTION__);
        $key2 = new Key(static::class . '::' . __FUNCTION__);

        $store1->save($key1);
        $this->assertTrue($store1->exists($key1));

        try {
            $store2->save($key2);
            $this->fail('The 2nd store shouldn\'t save the second key');
        } catch (LockConflictedException $e) {
        }

        usleep((4 * $clockDelay) + 1);

        $store2->save($key2);
        $this->assertTrue($store2->exists($key2));
        $this->assertFalse($store1->exists($key1));
    }
}
