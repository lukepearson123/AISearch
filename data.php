<?php

interface Data
{
    function getData() : string;
    function getPath() : string;
    function isValid() : bool;
}

class NetworkData implements Data
{
    public string $path = "network//meta.txt";
    public int $inputSize;
    public array $layers;
    public int $iterations;
    public string $activation;
    public array $targetSet;

    function getData() : string
    {
        $retString = $this->inputSize . "\n";
        foreach($this->layers as $layer)
        {
            $retString .= $layer . ",";
        }
        $retString = substr($retString, 0, -1);
        $retString .= "\n" . $this->iterations . "\n" . $this->activation . "\n";

        foreach($this->targetSet as $target)
        {
            $retString .= $target . ",";
        }
        $retString = substr($retString, 0, -1);

        return $retString;
    }

    function getPath() : string
    {
        return $this->path;
    }

    function isValid() : bool
    {
        if($this->inputSize < 1) return false;
        foreach($this->layers as $layer) if($layer < 1) return false;
        if($this->iterations < 1) return false;
        if(!in_array($this->activation,["sigmoid","binary-step","gaussian","tanh","PReLU","thresholded-ReLU"])) return false;
        if(count($this->targetSet) < 2) return false;
        return true;
    }
}

class StorageData implements Data
{
    public string $path = "samples//meta.txt";
    public int $wordCount;
    public array $courses;
    public array $material;
    public int $trainingPercentage;

    function getData() : string
    {
        $retString = $this->wordCount . "\n";
        foreach($this->courses as $course)
        {
            $retString .= $course . ",";
        }
        $retString = substr($retString, 0, -1);
        $retString .= "\n";

        foreach($this->material as $mat)
        {
            $retString .= $mat . ",";
        }
        $retString = substr($retString, 0, -1);
        $retString .= "\n";

        $retString .= $this->trainingPercentage;

        return $retString;
    }

    function getPath() : string
    {
        return $this->path;
    }

    function isValid() : bool
    {
        if($this->wordCount < 1) return false;
        if(count($this->courses) < 2) return false;
        if(count($this->material) < 1) return false;
        if($this->trainingPercentage <= 0|| $this->trainingPercentage > 100) return false;
        return true;
    }

}

class MetaData implements Data
{
    public string $path = "search//meta.txt";
    public string $state;

    function getData() : string
    {
        return $this->state;
    }

    function getPath() : string
    {
        return $this->path;
    }

    function isValid() : bool
    {
        if(!in_array($state, AISearch::STATES)) return false;
        return true;
    }
}
