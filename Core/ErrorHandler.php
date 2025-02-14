<?php
namespace Core;

class ErrorHandler {
    private $logFile = 'log';
    private $maxLines = 100;

    public function __construct() {
        set_error_handler([$this, 'customErrorHandler']);
    }
    public function customErrorHandler($errno, $errstr, $errfile, $errline) {
        $errorCount = 0;
        if (file_exists($this->logFile)) {
            $errorCount = count(file($this->logFile));
        }
        if ($errorCount >= $this->maxLines) {
            $logNum = 1;

            while (file_exists($this->logFile . $logNum)) {
                $logNum++;
            }

            rename($this->logFile, $this->logFile . $logNum);
        }

        error_log("[$errno] $errstr in $errfile on line $errline\n", 3, $this->logFile);
    }
}
