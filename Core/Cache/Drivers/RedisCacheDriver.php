<?php
namespace Core\Cache\Drivers;

use Redis;
use RuntimeException;

class RedisCacheDriver implements CacheDriverInterface {
    private $redis;
    private $cacheKey;
    private $sourceFile;
    private $ttl;

    public function __construct(Redis $redis, string $cacheKey, string $sourceFile, int $ttl = 3600) {
        $this->redis = $redis;
        $this->cacheKey = $cacheKey;
        $this->sourceFile = $sourceFile;
        $this->ttl = $ttl;
    }

    public function load(): array {
        if (!$this->isCacheValid()) {
            return [];
        }

        $data = $this->redis->get($this->cacheKey);
        return $data ? unserialize($data) : [];
    }

    public function save(array $data): void {
        $this->redis->setex($this->cacheKey, $this->ttl, serialize($data));
    }

    public function clear(): void {
        $this->redis->del($this->cacheKey);
    }

    public function isCacheValid(): bool {
        $lastModified = filemtime($this->sourceFile);
        $cacheTime = $this->redis->hGet("cache_meta", $this->cacheKey);
        return $cacheTime > $lastModified;
    }
}