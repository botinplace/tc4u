<?php
namespace Core;

use Core\Cache\CacheFactory;
use Core\Cache\Drivers\CacheDriverInterface;

class CacheNew {
    private $driver;
    private $sourceFile;

    public function __construct(array $config, string $sourceFile) {
        $this->driver = CacheFactory::create($config, $sourceFile);
        $this->sourceFile = $sourceFile;
    }

    public function load(): array {
        return $this->driver->load();
    }

    public function save(array $data): void {
        $this->driver->save($data);
    }

    public function clear(): void {
        $this->driver->clear();
    }
}