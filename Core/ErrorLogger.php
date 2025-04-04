<?php
namespace Core;

class ErrorLogger {
    private $logFile;
    private $maxLines;

    public function __construct($logFile = 'error_log.txt', $maxLines = 1000) {
        $this->logFile = $logFile;
        $this->maxLines = $maxLines;
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    public function handleError($errno, $errstr, $errfile, $errline) {
        $this->log("ERROR: [$errno] $errstr in $errfile on line $errline");
    }

    public function handleException($exception) {        
        $this->log("EXCEPTION: {$exception->getMessage()} On File: {$exception->getFile()} On Line: {$exception->getLine()}");
    }

    private function log($message) {
        if ($this->exceedsMaxLines()) {
            $this->rotateLog();
        }
        
        file_put_contents($this->logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
    }

    private function exceedsMaxLines() {
        if (!file_exists($this->logFile)) {
            return false;
        }
        
        $lines = count(file($this->logFile));
        return $lines >= $this->maxLines;
    }

    private function rotateLog() {
        $newLogFile = $this->logFile . '.' . time();
        rename($this->logFile, $newLogFile);
    }
}
