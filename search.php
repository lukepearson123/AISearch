<?php
require "ai_search.php";
$cr = new CourseRetriever;
$ai = new AISearch;
$prediction = $ai->predict(trim($_GET["q"]));
echo $prediction. ": " . $cr->getName($prediction);
?>