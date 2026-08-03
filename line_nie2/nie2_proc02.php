<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');
    require('./ngmode.php');

    function calcNGtotal($conn, $prodName, $invNo, $wo, $process) {
        $stmt = mysqli_prepare($conn,
            "SELECT COALESCE(SUM(NGqty),0) AS ngSum FROM tb_ng WHERE ProdName = ? AND InvNo = ? AND WO = ? AND Process = ?");
        mysqli_stmt_bind_param($stmt, 'ssss', $prodName, $invNo, $wo, $process);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        return $row ? (int)$row['ngSum'] : 0;
    }

    function decideResult($pcs, $ngTotal) {
        if ($pcs <= 280) {
            return $ngTotal === 0 ? 'Accept' : 'Reject';
        }
        if ($pcs >= 281 && $pcs <= 1200) {
            return $ngTotal < 2 ? 'Accept' : 'Reject';
        }
        if ($pcs >= 1201 && $pcs <=3200) {
            return $ngTotal < 3 ? 'Accept' : 'Reject';
        }
        return 'error';
    }

    function calcSamplingSize($n) {
        /* This calculation is from AQL level II 0.65 */
        if ($n >= 1    && $n <= 20)   return $n;
        if ($n >= 21   && $n <= 280)  return 20;
        if ($n >= 281  && $n <= 1200) return 80;
        if ($n >= 1201 && $n <= 3200) return 125;
        return 0;
    }

    $lot_id = '';
    $lot_prodname = $lot_invno = $lot_wo = '';
    $lot_prodname_raw = $lot_invno_raw = $lot_wo_raw = '';
    $lot_boxcount = 0;
    $lot_amountinv = 0;
    if (!empty($_GET['prodName']) && !empty($_GET['wo']) && !empty($_GET['boxNo'])) {
        $gStmt = mysqli_prepare($conn,
            "SELECT LotID, ProdName, InvNo, WO FROM tb_proc1 WHERE ProdName = ? AND WO = ? AND BoxNo = ? LIMIT 1");
        mysqli_stmt_bind_param($gStmt, 'sss', $_GET['prodName'], $_GET['wo'], $_GET['boxNo']);
        mysqli_stmt_execute($gStmt);
        $gRow = mysqli_fetch_assoc(mysqli_stmt_get_result($gStmt));
        if ($gRow) {
            $lot_id_raw       = $gRow['LotID'];
            $lot_id           = htmlspecialchars($gRow['LotID']);
            $lot_prodname_raw = $gRow['ProdName'];
            $lot_invno_raw    = $gRow['InvNo'];
            $lot_wo_raw       = $gRow['WO'];
            $lot_prodname     = htmlspecialchars($gRow['ProdName']);
            $lot_invno        = htmlspecialchars($gRow['InvNo']);
            $lot_wo           = htmlspecialchars($gRow['WO']);

            $cstmt = mysqli_prepare($conn,
                "SELECT COUNT(*) AS boxCount, COALESCE(SUM(BoxQty),0) AS totalQty FROM tb_proc1 WHERE LotID = ?");
            mysqli_stmt_bind_param($cstmt, 's', $lot_id_raw);
            mysqli_stmt_execute($cstmt);
            $crow = mysqli_fetch_assoc(mysqli_stmt_get_result($cstmt));
            if ($crow) {
                $lot_boxcount  = (int)$crow['boxCount'];
                $lot_amountinv = (int)$crow['totalQty'];
            }
        } else {
            echo "<script>alert('Not found the data');</script>";
        }
    }
    $lot_samplingsize = calcSamplingSize($lot_amountinv);
    $lot_boxnos = [];
    $incChkBox_qty = count($lot_boxnos);

    $process = '2. Incoming';
    $ngTotal = calcNGtotal($conn, $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $process);
    $decision = decideResult($lot_amountinv, $ngTotal);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $date       = $_POST['Date'];
        $time       = date('H:i:s');
        $opr        = (int)($_SESSION['us_id'] ?? 0);
        $boxCon     = $_POST['BoxCon'] ?? '';
        $remark     = $_POST['Remark'] ?? '';
        $ngTotal    = calcNGtotal($conn, $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $process);

        $dupStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_proc2 WHERE ProdName = ? AND InvNo = ? AND WO = ? LIMIT 1");
        mysqli_stmt_bind_param($dupStmt, 'sss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw);
        mysqli_stmt_execute($dupStmt);
        $dupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));

        if ($dupRow) {
            echo "<script>alert('There is redundant Product name, Invoice and WO in database. Please check the data intry');</script>";
        } else {
            $insStmt = mysqli_prepare($conn,
                "INSERT INTO `tb_proc2`
                 (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`BoxCon`,`PcsFromInv`,`SamplingSize`,`NGtotal`,`Remark`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($insStmt, "sssssisiiis",
                $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $date, $time, $opr,
                $boxCon, $lot_amountinv, $lot_samplingsize, $ngTotal, $remark);
            if (mysqli_stmt_execute($insStmt)) {
                $updStmt = mysqli_prepare($conn,
                    "UPDATE `tb_proc1` SET `Status` = 'waiting racking' WHERE `ProdName` = ? AND `InvNo` = ? AND `WO` = ?");
                mysqli_stmt_bind_param($updStmt, 'sss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw);
                mysqli_stmt_execute($updStmt);

                echo "<script>alert('บันทึกข้อมูลสำเร็จ'); location='./nie2_index.php';</script>";
            } else {
                echo "<script>alert('บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่');</script>";
            }
        }
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

  <div class="form-pro3-proc2-g1">
    <h2>2 Incoming — Ni-e Line 2</h2>
      
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc2-g1">

        <div class="pro3-proc2-g1-it"><label>Operator</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>

        <div class="pro3-proc2-g1-it"><label>Data from Lot Tag</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" id="lotTagData" autocomplete="off" placeholder="prod|wo|box|qty|mat" autofocus>
        </div>

        <div class="pro3-proc2-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_id; ?>" disabled>
        </div>
          
        <div class="pro3-proc2-g1-it"><label>Product name</label></div>
        <div class="pro3-proc2-g1-it">
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

        <div class="pro3-proc2-g1-it"><label>Date</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc2-g1-it"><label>Time</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="time" id="hdrTime" value="<?php echo date('H:i'); ?>" disabled>
        </div>

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>สภาพกล่องทุกกล่อง</label></div>
        <div class="pro3-proc2-g1-it">
          <select name="BoxCon" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="ผ่าน">ผ่าน</option>
            <option value="ไม่ผ่าน">ไม่ผ่าน</option>
          </select>
        </div>

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนกล่องตาม Inv</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" value="<?php echo $lot_boxcount; ?>" disabled>
        </div>
            
        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนชิ้นงานตาม Inv</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="AmountInv" value="<?php echo $lot_amountinv; ?>" min="0" disabled required>
        </div>

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนชิ้นงานที่ถูกสุ่ม</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="SamplingSize" value="<?php echo $lot_samplingsize; ?>" min="0" disabled required>
        </div>

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนกล่องที่ถูกสุ่ม</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="SampPledBox" value="<?php echo $incChkBox_qty; ?>" disabled>
        </div>

      </div>

      <?php
        $sorted_boxnos = $lot_boxnos;
        sort($sorted_boxnos);
        $ngSumStmt = mysqli_prepare($conn,
            "SELECT COALESCE(SUM(NGqty),0) AS ngSum FROM tb_ng WHERE ProdName = ? AND InvNo = ? AND WO = ? AND BoxNo = ?");
        foreach ($sorted_boxnos as $boxNo):
          mysqli_stmt_bind_param($ngSumStmt, 'ssss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $boxNo);
          mysqli_stmt_execute($ngSumStmt);
          $ngSumRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ngSumStmt));
          $ngSum = $ngSumRow ? (int)$ngSumRow['ngSum'] : 0;
      ?>
      <div class="pro3-proc2-qrset">
        <div class="pro3-proc2-qrset-it"><label>Box no</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" value="<?php echo htmlspecialchars($boxNo); ?>" disabled>
          <input type="hidden" name="box_subLot[]" value="<?php echo htmlspecialchars($boxNo); ?>">
        </div>

        <div class="pro3-proc2-qrset-it"><label>ยิง QR ที่นี่</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" class="qr-scan-input" onkeydown="handleQrScan(event, '<?php echo htmlspecialchars($boxNo, ENT_QUOTES); ?>')">
        </div>

        <div class="pro3-proc2-qrset-it"><label>เช็คการยิง</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" class="qr-result-input" disabled>
          <input type="hidden" class="qr-result-hidden" name="box_qrresult[]" value="">
        </div>

        <div class="pro3-proc2-qrset-it"><label>เช็คชิ้นงาน</label></div>
        <div class="pro3-proc2-qrset-it">
          <select class="app-check-select" name="box_appcheck[]" onchange="handleAppCheck(this)">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="ผ่าน">ผ่าน</option>
            <option value="ไม่ผ่าน">ไม่ผ่าน</option>
          </select>
          <button type="button" class="ngTypeBtn" style="display:none;" onclick="goNGtype('<?php echo htmlspecialchars($boxNo, ENT_QUOTES); ?>')">เลือก NG</button>
        </div>

        <div class="pro3-proc2-qrset-it"><label>NG รวมของกล่อง</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" value="<?php echo $ngSum; ?>" disabled>
        </div>

      </div>
      <?php endforeach; ?>

      <div class="pro3-proc2-summary">
        <div class="pro3-proc2-summary-it"><label>NG รวม</label></div>
        <div class="pro3-proc2-summary-it">
          <input type="number" value="<?php echo $ngTotal; ?>" readonly>
        </div>

        <div class="pro3-proc2-summary-it"><label>ผลการตัดสินใจ</label></div>
        <div class="pro3-proc2-summary-it">
          <select name="Decision">
            <option value="Accept" <?php echo $decision === 'Accept' ? 'selected' : ''; ?>>Accept</option>
            <option value="Reject" <?php echo $decision === 'Reject' ? 'selected' : ''; ?>>Reject</option>
            <option value="Special" <?php echo $decision === 'Special' ? 'selected' : ''; ?>>Special</option>
          </select>
        </div>

        <div class="pro3-proc2-summary-it"><label>Remark</label></div>
        <div class="pro3-proc2-summary-it">
          <textarea name="Remark"></textarea>
        </div>
      </div>

      <p style="display:flex; justify-content:space-between; padding:0 10px;">
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
        <button type="submit" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php mysqli_close($conn); ?>

  <script>
    document.getElementById('lotTagData').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var text = this.value.trim();
      var parts = text.split('|');
      if (parts.length !== 5) {
        alert('Data from Lot Tag is error. Please re-check');
        this.value = '';
        this.focus();
        return;
      }

      var prodName = parts[0].trim(), wo = parts[1].trim(), boxNo = parts[2].trim();

      var url = new URL(window.location.href);
      url.searchParams.set('prodName', prodName);
      url.searchParams.set('wo', wo);
      url.searchParams.set('boxNo', boxNo);
      window.location.href = url.toString();
    });

    function handleQrScan(e, boxNo) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      const input = e.target;
      const set = input.closest('.pro3-proc2-qrset');
      const resultInput = set.querySelector('.qr-result-input');
      const resultHidden = set.querySelector('.qr-result-hidden');
      const parts = input.value.split(/\s*,\s*/);
      const prodName = document.querySelector('input[name="ProdName"]').value;
      const wo = document.querySelector('input[name="WO"]').value;
      const ok = parts.length === 5
        && parts[0] === prodName
        && parts[1] === wo
        && parts[2] === boxNo;
      const resultText = ok ? 'ข้อมูลถูกต้อง' : 'ข้อมูลไม่ถูกต้อง';
      resultInput.value = resultText;
      resultInput.style.color = ok ? 'green' : 'red';
      resultHidden.value = resultText;
    }

    function handleAppCheck(sel) {
      const btn = sel.parentElement.querySelector('.ngTypeBtn');
      btn.style.display = sel.value === 'ไม่ผ่าน' ? 'inline-block' : 'none';
    }

    function goNGtype(boxNo) {
      window.location.href = './nie2_ng_input.php?process=' + encodeURIComponent('2. Incoming') + '&boxno=' + encodeURIComponent(boxNo);
    }
  </script>
</body>
</html>
