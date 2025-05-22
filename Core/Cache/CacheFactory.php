<?php
namespace Core\Cache;


use Core\Config\Config; // Правильный Config
use Core\Cache\Drivers\{FileCacheDriver, ApcuCacheDriver, RedisCacheDriver};
use Core\Cache\Drivers\CacheDriverInterface; // Интерфейс
use RuntimeException;
use Redis;

class CacheFactory {
    public static function create(Config $config, string $sourceFile): CacheDriverInterface {
        $driver = $config::get('default') ?? 'file';
        $prefix = $config::get('prefix') ?? 'app_cache';

        $cacheKey = $prefix . '_' . md5($sourceFile);

        switch ($driver) {
            case 'file':
                $path = $config::get('stores.file.path') ?? APP_DIR.'/cache';
                $cacheFile = $path . '/' . $cacheKey . '.php';
                return new FileCacheDriver($cacheFile, $sourceFile);
            
            case 'apcu':
                return new ApcuCacheDriver($cacheKey, $sourceFile);
            
            case 'redis':
                $redis = new Redis();
                $redis->connect(
                    $config::get('stores.redis.host') ?? '127.0.0.1',
                    $config::get('stores.redis.port') ?? 6379
                );
                return new RedisCacheDriver($redis, $cacheKey, $sourceFile);
            
            default:
                throw new RuntimeException("Unsupported cache driver: $driver");
        }
    }
}