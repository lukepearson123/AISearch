<html>
  <div class="container">
  <script>
    function getCourses()
    {
      var xmlhttp = new XMLHttpRequest();
      xmlhttp.onreadystatechange = function() {
        if(this.readyState == 4 && this.status == 200){
          document.getElementById("courseParagraph").innerHTML = this.responseText;
        }
      };
      xmlhttp.open("GET","getcourses.php", true);
      xmlhttp.send();
    }
  </script>
  <div class="config">
    <h1 class = "title">Configuration</h1>
    <form action = "train.php">
      <input type="button" value="Get Courses" onclick="getCourses()">
    </form>
    <p class= "courses" id="courseParagraph"></p>
    <form action = "train.php" method = "POST">
      <label for = "layers">Layer data</label>
      <input type = "text" id = "layers" name = "layers" value = "2,2" placeholder = "Layer1Size,Layer2Size,...">
      <br>
      <label for = "iters">Iteration count</label>
      <input type = "number" id = "iters" name = "iters" value = "1" placeholder = "iterations" min = 1>
      <br>
      <label for = "activ">Activation function</label>
      <select id = "activ" name = "activ">
        <option value = "sigmoid" name = "sigmoid">Sigmoid</option>
      </select>
      <br>
      <label for = "inputSize">Input Size</label>
      <input type = "number" id = "inputSize" name = "inputSize" value = "200" placeholder = "neurons" min = 1>
      <br>
      <input type = "submit" value = "Create Network"/>
    </form>
  </div>
</html>
