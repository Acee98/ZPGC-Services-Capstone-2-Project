<?php
/**
 * Azure App Service Health Check target.
 * Keep this cheap: no DB, no sessions — just prove PHP is alive.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
http_response_code(200);
echo 'ok';
