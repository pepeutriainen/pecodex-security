<?php
require_once('../../../wp-load.php');
global $wpdb;
$t1 = $wpdb->get_var("SHOW TABLES LIKE 'wp_pmc_traffic_log'");
$t2 = $wpdb->get_var("SHOW TABLES LIKE 'wp_pmc_geoip_cache'");
echo "Traffic Log Table: " . ($t1 ? "YES" : "NO") . "\n";
echo "GeoIP Cache Table: " . ($t2 ? "YES" : "NO") . "\n";
