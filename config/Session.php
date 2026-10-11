<?php
class Session {
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        // Regenerate session ID for security
        session_regenerate_id(true);
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    public static function destroy() {
        session_destroy();
    }

    public static function isLoggedIn($role) {
        $sessionKey = 'sesi_' . $role;
        return isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey]['status'] == 'login';
    }

    public static function getUserData($role) {
        $sessionKey = 'sesi_' . $role;
        return self::get($sessionKey);
    }
}
?>
