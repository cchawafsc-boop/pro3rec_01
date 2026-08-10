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
    <h2>6 Inspection — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc6-g1">

        <div class="pro3-proc6-g1-it"><label>Operator</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>

        <div class="pro3-proc6-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="text" id="lotIdDisplay" value="<?php echo $lot_id ?? ''; ?>" disabled>
        </div>

        <div class="pro3-proc6-g1-it"><label>Product name</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="text" id="lotProdnameDisplay" value="<?php echo $lot_prodname ?? ''; ?>" disabled>
          <input type="hidden" id="lotProdnameHidden" name="ProdName" value="<?php echo $lot_prodname ?? ''; ?>">
        </div>

        <div class="pro3-proc6-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="text" id="lotInvnoDisplay" value="<?php echo $lot_invno ?? ''; ?>" disabled>
          <input type="hidden" id="lotInvnoHidden" name="InvNo" value="<?php echo $lot_invno ?? ''; ?>">
        </div>

        <div class="pro3-proc6-g1-it"><label>WO</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="text" id="lotWoDisplay" value="<?php echo $lot_wo ?? ''; ?>" disabled>
          <input type="hidden" id="lotWoHidden" name="WO" value="<?php echo $lot_wo ?? ''; ?>">
        </div>

        <div class="pro3-proc6-g1-it"><label>Date</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="date" id="rackDate" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc6-g1-it"><label>Time</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="time" id="rackTime" name="Time" value="<?php echo date('H:i'); ?>" disabled>
        </div>

        <div class="pro3-proc6-g1-it"><label>จำนวนสุ่มต่อแร็ก</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="number" id="samplingSize" name="samplingSize" value="" disabled>
        </div>

      </div>

      <div id="input-plating" class="pro3-proc6-g2">
        <!-- 1 --><div class="pro3-proc6-g2-h">Box-no</div>
        <!-- 2 --><div class="pro3-proc6-g2-h">Plate-no</div>
        <!-- 3 --><div class="pro3-proc6-g2-h">FG-qty</div>
        <!-- 4 --><div class="pro3-proc6-g2-h">NG-mode</div>
        <!-- 5 --><div class="pro3-proc6-g2-h">NG-qty</div>
        <!-- 6 --><div class="pro3-proc6-g2-h">ShrOvr</div>
        <!-- 7 --><div class="pro3-proc6-g2-h">Oper.</div>
        <!-- 8 --><div class="pro3-proc6-g2-h">Status</div> 
        <!-- 9 --><div class="pro3-proc6-g2-h">Remark</div>
        <!--10 --><div class="pro3-proc6-g2-h">Action</div>

        <div class="pro3-proc6-g2-c" id="entryRowAnchor"><input type="text" id="newBoxNo" placeholder="Box-no"></div>
        <div class="pro3-proc6-g2-c"><input type="text"   id="newPlateNo" placeholder="Plate-no"></div>
        <div class="pro3-proc6-g2-c"><input type="number" id="newFGqty"   placeholder="FG-qty"></div>
        <div class="pro3-proc6-g2-c"><input type="text"   id="newNGmode"  placeholder="NG-mode"></div>
        <div class="pro3-proc6-g2-c"><input type="number" id="newNGqty"   placeholder="NG-qty"></div>
        <div class="pro3-proc6-g2-c"><input type="number" id="newShrOvr"  placeholder="ShrOvr"></div>
        <div class="pro3-proc6-g2-c"><input type="number" id="newOpr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled></div>
        <div class="pro3-proc6-g2-c">
          <select id="newStatus">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Accept">Accept</option>
            <option value="Reject">Reject</option>
            <option value="Hold">Hold</option>
            <option value="SpecialAccept">SpecialAccept</option>
          </select>
        </div>
        <div class="pro3-proc6-g2-c"><textarea id="newPltRemark" rows="2"></textarea></div>
        <div class="pro3-proc6-g2-c"><button type="button" id="newInspSubmitBtn">บันทึกเข้าระบบ</button></div>
      </div>

      <div class="pro3-proc6-summary">
        <div class="pro3-proc6-summary-it">จำนวนแร็กที่ plating: <span id="platingPlateCount">0</span></div>
        <div class="pro3-proc6-summary-it">จำนวนแร็กที่ racking: <span id="rackingPlateCount">0</span></div>
        <div class="pro3-proc6-summary-it summary-status" id="pltSummaryStatus"></div>
      </div>

      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2 </button>
      </p>
    </form>
  </div>

  <?php mysqli_close($conn); ?>
  <script>
    
  </script>
</body>
</html>
