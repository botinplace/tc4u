<?php
namespace Core;

class ErrorLogger {
    private $logDir;
    private $logFile;
    private $maxLines;

    public function __construct($logFile = 'error_log.txt', $maxLines = 1000) {
        // Добавляем расширение, если его нет
        if (pathinfo($logFile, PATHINFO_EXTENSION) === '') {
            $logFile .= '.log';
        }
        
        $this->logDir = dirname($logFile);
        try {
            if (!is_dir($this->logDir)) {
                if (!mkdir($this->logDir, 0775, true)) {
                    throw new \RuntimeException("Failed to create log directory: {$this->logDir}");
                }
            }
            
            $this->logFile = $logFile;
            $this->maxLines = $maxLines;
            
            // Создаем файл, если не существует
            if (!file_exists($this->logFile)) {
                if (!touch($this->logFile)) {
                    throw new \RuntimeException("Failed to create log file: {$this->logFile}");
                }
                chmod($this->logFile, 0664);
            }
            
            // Проверяем права на запись
            if (!is_writable($this->logFile)) {
                throw new \RuntimeException("Log file is not writable: {$this->logFile}");
            }
            
            set_error_handler([$this, 'handleError'], E_ALL);
            set_exception_handler([$this, 'handleException']);
            register_shutdown_function([$this, 'handleShutdown']);
            
        } catch (\Throwable $e) {
            error_log("Logger initialization failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function handleError($errno, $errstr, $errfile, $errline) {
        $this->log("ERROR: [$errno] $errstr in $errfile on line $errline");
    }

    public function handleException($exception) {
        $message = "EXCEPTION: ";
        if ($exception instanceof \Throwable) {
            $message .= "{$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}" . PHP_EOL;
            $message .= "Stack trace:" . PHP_EOL . $exception->getTraceAsString();
        } else {
            $message .= serialize($exception);
        }
        $this->log($message);
    }

    public function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->log("FATAL ERROR: [{$error['type']}] {$error['message']} in {$error['file']} on line {$error['line']}");
        }
    }

    private function log($message) {
        try {
            if ($this->exceedsMaxLines() || $this->exceedsMaxSize()) {
                $this->rotateLog();
            }
            
            $result = file_put_contents(
                $this->logFile, 
                date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 
                FILE_APPEND | LOCK_EX
            );
            
            if ($result === false) {
                throw new \RuntimeException("Failed to write to log file");
            }
        } catch (\Throwable $e) {
            // Fallback для ошибок логгера
            error_log("Logger error: " . $e->getMessage());
        }
    }

    private function exceedsMaxLines() {
        if (!file_exists($this->logFile)) return false;
        
        $lineCount = 0;
        $handle = fopen($this->logFile, 'r');
        while (!feof($handle)) {
            fgets($handle);
            $lineCount++;
        }
        fclose($handle);
        
        return $lineCount >= $this->maxLines;
    }

    private function exceedsMaxSize() {
        if (!file_exists($this->logFile)) return false;
        return filesize($this->logFile) > 5 * 1024 * 1024; // 5 MB
    }
    
    private function rotateLog() {
        $newFile = $this->logFile . '.' . date('Ymd_His');
        if (rename($this->logFile, $newFile)) {
            // Создаем новый файл лога
            touch($this->logFile);
            chmod($this->logFile, 0664);
            
            if (extension_loaded('zlib')) {
                $compressed = $newFile . '.gz';
                file_put_contents($compressed, gzencode(file_get_contents($newFile)));
                unlink($newFile);
            }
        }
    }
}