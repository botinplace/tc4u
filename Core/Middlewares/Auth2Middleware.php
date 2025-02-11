<?php
class AuthMiddleware {
    public function __invoke($request, $response, $next) {
        
        $isAuthorized = $this->checkAuthorization($request);
        
        if (!$isAuthorized) {
            return $response->withStatus(403)->write('Unauthorized');
        }
        
        return $next($request, $response);
    }

    private function checkAuthorization($request) {
        // логика проверки авторизации
    }
}
