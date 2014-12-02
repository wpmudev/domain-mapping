<?php
$sunrises = array(
	"dm_sunrise" => defined("DM_CUSTOM_SUNRISE") ? DM_CUSTOM_SUNRISE : ( defined( "WP_PLUGIN_DIR" ) ?  rtrim( WP_PLUGIN_DIR, '/\\' ) . "/domain-mapping/inc/sunrise.php" : dirname( __FILE__ ) .  "/plugins/domain-mapping/inc/sunrise.php" ),
	"md_sunrise" => defined("MD_CUSTOM_SUNRISE") ? MD_CUSTOM_SUNRISE : ( defined( "WP_PLUGIN_DIR" ) ?  rtrim( WP_PLUGIN_DIR, '/\\' ) . "/multi-domains/inc/sunrise.php" : dirname( __FILE__ ) .  "/plugins/multi-domains/inc/sunrise.php" )
);

foreach( $sunrises as $sunrise ){
	if( is_readable( $sunrise ) ){
		include $sunrise;
	}
}