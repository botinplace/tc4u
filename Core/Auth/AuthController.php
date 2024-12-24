<?php

namespace Core\Auth;

use Core\Ad\AdModel;

class AuthController {
    private $ldapModel;

    public function __construct() {
        $this->ldapModel = new LdapModel('ldap.example.com', 389); // Замените на адрес LDAP-сервера
    }

    public function login($username, $password) {
        if ($this->ldapModel->authenticate($username, $password)) {
            // Успешная аутентификация
            echo "Login successful!";
            // Здесь можно добавить перенаправление или другие действия
        } else {
            // Ошибка аутентификации
            echo "Invalid credentials.";
        }
    }
}