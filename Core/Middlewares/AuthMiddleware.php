<?php
namespace Core\Middelwares;

class AuthMiddleware {
    // Метод для проверки аутентификации
    public function handleRequest() {
        // Проверяем, имеется ли сессия или токен аутентификации
        if (!$this->isAuthenticated()) {
            // Если нет, перенаправляем на страницу входа
            $this->redirectToLogin();
        }
    }

    // Метод для проверки, аутентифицирован ли пользователь
    private function isAuthenticated(): bool {
        // Логика проверки, например, проверка сессии или токена
        return isset($_SESSION['user_id']);
    }

    // Метод для перенаправления на страницу входа
    private function redirectToLogin() {
        header("Location: /login");
        exit();
    }

    // Метод для проверки прав доступа
    public function checkPermissions($requiredRole) {
        // Проверяем роль пользователя
        if (!$this->hasPermission($requiredRole)) {
            // Если нет прав, перенаправляем на страницу ошибки
            $this->forbiddenResponse();
        }
    }

    // Метод для проверки, имеет ли пользователь необходимые права
    private function hasPermission($requiredRole): bool {
        // Логика проверки прав, например, сравнение с ролью в сессии
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $requiredRole;
    }

    // Метод для обработки случая без доступа
    private function forbiddenResponse() {
        http_response_code(403);
        echo "Доступ запрещен!";
        exit();
    }
}