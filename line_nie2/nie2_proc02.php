<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');
    require('./ngmode.php');

    function calcSamplingSize($n) {
        if ($n >= 2   && $n <= 8)    return 2;
        if ($n >= 9   && $n <= 15)   return 3;
        if ($n >= 16  && $n <= 25)   return 5;
        if ($n >= 26  && $n <= 50)   return 8;
        if ($n >= 51  && $n <= 90)   return 13;
        if ($n >= 91  && $n <= 150)  return 20;
        if ($n >= 151 && $n <= 280)  return 32;
        if ($n >= 281 && $n <= 500)  return 50;
        if ($n >= 501 && $n <= 1000) return 80;
        return 0;
    }

    $lot_prodname = $lot_invno = $lot_wo = '';
    $lot_prodname_raw = $lot_invno_raw = $lot_wo_raw = '';
    $lot_boxcount = 0;
    $lot_amountinv = 0;
    if (!empty($_SESSION['lotid'])) {
        $lstmt = mysqli_prepare($conn,
            "SELECT ProdName, InvNo, WO FROM tb_proc1 WHERE LotID = ? LIMIT 1");
        mysqli_stmt_bind_param($lstmt, 's', $_SESSION['lotid']);
        mysqli_stmt_execute($lstmt);
        $lrow = mysqli_fetch_assoc(mysqli_stmt_get_result($lstmt));
        if ($lrow) {
            $lot_prodname_raw = $lrow['ProdName'];
            $lot_invno_raw    = $lrow['InvNo'];
            $lot_wo_raw       = $lrow['WO'];
            $lot_prodname = htmlspecialchars($lrow['ProdName']);
            $lot_invno    = htmlspecialchars($lrow['InvNo']);
            $lot_wo       = htmlspecialchars($lrow['WO']);
        }

        $cstmt = mysqli_prepare($conn,
            "SELECT COUNT(*) AS boxCount, COALESCE(SUM(BoxQty),0) AS totalQty FROM tb_proc1 WHERE LotID = ?");
        mysqli_stmt_bind_param($cstmt, 's', $_SESSION['lotid']);
        mysqli_stmt_execute($cstmt);
        $crow = mysqli_fetch_assoc(mysqli_stmt_get_result($cstmt));
        if ($crow) {
            $lot_boxcount  = (int)$crow['boxCount'];
            $lot_amountinv = (int)$crow['totalQty'];
        }
    }
    $lot_samplingsize = calcSamplingSize($lot_amountinv);

    $lot_boxnos = [];
    if (!empty($_SESSION['lotid']) && $lot_samplingsize > 0) {
        $bstmt = mysqli_prepare($conn,
            "SELECT BoxNo, BoxQty FROM tb_proc1 WHERE LotID = ?");
        mysqli_stmt_bind_param($bstmt, 's', $_SESSION['lotid']);
        mysqli_stmt_execute($bstmt);
        $bres = mysqli_stmt_get_result($bstmt);

        $residual = $lot_samplingsize;
        while ($residual > 0 && ($brow = mysqli_fetch_assoc($bres))) {
            $lot_boxnos[] = $brow['BoxNo'];
            $residual -= (int)$brow['BoxQty'];
        }
    }
    $incChkBox_qty = count($lot_boxnos);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodName     = $_POST['ProdName'];
        $invNo        = $_POST['InvNo'];
        $wo           = $_POST['WO'];
        $date         = $_POST['Date'];
        $time         = date('H:i:s');
        $opr          = (int)$_POST['Opr'];
        $amountInv    = (int)$_POST['AmountInv'];
        $samplingSize = (int)$_POST['SamplingSize'];

        $boxSubLots   = $_POST['box_subLot']   ?? [];
        $boxAppChecks = $_POST['box_appcheck'] ?? [];
        $boxQrResults = $_POST['box_qrresult'] ?? [];

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc2`
             (`Prod_Name`,`InvNo`,`WO`,`SubLot`,`Date`,`Time`,`Opr`,
              `BoxCondition`,`AmountInv`,`SamplingSize`,
              `Break`,`Bumps`,`Burrs`,`Chip`,`Crack`,`Contam`,
              `Dent`,`Scratch`,`Scuff`,`Stain`,`Deform`,`Finger`,
              `NGtotal`,`Remark`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $break = $bumps = $burrs = $chip = $crack = $contam = 0;
        $dent = $scratch = $scuff = $stain = $deform = $finger = $ngTotal = 0;

        $req = true;
        foreach ($boxSubLots as $idx => $subLot) {
            $boxCondition = $boxAppChecks[$idx] ?? '';
            $remark       = $boxQrResults[$idx] ?? '';

            mysqli_stmt_bind_param($stmt, "ssssssissiiiiiiiiiiiiiis",
                $prodName, $invNo, $wo, $subLot, $date, $time, $opr,
                $boxCondition, $amountInv, $samplingSize,
                $break, $bumps, $burrs, $chip, $crack, $contam,
                $dent, $scratch, $scuff, $stain, $deform, $finger,
                $ngTotal, $remark);
            if (!mysqli_stmt_execute($stmt)) {
                $req = false;
            }
        }

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

  <div class="form-pro3-proc2-g1">
    <h2>2 Incoming — Ni-e Line 2</h2>
      
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc2-g1">

        <div class="pro3-proc2-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc2-g1-it" style="font-size:0.8em"><label>
          <?php 
            if (!empty($_SESSION['lotid'])):
              echo htmlspecialchars($_SESSION['lotid']);
            else:
              echo "กรุณาเลือก Lot ID";
            endif;
          ?></label>
        </div>

        <div class="pro3-proc2-g1-it"><label>Operator</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>
          
        <div class="pro3-proc2-g1-it"><label>Product name</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_prodname; ?>" autofocus disabled>
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

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนตาม Inv (box)</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" value="<?php echo $lot_boxcount; ?>" disabled>
        </div>
            
        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนตาม Inv (pcs)</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="AmountInv" value="<?php echo $lot_amountinv; ?>" min="0" disabled required>
        </div>

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนสุ่ม (pcs)</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="SamplingSize" value="<?php echo $lot_samplingsize; ?>" min="0" disabled required>
        </div>

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนสุ่ม (box)</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" value="<?php echo $incChkBox_qty; ?>" disabled>
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

        <div class="pro3-proc2-qrset-it"><label>เช็ค App</label></div>
        <div class="pro3-proc2-qrset-it">
          <select class="app-check-select" name="box_appcheck[]" onchange="handleAppCheck(this)">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="ผ่าน">ผ่าน</option>
            <option value="ไม่ผ่าน">ไม่ผ่าน</option>
          </select>
          <button type="button" class="ngTypeBtn" style="display:none;" onclick="goNGtype('<?php echo htmlspecialchars($boxNo, ENT_QUOTES); ?>')">เลือก NG</button>
        </div>

        <div class="pro3-proc2-qrset-it"><label>NG รวม</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" value="<?php echo $ngSum; ?>" disabled>
        </div>

        <div class="pro3-proc2-qrset-it"><label>หมายเหตุ</label></div>
        <div class="pro3-proc2-qrset-it"><textarea></textarea></div>
      </div>
      <?php endforeach; ?>

      <p style="display:flex; justify-content:space-between; padding:0 10px;">
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
        <button type="submit" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php if (!isset($req)) { mysqli_close($conn); } ?>

  <script>
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
