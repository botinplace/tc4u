<?php
namespace Core;

use Core\Request;

class Response {
    private int $statusCode = 200;
    private array $headers = [];
    private $body = null;
    private bool $shouldExit = true;
    private bool $headersSent = false;
    private bool $lockHeaders = false;

    public function setStatusCode(int $code): self {
        if ($code < 100 || $code > 599) {
            $this->logError('Invalid HTTP status code: ' . $code);
            throw new \InvalidArgumentException("Invalid HTTP status code: $code");
        }
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value, bool $replace = true): self {
        if ($this->headersSent) {
            $this->logWarning('Attempt to modify headers after sending');
            throw new \RuntimeException('Headers already sent, cannot modify');
        }
        
        $normalizedName = strtolower($name);
        if ($replace || !isset($this->headers[$normalizedName])) {
            $this->headers[$normalizedName] = [
                'original-name' => $name,
                'value' => $value
            ];
        }
        return $this;
    }

    public function removeHeader(string $name): self {
        if ($this->headersSent) {
            $this->logWarning('Attempt to remove headers after sending');
            throw new \RuntimeException('Headers already sent, cannot modify');
        }
        
        unset($this->headers[strtolower($name)]);
        return $this;
    }

    public function setJsonBody(array $data, int $options = JSON_THROW_ON_ERROR, int $depth = 512): self {
        try {
            $this->body = json_encode($data, $options | JSON_UNESCAPED_UNICODE, $depth);
            return $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\JsonException $e) {
            $this->logError('JSON encoding failed: ' . $e->getMessage());
            throw new \RuntimeException('JSON encoding failed', 0, $e);
        }
    }

    public function setHtmlBody(string $html, bool $escape = false): self {
        $this->body = $escape ? htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') : $html;
        return $this->setHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function setTextBody(string $text): self {
        $this->body = $text;
        return $this->setHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public function setFile(string $filePath, ?string $contentType = null, int $chunkSize = 8192): self
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->logError('File not accessible: ' . $filePath);
            throw new \RuntimeException("File not found or not readable");
        }
    
        $contentType = $contentType ?? $this->detectMimeType($filePath);
        $fileSize = filesize($filePath);
    
        $this->body = function () use ($filePath, $chunkSize) {
            $this->sendFileChunked($filePath, $chunkSize);
        };
    
        return $this->setHeader('Content-Type', $contentType)
                   ->setHeader('Content-Length', (string) $fileSize)
                   ->setHeader('Accept-Ranges', 'bytes');
    }

    public function redirect(string $url, int $statusCode = 302): self {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->logError('Invalid redirect URL: ' . $url);
            throw new \InvalidArgumentException('Invalid redirect URL');
        }

        if (Request::isAjax()) {
            return $this->setStatusCode(200)
                ->setJsonBody([
                    'redirect' => $url,
                    'status' => $statusCode
                ]);
        }
    
        return $this->setStatusCode($statusCode)
                   ->setHeader('Location', $url)
                   ->setBody('');
        }

    public function setBody($body): self {
        $this->body = $body;
        return $this;
    }

    public function preventExit(): self {
        $this->shouldExit = false;
        return $this;
    }

    public function send(bool $lockHeaders = true): void {
        if ($this->headersSent) {
            $this->logError('Attempt to send response multiple times');
            throw new \RuntimeException('Headers already sent');
        }

        $this->headersSent = $lockHeaders;

        try {
            http_response_code($this->statusCode);

            foreach ($this->headers as $header) {
                header("{$header['original-name']}: {$header['value']}", true);
            }

            if ($this->body !== null) {
                if (is_callable($this->body)) {
                    ($this->body)();
                } else {
                    echo $this->body;
                }
            }
        } catch (\Throwable $e) {
            $this->logCritical('Response sending failed: ' . $e->getMessage());
            throw $e;
        } finally {
            if ($this->shouldExit) {
                exit;
            }
        }
    }

    public function isSent(): bool {
        return $this->headersSent;
    }

    public function withCookie(
        string $name,
        string $value = "",
        int $expires = 0,
        string $path = "",
        string $domain = "",
        bool $secure = true,
        bool $httpOnly = true,
        string $sameSite = "Lax"
    ): self {
        $cookieHeader = rawurlencode($name) . '=' . rawurlencode($value);
        $options = [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite
        ];

        return $this->setHeader('Set-Cookie', $cookieHeader . $this->buildCookieOptions($options), false);
    }

    public function setCsp(string $policy): self {
        return $this->setHeader('Content-Security-Policy', $policy);
    }

    public function pushResource(string $url, array $headers = []): self {
        if (function_exists('header_register_callback')) {
            $linkHeader = '<' . $url . '>; rel=preload';
            foreach ($headers as $key => $value) {
                $linkHeader .= '; ' . $key . '="' . addslashes($value) . '"';
            }
            $this->setHeader('Link', $linkHeader, false);
        }
        return $this;
    }

    private function buildCookieOptions(array $options): string {
        $parts = [];
        foreach ($options as $key => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = is_bool($value) ? ($value ? $key : '') : "$key=$value";
            }
        }
        return $parts ? '; ' . implode('; ', array_filter($parts)) : '';
    }

    private function detectMimeType(string $filePath): string {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        return $mime ?: 'application/octet-stream';
    }

    private function sendFileChunked(string $filePath, int $chunkSize): void {
        $handle = fopen($filePath, 'rb');
        try {
            while (!feof($handle)) {
                echo fread($handle, $chunkSize);
                ob_flush();
                flush();
            }
        } finally {
            fclose($handle);
        }
    }

    private function logError(string $message): void {
        error_log('[ERROR] ' . $message);
    }

    private function logWarning(string $message): void {
        error_log('[WARNING] ' . $message);
    }

    private function logCritical(string $message): void {
        error_log('[CRITICAL] ' . $message);
    }
}
