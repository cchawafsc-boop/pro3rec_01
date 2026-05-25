<?php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  session_start();
  require('connect.php');
  require('init_session.php');  
?>

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
    <h2> ระบบการผลิต Production 3 </h2>
    <p><button type="button"   id="M1_btn"     >M1-Line</button></p>
    <p><button type="button"   id="Nie1_btn"   >Ni-e Line 1 (Semi-Auto)</button></p>
    <p><button type="button"   id="Nie2_btn"   onclick="goNieLine2()">Ni-e Line 2 (Full-Auto)</button></p>
  </div>

  <?php mysqli_close($conn);   // ปิดฐานข้อมูล
  ?>

  <script>
    function goNieLine2() {
      window.location.href = "./line_nie2/nie2_index.php";
    }
  </script>
  
</body>
</html>
