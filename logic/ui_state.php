<?php

/**
 * Keep the active UI tab (and mailbox ticket) across refresh until the session ends.
 */

if (!function_exists('zpgc_ui_allowed_tabs')) {
    function zpgc_ui_allowed_tabs($role)
    {
        $role = strtolower(trim((string) $role));
        if ($role === 'admin') {
            return ['dashboard', 'tickets', 'messages', 'utilities', 'profile', 'settings'];
        }
        if ($role === 'techn') {
            return ['dashboard', 'tickets', 'performance', 'messages', 'profile', 'settings'];
        }
        return ['dashboard', 'tickets', 'messages', 'profile', 'settings'];
    }
}

if (!function_exists('zpgc_ui_resolve_tab')) {
    function zpgc_ui_resolve_tab($role, $default = 'dashboard')
    {
        $allowed = zpgc_ui_allowed_tabs($role);
        $tab = strtolower(trim((string) ($_GET['tab'] ?? '')));
        if ($tab === '' && !empty($_SESSION['ui_last_tab'])) {
            $tab = strtolower(trim((string) $_SESSION['ui_last_tab']));
        }
        if (!in_array($tab, $allowed, true)) {
            $tab = $default;
        }
        $_SESSION['ui_last_tab'] = $tab;

        if ($tab === 'messages') {
            $tid = (int) ($_GET['ticket_id'] ?? 0);
            if ($tid <= 0 && !empty($_SESSION['ui_last_ticket_id'])) {
                $tid = (int) $_SESSION['ui_last_ticket_id'];
            }
            if ($tid > 0) {
                $_SESSION['ui_last_ticket_id'] = $tid;
                if (!isset($_GET['ticket_id'])) {
                    $_GET['ticket_id'] = (string) $tid;
                }
            }
        }

        return $tab;
    }
}

if (!function_exists('zpgc_ui_no_store')) {
    function zpgc_ui_no_store()
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}

if (!function_exists('zpgc_ui_persist_redirect')) {
    /**
     * If the browser refreshed without ?tab=, bounce once to the remembered tab URL.
     */
    function zpgc_ui_persist_redirect($tab)
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            return;
        }
        if (isset($_GET['tab'])) {
            return;
        }
        // Default dashboard needs no query string.
        if ($tab === '' || $tab === 'dashboard') {
            return;
        }
        $script = basename(str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? 'index.php')));
        if (!preg_match('/^[a-z0-9_\-]+\.php$/i', $script)) {
            return;
        }
        $q = ['tab' => $tab];
        if ($tab === 'messages') {
            $tid = (int) ($_GET['ticket_id'] ?? ($_SESSION['ui_last_ticket_id'] ?? 0));
            if ($tid > 0) {
                $q['ticket_id'] = $tid;
            }
        }
        header('Location: ' . $script . '?' . http_build_query($q));
        exit();
    }
}

if (!function_exists('zpgc_nav_selected_class')) {
    function zpgc_nav_selected_class($tab, $nav)
    {
        return strtolower((string) $tab) === strtolower((string) $nav) ? ' selected' : '';
    }
}
