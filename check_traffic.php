<?php
require_once('../../../wp-load.php');
global $wpdb;
$results = $wpdb->get_results("SELECT * FROM wp_pmc_traffic_log ORDER BY id DESC LIMIT 5");
print_r($results);
