<?php
require_once('../../../wp-load.php');
require_once('includes/class-pecodex-firewall.php');
Pecodex_Firewall::install();
echo "Tables created successfully.";
