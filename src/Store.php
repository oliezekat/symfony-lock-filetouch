<?php

namespace Oliezekat\SymfonyLockFileTouch;

use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Store\ExpiringStoreTrait;

class Store implements PersistingStoreInterface
{
    use ExpiringStoreTrait;

    private ?string $locksDirPath = null;
    private array $locks = [];
    private ?int $maxLifeTime = null;

    public function __construct(?string $locksDirPath = null, int $maxLifeTime = 300)
    {
        if ($maxLifeTime < 1) {
            throw new InvalidArgumentException(\sprintf('"%s()" expects a strictly positive maximum lifetime. Got %d.', __METHOD__, $maxLifeTime));
        }
        $this->maxLifeTime = $maxLifeTime;
        if ($locksDirPath !== null) {
            $this->setLocksDirPath($locksDirPath);
        }
    }

    private function getLocksDirPath(): string
    {
        if ($this->locksDirPath === null) {
            $locksDirPath = implode(DIRECTORY_SEPARATOR, array(sys_get_temp_dir(), md5(base64_encode(random_bytes(32))) . '_locks'));
            $this->setLocksDirPath($locksDirPath);
        }
        return $this->locksDirPath;
    }

    private function setLocksDirPath(string $locksDirPath): void
    {
        if (file_exists($locksDirPath) === false) {
            mkdir($locksDirPath, 0777, true);
        }
        $this->locksDirPath = realpath($locksDirPath);
    }

    private function getKeySlug(Key $key): string
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

    private function getFilename(Key $key): string
    {
        return $this->getKeySlug($key) . '.lock';
    }

    private function getFilepath(Key $key): string
    {
        return implode(DIRECTORY_SEPARATOR, array($this->getLocksDirPath(), $this->getFilename($key)));
    }

    private function getFileModifiedTime(Key $key): ?int
    {
        $filePath = $this->getFilepath($key);
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

    private function getKeyToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__ . '::TOKEN')) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__ . '::TOKEN', $token);
        }
        return $key->getState(__CLASS__ . '::TOKEN');
    }

    /* PersistingStoreInterface */

    public function save(Key $key): void
    {
        $slug = $this->getKeySlug($key);
        $token = $this->getKeyToken($key);
        if (isset($this->locks[$slug])) {
            // already acquired
            if ($this->locks[$slug] === $token) {
                return;
            }
            throw new LockConflictedException();
        }
        $mtime = $this->getFileModifiedTime($key);
        if (($mtime !== null) && ($mtime + $this->maxLifeTime > time())) {
            throw new LockConflictedException();
        }
        $key->reduceLifetime($this->maxLifeTime);
        touch($this->getFilepath($key));
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
        if ($ttl > $this->maxLifeTime) {
            throw new InvalidTtlException(\sprintf('"%s()" expects a TTL lower or equals to maximum life time of "%s" seconds. Got "%s".', __METHOD__, $this->maxLifeTime, $ttl));
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
        if ($mtime + $this->maxLifeTime <= time() + ceil($key->getRemainingLifetime() ?? 0)) {
            touch($this->getFilepath($key));
        }
        $this->checkNotExpired($key);
    }

    public function delete(Key $key): void
    {
        $slug = $this->getKeySlug($key);
        if (isset($this->locks[$slug]) === false) {
            // not acquired
            return;
        }
        // The lock is maybe not acquired.
        if (!$key->hasState(__CLASS__ . '::TOKEN')) {
            return;
        }
        $token = $this->getKeyToken($key);
        if ($this->locks[$slug] !== $token) {
            // already acquired by another lock
            return;
        }
        $filePath = $this->getFilepath($key);
        unlink($filePath);
        unset($this->locks[$slug]);
        $key->removeState(__CLASS__ . '::TOKEN');
    }

    public function exists(Key $key): bool
    {
        $slug = $this->getKeySlug($key);
        $token = $this->getKeyToken($key);
        return ($this->locks[$slug] ?? null) === $token;
    }
}
