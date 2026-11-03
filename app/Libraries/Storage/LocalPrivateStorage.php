<?php

declare(strict_types=1);

namespace App\Libraries\Storage;

use RuntimeException;

final class LocalPrivateStorage implements PrivateStorageInterface
{
    public function __construct(private readonly string $root)
    {
        if ($this->root === '' || str_contains($this->root, "\0")) {
            throw new RuntimeException('A private storage root is required.');
        }

        $publicRoot = realpath(FCPATH);
        $storageRoot = $this->normalise($this->root);

        if ($publicRoot !== false) {
            $publicRoot = $this->normalise($publicRoot);

            if ($storageRoot === $publicRoot
                || str_starts_with($storageRoot, $publicRoot . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Private storage cannot be inside the public web root.');
            }
        }
    }

    public function store(string $key, string $contents): void
    {
        $path = $this->pathFor($key);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create private storage directory.');
        }

        $temporary = tempnam($directory, '.mcc-');
        if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write private storage object.');
        }

        chmod($temporary, 0600);
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to commit private storage object.');
        }
    }

    public function read(string $key): string
    {
        $path = $this->pathFor($key);
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Private storage object is unavailable.');
        }

        return $contents;
    }

    public function exists(string $key): bool
    {
        return is_file($this->pathFor($key));
    }

    public function delete(string $key): void
    {
        $path = $this->pathFor($key);

        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('Unable to remove private storage object.');
        }
    }

    private function pathFor(string $key): string
    {
        if ($key === '' || str_contains($key, "\0") || str_contains($key, '\\')
            || preg_match('#(^|/)\.\.?(/|$)#', $key) === 1
            || preg_match('#^[A-Za-z]:#', $key) === 1
            || str_starts_with($key, '/')) {
            throw new RuntimeException('Invalid private storage key.');
        }

        $root = rtrim($this->root, DIRECTORY_SEPARATOR);
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
        $resolvedRoot = realpath($root);
        $resolvedParent = realpath(dirname($path));

        if ($resolvedRoot !== false && $resolvedParent !== false
            && ! str_starts_with(strtolower($resolvedParent . DIRECTORY_SEPARATOR), strtolower($resolvedRoot . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Private storage key escapes storage root.');
        }

        return $path;
    }

    private function normalise(string $path): string
    {
        return strtolower(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
    }
}
