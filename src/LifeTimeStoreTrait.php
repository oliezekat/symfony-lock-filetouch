<?php

namespace Oliezekat\SymfonyLockFileTouch;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockStorageException;

trait LifeTimeStoreTrait
{
    private ?int $lifeTime = null;

    public function setLifeTime(int $lifeTime): self
    {
        if ($this->lifeTime !== null) {
            return $this;
        }
        if ($lifeTime < 1) {
            throw new InvalidArgumentException(\sprintf('"%s()" expects a strictly positive lifetime. Got %d.', __METHOD__, $lifeTime));
        }
        $this->lifeTime = $lifeTime;
        return $this;
    }

    public function disableLifeTime(): self
    {
        if ($this->lifeTime !== null) {
            return $this;
        }
        $this->lifeTime = -1;
        return $this;
    }

    private function hasLifeTime(): bool
    {
        if ($this->lifeTime === null) {
            $this->lifeTime = -1;
        }
        return (($this->lifeTime !== null) && ($this->lifeTime >= 1));
    }

    private function getLifeTime(): int
    {
        if ($this->lifeTime === null) {
            $this->lifeTime = -1;
        }
        if ($this->lifeTime < 1) {
            throw new LockStorageException(\sprintf('"%s()" disabled lifetime.', __METHOD__));
        }
        return $this->lifeTime;
    }
}
