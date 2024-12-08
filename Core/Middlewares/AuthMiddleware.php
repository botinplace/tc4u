<?php
namespace Core\Middelwares;

use Core\Middleware;
use Core\Request;

class AuthMiddleware extends Middleware {
    // Метод для проверки аутентификации
    public function handleRequest(Request $request, callable $next) {
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
		//Подставить пути страницы авторизации!!!!!!!!
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
	
	    // Метод для авторизации пользователя
    public function login($username, $password): bool {
        // Здесь должна быть логика проверки учетных данных пользователя
        // Например, запрос к базе данных
        $user = $this->getUserByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            // Успешная авторизация, сохраняем данные в сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }

        // Неверные учетные данные
        return false;
    }

    // Метод для выхода пользователя
    public function logout() {
        // Удаляем данные сессии
        unset($_SESSION['user_id']);
        unset($_SESSION['user_role']);
        session_destroy();
		//Подставить пути страницы авторизации!!!!!!!!
        header("Location: /login");
        exit();
    }

    // Метод для получения пользователя по имени
    private function getUserByUsername($username) {
        // Здесь должна быть логика для запроса к базе данных
        // Это пример, верните данные пользователя или NULL
        $users = [
            'admin' => ['id' => 1, 'password' => password_hash('adminpass', PASSWORD_DEFAULT), 'role' => 'admin'],
            'user' => ['id' => 2, 'password' => password_hash('userpass', PASSWORD_DEFAULT), 'role' => 'user'],
        ];

        return $users[$username] ?? null;
    }
}