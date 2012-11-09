<?php


function set_domainmap_dir($base) {

	global $domainmap_dir;

	if(defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename($base))) {
		$domainmap_dir = trailingslashit(WPMU_PLUGIN_URL);
	} elseif(defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/domain-mapping/' . basename($base))) {
		$domainmap_dir = trailingslashit(WP_PLUGIN_DIR . '/domain-mapping');
	} else {
		$domainmap_dir = trailingslashit(WP_PLUGIN_DIR . '/domain-mapping');
	}


}

function domainmap_dir($extended) {

	global $domainmap_dir;

	return $domainmap_dir . $extended;


}

?>