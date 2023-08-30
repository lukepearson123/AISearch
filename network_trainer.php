<?php

require "network.php";
require "sample_data.php";

include 'vendor/autoload.php';

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
use Phpml\ModelManager;

set_time_limit(0);
ini_set('memory_limit', '-1');

$debug = true;

if ( $debug ) {

    error_reporting( E_ALL );

    ini_set( 'display_errors',  'On' );

}

/**
 * Class to train a network based on data from CourseRetriever
 */
class NetworkTrainer
{
// ---------- VARIABLES ----------
    private Network $network;
    private CourseRetriever $courseRetriever;
    private int $chunkSize;
    private array $trainingSamples, $trainingTargets;
    private ?array $testingSamples, $testingTargets;
    
    private bool $trainable,$trained;
// ---------- PUBLIC FUNCTIONS ----------
    /**
     * @param layers
     * @param chunkSize
     * @param iterations
     * @param activationfunction
     */
    public function __construct(array $layers, int $chunkSize, int $iterations, string $af, array $material, float $percentage)
    {
        //init variables
        $this->chunkSize = $chunkSize;
        $ds = new DataSampler($chunkSize, $material, $percentage);
        [$this->trainingSamples, $this->trainingTargets, $this->testingSamples, $this->testingTargets] = $ds->getSamples();

        $targetSet = $ds->getTargetSet();
        //$trainingSamples
        if($this->isNetworkMetaMatched($layers, $iterations, $af, $targetSet)) 
        {
            $this->network =  new Network();
            $this->trainable = true;
            $this->trained = true;
            echo "network meta matches<br>";
        }
        else
        {
            //convert given activation function string to corresponding ActivationFunction
            $activation;
            switch($af)
            {
                case "sigmoid":
                    $activation = new Sigmoid;
                    break;
                case "binary-step":
                    $activation = new BinaryStep;
                    break;
                case "gaussian":
                    $activation = new Gaussian;
                    break;
                case "tanh":
                    $activation = new HyperbolicTangent;
                    break;
                case "PReLU":
                    $activation = new PReLU;
                    break;
                case "thresholded-ReLU":
                    $activation = new ThreshholdedReLU;
                    break;
                default:
                    echo "Activation function not found. ";
                    break;
            }
            //create network if parameters are valid
            if($this->chunkSize>0 && count($targetSet)>1 && $iterations > 0 && $activation !== null)
            {
                $this->network = new Network(count($this->trainingSamples[0]),$targetSet,$iterations,$activation,$layers);
                $this->setNetworkMeta($layers, $iterations, $af, $targetSet);
                $this->trainable = true;
                $this->trained = false;
            }    
        }
    }

    /**
     * returns whether or not the network is trainable
     * @return bool: trainable
     */
    public function isTrainable() : bool
    {
        return $this->trainable;
    }

    public function isTrained() : bool
    {
        return $this->trained;
    }

    /**
     * train the network with samples and targets
     */
    public function trainNetwork()
    {
        $this->network->train($this->trainingSamples,$this->trainingTargets);
        $this->network->save();
    }

    public function testNetwork()
    {
        for($i = 0;$i < count($this->testingSamples);$i++)
        {
            $prediction = $this->network->predict($this->testingSamples[$i]);
            if($prediction==$this->testingTargets[$i])
            {
                echo $this->testingTargets[$i] . " gives " . $prediction . "correct! <br>";
            }
            else
            {
                echo $this->testingTargets[$i] . " gives " . $prediction . "incorrect! <br>";
            }
        }
    }

    private function isNetworkMetaMatched(array $layers, int $iterations, string $af, array $targetSet)
    {
        $metaFile = fopen("network//meta.txt","r") or die("Unable to open meta file");
        $metaData = fread($metaFile,filesize("network//meta.txt"));
        fclose($metaFile);
        $metaPoints = explode("\n",$metaData);
        $matches = true;
        if($metaPoints[0] != implode(",",$layers)) $matches = false;
        if($metaPoints[1] != $iterations) $matches = false;
        if($metaPoints[2] != $af) $matches = false;
        if($metaPoints[3] != implode(",",$targetSet)) $matches = false;

        return $matches;
    }

    private function setNetworkMeta(array $layers, int $iterations, string $af, array $targetSet)
    {
        $metaFile = fopen("network//meta.txt","w");
        fwrite($metaFile, implode(",",$layers) . "\n"  . $iterations . "\n" . $af . "\n" . implode(",",$targetSet));
        fclose($metaFile);
    }
}