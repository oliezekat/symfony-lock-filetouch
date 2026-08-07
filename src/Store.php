<?php

namespace Oliezekat\SymfonyLockFileTouch;

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\ExpiringStoreTrait;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockStorageException;

class Store implements PersistingStoreInterface
{
    use DirectoryStoreTrait;
    use LifeTimeStoreTrait;
    use SlugStoreTrait;
    use TokenStoreTrait;
    use ExpiringStoreTrait;

    private array $locks = [];
    private ?string $fileExtension = null;
    protected int $maxFileNameLength = 256;

    public function __construct(?string $directoryPath = null, ?float $lifeTime = null)
    {
        if ($directoryPath !== null) {
            $this->setDirectoryPath($directoryPath);
        }
        if ($lifeTime !== null) {
            $this->setLifeTime($lifeTime);
        }
    }

    protected function defineDefaultDirectoryPath(): string
    {
        return implode(DIRECTORY_SEPARATOR, array(sys_get_temp_dir(), str_replace('\\', '-', static::class)));
    }

    protected function defineDefaultFileExtension(): string
    {
        return 'lock';
    }

    public function setFileExtension(string $extension = ''): self
    {
        if ($this->fileExtension !== null) {
            return $this;
        }
        $extension = str_replace(['.', '*', ' '], '', $extension);
        $extension = trim($extension);
        $this->fileExtension = $extension;
        return $this;
    }

    private function getFileExtension(): string
    {
        if ($this->fileExtension === null) {
            $this->fileExtension = $this->defineDefaultFileExtension();
        }
        return $this->fileExtension;
    }

    private function getFileNameStateKey(): string
    {
        return static::class . '::FILENAME';
    }

    private function generateFileName(Key $key): string
    {
        $slug = $this->generateSlugAlphaNumDashed($key);
        $extension = (empty($this->getFileExtension()) ? '' : '.' . $this->getFileExtension());
        $fileName = $slug . $extension;
        return $fileName;
    }

    private function getFileName(Key $key): string
    {
        $stateKey = $this->getFileNameStateKey();
        if (!$key->hasState($stateKey)) {
            $fileName = $this->generateFileName($key);
            if (strlen($fileName) > $this->maxFileNameLength) {
                throw new LockStorageException(\sprintf('"%s()" Lock key is too long.', __METHOD__));
            }
            $key->setState($stateKey, $fileName);
        }
        return $key->getState($stateKey);
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
        $fileName = $this->getFileName($key);
        $token = $this->getToken($key);
        if (isset($this->locks[$fileName])) {
            // already acquired
            if ($this->locks[$fileName] === $token) {
                return;
            }
            throw new LockConflictedException();
        }
        $mtime = $this->getFileModifiedTime($key);
        if ($mtime !== null) {
            if (($this->hasLifeTime() === false) || ($mtime + $this->getLifeTime() > microtime(true))) {
                throw new LockConflictedException();
            }
        }
        if ($this->hasLifeTime()) {
            $key->reduceLifetime($this->getLifeTime());
        }
        $this->touchFile($key);
        $this->locks[$fileName] = $token;
        $key->markUnserializable();
        $this->checkNotExpired($key);
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        if ($this->hasLifeTime() && ($ttl > $this->getLifeTime())) {
            throw new InvalidTtlException(\sprintf('"%s()" expects a TTL lower or equals to life time of "%02.1F" seconds. Got "%02.1F".', __METHOD__, $this->getLifeTime(), $ttl));
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
        $this->checkNotExpired($key);
        if ($this->hasLifeTime() && ($mtime + $this->getLifeTime() <= microtime(true) + ($key->getRemainingLifetime() ?? 0))) {
            $this->touchFile($key);
        }
    }

    public function delete(Key $key): void
    {
        $fileName = $this->getFileName($key);
        if (isset($this->locks[$fileName]) === false) {
            // not acquired
            return;
        }
        // The lock is maybe not acquired.
        if ($this->hasToken($key) === false) {
            return;
        }
        $token = $this->getToken($key);
        if ($this->locks[$fileName] !== $token) {
            // already acquired by another lock
            return;
        }
        $this->deleteFile($key);
        unset($this->locks[$fileName]);
        $this->removeToken($key);
    }

    public function exists(Key $key): bool
    {
        $fileName = $this->getFileName($key);
        if (isset($this->locks[$fileName]) === false) {
            return false;
        }
        $token = $this->getToken($key);
        if ($this->locks[$fileName] !== $token) {
            return false;
        }
        if ($key->isExpired()) {
            // if it has expired
            return false;
        }
        return true;
    }
}
