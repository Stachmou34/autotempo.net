<?php
declare(strict_types=1);

/**
 * Fabrique de connexions PDO.
 * Deux bases : JLASSURE (lecture seule) et MCJ (gestion).
 */
final class Database
{
    /** @var array<string,PDO> */
    private static array $pool = [];

    /**
     * @param array{host:string,port:int,name:string,user:string,pass:string,charset:string} $cfg
     */
    public static function connect(array $cfg, string $key): PDO
    {
        if (isset(self::$pool[$key])) {
            return self::$pool[$key];
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            error_log('[MCJ] Connexion DB "' . $key . '" échouée : ' . $e->getMessage());
            exit('Impossible de se connecter à la base de données (' . htmlspecialchars($key) . '). Vérifiez config/config.php.');
        }

        return self::$pool[$key] = $pdo;
    }

    public static function jlassure(array $config): PDO
    {
        return self::connect($config['jlassure_db'], 'jlassure');
    }

    public static function mcj(array $config): PDO
    {
        return self::connect($config['mcj_db'], 'mcj');
    }
}
