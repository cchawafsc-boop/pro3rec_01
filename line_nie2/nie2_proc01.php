<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodName = $_POST['ProdName'];
        $invNo    = $_POST['InvNo'];
        $wo       = $_POST['WO'];
        $subLot   = $_POST['SubLot'];
        $date     = $_POST['Date'];
        $time     = $_POST['Time'];
        $opr      = $_POST['Opr'];
        $appCheck = $_POST['AppCheck'];
        $boxQty   = (int)$_POST['BoxQty'];
        $boxJudge = $_POST['BoxJudge'];
        $lotID    = $_POST['LotID'];
        $lotIDFull= $lotID."_".$date."_".$time;
        $done_f   = 'no';

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc1` (`ProdName`,`InvNo`,`WO`,`SubLot`,`Date`,`Time`,`Opr`,`AppCheck`,`BoxQty`,`BoxJudge`,`LotID`,`DoneFlag`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "ssssssssisss", $prodName, $invNo, $wo, $subLot, $date, $time, $opr, $appCheck, $boxQty, $boxJudge, $lotIDFull, $done_f);
        $req = mysqli_stmt_execute($stmt);
        if ($req) {
            echo "<script>alert('บันทึกข้อมูลสำเร็จ'); location='./nie2_index.php';</script>";
        } else {
            echo "<script>alert('บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่');</script>";
        }
        mysqli_close($conn);
    }
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0">
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="form-pro3-proc1">
    <h2>1 Receiving — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc1-g">

        <div class="pro3-proc1-g-it"><label>Product name from Lot Tag</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="ProdName_LotTag" autofocus required>
        </div>

        <div class="pro3-proc1-g-it"><label>Product name from Travelling Sheet</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="ProdName" required>
        </div>

        <div class="pro3-proc1-g-it"><label>WO from Lot Tag</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="WO_LotTag" required>
        </div>

        <div class="pro3-proc1-g-it"><label>WO from Traveling Sheet </label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="WO" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Invoice no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="InvNo" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Sub lot no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="SubLot" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Date</label></div>
        <div class="pro3-proc1-g-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Time</label></div>
        <div class="pro3-proc1-g-it">
          <input type="time" name="Time" value="<?php echo date('H:i'); ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Operator</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" readonly required>
        </div>

        <div class="pro3-proc1-g-it"><label>App check</label></div>
        <div class="pro3-proc1-g-it">
          <select name="AppCheck" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="OK">OK</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <div class="pro3-proc1-g-it"><label>Box quantity</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="BoxQty" min="0" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Box judge</label></div>
        <div class="pro3-proc1-g-it">
          <select name="BoxJudge" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="OK">OK</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <div class="pro3-proc1-g-it"><label>Lot ID</label></div>
        <div class="pro3-proc1-g-it">
          <select name="LotID" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="L01">L01</option>
            <option value="L02">L02</option>
            <option value="L03">L03</option>
            <option value="L04">L04</option>
            <option value="L05">L05</option>
            <option value="L06">L06</option>
            <option value="L07">L07</option>
            <option value="L08">L08</option>
            <option value="L09">L09</option>
            <option value="L10">L10</option>
          </select>
        </div>
      </div>

      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
        <button type="submit" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php if (!isset($req)) { mysqli_close($conn); } ?>
</body>
</html>
