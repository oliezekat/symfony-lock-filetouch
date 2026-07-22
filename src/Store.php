<?php

namespace Oliezekat\SymfonyLockFileTouch;

use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Store\ExpiringStoreTrait;

class Store implements PersistingStoreInterface
{
    use DirectoryStoreTrait;
    use LifeTimeStoreTrait;
    use TokenStoreTrait;
    use ExpiringStoreTrait;

    private array $locks = [];

    public function __construct(?string $directoryPath = null, ?int $lifeTime = null)
    {
        if ($directoryPath !== null) {
            $this->setDirectoryPath($directoryPath);
        }
        if ($lifeTime !== null) {
            $this->setLifeTime($lifeTime);
        }
    }

    private function getSlug(Key $key): string
    {
        if (!$key->hasState(__CLASS__ . '::SLUG')) {
            $slug = (string) $key;
            $slug = str_replace(
                array(
                    '://',
                    ":\\",
                    DIRECTORY_SEPARATOR,
                    '/',
                    "\\",
                    "'",
                    ':',
                    '*',
                    '?',
                    '"',
                    '<',
                    '>',
                    '|',
                    '.',
                    '&',
                    '=',
                    '_',
                    '  ',
                    ),
                ' ',
                $slug
            );
            $slug = trim($slug);
            $slug = strtolower($slug);
            $slug = str_replace(' ', '-', $slug);
            // todo check valid filename & filepath length
            $key->setState(__CLASS__ . '::SLUG', $slug);
        }
        return $key->getState(__CLASS__ . '::SLUG');
    }

    private function getFileName(Key $key): string
    {
        return $this->getSlug($key) . '.lock';
    }

    private function getFilePath(Key $key): string
    {
        return implode(DIRECTORY_SEPARATOR, array($this->getDirectoryPath(), $this->getFileName($key)));
    }

    private function getFileModifiedTime(Key $key): ?int
    {
        $filePath = $this->getFilePath($key);
        clearstatcache(true, $filePath);
        if (file_exists($filePath) === false) {
            return null;
        }
        $mtime = filemtime($filePath);
        if ($mtime === false) {
            return null;
        }
        return $mtime;
    }

    private function touchFile(Key $key): bool
    {
        $filePath = $this->getFilePath($key);
        $result = @touch($filePath);
        if ($result === false) {
            throw new LockStorageException(\sprintf('"%s()" failed to touch the lock file "%s".', __METHOD__, basename($filePath)));
        }
        return $result;
    }

    private function deleteFile(Key $key): bool
    {
        $filePath = $this->getFilePath($key);
        $result = @unlink($filePath);
        if (($result === false) && (file_exists($filePath) === true)) {
            throw new LockStorageException(\sprintf('"%s()" failed to delete the lock file "%s".', __METHOD__, basename($filePath)));
        }
        return $result;
    }

    /* PersistingStoreInterface */

    public function save(Key $key): void
    {
        $slug = $this->getSlug($key);
        $token = $this->getToken($key);
        if (isset($this->locks[$slug])) {
            // already acquired
            if ($this->locks[$slug] === $token) {
                return;
            }
            throw new LockConflictedException();
        }
        $mtime = $this->getFileModifiedTime($key);
        if ($mtime !== null) {
            if (($this->hasLifeTime() === false) || ($mtime + $this->getLifeTime() > time())) {
                throw new LockConflictedException();
            }
        }
        if ($this->hasLifeTime()) {
            $key->reduceLifetime($this->getLifeTime());
        }
        $this->touchFile($key);
        $this->locks[$slug] = $token;
        $this->checkNotExpired($key);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        if ($ttl < 1) {
            throw new InvalidTtlException(\sprintf('"%s()" expects a TTL greater or equals to 1 second. Got "%s".', __METHOD__, $ttl));
        }
        // Interface defines a float value but Store required an integer.
        $ttl = (int) ceil($ttl);
        if ($this->hasLifeTime() && ($ttl > $this->getLifeTime())) {
            throw new InvalidTtlException(\sprintf('"%s()" expects a TTL lower or equals to maximum life time of "%s" seconds. Got "%s".', __METHOD__, $this->getLifeTime(), $ttl));
        }
        // We have to call exists to know if we are the owner
        if (!$this->exists($key)) {
            throw new LockConflictedException();
        }
        $mtime = $this->getFileModifiedTime($key);
        if ($mtime === null) {
            // Something deleted the lock file, we are not the owner anymore
            throw new LockConflictedException();
        }
        $key->reduceLifetime($ttl);
        if ($this->hasLifeTime() && ($mtime + $this->getLifeTime() <= time() + ceil($key->getRemainingLifetime() ?? 0))) {
            $this->touchFile($key);
        }
        $this->checkNotExpired($key);
    }

    public function delete(Key $key): void
    {
        $slug = $this->getSlug($key);
        if (isset($this->locks[$slug]) === false) {
            // not acquired
            return;
        }
        // The lock is maybe not acquired.
        if ($this->hasToken($key) === false) {
            return;
        }
        $token = $this->getToken($key);
        if ($this->locks[$slug] !== $token) {
            // already acquired by another lock
            return;
        }
        $this->deleteFile($key);
        unset($this->locks[$slug]);
        $this->removeToken($key);
    }

    public function exists(Key $key): bool
    {
        $slug = $this->getSlug($key);
        $token = $this->getToken($key);
        return ($this->locks[$slug] ?? null) === $token;
    }
}
