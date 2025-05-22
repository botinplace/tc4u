<?php
namespace Core\Cache\Drivers;

use Core\Cache\Drivers\CacheDriverInterface;
use RuntimeException;


class FileCacheDriver implements CacheDriverInterface {
    private $cacheFile;
    private $sourceFile;
	private $cacheDir;
    private $permissions;

    public function __construct(string $cacheFile, string $sourceFile, int $permissions = 0750) {
        $this->cacheFile = $cacheFile;
        $this->sourceFile = $sourceFile;
		$this->cacheDir = dirname($cacheFile);
        $this->permissions = $permissions;
    }

    public function load(): array {
        if (!$this->isCacheValid() || !file_exists($this->cacheFile)) {
			$this->clear();
            return [];
        }

        try {
			error_log($this->cacheFile);
            return require $this->cacheFile;
        } catch (\Throwable $e) {
            error_log("File cache error: " . $e->getMessage());
            return [];
        }
    }

	public function save($data): void {
		if (empty($data)) {
			throw new \InvalidArgumentException("Cache data cannot be empty");
		}

		if (!is_dir($this->cacheDir)) {
			if (!mkdir($this->cacheDir, $this->permissions, true)) {
				throw new RuntimeException("Failed to create directory: {$this->cacheDir}");
			}
		}

		$tempFile = tempnam($this->cacheDir, 'tmp_cache');
		$dataString = "<?php\n// Auto-generated cache\nreturn " . var_export($data, true) . ";";

		// Запись во временный файл + атомарное переименование
		if (file_put_contents($tempFile, $dataString) && rename($tempFile, $this->cacheFile)) {
			chmod($this->cacheFile, 0644); // Права только на чтение
		} else {
			unlink($tempFile);
			throw new RuntimeException("Failed to save cache to {$this->cacheFile}");
		}
	}
	
    public function clear(): void {
        if (file_exists($this->cacheFile)) {
            if (!unlink($this->cacheFile)) {
                error_log("Не удалось удалить файл кэша: {$this->cacheFile}");
            }
        }
    }

    public function isCacheValid(): bool {
        return file_exists($this->cacheFile) && 
               filemtime($this->cacheFile) > filemtime($this->sourceFile);
    }
	
}