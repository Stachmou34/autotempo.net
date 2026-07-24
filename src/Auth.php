<?php
/**
 * Authentification simple (un compte administrateur défini en config)
 * + protection CSRF.
 *
 * Compatible PHP 5.6+.
 */
final class Auth
{
    public static function check(array $config, $username, $password)
    {
        $expectedUser = isset($config['auth']['username']) ? $config['auth']['username'] : '';
        $hash         = isset($config['auth']['password_hash']) ? $config['auth']['password_hash'] : '';

        // Comparaison à temps constant pour le nom d'utilisateur.
        if (!hash_equals($expectedUser, (string) $username)) {
            return false;
        }
        return password_verify($password, $hash);
    }

    public static function login($username)
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $username;
        $_SESSION['logged_in_at'] = time();
    }

    public static function logout()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn()
    {
        return !empty($_SESSION['user']);
    }

    /** Redirige vers la page de connexion si non authentifié. */
    public static function requireLogin()
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function user()
    {
        return isset($_SESSION['user']) ? (string) $_SESSION['user'] : '';
    }

    // ── CSRF ──────────────────────────────────────────────────────
    public static function csrfToken()
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = self::randomToken();
        }
        return $_SESSION['csrf'];
    }

    public static function csrfValidate($token)
    {
        return is_string($token)
            && !empty($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }

    /** Jeton aléatoire compatible PHP 5.6 (pas de random_bytes). */
    private static function randomToken()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(32));
        }
        // Dernier recours (moins robuste).
        return hash('sha256', uniqid((string) mt_rand(), true) . microtime());
    }
}
