<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    $lot_prodname = $lot_invno = $lot_wo = $lot_sublot = '';
    if (!empty($_SESSION['lotid'])) {
        $lstmt = mysqli_prepare($conn,
            "SELECT ProdName, InvNo, WO, SubLot FROM tb_proc1 WHERE LotID = ? LIMIT 1");
        mysqli_stmt_bind_param($lstmt, 's', $_SESSION['lotid']);
        mysqli_stmt_execute($lstmt);
        $lrow = mysqli_fetch_assoc(mysqli_stmt_get_result($lstmt));
        if ($lrow) {
            $lot_prodname = htmlspecialchars($lrow['ProdName']);
            $lot_invno    = htmlspecialchars($lrow['InvNo']);
            $lot_wo       = htmlspecialchars($lrow['WO']);
            $lot_sublot   = htmlspecialchars($lrow['SubLot']);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodName          = $_POST['ProdName'];
        $invNo             = $_POST['InvNo'];
        $wo                = $_POST['WO'];
        $subLot            = $_POST['SubLot'];
        $date              = $_POST['Date'];
        $time              = $_POST['Time'];
        $opr               = (int)$_POST['Opr'];
        $thickSamplingSize = (int)$_POST['ThickSamplingSize'];
        $thickJudge        = $_POST['ThickJudge'];
        $glossSamplingSize = (int)$_POST['GlossSamplingSize'];
        $glossJudge        = $_POST['GlossJudge'];
        $qaJudge           = $_POST['QAJudge'];
        $remark            = $_POST['Remark'];

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc6`
             (`ProdName`,`InvNo`,`WO`,`SubLot`,`Date`,`Time`,`Opr`,
              `ThickSamplingSize`,`ThickJudge`,
              `GlossSamplingSize`,`GlossJudge`,
              `QAJudge`,`Remark`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "ssssssiisisss",
            $prodName, $invNo, $wo, $subLot, $date, $time, $opr,
            $thickSamplingSize, $thickJudge,
            $glossSamplingSize, $glossJudge,
            $qaJudge, $remark);
        $req = mysqli_stmt_execute($stmt);
        if ($req) {
            if ($qaJudge === 'PASS' && !empty($_SESSION['lotid'])) {
                $ustmt = mysqli_prepare($conn,
                    "UPDATE `tb_proc1` SET `DoneFlag`='yes' WHERE `LotID`=?");
                mysqli_stmt_bind_param($ustmt, 's', $_SESSION['lotid']);
                mysqli_stmt_execute($ustmt);
            }
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
    <h2>6 QA Outgoing — Ni-e Line 2</h2>

    <?php if (!empty($_SESSION['lotid'])): ?>
    <p style="color:#1a6e1a; font-weight:bold; font-size:0.95em;">
      Lot ID : <?php echo htmlspecialchars($_SESSION['lotid']); ?>
    </p>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc1-g">

        <div class="pro3-proc1-g-it"><label>Product name</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="ProdName" value="<?php echo $lot_prodname; ?>" autofocus required>
        </div>

        <div class="pro3-proc1-g-it"><label>Invoice no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="InvNo" value="<?php echo $lot_invno; ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>WO</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="WO" value="<?php echo $lot_wo; ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Sub lot no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="SubLot" value="<?php echo $lot_sublot; ?>" required>
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

        <div class="pro3-proc1-g-it"><label>สุ่มวัดความหนา (pcs)</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="ThickSamplingSize" required>
        </div>

        <div class="pro3-proc1-g-it"><label>ผลวัดความหนา</label></div>
        <div class="pro3-proc1-g-it">
          <select name="ThickJudge" id="f_ThickJudge" onchange="updateQAJudge()" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="PASS">PASS</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <div class="pro3-proc1-g-it"><label>สุ่มวัด gloss (pcs)</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="GlossSamplingSize" required>
        </div>

        <div class="pro3-proc1-g-it"><label>ผลวัด gloss</label></div>
        <div class="pro3-proc1-g-it">
          <select name="GlossJudge" id="f_GlossJudge" onchange="updateQAJudge()" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="PASS">PASS</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <div class="pro3-proc1-g-it"><label>สรุปการตัดสินใจ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="QAJudge" id="f_QAJudge" readonly required style="font-weight:bold;">
        </div>

        <!-- Remark -->
        <div class="pro3-proc1-g-it" style="grid-column:1/span 2; justify-content:center; margin-top:6px;">
          <label>-หมายเหตุ-</label>
        </div>
        <div class="pro3-proc1-g-it" style="grid-column:1/span 2; justify-content:center;">
          <textarea name="Remark" rows="3" style="width:270px;"></textarea>
        </div>

      </div>

      <p style="display:flex; justify-content:space-between; padding:0 10px;">
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
        <button type="submit" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php if (!isset($req)) { mysqli_close($conn); } ?>

  <script>
    function updateQAJudge() {
      const thick = document.getElementById('f_ThickJudge').value;
      const gloss = document.getElementById('f_GlossJudge').value;
      const qa    = document.getElementById('f_QAJudge');
      if (thick === 'PASS' && gloss === 'PASS') {
        qa.value = 'PASS';
      } else if (thick === 'FAIL' || gloss === 'FAIL') {
        qa.value = 'FAIL';
      } else {
        qa.value = '';
      }
    }
  </script>
</body>
</html>

