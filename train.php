<?php
    [$layers, $iterations, $activation, $inputSize] = [$_POST['layers'],$_POST['iters'],$_POST['activ'],$_POST['inputSize']];
    
    $debug = true;

if ( $debug ) {

    error_reporting( E_ALL );

    ini_set( 'display_errors',  'On' );

}
?>

<html>
    <div class="train">
      <h1 class = "title">Training</h1>
      <form action="test.php" method = "POST">
        <input type = "hidden" name = "layers" value = "<?php echo $layers?>">
        <input type = "hidden" name = "iters" value = "<?php echo $iterations?>">
        <input type = "hidden" name = "activ" value = "<?php echo $activation?>">
        <input type = "hidden" name = "inputSize" value = "<?php echo $inputSize?>">
        <input type="checkbox" id="questions" name="ques" value="ques">
        <label for="questions"> Use questions</label><br>
        <input type="checkbox" id="answers" name="ans" value="ans">
        <label for="answers"> Use answers</label><br>
        <input type="checkbox" id="transcripts" name="tran" value="tran">
        <label for="transcripts"> Use transcripts</label><br>
        <input type="checkbox" id="pdfs" name="pdfs" value="pdfs">
        <label for="pdfs"> Use pdfs</label><br>
        <input type="range" id="trainingPerc" name="trainingPerc" min = "0" max = "100">
        <label for="trainingPerc">Training Percentage</label>
        <br>
        <input type="submit" value="Train">
      </form>
    </div>
</html>