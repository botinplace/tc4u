<?php

namespace Core\Middelwares;

use Core\Middleware;
use Core\Request;

class AuthMiddleware extends Middleware {
    
    public function handle(Request $request, callable $next) {
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
        } else {
            return $next($request);
        }
    }

    private function isAuthenticated(): bool {
        return isset($_SESSION['user_id']);
    }

    private function redirectToLogin() {
        header("Location: /login");
        exit();
    }

    public function checkPermissions($requiredRole) {
        if (!$this->hasPermission($requiredRole)) {
            $this->forbiddenResponse();
        }
    }

    private function hasPermission($requiredRole): bool {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $requiredRole;
    }

    private function forbiddenResponse() {
        http_response_code(403);
        echo "Доступ запрещен!";
        exit();
    }

    public function login($username, $password): bool {
        $user = $this->getUserByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }

        return false;
    }

    public function logout() {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_role']);
        session_destroy();
        header("Location: /login");
        exit();
    }

    private function getUserByUsername($username) {
        $users = [
            'admin' => ['id' => 1, 'password' => password_hash('adminpass', PASSWORD_DEFAULT), 'role' => 'admin'],
            'user' => ['id' => 2, 'password' => password_hash('userpass', PASSWORD_DEFAULT), 'role' => 'user'],
        ];

        return $users[$username] ?? null;
    }
}
