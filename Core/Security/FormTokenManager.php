<?php
namespace Core\Security;

use Core\Session;

class FormTokenManager {
    private $sessionKey = 'form_tokens';
    private $tokenLifetime = 3600; // 1 час
    private $maxTokens = 20;

    public function __construct() {
        if (!Session::has($this->sessionKey)) {
            Session::set($this->sessionKey, []);
        }
    }

    public function generateToken(): array {
        $this->cleanupExpiredTokens();
        
        $formId = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        
        $tokens = Session::get($this->sessionKey, []);
        $tokens[$formId] = [
            'token' => $token,
            'created_at' => time()
        ];
        Session::set($this->sessionKey, $tokens);
        
        $this->enforceMaxTokens();
        
        return [
            'form_id' => $formId,
            'token' => $token
        ];
    }

    public function validateToken(string $formId, string $token): bool {
        $tokens = Session::get($this->sessionKey, []);
        
        if (!isset($tokens[$formId])) {
            return false;
        }

        $stored = $tokens[$formId];
        unset($tokens[$formId]);
        Session::set($this->sessionKey, $tokens);

        return hash_equals($stored['token'], $token) && 
               (time() - $stored['created_at'] <= $this->tokenLifetime);
    }

    private function cleanupExpiredTokens(): void {
        $tokens = Session::get($this->sessionKey, []);
        $now = time();
        
        foreach ($tokens as $formId => $data) {
            if ($now - $data['created_at'] > $this->tokenLifetime) {
                unset($tokens[$formId]);
            }
        }
        
        Session::set($this->sessionKey, $tokens);
    }

    private function enforceMaxTokens(): void {
        $tokens = Session::get($this->sessionKey, []);
        
        if (count($tokens) > $this->maxTokens) {
            $tokens = array_slice($tokens, -$this->maxTokens, null, true);
            Session::set($this->sessionKey, $tokens);
        }
    }

    public function getFormFields(): string {
        $tokenData = $this->generateToken();
        return sprintf(
            '<input type="hidden" name="form_id" value="%s">
             <input type="hidden" name="token" value="%s">',
            htmlspecialchars($tokenData['form_id'], ENT_QUOTES),
            htmlspecialchars($tokenData['token'], ENT_QUOTES)
        );
    }
}
