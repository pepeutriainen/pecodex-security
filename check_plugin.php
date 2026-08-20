<?php
require_once('../../../wp-load.php');
$active_plugins = get_option('active_plugins');
if (in_array('pecodex-security/pecodex-security.php', $active_plugins)) {
    echo "PLUGIN_IS_ACTIVE";
} else {
    echo "PLUGIN_IS_DEACTIVATED";
}
