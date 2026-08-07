<?php

/**
 * Copy fixed of
 * Symfony\Component\Lock\Tests\Store\ExpiringStoreTestTrait
 * Tests/Store/ExpiringStoreTestTrait.php
 */

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Exception\LockExpiredException;

trait ExpiringStoreTestTrait
{
    /**
     * Amount of microseconds used as a delay to test expiration. Should be
     * small enough not to slow the test suite too much, and high enough not to
     * fail because of race conditions.
     */
    abstract protected function getClockDelay(): int;

    /**
     * @see AbstractStoreTestCase::getStore()
     */
    abstract protected function getStore(): PersistingStoreInterface;

    /**
     * Tests the store automatically delete the key when it expire.
     *
     * This test is time-sensitive: the $clockDelay could be adjusted.
     * @depends testSave
     */
    public function testExpiration(): void
    {
        $key = new Key(static::class . '::' . __FUNCTION__);
        $clockDelay = $this->getClockDelay();

        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 2 * $clockDelay / 1000000);
        $this->assertTrue($store->exists($key));

        usleep(3 * $clockDelay);
        $this->assertFalse($store->exists($key));
    }

    /**
     * Tests the store thrown exception when TTL expires.
     * @depends testExpiration
     */
    public function testAbortAfterExpiration(): void
    {
        $this->expectException(LockExpiredException::class);
        $key = new Key(static::class . '::' . __FUNCTION__);

        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 1 / 1000000);
    }

    /**
     * Tests the refresh can push the limits to the expiration.
     *
     * This test is time-sensitive: the $clockDelay could be adjusted.
     * @depends testExpiration
     */
    public function testRefreshLock(): void
    {
        $clockDelay = $this->getClockDelay();

        $key = new Key(static::class . '::' . __FUNCTION__);

        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 2 * $clockDelay / 1000000);
        $this->assertTrue($store->exists($key));

        usleep(3 * $clockDelay);
        $this->assertFalse($store->exists($key));
    }

    /**
     * @depends testExpiration
     */
    public function testSetExpiration(): void
    {
        $key = new Key(static::class . '::' . __FUNCTION__);

        $store = $this->getStore();

        $store->save($key);
        $store->putOffExpiration($key, 1);
        $this->assertGreaterThanOrEqual(0, $key->getRemainingLifetime());
        $this->assertLessThanOrEqual(1, $key->getRemainingLifetime());
    }

    /**
     * @depends testSave
     */
    public function testExpiredLockCleaned(): void
    {
        $key1 = new Key(static::class . '::' . __FUNCTION__);
        $key2 = new Key(static::class . '::' . __FUNCTION__);

        $store = $this->getStore();
        $key1->reduceLifetime(0);

        $this->assertTrue($key1->isExpired());
        try {
            $store->save($key1);
            $this->fail('The store shouldn\'t have save an expired key');
        } catch (LockExpiredException $e) {
        }

        $this->assertFalse($store->exists($key1));

        $store->save($key2);
        $this->assertTrue($store->exists($key2));
    }
}
