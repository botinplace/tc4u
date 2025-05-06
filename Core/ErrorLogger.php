<?php
namespace Core;

class ErrorLogger {
    private $logDir;
    private $logFile;
    private $maxLines;

    public function __construct($logFile = 'error_log.txt', $maxLines = 1000) {
        $this->logDir = dirname($logFile);
        try {
            if (!is_dir($this->logDir) && !mkdir($this->logDir, 0755, true)) {
                throw new \RuntimeException("Failed to create log directory: {$this->logDir}");
            }
            
            $this->logFile = $logFile;
            $this->maxLines = $maxLines;
            
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
            if ($this->exceedsMaxLines()) {
                $this->rotateLog();
            }
            
            if (!is_writable($this->logDir)) {
                throw new \RuntimeException("Log directory is not writable: {$this->logDir}");
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
       return filesize($this->logFile) > 5 * 1024 * 1024; // 5 MB
   }
    
   private function rotateLog() {
       $newFile = $this->logFile . '.' . date('Ymd_His');
       rename($this->logFile, $newFile);
       if (extension_loaded('zlib')) {
           file_put_contents("{$newFile}.gz", gzencode(file_get_contents($newFile)));
           unlink($newFile);
       }
   }

}
