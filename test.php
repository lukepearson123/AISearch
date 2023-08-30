<?php
    //require "network_trainer.php";
    //store form inputs
    //[$layers, $iterations, $activation, $inputSize, $useQuestions, $useAnswers, $useTranscripts, $usePDFs, $trainingPercentage] = [$_POST['layers'],$_POST['iters'],$_POST['activ'],$_POST['inputSize'],$_POST['ques'],$_POST['ans'],$_POST['tran'],$_POST['pdfs'],$_POST['trainingPerc']];
    //format layers to array
    //$layersFormatted = array_map("intval",explode(",",$layers));
?>

<html>
    <script>
        function predict(inp)
        {
            document.getElementById("prediction").innerHTML = "predicting...";
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if(this.readyState == 4 && this.status == 200)
                {
                  document.getElementById("prediction").innerHTML = this.responseText;
                }
            };
            xmlhttp.open("GET","search.php?q="+inp, true);
            xmlhttp.send();
        }
        function getAccuracy()
        {
            document.getElementById("accuracy").innerHTML = "calculating...";
            var xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if(this.readyState == 4 && this.status == 200)
                {
                  document.getElementById("accuracy").innerHTML = this.responseText;
                }
            };
            xmlhttp.open("GET","accuracy.php", true);
            xmlhttp.send();
        }

    </script>
    <div class="test">
      <h1 class = "title">Testing</h1>
      <form>
        <input type="text" id = "input">
        <input type="button" onClick="predict(input.value)" value="predict">
      <p id="prediction"></p>
      <input type = "button" onClick="getAccuracy()" value="Show Accuracy">
      <p id="accuracy"></p>
    </div>
</html>