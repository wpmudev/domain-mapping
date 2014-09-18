<?php
$dm_sunrise =  __DIR__ . "/plugins/domain-mapping/inc/sunrise.php";
if( is_readable( $dm_sunrise) ){
  include $dm_sunrise;
}