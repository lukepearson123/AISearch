<?php
include 'vendor/autoload.php';

use Phpml\Classification\MLPClassifier;
use Phpml\NeuralNetwork\ActivationFunction\Sigmoid;
use Phpml\NeuralNetwork\ActivationFunction;
use Phpml\ModelManager;

class Network
{
    private $mlp;

    public function __construct(int $inputSize = null, array $classes = null, int $iters = null, ActivationFunction $af = null,array $layers = null)
    {
        if($inputSize && $classes && $iters && $af && $layers) $this->mlp = new MLPClassifier($inputSize, $layers, $classes, $iters, $af);
        else $this->load();
    }

    public function predict(array $inp) : string
    {
        return $this->mlp->predict($inp);
    }

    public function train(array $samples, array $targets)
    {
        $this->mlp->train($samples,$targets);
    }

    public function getClassifier()
    {
        return $this->mlp;
    }

    public function save()
    {
        $modelManager = new ModelManager();
        $modelManager->saveToFile($this->mlp,"network//network.txt");
    }

    public function load()
    {
        $modelManager = new ModelManager();
        $this->mlp = $modelManager->restoreFromFile("network//network.txt");
    }
}