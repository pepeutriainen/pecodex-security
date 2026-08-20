<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
try {
    include 'templates/security-dashboard.php';
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " on line " . $e->getLine();
}
