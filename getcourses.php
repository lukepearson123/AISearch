<?php

include "moodle_data.php";

$cr = new CourseRetriever();
foreach($cr->getCourses(true) as $course)
{
    echo "<li>" . $course->getName() . "<br>";
}