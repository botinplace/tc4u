<?php

namespace Core\Ad;

use Core\Controller; 
use Core\Ad\AdModel;
use Core\Request;  
use Core\Session;

class AdController extends Controller
{
    protected $adModel;

    public function __construct()
    {
        $this->adModel = new AdModel();
    }

    public function login(Request $request)
    {
        $username = $request->post('username');
        $password = $request->post('password');

        
        if ($this->adModel->authenticate($username, $password)) {
            Session::set('user', $username);
            return ['status' => 'success', 'message' => 'Успешный вход'];
        }

        return ['status' => 'error', 'message' => 'Неверное имя пользователя или пароль'];
    }

    public function getUserInfo()
    {
        $username = Session::get('user');
        
        if ($username) {
            $userInfo = $this->adModel->getUserInfo($username);
            return ['status' => 'success', 'data' => $userInfo];
        }

        return ['status' => 'error', 'message' => 'Пользователь не аутентифицирован'];
    }


    public function logout()
    {
        Session::destroy();
        return ['status' => 'success', 'message' => 'Вы вышли из системы'];
    }
}
