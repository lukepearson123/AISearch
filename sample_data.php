<?php

include 'vendor/autoload.php';

require "moodle_data.php";

use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\NeuralNetwork\ActivationFunction;
use Phpml\NeuralNetwork\ActivationFunction\Sigmoid;
use Phpml\NeuralNetwork\ActivationFunction\BinaryStep;
use Phpml\NeuralNetwork\ActivationFunction\Gaussian;
use Phpml\NeuralNetwork\ActivationFunction\HyperbolicTangent;
use Phpml\NeuralNetwork\ActivationFunction\PReLU;
use Phpml\NeuralNetwork\ActivationFunction\ThresholdedReLU;

set_time_limit(0);
ini_set('memory_limit', '-1');

$debug = true;

if ( $debug ) {

    error_reporting( E_ALL );

    ini_set( 'display_errors',  'On' );

}

/**
 * Samples data from database with given parameters. if same parameters used last time, get samples from memory. always saves previous parameters and samples.
 */
class DataSampler
{

    private $chunkSize;
    private $courses;
    private $dataPointStrings;
    private $dataPoints;
    private $trainingPercentage;
    private $targetSet = [];
    private $stringCourses = "";
    private $sampleData;
    private $metaMatches = true;

    //dat files, even index -> samples, odd index -> targets
    private $sourceFiles = ["course_training_samples","course_training_targets","course_testing_samples","course_testing_targets"];

    /**
     * create, load and sample database with given parameters.
     * @param chunkSize: how many words each sample represents
     * @param dataPoints: which resources should be sampled
     * @param trainingPercentage: how much of the samples should be for training
     */
    public function __construct(int $chunkSize, array $dataPoints, float $trainingPercentage)
    {
        //init variables
        $this->chunkSize = $chunkSize;
        $this->dataPoints = $dataPoints;
        $this->trainingPercentage = $trainingPercentage;

        //get courses
        $cr = new CourseRetriever;
        $this->courses = $cr->getCourses(true);
        
        //generate targetset
        foreach($this->courses as $course)
        {
            $this->targetSet[] = trim($course->getId());
        }
        
        //get meta data
        $metaFile = fopen("samples//meta.txt","r") or die("Unable to open meta file");
        $metaData = fread($metaFile,filesize("samples//meta.txt"));
        fclose($metaFile);
        $metaPoints = explode("\n",$metaData);
        $metaData = [];

        //verify chunkSize
        if($metaPoints[0] != $chunkSize)
        {
            $this->metaMatches = false;
        } 
        
        //verify courses
        foreach($this->courses as $course)
        {
            $this->stringCourses .= $course->getId() . ",";
        }
        if(substr($this->stringCourses, -1) == ",")
        {
            $this->stringCourses = substr($this->stringCourses, 0, -1);
        }
        if(trim($metaPoints[1]) != $this->stringCourses) $this->metaMatches = false;


        //verify datapoints
        $this->dataPointStrings = implode(",",array_filter($dataPoints));
        if(trim($metaPoints[2]) != $this->dataPointStrings) $this->metaMatches = false;
        
        //verify trainingPercentage
        if($metaPoints[3] != $this->trainingPercentage) $this->metaMatches = false;
        
        //if not matching meta, store and retrieve courses else just retrieve
        if(!$this->metaMatches)
        {
            echo "this not meta";
            foreach($this->courses as $course) $cr->loadRestofData($course);
            $this->storeAndRetrieveCourses();
        }
        else
        {
            $this->retrieveCourses();
        }
    }

    /**
     * Get samples created during initialization.
     */
    public function getSamples() : array
    {
        foreach($this->sampleData as $sample) if($sample === null) $sample = [];
        return $this->sampleData;
    }
    /**
     * Get set of targets generated during initialization.
     */
    public function getTargetSet() : array
    {
        return $this->targetSet;
    }

    public function hasSavedNetwork() : bool
    {
        return $this->metaMatches && file_exists("network\\network.txt");
    }

    /**
     * set sample data from memory.
     */
    private function retrieveCourses()
    {
        //foreach source file, get data
        foreach($this->sourceFiles as $id=>$file)
        {
            $file = fopen("samples//" . $file . ".txt", "r") or die("Unable to open " . $file .".txt");
            
            //if sample file, convert from string to float array
            if($id % 2 == 0)
            {
                while(($line = fgets($file)) !== false)
                {
                    $this->sampleData[$id][] = array_map("floatval",explode(",",$line));
                }
            }
            else //if target file, store as trimmed string
            {
                while(($line = fgets($file)) !== false)
                {
                    $this->sampleData[$id][] = trim($line);
                }
            }
            fclose($file);
        }
    }

    /**
     * store sampleData in memory
     */
    private function storeAndRetrieveCourses()
    {
        $this->vectorize();
        

        foreach($this->sourceFiles as $id=>$file)
        {
            $file = fopen("samples//" . $file . ".txt", "w");
                    
            if($id % 2 == 0)
            {
                foreach($this->sampleData[$id] as $sample)
                {
                    $stringSample = "";
                    foreach($sample as $element)
                    {
                        $stringSample .= $element . ",";
                    }
                    fwrite($file, $stringSample . "\n");
                }
            }
            else
            {
                foreach($this->sampleData[$id] as $sample)
                {
                    fwrite($file, $sample . "\n");
                }
            }
            fclose($file);
        }

        $metaFile = fopen("samples//meta.txt","w");
        fwrite($metaFile, $this->chunkSize . "\n" . $this->stringCourses . "\n" . $this->dataPointStrings . "\n" . $this->trainingPercentage);
        fclose($metaFile);
    }

    private function vectorize()
    {
        $trainingSamples = [];
        $trainingTargets = [];
        $testingSamples = [];
        $testingTargets = [];

        foreach($this->courses as $course)
        {
            $courseSamples = [];
            $courseTargets = [];
            $data = [];
            foreach($this->dataPoints as $dataPoint)
            {
                if($dataPoint == "ques")
                {
                    $data = array_merge($data, $course->getQuestions());
                }
                if($dataPoint == "ans")
                {
                    $data = array_merge($data, $course->getAnswers());
                }
                if($dataPoint == "tran")
                {
                    $data = array_merge($data, $course->getTranscripts());
                }
                if($dataPoint == "pdfs")
                {
                    $data = array_merge($data, $course->getText());
                }
            }
            
            $limit = 99999;
            $limitCount = 0;
            foreach($data as $dataBit)
            {
                if($limitCount > $limit)
                {
                    break;
                }
                $limitCount++;
                $count = 0;
                $currSample = "";

                foreach(array_chunk(explode(" ",$dataBit), $this->chunkSize) as $chunk)
                {
                    $courseSamples[] = join(" ", $chunk);
                    $courseTargets[] = $course->getId();
                }

            }

            $samplesCount = count($courseSamples);

            $trainingAmount = $this->trainingPercentage * 0.01 * $samplesCount;

            $chunkedSamples = array_chunk($courseSamples, $trainingAmount);
            $chunkedTargets = array_chunk($courseTargets, $trainingAmount);
            if(count($chunkedSamples)>2)
            {
                $mergedSamps = [];
                $mergedTargs = [];
                for($i = 1;$i < count($chunkedSamples);$i++)
                {
                    $mergedSamps = array_merge($mergedSamps, $chunkedSamples[$i]);
                    $mergedTargs = array_merge($mergedTargs, $chunkedTargets[$i]);
                }
                $chunkedSamples = [$chunkedSamples[0], $mergedSamps];
                $chunkedTargets = [$chunkedTargets[0], $mergedTargs];
            }
            
            $trainingSamples = array_merge($trainingSamples, $chunkedSamples[0]);
            if($this->trainingPercentage != 100) $testingSamples = array_merge($testingSamples, $chunkedSamples[1]);

            $trainingTargets = array_merge($trainingTargets, $chunkedTargets[0]);
            if($this->trainingPercentage != 100) $testingTargets = array_merge($testingTargets, $chunkedTargets[1]);
        }

        $vectorizer = new TokenCountVectorizer(new WhitespaceTokenizer());

        $samples = array_merge($trainingSamples,$testingSamples);

        //var_dump($trainingSamples);

        $vectorizer->fit($samples);
        $vectorizer->transform($trainingSamples);
        if($this->trainingPercentage != 100) $vectorizer->transform($testingSamples);

        $samples = array_merge($trainingSamples,$testingSamples);

        $transformer = new TfIdfTransformer($samples);
        $transformer->transform($trainingSamples);
        if($this->trainingPercentage != 100) $transformer->transform($testingSamples);

        $this->sampleData = [$trainingSamples,$trainingTargets,$testingSamples,$testingTargets];
    }

}