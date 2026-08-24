<?php
// app/helpers/Database.php
// Single source of the PDO connection. Replaces the database.php / db_connect.php
// duplication so there is exactly one place where the connection (and, through the
// base Model, the tenant scope) is established.

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        return self::getConnection();
    }

    public static function extractJwtRole(): ?string
    {
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        } else {
            $headers = getallheaders();
        }
        
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                return $payload['role'] ?? null;
            }
        }
        return null;
    }

    public static function getConnection($tenantId = null): PDO
    {
        if (self::$pdo === null) {
            $cfg = require ROOT_PATH . '/app/config/database.php';

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $cfg['host'],
                $cfg['port'] ?? '5432',
                $cfg['dbname']
            );

            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // fail loudly
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                 // real prepared stmts
            ]);
            
            // Set PostgreSQL timezone to match PHP
            self::$pdo->exec("SET timezone TO '+03:00'");
        }
        
        $role = self::extractJwtRole();
        
        if ($role !== 'SUPER_ADMIN' && $tenantId !== null) {
            $stmt = self::$pdo->prepare("SET LOCAL app.current_tenant_id = :tenant_id");
            $stmt->execute(['tenant_id' => (int) $tenantId]);
        }
        
        return self::$pdo;
    }

    /** For tests: inject a pre-built PDO (e.g. a throwaway test database). */
    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}