<?php

require "course.php";

set_time_limit(0);
ini_set('memory_limit', '-1');

$debug = true;

if ( $debug ) {

    error_reporting( E_ALL );

    ini_set( 'display_errors',  'On' );

}


/**
 * Used to retrieve course information from given database.
 */
class CourseRetriever
{
// ---------- VARIABLES ----------
    
    private $db;

// ---------- PUBLIC FUNCTIONS ----------
    /**
     * @param $database: name of database to be accessed
     * @param $user: name of user to access database as
     * @param $password: password of user accessing database
     */
    public function __construct()
    {
        $this->db = new PDO("mysql:host=localhost;dbname=moodle_data","php_user","password");
    }
    /**
    * Gets all course data from database as Course classes
    * @return  array of Course classes
    */
    public function getCourses(bool $identifierOnly = false, array $material) : array
    {
        $courses = [];
        //create new course class for each course found in mdl_course
        try
        {
            foreach($this->db->query("SELECT id,fullname FROM mdl_course") as $row)
            {
                $courses[] = new Course(trim($row['id']), $row['fullname']);
            }
        }
        catch(PDOException $e)
        {
            print "Error!: " . $e->getMessage() . PHP_EOL;
            die();
        }
        if(!$identifierOnly) foreach($courses as $course) $this->loadRestofData($course, $material);
        return $courses;
    }

    public function getName(string $id) : string
    {
        try
        {
            foreach($this->db->query("SELECT fullname FROM mdl_course WHERE id = '$id'") as $result)
            {
                return $result['fullname'];
            }
            return null;
        }
        catch(PDOException $e)
        {
            print "Error!: " . $e->getMessage() . PHP_EOL;
            die();
        }
    }

    private function loadRestofData(Course &$course, array $material)
    {
        foreach($material as $mat)
        {
            switch($mat)
            {
                case "ques":
                    $this->setQuestionsAndAnswersData($course);
                    break;
                case "ans":
                    $this->setQuestionsAndAnswersData($course);
                    break;
                case "tran":
                    $this->setTranscriptData($course);
                    break;
                case "pdfs":
                    $this->setTextData($course);
                    break;
            }
        }
    }

// ---------- PRIVATE FUNCTIONS ----------
   
    /**
     * Loads questions and answers data into given CourseData class
     * @param $cd: CourseData class
     */
    private function setQuestionsAndAnswersData(Course &$course)
    {
        $ID = $course->getId();
        //get id for course in question categories
        $courseQuestionCategory = $this->db->query("SELECT id FROM mdl_question_categories WHERE name in (SELECT shortname FROM mdl_course WHERE id = '$ID')");
        
        //get question categories id related to that course's question category id
        $questionCategories = $this->db->query("SELECT id FROM mdl_question_categories WHERE parent = '$ID'");
        //get question bank entries for every question category
        $questionBankEntries = [];
        foreach($questionCategories as $category)
        {
            $id = $category['id'];
            $questionBankEntries[] = $this->db->query("SELECT id FROM mdl_question_bank_entries WHERE questioncategoryid = '$id'");
        }
        //get question ids for every question bank entry
        $questionIDs = [];
        foreach($questionBankEntries as $category)
        {
            foreach($category as $qBank)
            {
                $qBankID = $qBank['id'];
                $questionIDs[] = $this->db->query("SELECT questionid FROM mdl_question_versions WHERE questionbankentryid = '$qBankID'");
            }
        }
        //get questions and answers for every questionid
        $questionsRows = [];
        $answersRows = [];
        foreach($questionIDs as $questionIDROWs)
        {
            foreach($questionIDROWs as $questionIDRow)
            {
                $questionID = $questionIDRow['questionid'];
                $questionsRows[] = $this->db->query("SELECT questiontext FROM mdl_question WHERE id = '$questionID'");
                $answersRows[] = $this->db->query("SELECT answer,fraction FROM mdl_question_answers WHERE question = '$questionID'");
            }
        }
        //set cd questions as questions with html stripped
        $questions = [];
        foreach($questionsRows as $questionRow)
        {
            foreach($questionRow->fetchAll(PDO::FETCH_COLUMN,0) as $row)
            {
                if($row <> "0" && $row <> "1")
                {
                    $questions[] = strip_tags($row);
                }
            }
        }
        $course->setQuestions($questions);
        //set cd answers as answers with html stripped
        $answers = [];
        foreach($answersRows as $answerRow)
        {
            foreach($answerRow as $row)
            {
                if($row['fraction']>0)
                {
                    $answers[] = strip_tags($row['answer']);
                }
            }
        }
        $course->setAnswers($answers);
    }

    /**
     * Loads text data into given CourseData class
     * @param $cd: CourseData class
     */
    private function setTextData(Course &$course)
    {
        $ID = $course->getId();
        $text = [];
        //file get contents
        foreach($this->db->query("SELECT intro FROM mdl_mpiricalpdf WHERE course = '$ID'") as $row)
        {
            if($row['intro']=="") continue;
            $someText = preg_replace('/[^.\w]/',' ',strip_tags($row['intro']));
            $text[] = str_replace(" . ", "" ,(str_replace("..","",$someText)));
        }
        $course->setText($text);
    }

    /**
     * Loads transcript data into given CourseData class
     * @param $cd: CourseData class
     */
    private function setTranscriptData(Course &$course)
    {
        $ID = $course->getId();
        //get vimeo_subtitle
        $transcripts = [];
        foreach($this->db->query("SELECT vimeo_subtitle FROM mdl_vimeo WHERE course = '$ID'")->fetchAll(PDO::FETCH_COLUMN,0) as $row)
        {
            $transcripts[] = $this->stripTranscript($row);//strip to get raw text
        }
        //set cd transcript data by video
        $course->setTranscripts($transcripts);
    }

    /**
     * Strips given transcript string of its vimeo meta data
     * @param $inp: input transcript string
     * @return stripped transcript string
     */
    private function stripTranscript(string $inp) : string
    {   
        $stripped = "";
        $lines = explode("\n",$inp);
        foreach($lines as $line)
        {
            if($line==="\r") continue;
            if(is_numeric($line)) continue;
            if($line[2]==':' && $line[5]==':' && $line[8]=='.') continue;
            $stripped .= $line;
        }
        return $stripped;
    }
}