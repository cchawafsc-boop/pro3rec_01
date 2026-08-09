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

  <div class="form-pro3-proc4-g1">
    <h2>4 Plating — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div>

        <div class="pro3-proc4-g1-it"><label>Operator</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>

        <div class="pro3-proc4-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="text" value="<?php echo $lot_id; ?>" disabled>
        </div>
          
        <div class="pro3-proc4-g1-it"><label>Product name</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="text" value="<?php echo $lot_prodname; ?>" disabled>
          <input type="hidden" name="ProdName" value="<?php echo $lot_prodname; ?>">
        </div>

        <div class="pro3-proc4-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="text" value="<?php echo $lot_invno; ?>" disabled>
          <input type="hidden" name="InvNo" value="<?php echo $lot_invno; ?>">
        </div>

        <div class="pro3-proc4-g1-it"><label>WO</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="text" value="<?php echo $lot_wo; ?>" disabled>
          <input type="hidden" name="WO" value="<?php echo $lot_wo; ?>">
        </div>

        <div class="pro3-proc4-g1-it"><label>Date</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="date" id="rackDate" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc4-g1-it"><label>Time</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="time" id="rackTime" name="Time" value="<?php echo date('H:i'); ?>" disabled>
        </div>

      </div>

      <div id="input-plating" class="pro3-proc4-g2">
        <div class="pro3-proc4-g2-h">Box-no</div>
        <div class="pro3-proc4-g2-h">Lot-plate</div>
        <div class="pro3-proc4-g2-h">Plate-no</div>
        <div class="pro3-proc4-g2-h">Rack-no</div>
        <div class="pro3-proc4-g2-h">Qty</div>
        <div class="pro3-proc4-g2-h">Operator</div>
        <div class="pro3-proc4-g2-h">Status</div>
        <div class="pro3-proc4-g2-h">Remark</div>
        <div class="pro3-proc4-g2-h">Action</div>

        <div class="pro3-proc4-g2-c"><input type="text"></div>
        <div class="pro3-proc4-g2-c"><input type="text" ></div>
        <div class="pro3-proc4-g2-c"><input type="text" ></div>
        <div class="pro3-proc4-g2-c"><input type="text"></div>
        <div class="pro3-proc4-g2-c"><input type="number"></div>
        <div class="pro3-proc4-g2-c"><input type="number"></div>
        <div class="pro3-proc4-g2-c">
          <select>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Accept">Accept</option>
            <option value="Reject">Reject</option>
            <option value="Hold">Hold</option>
            <option value="SpecialAccept">SpecialAccept</option>
          </select>
        </div>
        <div class="pro3-proc4-g2-c"><textarea rows="2"></textarea></div>
        <div class="pro3-proc4-g2-c"><button type="button">บันทึกเข้าระบบ</button></div>
      </div>

      <div class="pro3-proc4-summary">
        <div class="pro3-proc4-summary-it">Racked Box-no:</div>
        <div class="pro3-proc4-summary-it">Lot Box-no:</div>
        <div class="pro3-proc4-summary-it racking-summary-status"></div>
      </div>

      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2 </button>
      </p>
    </form>
  </div>

  <?php mysqli_close($conn); ?>

</body>
</html>
