<?php
$sunrises = array(
  "dm_sunrise" => __DIR__ . "/plugins/domain-mapping/inc/sunrise.php",
  "md_sunrise" => __DIR__ . "/plugins/multi-domains/inc/sunrise.php"
);

foreach( $sunrises as $sunrise ){
  if( is_readable( $sunrise ) ){
    include $sunrise;
  }
}
