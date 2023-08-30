<?php

include "vendor/autoload.php";
use Phpml\Dataset\FilesDataset;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\FeatureExtraction\StopWords\English;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Metric\Accuracy;
use Phpml\Pipeline;
use Phpml\Tokenization\NGramTokenizer;
use Phpml\ModelManager;
use Phpml\Classification\SVC;
use Phpml\SupportVectorMachine\Kernel;

require "moodle_data.php";
require "data.php";

set_time_limit(0);
ini_set('memory_limit', '-1');

class AISearch
{
    const STATES = ["nothing","loaded","trained"];

    private $networkData;
    private $storedData;
    private $metaData;
    private $network;

    /**
     * on construction, loads meta data for search, data and network
     */
    public function __construct()
    {
        $this->loadData();
    }

    public function getState() : string
    {
        return $this->metaData->state;
    }

    /**
     * If given network data is valid, set and store it.
     * @param $nd NetworkData to be be set and stored.
     */
    public function setNetworkData(NetworkData $nd)
    {
        if($nd->isValid())
        {
            $this->networkData = $nd;
            $this->saveData($this->networkData);
        }
    }


    /**
     * If given storage data is valid, set and store it.
     * @param $sd StorageData to be set and stored.
     */
    public function setStorageData(StorageData $sd)
    {
        if($sd->isValid())
        {
            $this->storedData = $sd;
            $this->saveData($this->storedData);
        }
    }

    public function doTFIDF()
    {   
        $pipeline = new Pipeline([
            new TokenCountVectorizer(new NGramTokenizer(1, 4), new English()),
            new TfIdfTransformer
        ]);
        $dataset = new FilesDataset("data/");
            
        $split = new StratifiedRandomSplit($dataset, $this->storedData->trainingPercentage/100);

        $count = count($split->getTestLabels());

        $file = fopen("samples//testing_samples.txt", "w");
        for($i = 0;$i < $count;$i++)
        {
            $writing = $split->getTestSamples()[$i];
            if($i < $count-1) $writing .= "";
            fwrite($file, $writing);
        }
        fclose($file);

        $file = fopen("samples//testing_targets.txt", "w");
        for($i = 0;$i < $count;$i++)
        {
            fwrite($file, $split->getTestLabels()[$i] . "\n");
        }
        fclose($file);

        $samples = $split->getTrainSamples();
        $pipeline->fit($samples);
        $pipeline->transform($samples);

        $modelManager = new ModelManager();
        $modelManager->saveToFile($pipeline, "network//vectorizer");

        $files = glob("samples//raw//*.txt"); // get all file names
        foreach($files as $file)
        { // iterate files
          if(is_file($file)) 
          {
            unlink($file); // delete file
          }
        }

        $counter = 0;
        foreach($samples as $sample)
        {
            $file = fopen("samples//raw//".$counter++.".txt","w");
            fwrite($file, implode(",",$sample));
            fclose($file);
        }

        echo $counter . "\n";

        $file = fopen("samples//targets.txt","w");
        fwrite($file, implode("\n",$split->getTrainLabels()));
        fclose($file);

        echo count($samples) . " " . count($split->getTrainSamples()) . " " . count($split->getTrainLabels()) . "\n";

        $this->metaData->state = "vectorized";
        $this->saveData($this->metaData);
    }

    /**
     * Retrieve, vectorize and train network before testing.
     * @param $nd NetworkData to be set.
     * @param $sd StorageData to be set.
     */
    public function trainAndTestNetwork(NetworkData $nd = null, StorageData $sd = null) : float
    {
        if(isset($nd)) $this->setNetworkData($nd);
        if(isset($sd)) $this->setStorageData($sd);

        $this->metaData->state = "nothing";
        $this->saveData($this->metaData);

        $this->readData();
        $this->trainNetwork();
        return $this->testNetwork();
    }

    /**
     * Load network ready for prediction.
     */
    public function primePredictor()
    {
        $modelManager = new ModelManager();
        $this->network = $modelManager->restoreFromFile("network//network");
    }

    /**
     * Predict course based on input. Calls primePredictor if not already called.
     * @param $inp input to be used to predict course.
     */
    public function predict(string $inp) : string
    {
        if(!isset($this->network))
        {
            $this->primePredictor();
        }
        return $this->network->predict([$inp])[0];
    }

    /**
     * If state = "nothing" then read data from database and store in chunks.
     */
    public function readData()
    {
        //data already read?
        if($this->metaData->state == "nothing" || true)
        {
            //write courses into data folder
            $courseRetriever = new CourseRetriever;
            $courses = $courseRetriever->getCourses(false,$material = $this->storedData->material);

            
            foreach($courses as $course)
            {
                if (!file_exists("data//".$course->getId())) {
                    echo "makin dir for " . $course->getId() . PHP_EOL;
                    mkdir("data//".$course->getId(), 0777, true);
                }
                $files = glob("data//".$course->getId()."//*.txt"); // get all file names
                foreach($files as $file)
                { // iterate files
                  if(is_file($file)) 
                  {
                    unlink($file); // delete file
                  }
                }
                $counter = 0;
                foreach($course->getData() as $dataBit)
                {
                  foreach(array_chunk(explode(" ",$dataBit), $this->storedData->wordCount) as $chunk)
                  {
                      $dataFile = fopen("data//".$course->getId()."//".++$counter.".txt","w");
                      fwrite($dataFile, str_replace("\r","",join(" ", $chunk)) . "\n");
                      fclose($dataFile);
                  }
                }
            }

            $this->metaData->state = "loaded";
            $this->saveData($this->metaData);
        }
        
    }

    /**
     * if state = "loaded" then create, train and store network.
     */
    public function trainNetwork()
    {
        if($this->metaData->state == "loaded")
        {
            $pipeline = new Pipeline([
                new TokenCountVectorizer(new NGramTokenizer(1, 4), new English()),
                new TfIdfTransformer
            ], new NaiveBayes);

            $dataset = new FilesDataset("data/");
            $split = new StratifiedRandomSplit($dataset, $this->storedData->trainingPercentage/100);
            echo "data loaded \n";
            unset($dataset);

            $samples = $split->getTestSamples();
            $labels = $split->getTestLabels();
            $count = count($labels);
            $file = fopen("samples//testing_samples.txt", "w");
            for($i = 0;$i < $count;$i++)
            {
                $writing = $samples[$i];
                if($i < $count-1) $writing .= "";
                fwrite($file, $writing);
            }
            fclose($file);
            $file = fopen("samples//testing_targets.txt", "w");
            for($i = 0;$i < $count;$i++)
            {
                fwrite($file, $labels[$i] . "\n");
            }
            fclose($file);

            unset($file);

            echo "test samples saved\n";

            $samples = $split->getTrainSamples();
            $labels = $split->getTrainLabels();

            unset($split);

            $pipeline->train($samples, $labels);

            echo $pipeline->predict(["crypto"])[0] . "\n";

            echo "network trained \n";

            unset($samples, $labels);

            $modelManager = new ModelManager();
            $modelManager->saveToFile($pipeline, "network//network");

            echo "network saved \n";

            $this->metaData->state = "trained";
            $this->saveData($this->metaData);
        }
        else if($this->metaData->state == "vectorized")
        {
            $samples = [];
            foreach (scandir("samples//raw//") as $fileName) 
            {
                if ($fileName !== '.' && $fileName !== '..') 
                {
                    $file = fopen("samples//raw//".$fileName, "r");
                    $samples[] = array_map("floatval",explode(",",fread($file, filesize("samples//raw//".$fileName))));
                    fclose($file);
                }
            }
            $file = fopen("samples//targets.txt","r");
            $targets = explode("\n", fread($file, filesize("samples//targets.txt")));

            echo count($samples) . " " . count($targets) . "\n";
            //$classifier = new SVC(Kernel::RBF, $cost = 10, $degree = 4, $gamma = 1, $coef0 = 0.0, $tolerance = 0.001, $cacheSize = 1000, $shrinking = true, $probabilityEstimates = false);
            $classifier = new NaiveBayes;
            $classifier->train($samples, $targets);
            echo "there!\n";
            $modelManager = new ModelManager();
            $model = $modelManager->restoreFromFile("network//vectorizer");

            $pipeline = new Pipeline($model->getTransformers(), $classifier);

            $modelManager = new ModelManager();
            $modelManager->saveToFile($pipeline, "network//network");

            $this->metaData->state = "trained";
            $this->saveData($this->metaData);
        }
    }

    /**
     * Test network against 
     */
    public function testNetwork() : float
    {
        if($this->metaData->state == "trained")
        {
            $modelManager = new ModelManager();
            $model = $modelManager->restoreFromFile("network//network");

            $file = fopen("samples//testing_samples.txt" , "r");
            $testingSamples = explode("\n",fread($file, filesize("samples//testing_samples.txt")));
            fclose($file);
            $file = fopen("samples//testing_targets.txt", "r");
            $testingTargets = explode("\n",fread($file, filesize("samples//testing_targets.txt")));
            fclose($file);

            $predicted = $model->predict($testingSamples);
            return Accuracy::score($testingTargets, $predicted);
        }
    }

    private function loadData()
    {
        $this->metaData = $this->getMetaData();
        $this->networkData = $this->getNetworkData();
        $this->storedData = $this->getStorageData(); 
    }

    private function saveData(Data $data)
    {
        $file = fopen($data->getPath(), "w") or die($data->getPath . " could not be opened.");
        fwrite($file, $data->getData());
        fclose($file);
    }

    private function getTrainingSamples() : array
    {
        $file = fopen("samples//train_samples.txt","r");
        $samples = [];
        foreach(explode("\n",fread($file, filesize("samples//train_samples.txt"))) as $stringSample)
        {
            $samples[] = array_map("floatval",explode(",",$stringSample));
        }
        fclose($file);
        return $samples;
    }

    private function getTrainingTargets() : array
    {
        $file = fopen("samples//train_targets.txt", "r");
        $targets = explode("\n",fread($file, filesize("samples//train_targets.txt")));
        fclose($file);
        return $targets;
    }

    private function getTestingSamples() : array
    {
        $file = fopen("samples//test_samples.txt","r");
        $samples = [];
        foreach(explode("\n",fread($file, filesize("samples//test_samples.txt"))) as $stringSample)
        {
            $samples[] = array_map("floatval",explode(",",$stringSample));
        }
        fclose($file);
        return $samples;
    }

    private function getTestingTargets() : array
    {
        $file = fopen("samples//test_targets.txt", "r");
        $targets = explode("\n",fread($file, filesize("samples//test_targets.txt")));
        fclose($file);
        return $targets;
    }

    private function saveSamplesAndTargets(array $trainSamples, array $trainTargets, array $testSamples, array $testTargets)
    {
        $file = fopen("samples//train_samples.txt", "w");
        foreach($trainSamples as $sample)
        {
            foreach($sample as $key => $coord)
            {
                if($key === array_key_last($sample)) fwrite($file, $coord . "\n");
                else fwrite($file, $coord . ",");
            }

        } 
        fclose($file);

        $file = fopen("samples//train_targets.txt", "w");
        foreach($trainTargets as $target) fwrite($file, $target . "\n");
        fclose($file);

        $file = fopen("samples//test_samples.txt", "w");
        foreach($testSamples as $sample)
        {
            foreach($sample as $key => $coord)
            {
                if($key === array_key_last($sample)) fwrite($file, $coord . "\n");
                else fwrite($file, $coord . ",");
            }

        } 
        fclose($file);

        $file = fopen("samples//test_targets.txt", "w");
        foreach($testTargets as $target) fwrite($file, $target . "\n");
        fclose($file);
    }

    private function getMetaData() : MetaData
    {
        $metaData = new MetaData;

        $data = $this->getFileData($metaData->path);
     
        $metaData->state = $data[0];

        return $metaData;
    }

    public function getStorageData() : StorageData
    {
        $storageData = new StorageData;

        $data = $this->getFileData($storageData->path);

        $storageData->wordCount = intval($data[0]);
        $storageData->courses = explode(",",$data[1]);
        $storageData->material = explode(",", $data[2]);
        $storageData->trainingPercentage = intval($data[3]);

        return $storageData;
    }

    public function getNetworkData() : NetworkData
    {
        $networkData = new NetworkData;

        $data = $this->getFileData($networkData->path);

        $networkData->inputSize = intval($data[0]);
        $networkData->layers = array_map("intval",explode(",",$data[1]));
        $networkData->iterations = intval($data[2]);
        $networkData->activation = $data[3];
        $networkData->targetSet = explode(",", $data[4]);

        return $networkData;
    }

    private function getFileData($filePath) : array
    {
        $file = fopen($filePath, "r") or die($filePath . " cannot be opened.");
        $data = fread($file, filesize($filePath));
        fclose($file);
        return explode("\n", $data);
    }
}


/*
$ai = new AISearch;
$ai->readData();
$ai->vectorizeData();
$ai->trainNetwork();
*/