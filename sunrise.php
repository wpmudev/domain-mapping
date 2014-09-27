<?php
$sunrises = array(
	"dm_sunrise" => defined( "WP_PLUGIN_DIR" ) ?  rtrim( WP_PLUGIN_DIR, '/\\' ) . "/domain-mapping/inc/sunrise.php" : dir( __FILE__ ) .  "/plugins/domain-mapping/inc/sunrise.php",
	"md_sunrise" => defined( "WP_PLUGIN_DIR" ) ?  rtrim( WP_PLUGIN_DIR, '/\\' ) . "/multi-domains/inc/sunrise.php" : dir( __FILE__ ) .  "/plugins/multi-domains/inc/sunrise.php"
);

foreach( $sunrises as $sunrise ){
  if( is_readable( $sunrise ) ){
	include $sunrise;
  }
}