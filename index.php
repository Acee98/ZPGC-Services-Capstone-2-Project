<?php
/**
 * Cloud / Hostinger document-root entry — send visitors to login.
 */
header('Location: pages/login_signup.php', true, 302);
exit;
