<?php

namespace Oliezekat\SymfonyLockFileTouch;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockStorageException;

trait LifeTimeStoreTrait
{
    private ?float $lifeTime = null;
    private static float $DISABLED_LIFETIME = -1;

    public function setLifeTime(float $lifeTime): self
    {
        if ($this->lifeTime !== null) {
            return $this;
        }
        if ($lifeTime <= 0) {
            throw new InvalidArgumentException(\sprintf('"%s()" expects a strictly positive life time. Got "%02.1F".', __METHOD__, $lifeTime));
        }
        $this->lifeTime = $lifeTime;
        return $this;
    }

    public function disableLifeTime(): self
    {
        if ($this->lifeTime !== null) {
            return $this;
        }
        $this->lifeTime = self::$DISABLED_LIFETIME;
        return $this;
    }

    private function hasLifeTime(): bool
    {
        if ($this->lifeTime === null) {
            $this->lifeTime = self::$DISABLED_LIFETIME;
        }
        return (($this->lifeTime !== null) && ($this->lifeTime > 0));
    }

    private function getLifeTime(): float
    {
        if ($this->lifeTime === null) {
            $this->lifeTime = self::$DISABLED_LIFETIME;
        }
        if ($this->lifeTime <= 0) {
            throw new LockStorageException(\sprintf('"%s()" disabled life time.', __METHOD__));
        }
        return $this->lifeTime;
    }
}
