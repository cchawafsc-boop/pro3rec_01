<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0" >
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
</head>
<body>
  <?php
    session_start();
    require('../connect.php');
    require('../init_session.php');
    require('../topbar.php');
  ?>
  
  <!-- Main menu -->
  <div class="main-menu">
    <h2> This is nie_index.php </h2>
  </div>

  <?php mysqli_close($conn);   // ปิดฐานข้อมูล
  ?>
  
</body>
</html>
