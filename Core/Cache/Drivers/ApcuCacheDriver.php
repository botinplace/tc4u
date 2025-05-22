<?php
namespace Core\Cache\Drivers;

use RuntimeException;

class Cache {
    private $cacheKey;
    private $sourceFile;
    private $ttl;

    public function __construct(string $cacheKey, string $sourceFile, int $ttl = 3600) {
        $this->cacheKey = $cacheKey;
        $this->sourceFile = $sourceFile;
        $this->ttl = $ttl;

        if (!extension_loaded('apcu')) {
            throw new RuntimeException('APCu is required for caching.');
        }
    }

    public function load(): array {
        if (!$this->isCacheValid()) {
            $this->clear();
            return [];
        }

        $data = apcu_fetch($this->cacheKey, $success);
        return $success ? $data : [];
    }

    public function save(array $data): void {
        if (!apcu_store($this->cacheKey, $data, $this->ttl)) {
            throw new RuntimeException('Failed to save data to APCu');
        }
    }

    public function clear(): void {
        apcu_delete($this->cacheKey);
    }

    private function isCacheValid(): bool {
        // Если исходный файл изменился, кэш невалиден
        $lastModified = filemtime($this->sourceFile);
        $cacheInfo = apcu_cache_info(true);

        foreach ($cacheInfo['cache_list'] as $item) {
            if ($item['info'] === $this->cacheKey) {
                return $item['mtime'] > $lastModified;
            }
        }

        return false;
    }
}