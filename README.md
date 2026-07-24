# Symfony Lock FileTouch

A storage implementation for [Symfony Lock](https://github.com/symfony/lock) component, based on the *PersistingStoreInterface* which **uses touch(), file_exists(), or filemtime() on empty files within a dedicated directory**.

It supports locking, blocking mode, and auto-release across different applications, provided they use the same directory and the same maximum lifetime.

Lock refresh() requires a TTL that is less than or equal to the maximum lifetime.

## Installation

```bash
composer require oliezekat/symfony-lock-filetouch
```

## Usage

```php
use Oliezekat\SymfonyLockFileTouch\Store;
use Symfony\Component\Lock\Factory;

$store = new Store(/* $directoryPath, $lifeTime */);
$store->setDirectoryPath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test');
$store->setLifeTime(300);
$factory = new Factory($store);

$lock = $factory->createLock('test');
if ($lock->acquire()) {
    ...
    $lock->refresh();
}
...
$lock->release();
```

## Resources

 * [Symfony Lock documentation](https://symfony.com/doc/current/components/lock.html)
 * [Symfony Lock 6.4 code](https://github.com/symfony/lock/tree/6.4)

