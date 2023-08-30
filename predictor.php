<?php

require "ai_search.php";

$ai = new AISearch;

$ai->primePredictor();

$line = "";
while(trim($line) != "exit")
{
   $handle = fopen ("php://stdin","r");
   $line = fgets($handle);
   
   $start = microtime(true);

   $prediction = $ai->predict(trim($line));
   
   $end = microtime(true);

   echo ($end - $start) . " seconds.\n";

   echo  $prediction . PHP_EOL;
}