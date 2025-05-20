<?php
namespace Core\Security;

use Core\Session;

class FormTokenManager {
    private $sessionKey = 'form_tokens';
    private $tokenLifetime = 3600; // 1 час
    private $maxTokens = 20;

    public function __construct() {
        if (!Session:has($this->sessionKey) ) {
            Session::set($this->sessionKey , [] );
        }
    }

    /**
     * Генерирует новый токен для формы
     */
    public function generateToken(): array {
        $this->cleanupExpiredTokens();
        
        $formId = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        
        Session::set( $this->sessionKey.'.'.$formId , [
            'token' => $token,
            'created_at' => time()
        ]);
        
        $this->enforceMaxTokens();
        
        return [
            'form_id' => $formId,
            'token' => $token
        ];
    }

    /**
     * Валидирует переданный токен
     */
    public function validateToken(string $formId, string $token): bool {
        if (!Session::has( $this->sessionKey.'.'.$formId)  {
            return false;
        }

        $stored = Session::get( $this->sessionKey.'.'.$formId);
        Session::remove( $this->sessionKey.'.'.$formId );

        return hash_equals($stored['token'], $token);
    }

    /**
     * Очищает просроченные токены
     */
    private function cleanupExpiredTokens(): void {
        foreach (Session::get($this->sessionKey) as $formId => $data) {
            if (time() - $data['created_at'] > $this->tokenLifetime) {
                Session::remove( $this->sessionKey.'.'.$formId);
            }
        }
    }

    /**
     * Ограничивает максимальное количество токенов
     */
    private function enforceMaxTokens(): void {
        while (count(Session::get($this->sessionKey) ) > $this->maxTokens) {
            array_shift( Session::get($this->sessionKey) );
        }
    }

    /**
     * Получает HTML-поля для вставки в форму
     */
    public function getFormFields(): string {
        $tokenData = $this->generateToken();
        return sprintf(
            '<input type="hidden" name="form_id" value="%s">
             <input type="hidden" name="token" value="%s">',
            htmlspecialchars($tokenData['form_id']),
            htmlspecialchars($tokenData['token'])
        );
    }
}
