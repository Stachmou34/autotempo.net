<?php
/**
 * Fabrique de connexions PDO.
 * Deux bases : JLASSURE (lecture seule) et MCJ (gestion).
 *
 * Compatible PHP 5.6+.
 */
final class Database
{
    /** @var array */
    private static $pool = array();

    /**
     * @param array  $cfg host, port, name, user, pass, charset
     * @param string $key identifiant de la connexion (cache)
     * @return PDO
     */
    public static function connect(array $cfg, $key)
    {
        if (isset(self::$pool[$key])) {
            return self::$pool[$key];
        }

        $port    = isset($cfg['port']) ? (int) $cfg['port'] : 3306;
        $charset = isset($cfg['charset']) ? $cfg['charset'] : 'utf8mb4';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $port,
            $cfg['name'],
            $charset
        );

        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ));
        } catch (PDOException $e) {
            error_log('[MCJ] Connexion DB "' . $key . '" echouee : ' . $e->getMessage());
            header('Content-Type: text/html; charset=utf-8');
            echo '<h2 style="font-family:sans-serif;color:#c02b2b">Connexion base de données impossible (' . htmlspecialchars($key) . ')</h2>';
            echo '<p style="font-family:sans-serif">Vérifiez les identifiants de la base <code>' . htmlspecialchars($key) . '</code> dans <code>config/config.php</code>.</p>';
            echo '<pre style="background:#fde3e3;padding:12px;border-radius:6px;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre>';
            exit;
        }

        self::$pool[$key] = $pdo;
        return $pdo;
    }

    public static function jlassure(array $config)
    {
        return self::connect($config['jlassure_db'], 'jlassure');
    }

    public static function mcj(array $config)
    {
        return self::connect($config['mcj_db'], 'mcj');
    }
}
