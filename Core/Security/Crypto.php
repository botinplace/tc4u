<?php
namespace Core\Security;

class Crypto {
    // Хеширование пароля
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // Проверка пароля
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    // Шифрование данных (с использованием OpenSSL)
    public static function encrypt(string $data, string $key): string {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    // Дешифровка
    public static function decrypt(string $encryptedData, string $key): string {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $payload = substr($data, 16);
        return openssl_decrypt($payload, 'aes-256-cbc', $key, 0, $iv);
    }
}
