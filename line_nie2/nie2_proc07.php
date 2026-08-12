<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');
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
    <h2>7 QA Outgoing — Ni-e Line 2</h2>

    <?php if (!empty($_SESSION['lotid'])): ?>
    <p style="color:#1a6e1a; font-weight:bold; font-size:0.95em;">
      Lot ID : <?php echo htmlspecialchars($_SESSION['lotid']); ?>
    </p>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc7-g">

        <div class="pro3-proc7-g-it"><label>Operator</label></div>
        <div class="pro3-proc7-g-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" readonly required>
        </div>

        <div class="pro3-proc7-g-it"><label>Lot tag</label></div>
        <div class="pro3-proc7-g-it">
          <input placeholder="ProdName|WO|BoxNo|BoxQty|Material">
        </div>

        <div class="pro3-proc2-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_id; ?>" disabled>
        </div>

        <div class="pro3-proc7-g-it"><label>Product name</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" value="<?php echo $lot_prodname; ?>" disabled>
          <input type="hidden" name="ProdName" value="<?php echo $lot_prodname; ?>">
        </div>

        <div class="pro3-proc2-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_invno; ?>" disabled>
          <input type="hidden" name="InvNo" value="<?php echo $lot_invno; ?>">
        </div>

        <div class="pro3-proc2-g1-it"><label>WO</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_wo; ?>" disabled>
          <input type="hidden" name="WO" value="<?php echo $lot_wo; ?>">
        </div>

        <div class="pro3-proc7-g-it"><label>Date</label></div>
        <div class="pro3-proc7-g-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc7-g-it"><label>Time</label></div>
        <div class="pro3-proc7-g-it">
          <input type="time" name="Time" value="<?php echo date('H:i'); ?>" required>
        </div>

        <div class="pro3-proc7-g-it"><label>สุ่มวัดความหนา (pcs)</label></div>
        <div class="pro3-proc7-g-it">
          <input type="number" name="ThickSamplingSize" required>
        </div>

        <div class="pro3-proc7-g-it"><label>ผลวัดความหนา</label></div>
        <div class="pro3-proc7-g-it">
          <select name="ThickJudge" id="f_ThickJudge" onchange="updateQAJudge()" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="PASS">PASS</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <div class="pro3-proc7-g-it"><label>สุ่มวัด gloss (pcs)</label></div>
        <div class="pro3-proc7-g-it">
          <input type="number" name="GlossSamplingSize" required>
        </div>

        <div class="pro3-proc7-g-it"><label>ผลวัด gloss</label></div>
        <div class="pro3-proc7-g-it">
          <select name="GlossJudge" id="f_GlossJudge" onchange="updateQAJudge()" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="PASS">PASS</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <div class="pro3-proc7-g-it"><label>สรุปการตัดสินใจ</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" name="QAJudge" id="f_QAJudge" readonly required style="font-weight:bold;">
        </div>

        <!-- Remark -->
        <div class="pro3-proc7-g-it" style="grid-column:1/span 2; justify-content:center; margin-top:6px;">
          <label>-หมายเหตุ-</label>
        </div>
        <div class="pro3-proc7-g-it" style="grid-column:1/span 2; justify-content:center;">
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

  </script>
</body>
</html>

