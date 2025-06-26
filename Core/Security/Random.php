<?php
namespace Core\Security;

class Random
{
    public static function password(int $length = 12): string
    {
        try {
            // Минимальная длина - 8 символов
            $length = max($length, 8);
            
            $lower = 'abcdefghijklmnopqrstuvwxyz';
            $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $numbers = '0123456789';
            $symbols = '!@#$%()-_=+';
            $all = $lower . $upper . $numbers . $symbols;
            
            $password = [
                $lower[random_int(0, 25)],
                $upper[random_int(0, 25)],
                $numbers[random_int(0, 9)],
                $symbols[random_int(0, strlen($symbols) - 1)]
            ];
            
            for ($i = 4; $i < $length; $i++) {
                $password[] = $all[random_int(0, strlen($all) - 1)];
            }
            
            shuffle($password);
            return implode('', $password);
            
        } catch (\Exception $e) {
            // Фолбэк на менее безопасный метод
            return self::fallbackPassword($length);
        }
    }
    
    private static function fallbackPassword(int $length): string
    {
        $bytes = openssl_random_pseudo_bytes($length);
        return substr(
            str_replace(['/', '+', '='], '', base64_encode($bytes)),
            0, $length
        );
    }
    
    public static function token(int $length = 32): string
    {
        try {
            return bin2hex(random_bytes($length / 2));
        } catch (\Exception $e) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        }
    }
}
