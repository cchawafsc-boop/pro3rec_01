<<?php
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
    <?php if (!empty($_SESSION['lotid'])): ?>
    <p style="color:#1a6e1a; font-weight:bold; font-size:1.05em;">
      Lot ID : <?php echo htmlspecialchars($_SESSION['lotid']); ?>
    </p>
    <?php endif; ?>
    <p> <button type="button"   id="Nie2_BfIndexBtn"   onclick="goBfIndex()">กลับหน้าเลือก lot</button>
        <button type="button"   id="Nie2_HistBtn"      onclick="goHist()">ประวัติ Lot Card </button> </p>
    <p> <button type="button"   id="์Nie2_Proc01_Btn"   onclick="goProc01()">1. Receiving  </button> </p>
    <p> <button type="button"   id="์Nie2_Proc02_Btn"   onclick="goProc02()">2. Incoming   </button> </p>
    <p> <button type="button"   id="์Nie2_Proc03_Btn"   onclick="goProc03()">3. Racking    </button> </p>
    <p> <button type="button"   id="์Nie2_Proc04_Btn"   onclick="goProc04()">4. Plating    </button> </p>
    <p> <button type="button"   id="์Nie2_Proc05_Btn"   onclick="goProc05()">5. Inspection </button> </p>
    <p> <button type="button"   id="์Nie2_Proc06_Btn"   onclick="goProc06()">6. QAoutgoing </button> </p>
        
  </div>

  <?php mysqli_close($conn);   // ปิดฐานข้อมูล
  ?>

  <script>
    function goBfIndex() {
      window.location.href = "./nie2_before_index.php";
    }
    function goHist() {
      window.location.href = "./nie2_lcard_index.php";
    }
    function goProc01() {
      window.location.href = "./nie2_proc01.php";
    }
    function goProc02() {
      window.location.href = "./nie2_proc02.php";
    }
    function goProc03() {
      window.location.href = "./nie2_proc03.php";
    }
    function goProc04() {
      window.location.href = "./nie2_proc04.php";
    }
    function goProc05() {
      window.location.href = "./nie2_proc05.php";
    }
    function goProc06() {
      window.location.href = "./nie2_proc06.php";
    }
  </script>
  
</body>
</html>

