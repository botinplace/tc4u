<?php
namespace Core\Middlewares;

use Core\Security\CsrfToken;
use Core\Request;
use Core\Response;

class CsrfMiddleware {
    public function handle(): bool {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        // Пропускаем GET, HEAD, OPTIONS
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }

        // Получаем токен из запроса
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$token || !CsrfToken::validate($token)) {
            (new Response())->setStatusCode(419)->send('CSRF Token Mismatch!');
            return false;
        }

        return true;
    }
}
