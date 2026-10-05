-- Azure / hardened deploys: MySQL-backed PHP sessions (logic/session_db.php).
CREATE TABLE IF NOT EXISTS php_sessions (
  id VARCHAR(128) NOT NULL PRIMARY KEY,
  data MEDIUMBLOB NOT NULL,
  last_activity INT UNSIGNED NOT NULL,
  KEY idx_php_sessions_last (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
