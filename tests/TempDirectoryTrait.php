<?php

declare(strict_types=1);

namespace Oliezekat\SymfonyLockFileTouch\Tests;

trait TempDirectoryTrait
{
    private static ?string $testTempDirectoryPath = null;

    private static function createTempDirectory(): bool
    {
        if (self::$testTempDirectoryPath !== null) {
            return true;
        }
        $path = null;
        while (($path === null) || is_dir($path) || file_exists($path)) {
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpunit_' . md5(static::class . '::' . date('U') . rand(1000, 9999), false);
        }
        if (mkdir($path, 0777, true)) {
            self::$testTempDirectoryPath = $path;
            return true;
        }
        return false;
    }

    private function getTempDirectoryPath(): ?string
    {
        return self::$testTempDirectoryPath;
    }

    private function assertTempDirectoryDefined(): void
    {
        $this->assertTrue($this->createTempDirectory(), 'Path not null');
    }

    private function assertTempDirectoryDeleted(): void
    {
        self::deleteTempDirectory();
        $this->assertTrue($this->getTempDirectoryPath() === null, 'Path is null');
    }

    private static function deleteTempDirectory(): void
    {
        if (self::$testTempDirectoryPath === null) {
            return;
        }
        self::deleteDirectory(self::$testTempDirectoryPath);
        self::$testTempDirectoryPath = null;
    }

    private static function deleteDirectory(string $path): void
    {
        if (is_dir($path) === false) {
            return;
        }
        foreach (scandir($path, SCANDIR_SORT_NONE) as $key => $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $filepath = $path . DIRECTORY_SEPARATOR . $name;
            if (is_dir($filepath)) {
                self::deleteDirectory($filepath);
            } else {
                @unlink($filepath);
            }
        }
        @rmdir($path);
    }
}
