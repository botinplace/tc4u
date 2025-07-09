<?php
namespace Core;

use Exception;
use RuntimeException;

class FileUploader
{
    protected array $allowedExtensions = [];
    protected int $maxFileSize = 2 * 1024 * 1024; // 2MB по умолчанию
    protected bool $overwriteExisting = false;
    protected string $baseUploadPath;
    protected string $publicBaseUrl = '/uploads/';

    public function __construct(string $baseUploadPath = null)
    {
        //$this->baseUploadPath = $baseUploadPath ?? $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
        $this->baseUploadPath = $baseUploadPath ?? ROOT . '/uploads/';
    }

    public function setAllowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
        return $this;
    }

    public function setMaxFileSize(int $bytes): self
    {
        $this->maxFileSize = $bytes;
        return $this;
    }

    public function setOverwriteExisting(bool $overwrite): self
    {
        $this->overwriteExisting = $overwrite;
        return $this;
    }

    public function setPublicBaseUrl(string $url): self
    {
        $this->publicBaseUrl = rtrim($url, '/') . '/';
        return $this;
    }

    public function upload(array $file, string $directory, string $customFilename = null): array
    {
        $this->validateUpload($file);

        $directory = $this->sanitizePath($directory);
        $uploadDir = $this->createUploadDirectory($directory);
        
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $filename = $customFilename ?? $this->generateFilename($originalName, $extension);
        $filepath = $uploadDir . $filename;
        
        if (!$this->overwriteExisting && file_exists($filepath)) {
            throw new RuntimeException("Файл {$filename} уже существует");
        }

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new RuntimeException("Ошибка перемещения загруженного файла");
        }

        // Установка безопасных прав доступа
        chmod($filepath, 0644);

        return [
            'original_name' => $file['name'],
            'stored_name' => $filename,
            'path' => $filepath,
            'size' => $file['size'],
            'mime_type' => $file['type'],
            'extension' => $extension,
            'public_url' => $this->publicBaseUrl . $directory . '/' . $filename,
            'directory' => $directory
        ];
    }

    protected function validateUpload(array $file): void
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException("Некорректные параметры загрузки файла");
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException("Превышен максимальный размер файла");
            case UPLOAD_ERR_PARTIAL:
                throw new RuntimeException("Файл загружен только частично");
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException("Файл не был загружен");
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new RuntimeException("Отсутствует временная папка");
            case UPLOAD_ERR_CANT_WRITE:
                throw new RuntimeException("Не удалось записать файл на диск");
            case UPLOAD_ERR_EXTENSION:
                throw new RuntimeException("Загрузка остановлена расширением PHP");
            default:
                throw new RuntimeException("Неизвестная ошибка загрузки");
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new RuntimeException("Размер файла превышает допустимый лимит");
        }

        if (!empty($this->allowedExtensions)) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowedExtensions)) {
                throw new RuntimeException("Недопустимый тип файла");
            }
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException("Возможная атака при загрузке файла");
        }
    }

    protected function sanitizePath(string $path): string
    {
        // Удаляем небезопасные символы
        $path = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $path);
        // Удаляем двойные слеши
        $path = preg_replace('/\/+/', '/', $path);
        // Удаляем ведущие и завершающие слеши
        return trim($path, '/');
    }

    protected function createUploadDirectory(string $directory): string
    {
        $fullPath = $this->baseUploadPath . $directory . '/';
        
        if (!file_exists($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                throw new RuntimeException("Не удалось создать директорию для загрузки");
            }
        }

        if (!is_writable($fullPath)) {
            throw new RuntimeException("Директория для загрузки недоступна для записи");
        }

        return $fullPath;
    }

    protected function generateFilename(string $originalName, string $extension): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $originalName);
        $safeName = substr($safeName, 0, 50); // Ограничиваем длину имени
        return uniqid($safeName . '_') . '.' . $extension;
    }

    public function delete(string $filepath): bool
    {
        $fullPath = $this->baseUploadPath . ltrim($filepath, '/');
        
        if (!file_exists($fullPath)) {
            return false;
        }

        if (!is_writable($fullPath)) {
            throw new RuntimeException("Файл недоступен для удаления");
        }

        return unlink($fullPath);
    }
}
