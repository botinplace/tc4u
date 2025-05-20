<?php
namespace Core;

use RuntimeException;

class Cache {
    private $cacheFile;
    private $sourceFile;
    private $cacheDir;
    private $permissions;

    public function __construct(string $cacheFile, string $sourceFile, int $permissions = 0755) {
        $this->cacheFile = $cacheFile;
        $this->sourceFile = $sourceFile; // Файл, на основе которого создаётся кэш
        $this->cacheDir = dirname($this->cacheFile);
        $this->permissions = $permissions; // Устанавливаем права доступа по умолчанию
    }

    public function load(): array {
        // Проверяем актуальность кэша
        if (!$this->isCacheValid()) {
            $this->clear(); // Если кэш недействителен, очищаем его
            return []; // Возвращаем пустой массив, поскольку кэш очищен
        }

        if (!file_exists($this->cacheFile)) {
            return []; // Кэш файл не существует
        }

        try {
            $routes = require $this->cacheFile; 
        } catch (\Throwable $e) {
            error_log("Ошибка загрузки кэша: " . $e->getMessage());
            return []; // Логируем ошибку и возвращаем пустой массив
        }

        return $routes ?? []; // Возвращаем пустой массив, если кэш имеет недопустимое значение
    }

	public function save($data): void {
		if (!is_dir($this->cacheDir)) {

			if (!mkdir($this->cacheDir, $this->permissions, true) && !is_dir($this->cacheDir)) {
				error_log("Не удалось создать директорию: {$this->cacheDir}");
				return;
			}
		}

		$dataString = "<?php \nreturn " . var_export($data, true) . ";";

		try {
			if (file_put_contents($this->cacheFile, $dataString) === false) {
				throw new RuntimeException("Не удалось записать данные в файл кэша: {$this->cacheFile}");
			}
		} catch (RuntimeException $e) {
			error_log($e->getMessage());
		}
	}

    public function clear(): void {
        if (file_exists($this->cacheFile)) {
            if (!unlink($this->cacheFile)) {
                error_log("Не удалось удалить файл кэша: {$this->cacheFile}");
            }
        }
    }

    private function isCacheValid(): bool {
        // Проверяем, существует ли кэш файл и файл-источник
        if (!file_exists($this->cacheFile) || !file_exists($this->sourceFile)) {
            return false; // Если один из файлов не существует, кэш недействителен
        }
		
		if(empty( $this->cacheFile ) && !empty($this->sourceFile) );

        // Сравниваем время последнего изменения кэш-файла и файла-источника
        return filemtime($this->cacheFile) > filemtime($this->sourceFile);
    }
}
