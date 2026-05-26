<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0" >
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
</head>
<body>
  <?php
    require('../topbar.php');
  ?>
  
  <!-- Main menu -->
  <div class="main-menu">
    <h2> ระบบการผลิต Ni-e line 2 (Full-Auto) </h2>
    <p> <button type="button"   id="Nie2_HomeBtn"      onclick="goHome()">กลับหน้าหลัก</button>
        <button type="button"   id="Nie2_HistBtn"      onclick="goHist()">ประวัติ Lot Card </button> </p>
    <p> <button type="button"   id="์Nie2_Proc01_Btn"   onclick="goProc01()">1 Receiving  </button> </p>
    <p> <button type="button"   id="์Nie2_Proc02_Btn"   >2 Incoming   </button> </p>
    <p> <button type="button"   id="์Nie2_Proc03_Btn"   >3 Racking    </button> </p>
    <p> <button type="button"   id="์Nie2_Proc04_Btn"   >4 Plating    </button> </p>
    <p> <button type="button"   id="์Nie2_Proc05_Btn"   >5 Unracking  </button> </p>
    <p> <button type="button"   id="์Nie2_Proc06_Btn"   >6 Inspection </button> </p>
    <p> <button type="button"   id="์Nie2_Proc07_Btn"   >7 QAoutgoing </button> </p>
        
  </div>

  <?php mysqli_close($conn);   // ปิดฐานข้อมูล
  ?>

  <script>
    function goHome() {
      window.location.href = "../index.php";
    }
    function goHist() {
      window.location.href = "./lotcardhist.php";
    }
    function goProc01() {
      window.location.href = "./nie2_proc01.php";
    }
  </script>
  
</body>
</html>
