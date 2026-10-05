<?php

/**
 * MySQL-backed PHP sessions for Azure / multi-instance hosting.
 * Filesystem sessions on App Service are wiped on recycle → surprise logouts.
 *
 * Never fatals the request: if MySQL/SSL/table setup fails, the handler
 * soft-falls back to an in-request memory store so login_signup stays up.
 */

if (!function_exists('zpgc_env_first')) {
    function zpgc_env_first($key, $default = '')
    {
        $candidates = [];
        $g = getenv($key);
        if ($g !== false) {
            $candidates[] = $g;
        }
        if (isset($_SERVER[$key])) {
            $candidates[] = $_SERVER[$key];
        }
        if (isset($_ENV[$key])) {
            $candidates[] = $_ENV[$key];
        }
        foreach ($candidates as $raw) {
            $val = trim((string) $raw);
            if ($val !== '') {
                return $val;
            }
        }
        return $default;
    }
}

if (!class_exists('ZpgcDbSessionHandler')) {
    class ZpgcDbSessionHandler implements SessionHandlerInterface
    {
        /** @var mysqli|null */
        private $db = null;
        private $ready = false;
        /** When true, close() must not destroy the shared app mysqli. */
        private $ownsDb = false;
        /** @var bool when DB is unavailable after the handler was registered */
        private $memoryOnly = false;
        /** @var array<string,string> */
        private $memory = [];

        public function usingDatabase(): bool
        {
            return !$this->memoryOnly && $this->ready;
        }

        private function connect()
        {
            if ($this->db instanceof mysqli) {
                return true;
            }
            // Prefer the single request-scoped connection from logic/config.php.
            if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                $this->db = $GLOBALS['conn'];
                $this->ownsDb = false;
                return true;
            }
            if (isset($GLOBALS['zpgc_mysqli']) && $GLOBALS['zpgc_mysqli'] instanceof mysqli) {
                $this->db = $GLOBALS['zpgc_mysqli'];
                $this->ownsDb = false;
                return true;
            }

            $host = zpgc_env_first('DB_HOST', 'localhost');
            $user = zpgc_env_first('DB_USER', 'root');
            // Empty string is valid (local XAMPP root with no password).
            $password = zpgc_env_first('DB_PASSWORD', '');
            $database = zpgc_env_first('DB_NAME', 'zpgc_services_db');
            $port = (int) zpgc_env_first('DB_PORT', '3306');
            $useSsl = in_array(
                strtolower(zpgc_env_first('DB_SSL', '')),
                ['1', 'true', 'yes'],
                true
            );

            $mysqli = mysqli_init();
            if ($mysqli === false) {
                return false;
            }
            if ($useSsl) {
                mysqli_ssl_set($mysqli, null, null, null, null, null);
            }
            $flags = $useSsl ? MYSQLI_CLIENT_SSL : 0;
            $ok = @$mysqli->real_connect($host, $user, $password, $database, $port, null, $flags);
            if (!$ok || $mysqli->connect_error) {
                return false;
            }
            $mysqli->set_charset('utf8mb4');
            $this->db = $mysqli;
            $this->ownsDb = true;
            $GLOBALS['zpgc_mysqli'] = $mysqli;
            if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
                $GLOBALS['conn'] = $mysqli;
            }
            return true;
        }

        private function ensureTable()
        {
            if ($this->ready || !$this->db) {
                return $this->ready;
            }
            // Process-wide cache — avoids SHOW TABLES on every open/close cycle.
            if (!empty($GLOBALS['zpgc_php_sessions_ready'])) {
                $this->ready = true;
                return true;
            }

            // Prefer existing table (Azure users may lack CREATE).
            $check = @$this->db->query("SHOW TABLES LIKE 'php_sessions'");
            if ($check && $check->num_rows > 0) {
                $this->ready = true;
                $GLOBALS['zpgc_php_sessions_ready'] = true;
                return true;
            }

            if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
                $probe = @$this->db->query('SELECT 1 FROM php_sessions LIMIT 1');
                $this->ready = (bool) $probe;
                if ($this->ready) {
                    $GLOBALS['zpgc_php_sessions_ready'] = true;
                }
                return $this->ready;
            }
            $sql = 'CREATE TABLE IF NOT EXISTS php_sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data MEDIUMBLOB NOT NULL,
                last_activity INT UNSIGNED NOT NULL,
                KEY idx_php_sessions_last (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            if (@$this->db->query($sql)) {
                $this->ready = true;
                $GLOBALS['zpgc_php_sessions_ready'] = true;
                return true;
            }

            // Last resort: table may exist even if SHOW/CREATE was denied.
            $probe = @$this->db->query('SELECT 1 FROM php_sessions LIMIT 1');
            $this->ready = (bool) $probe;
            if ($this->ready) {
                $GLOBALS['zpgc_php_sessions_ready'] = true;
            }
            return $this->ready;
        }

        private function useMemory()
        {
            $this->memoryOnly = true;
            $this->ready = false;
            $GLOBALS['zpgc_php_sessions_memory'] = true;
        }

        public function open(string $path, string $name): bool
        {
            // Always return true so session_start() never 500s the page.
            if (!$this->connect()) {
                $this->useMemory();
                return true;
            }
            if (!$this->ensureTable()) {
                $this->useMemory();
                return true;
            }
            return true;
        }

        public function close(): bool
        {
            // Never close the shared app connection — only privately owned links.
            if ($this->ownsDb && $this->db instanceof mysqli) {
                @$this->db->close();
                $this->db = null;
                $this->ownsDb = false;
                // Owned connections are gone; shared readiness flag can stay.
            } else {
                // Shared mysqli stays alive — keep handler ready for next session I/O.
                $this->db = null;
                $this->ownsDb = false;
            }
            return true;
        }

        public function read(string $id): string|false
        {
            if ($this->memoryOnly) {
                return $this->memory[$id] ?? '';
            }
            if (!$this->db || !$this->ensureTable()) {
                return '';
            }
            $stmt = @$this->db->prepare(
                'SELECT data FROM php_sessions WHERE id = ? AND last_activity > ? LIMIT 1'
            );
            if (!$stmt) {
                return '';
            }
            $min = time() - (int) ini_get('session.gc_maxlifetime');
            $stmt->bind_param('si', $id, $min);
            if (!@$stmt->execute()) {
                $stmt->close();
                return '';
            }
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if (!$row) {
                return '';
            }
            return (string) $row['data'];
        }

        public function write(string $id, string $data): bool
        {
            if ($this->memoryOnly) {
                $this->memory[$id] = $data;
                return true;
            }
            if (!$this->db || !$this->ensureTable()) {
                // Soft-success: never fail the request over session persistence.
                return true;
            }
            $now = time();
            $stmt = @$this->db->prepare(
                'INSERT INTO php_sessions (id, data, last_activity)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
            );
            if (!$stmt) {
                return true;
            }
            $stmt->bind_param('ssi', $id, $data, $now);
            @$stmt->execute();
            $stmt->close();
            return true;
        }

        public function destroy(string $id): bool
        {
            if ($this->memoryOnly) {
                unset($this->memory[$id]);
                return true;
            }
            if (!$this->db || !$this->ensureTable()) {
                return true;
            }
            $stmt = @$this->db->prepare('DELETE FROM php_sessions WHERE id = ?');
            if (!$stmt) {
                return true;
            }
            $stmt->bind_param('s', $id);
            @$stmt->execute();
            $stmt->close();
            return true;
        }

        public function gc(int $max_lifetime): int|false
        {
            if ($this->memoryOnly || !$this->db || !$this->ensureTable()) {
                return 0;
            }
            $cutoff = time() - $max_lifetime;
            $stmt = @$this->db->prepare('DELETE FROM php_sessions WHERE last_activity < ?');
            if (!$stmt) {
                return 0;
            }
            $stmt->bind_param('i', $cutoff);
            @$stmt->execute();
            $n = (int) $stmt->affected_rows;
            $stmt->close();
            return $n;
        }
    }
}

if (!function_exists('zpgc_register_db_sessions')) {
    /**
     * @return bool true when the MySQL handler was registered
     */
    function zpgc_register_db_sessions()
    {
        static $done = false;
        static $registered = false;
        if ($done) {
            return $registered;
        }
        $done = true;

        try {
            // Skip throwaway probe open/close when we already know the table exists.
            if (empty($GLOBALS['zpgc_php_sessions_ready'])) {
                $probe = new ZpgcDbSessionHandler();
                $probe->open('', 'probe');
                $dbOk = $probe->usingDatabase();
                $probe->close();
                if (!$dbOk) {
                    return false;
                }
            }
            session_set_save_handler(new ZpgcDbSessionHandler(), true);
            $registered = true;
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
