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

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc7-g">

        <div class="pro3-proc7-g-it"><label>Operator</label></div>
        <div class="pro3-proc7-g-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>

        <div class="pro3-proc7-g-it"><label>Lot tag</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" placeholder="Scan Lot tag ที่นี่">
        </div>

        <div class="pro3-proc7-g-it"><label>Lot ID</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" value="<?php echo $lot_id; ?>" disabled>
        </div>

        <div class="pro3-proc7-g-it"><label>Product name</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" value="<?php echo $lot_prodname; ?>" disabled>
          <input type="hidden" name="ProdName" value="<?php echo $lot_prodname; ?>">
        </div>

        <div class="pro3-proc7-g-it"><label>Invoice no</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" value="<?php echo $lot_invno; ?>" disabled>
          <input type="hidden" name="InvNo" value="<?php echo $lot_invno; ?>">
        </div>

        <div class="pro3-proc7-g-it"><label>WO</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" value="<?php echo $lot_wo; ?>" disabled>
          <input type="hidden" name="WO" value="<?php echo $lot_wo; ?>">
        </div>

        <div class="pro3-proc7-g-it"><label>Date</label></div>
        <div class="pro3-proc7-g-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc7-g-it"><label>Time</label></div>
        <div class="pro3-proc7-g-it">
          <input type="time" name="Time" value="<?php echo date('H:i'); ?>" disabled required>
        </div>

        <div class="pro3-proc7-g-it">
          <label style="font-size: 0.8em;">App.<span style="font-size: 0.5em;"> AQL 0.65 Lv.II</span>
          </label>
        </div>
        <div class="pro3-proc7-g-it">
          <select name="AppAQLCheck" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
          </select>
        </div>

        <div class="pro3-proc7-g-it"><label style="font-size: 0.8em;">สุ่มวัดความหนา (pcs)</label></div>
        <div class="pro3-proc7-g-it">
          <input type="number" name="ThickSmpSize" required>
        </div>

        <div class="pro3-proc7-g-it"><label style="font-size: 0.8em;">ผลวัดความหนา</label></div>
        <div class="pro3-proc7-g-it">
          <select name="ThickJudge" id="ThickJudge" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
          </select>
        </div>

        <div class="pro3-proc7-g-it"><label style="font-size: 0.8em;">สุ่มวัด gloss (pcs)</label></div>
        <div class="pro3-proc7-g-it">
          <input type="number" name="GlossSmpSize" required>
        </div>

        <div class="pro3-proc7-g-it"><label style="font-size: 0.8em;">ผลวัด gloss</label></div>
        <div class="pro3-proc7-g-it">
          <select name="GlossJudge" id="GlossJudge" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
          </select>
        </div>

        <!-- Remark -->
        <div class="pro3-proc7-g-it full-row">
          <label>-หมายเหตุ-</label>
        </div>
        <div class="pro3-proc7-g-it full-row">
          <textarea name="Remark" rows="3"></textarea>
        </div>

      </div>

      <p>
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

