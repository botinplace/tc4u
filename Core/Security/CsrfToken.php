<?php
namespace Core\Security;

use Core\Session;

class CsrfToken {
    private const KEY = 'csrf_token';

    // Генерация токена (если его нет)
    public static function generate(): string {
        if (!Session::has(self::KEY)) {
            Session::set(self::KEY, bin2hex(random_bytes(32)));
        }
        return Session::get(self::KEY);
    }

    // Проверка токена
    public static function validate(string $token): bool {
        $storedToken = Session::get(self::KEY);
        return hash_equals($storedToken, $token);
    }

    // Удаление токена (опционально)
    public static function destroy(): void {
        Session::remove(self::KEY);
    }
}
