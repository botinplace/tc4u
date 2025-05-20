<?php
namespace Core\Middlewares;

use Core\Security\CsrfToken;
use Core\Request;
use Core\Response;

class CsrfMiddleware {
    public function handle(): bool {
        $method = strtoupper ( Request::method() );

        // Пропускаем GET, HEAD, OPTIONS
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }

        // Получаем токен из запроса
        $token = Request::post('csrf_token') ?? Request::header('HTTP_X_CSRF_TOKEN') ?? null;

        if (!$token || !CsrfToken::validate($token)) {
            (new Response())->setStatusCode(419)->send('CSRF Token Mismatch!');
            return false;
        }

        return true;
    }
}
