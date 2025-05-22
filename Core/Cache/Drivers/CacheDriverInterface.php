<?php
namespace Core\Cache\Drivers;

interface CacheDriverInterface {
    public function load(): array;
    public function save(array $data): void;
    public function clear(): void;
    public function isCacheValid(): bool;
}