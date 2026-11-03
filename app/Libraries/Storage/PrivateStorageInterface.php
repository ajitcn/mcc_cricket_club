<?php

namespace App\Libraries\Storage;

interface PrivateStorageInterface
{
    public function store(string $key, string $contents): void;

    public function read(string $key): string;

    public function exists(string $key): bool;

    public function delete(string $key): void;
}
