<?php

if (!function_exists('audit_ready')) {
    function audit_ready(mysqli $conn)
    {
        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            return;
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS admin_audit (
                id INT(11) NOT NULL AUTO_INCREMENT,
                actor_id INT(11) NOT NULL,
                action VARCHAR(80) NOT NULL,
                target_id INT(11) DEFAULT NULL,
                detail VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

if (!function_exists('audit_write')) {
    function audit_write(mysqli $conn, $action, $targetId, $detail)
    {
        audit_ready($conn);
        $actor = function_exists('current_user_id') ? current_user_id($conn) : 0;
        $stmt = $conn->prepare(
            'INSERT INTO admin_audit (actor_id, action, target_id, detail) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('isis', $actor, $action, $targetId, $detail);
        $stmt->execute();
        $stmt->close();
    }
}
