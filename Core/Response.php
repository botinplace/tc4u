<?php
namespace Core;

class Response {
    private $statusCode = 200;
    private $headers = [];
    private $body;

    public function setStatusCode(int $code): void {
        $this->statusCode = $code;
    }

    public function setHeader(string $name, string $value): void {
        $this->headers[$name] = $value;
    }

    public function setJsonBody(array $data): void {
        $this->body = json_encode($data);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function setHtmlBody(string $html): void {
        $this->body = $html;
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function send(): void {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
        exit();
    }
}