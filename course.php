<?php
class Course
{
    private $id;
    private $name;
    private $questions = [];
    private $answers = [];
    private $transcripts = [];
    private $text = [];

    function __construct($id, $name = null, $questions = null, $answers = null, $transcripts = null, $text = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->questions = $questions;
        $this->answers = $answers;
        $this->transcripts = $transcripts;
        $this->text = $text;
    }

    function getData() : array
    {
        $retArray = [];
        if($this->questions != null) $retArray = array_merge($retArray, $this->questions);
        if($this->answers != null) $retArray = array_merge($retArray, $this->answers);
        if($this->transcripts != null) $retArray = array_merge($retArray, $this->transcripts);
        if($this->text != null) $retArray = array_merge($retArray, $this->text);

        return $retArray;
    }

    function __call($method, $params)
    {
        $var = lcfirst(substr($method, 3));

        if(strncasecmp($method, "get", 3) === 0) return $this->$var;
        if(strncasecmp($method, "set", 3) === 0) $this->$var = $params[0];
    }

    function isComplete() : bool
    {
        return ($this->id && $this->name && $this->questions && $this->answers && $this->transcripts && $this->text); 
    }
}