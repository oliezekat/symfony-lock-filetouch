<?php

namespace Oliezekat\SymfonyLockFileTouch;

use Symfony\Component\Lock\Key;

trait TokenStoreTrait
{
    private function getTokenStateKey(Key $key): string
    {
        return __CLASS__ . '::TOKEN';
    }

    private function generateUniqueToken(): string
    {
        return base64_encode(random_bytes(32));
    }

    private function getToken(Key $key): string
    {
        $stateKey = $this->getTokenStateKey($key);
        if (!$key->hasState($stateKey)) {
            $token = $this->generateUniqueToken();
            $key->setState($stateKey, $token);
        }
        return $key->getState($stateKey);
    }

    private function hasToken(Key $key): bool
    {
        return $key->hasState($this->getTokenStateKey($key));
    }

    private function removeToken(Key $key): void
    {
        $key->removeState($this->getTokenStateKey($key));
    }
}
