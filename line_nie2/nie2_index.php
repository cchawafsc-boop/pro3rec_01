<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0" >
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="./style01.css">
</head>
<body>
  <?php
    require('topbar.php');
  ?>
  
  <!-- Main menu -->
  <div class="main-menu">
    <h2> This is nie_index.php </h2>
  </div>

  <?php mysqli_close($conn);   // ปิดฐานข้อมูล
  ?>
  
  <script>
    function goPro1Lotcard() {
      window.location.href = "./line_nie2/nie2_index.php";
    }
  </script>
</body>
</html>
