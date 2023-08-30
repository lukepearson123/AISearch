<?php

require "ai_search.php";

set_time_limit(0);
ini_set('memory_limit', '-1');

$debug = true;

if ( $debug ) {

    error_reporting( E_ALL );

    ini_set( 'display_errors',  'On' );

}

$ai = new AISearch;

$start = microtime(true);

echo $ai->trainNetwork() . "\n";

$end = microtime(true);

echo ($end - $start) . " seconds.\n";
