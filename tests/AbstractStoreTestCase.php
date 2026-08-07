<?php

/**
 * Copy fixed of
 * Symfony\Component\Lock\Test\AbstractStoreTestCase
 * Test/AbstractStoreTestCase.php
 */

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;

abstract class AbstractStoreTestCase extends TestCase
{
    private ?PersistingStoreInterface $store = null;

    abstract protected function createStoreInstance(): PersistingStoreInterface;

    final protected function getStore(): PersistingStoreInterface
    {
        if ($this->store === null) {
            $this->store = $this->createStoreInstance();
        }
        return $this->store;
    }

    public function testSave(): void
    {
        $store = $this->getStore();

        $key = new Key(static::class . '::' . __FUNCTION__);

        $this->assertFalse($store->exists($key));
        $store->save($key);
        $this->assertTrue($store->exists($key));
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    /**
     * @depends testSave
     */
    public function testSaveWithDifferentResources(): void
    {
        $store = $this->getStore();

        $key1 = new Key(static::class . '::' . __FUNCTION__ . '1');
        $key2 = new Key(static::class . '::' . __FUNCTION__ . '2');

        $store->save($key1);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->save($key2);
        $this->assertTrue($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key2);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));
    }

    /**
     * @depends testSave
     */
    public function testSaveWithDifferentKeysOnSameResources(): void
    {
        $store = $this->getStore();

        $key1 = new Key(static::class . '::' . __FUNCTION__);
        $key2 = new Key(static::class . '::' . __FUNCTION__);

        $store->save($key1);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        try {
            $store->save($key2);
            $this->fail('The store shouldn\'t save the second key');
        } catch (LockConflictedException $e) {
        }

        // The failure of previous attempt should not impact the state of current locks
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->delete($key1);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->save($key2);
        $this->assertFalse($store->exists($key1));
        $this->assertTrue($store->exists($key2));

        $store->delete($key2);
        $this->assertFalse($store->exists($key1));
        $this->assertFalse($store->exists($key2));
    }

    /**
     * @depends testSave
     */
    public function testSaveTwice(): void
    {
        $store = $this->getStore();

        $key = new Key(static::class . '::' . __FUNCTION__);

        $store->save($key);
        $store->save($key);
        // just asserts it don't throw an exception
        $this->addToAssertionCount(1);

        $store->delete($key);
    }

    /**
     * @depends testSave
     */
    public function testDeleteIsolated(): void
    {
        $store = $this->getStore();

        $key1 = new Key(static::class . '::' . __FUNCTION__ . '1');
        $key2 = new Key(static::class . '::' . __FUNCTION__ . '2');

        $store->save($key1);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));

        $store->delete($key2);
        $this->assertTrue($store->exists($key1));
        $this->assertFalse($store->exists($key2));
    }
}
