<?php
namespace App;


class Auth {
    protected $cfg;
    public function __construct(array $cfg) {
        $this->cfg = $cfg;
        if(session_status() === PHP_SESSION_NONE) {
            session_name($cfg['session_name']);
            session_start();
        }
    }


    public function login($user, $pass) {
        if ($user !== $this->cfg['admin_user']) return false;
        if (!password_verify($pass, $this->cfg['admin_pass'])) return false;
        $_SESSION['is_admin'] = true;
        $_SESSION['login_time'] = time();
        return true;
    }


    public function check() {
        return !empty($_SESSION['is_admin']);
    }


    public function logout() {
        session_unset();
        session_destroy();
    }
}