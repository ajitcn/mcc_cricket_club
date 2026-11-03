<?php

namespace Tests\Unit;

use App\Libraries\Storage\LocalPrivateStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;
final class PrivateStorageTest extends TestCase
{
    private string $root;
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mcc-private-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }
    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }
    public function testObjectsAreStoredOutsidePublicPathsAndCanBeRead(): void
    {
        $storage = new LocalPrivateStorage($this->root);
        $storage->store('internal/object-1.bin', 'private-content');
        self::assertTrue($storage->exists('internal/object-1.bin'));
        self::assertSame('private-content', $storage->read('internal/object-1.bin'));
        self::assertFileDoesNotExist(dirname(__DIR__,
    2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'internal' . DIRECTORY_SEPARATOR . 'object-1.bin');
    }
    public function testPathTraversalAndAbsoluteKeysAreRejected(): void
    {
        $storage = new LocalPrivateStorage($this->root);
        $this->expectException(RuntimeException::class);
        $storage->store('../outside.bin', 'blocked');
    }
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new \FilesystemIterator($directory) as $item) {
            if ($item->isDir()) {
                $this->removeDirectory($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($directory);
    }
}
