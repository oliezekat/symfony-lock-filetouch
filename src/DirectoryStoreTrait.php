<?php

namespace Oliezekat\SymfonyLockFileTouch;

use Symfony\Component\Lock\Exception\InvalidArgumentException;

trait DirectoryStoreTrait
{
    private ?string $directoryPath = null;

    abstract protected function defineDefaultDirectoryPath(): string;

    private function getDirectoryPath(): string
    {
        if ($this->directoryPath === null) {
            // Set temporary directory
            $directoryPath = $this->defineDefaultDirectoryPath();
            $this->setDirectoryPath($directoryPath);
        }
        return $this->directoryPath;
    }

    public function setDirectoryPath(string $path): self
    {
        if ($this->directoryPath !== null) {
            return $this;
        }
        if (is_dir($path) === false) {
            if ((@mkdir($path, 0777, true) === false) && !is_dir($path)) {
                throw new InvalidArgumentException(\sprintf('The directory "%s" does not exists and cannot be created.', basename($path)));
            }
        }
        $directoryPath = realpath($path);
        if ($directoryPath === false) {
            throw new InvalidArgumentException(\sprintf('The directory "%s" cannot be used.', basename($path)));
        }
        if (!is_writable($directoryPath)) {
            throw new InvalidArgumentException(\sprintf('The directory "%s" is not writable.', basename($directoryPath)));
        }
        $this->directoryPath = $directoryPath;
        return $this;
    }
}
