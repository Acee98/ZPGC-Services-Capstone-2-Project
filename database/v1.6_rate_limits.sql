-- IP rate-limit hits (login, OTP, ticket submit, AI). Safe to run more than once.
CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(64) NOT NULL,
    client_key VARCHAR(64) NOT NULL,
    hit_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_rl_lookup (bucket, client_key, hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
