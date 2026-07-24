<?php
declare(strict_types=1);

/**
 * Authentification simple (un compte administrateur défini en config)
 * + protection CSRF.
 */
final class Auth
{
    public static function check(array $config, string $username, string $password): bool
    {
        $expectedUser = $config['auth']['username'] ?? '';
        $hash         = $config['auth']['password_hash'] ?? '';

        // Comparaison à temps constant pour le nom d'utilisateur.
        if (!hash_equals($expectedUser, $username)) {
            return false;
        }
        return password_verify($password, $hash);
    }

    public static function login(string $username): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $username;
        $_SESSION['logged_in_at'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user']);
    }

    /** Redirige vers la page de connexion si non authentifié. */
    public static function require(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function user(): string
    {
        return (string) ($_SESSION['user'] ?? '');
    }

    // ── CSRF ──────────────────────────────────────────────────────
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfValidate(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }
}
