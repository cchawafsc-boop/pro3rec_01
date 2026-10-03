<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    function calcNGtotal($conn, $prodName, $invNo, $wo, $process) {
      $stmt = mysqli_prepare($conn,
        "SELECT COALESCE(SUM(NGqty),0) AS ngSum FROM tb_ng WHERE ProdName = ? AND InvNo = ? AND WO = ? AND Process = ?");
      mysqli_stmt_bind_param($stmt, 'ssss', $prodName, $invNo, $wo, $process);
      mysqli_stmt_execute($stmt);
      $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
      return $row ? (int)$row['ngSum'] : 0;
    }

    function rejectQty($pcs){
      // This is based on AQL level-II 0.65
      if ($pcs <= 280) {return 1;}
      if ($pcs >= 281 && $pcs <= 1200) {return 2;}
      if ($pcs >= 1201 && $pcs <=3200) {return 3;}
      return 'error';
    }
    
    function decideResult($pcs, $ngTotal) {
      // This is based on AQL level-II 0.65
      if ($pcs <= 280)                 {return $ngTotal === 0 ? 'Accept' : 'Reject';}
      if ($pcs >= 281 && $pcs <= 1200) {return $ngTotal < 2   ? 'Accept' : 'Reject';}
      if ($pcs >= 1201 && $pcs <=3200) {return $ngTotal < 3   ? 'Accept' : 'Reject';}
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

    // 1) Loading Lot data from eiter $_GET['selected_lotid']) or {$_GET['prodName', 'wo', 'boxNo']}
    // 2) Summarizing the quantity of the selected lot ($lot_amountinv)
    $lot_id = '';
    $lot_id_raw = '';
    $lot_prodname = $lot_invno = $lot_wo = '';
    $lot_prodname_raw = $lot_invno_raw = $lot_wo_raw = '';
    $lot_boxcount = 0;
    $lot_amountinv = 0;
    if (!empty($_GET['selected_lotid'])) {
      // Loading Lot data from $_GET['selected_lotid'])
      $gStmt = mysqli_prepare($conn,
        "SELECT LotID, ProdName, InvNo, WO FROM tb_proc1 WHERE LotID = ? LIMIT 1");
      mysqli_stmt_bind_param($gStmt, 's', $_GET['selected_lotid']);
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
    } elseif (!empty($_GET['prodName']) && !empty($_GET['wo']) && !empty($_GET['boxNo'])) {
      // Loading Lot data from {$_GET['prodName', 'wo', 'boxNo']}
      $gStmt = mysqli_prepare($conn,
        "SELECT LotID, ProdName, InvNo, WO, Status FROM tb_proc1 WHERE ProdName = ? AND WO = ? AND BoxNo = ?
         ORDER BY `Date` DESC, `Time` DESC LIMIT 1");
      mysqli_stmt_bind_param($gStmt, 'sss', $_GET['prodName'], $_GET['wo'], $_GET['boxNo']);
      mysqli_stmt_execute($gStmt);
      $gRow = mysqli_fetch_assoc(mysqli_stmt_get_result($gStmt));
      if ($gRow && $gRow['Status'] === 'Reject') {
        // Latest receiving record is Reject: the box went back to the customer and has not been received again.
        echo "<script>alert('กล่องนี้ถูก Reject ที่ Receiving');</script>";
      } elseif ($gRow) {
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

    // This part is to calculate the number of boxes that are inspected ($incChkBox_qty) 
    $lot_boxnos = [];
    $lot_boxqty = [];
    if (!empty($lot_id_raw) && $lot_samplingsize > 0) {
      $bstmt = mysqli_prepare($conn,
        "SELECT BoxNo, BoxQty FROM tb_proc1 WHERE LotID = ? ORDER BY BoxNo ASC");
      mysqli_stmt_bind_param($bstmt, 's', $lot_id_raw);
      mysqli_stmt_execute($bstmt);
      $bres = mysqli_stmt_get_result($bstmt);

      $residual = $lot_samplingsize;
      while ($residual > 0 && ($brow = mysqli_fetch_assoc($bres))) {
        $lot_boxnos[] = $brow['BoxNo'];
        $lot_boxqty[$brow['BoxNo']] = (int)$brow['BoxQty'];
        $residual -= (int)$brow['BoxQty'];
      }
    }
    $incChkBox_qty = count($lot_boxnos);

    // Full box-no list for the lot (used by the box-condition entry select — tb_proc2_box)
    $all_boxnos = [];
    if (!empty($lot_id_raw)) {
      $abstmt = mysqli_prepare($conn, "SELECT BoxNo FROM tb_proc1 WHERE LotID = ? ORDER BY BoxNo ASC");
      mysqli_stmt_bind_param($abstmt, 's', $lot_id_raw);
      mysqli_stmt_execute($abstmt);
      $abres = mysqli_stmt_get_result($abstmt);
      while ($abrow = mysqli_fetch_assoc($abres)) {
        $all_boxnos[] = $abrow['BoxNo'];
      }
    }

    // Existing box-condition records (tb_proc2_box) for this lot, keyed by BoxNo
    $boxcond_map = [];
    $lot_saved = false;
    if (!empty($lot_id_raw)) {
        $bcmStmt = mysqli_prepare($conn,
            "SELECT BoxNo, BoxCond, BoxCondStatus FROM tb_proc2_box WHERE ProdName = ? AND InvNo = ? AND WO = ?");
        mysqli_stmt_bind_param($bcmStmt, 'sss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw);
        mysqli_stmt_execute($bcmStmt);
        $bcmRes = mysqli_stmt_get_result($bcmStmt);
        while ($bcmRow = mysqli_fetch_assoc($bcmRes)) {
            $boxcond_map[$bcmRow['BoxNo']] = $bcmRow;
        }

        // Lot already saved in tb_proc2 -> box-condition rows are read-only, no delete
        $lsStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_proc2 WHERE ProdName = ? AND InvNo = ? AND WO = ? LIMIT 1");
        mysqli_stmt_bind_param($lsStmt, 'sss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw);
        mysqli_stmt_execute($lsStmt);
        $lot_saved = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($lsStmt));
    }

    // Main PHP part to insert data into tb_proc2 & tb_proc2_sup
    $process = '2. Incoming';
    $ngTotal = calcNGtotal($conn, $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $process);
    $decision = decideResult($lot_amountinv, $ngTotal);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Operator must come from the session, never from the form.
        if (empty($_SESSION['us_id'])) {
            mysqli_close($conn);
            echo "<script>alert('โปรด Login ก่อนบันทึกข้อมูล'); location='./nie2_proc02.php';</script>";
            exit;
        }

        $lot_prodname_raw = $_POST['ProdName'] ?? $lot_prodname_raw;
        $lot_invno_raw    = $_POST['InvNo'] ?? $lot_invno_raw;
        $lot_wo_raw       = $_POST['WO'] ?? $lot_wo_raw;
        $lot_amountinv    = (int)($_POST['AmountInv'] ?? $lot_amountinv);
        $lot_samplingsize = (int)($_POST['SamplingSize'] ?? $lot_samplingsize);
        $date       = $_POST['Date'] ?? '';
        $time       = date('H:i:s');
        $opr        = (int)($_SESSION['us_id'] ?? 0);
        $status     = $_POST['Decision'] ?? '';
        $remark     = $_POST['Remark'] ?? '';
        $ngTotal    = calcNGtotal($conn, $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $process);

        // Decision must be one of the 4 allowed values and Date a real Y-m-d date; otherwise save nothing.
        $formError = '';
        $dateObj   = DateTime::createFromFormat('Y-m-d', $date);
        if (!in_array($status, ['Accept', 'Hold', 'Reject', 'SpecialAccept'], true)) {
            $formError = 'ผลการตัดสินใจไม่ถูกต้อง';
        } elseif (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $formError = 'วันที่ไม่ถูกต้อง';
        }

        $dupStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_proc2 WHERE ProdName = ? AND InvNo = ? AND WO = ? LIMIT 1");
        mysqli_stmt_bind_param($dupStmt, 'sss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw);
        mysqli_stmt_execute($dupStmt);
        $dupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));

        // Validate box-condition rows (tb_proc2_box) before saving anything
        $lotBoxStmt = mysqli_prepare($conn,
            "SELECT BoxNo FROM tb_proc1 WHERE ProdName = ? AND InvNo = ? AND WO = ?");
        mysqli_stmt_bind_param($lotBoxStmt, 'sss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw);
        mysqli_stmt_execute($lotBoxStmt);
        $lotBoxRes = mysqli_stmt_get_result($lotBoxStmt);
        $lotBoxSet = [];
        while ($lbRow = mysqli_fetch_assoc($lotBoxRes)) {
            $lotBoxSet[$lbRow['BoxNo']] = true;
        }

        $bcBoxNos   = $_POST['bc_boxno'] ?? [];
        $bcConds    = $_POST['bc_cond'] ?? [];
        $bcStatuses = $_POST['bc_status'] ?? [];
        $bcValid = is_array($bcBoxNos) && is_array($bcConds) && is_array($bcStatuses)
            && count($lotBoxSet) > 0
            && count(array_unique($bcBoxNos)) === count($lotBoxSet)
            && count($bcBoxNos) === count($lotBoxSet);
        if ($bcValid) {
            foreach ($bcBoxNos as $bcIdx => $bcBoxNo) {
                $bcCond   = $bcConds[$bcIdx] ?? '';
                $bcStatus = $bcStatuses[$bcIdx] ?? '';
                if (!isset($lotBoxSet[$bcBoxNo])
                    || !in_array($bcCond, ['ปกติ', 'ชำรุด'], true)
                    || !in_array($bcStatus, ['Accept', 'Reject', 'Hold', 'SpecialAccept'], true)
                    || ($bcCond === 'ชำรุด' && $bcStatus === 'Accept')) {
                    $bcValid = false;
                    break;
                }
            }
        }

        if ($formError !== '') {
            echo "<script>alert(" . json_encode($formError) . ");</script>";
        } elseif ($dupRow) {
            echo "<script>alert('There is redundant Product name, Invoice and WO in database. \\nPlease check the data intry');</script>";
        } elseif (!$bcValid) {
            echo "<script>alert('ข้อมูลสภาพกล่องไม่ครบหรือไม่ถูกต้อง ไม่ได้บันทึกข้อมูล');</script>";
        } else {
            // All-or-nothing: tb_proc2 + tb_proc2_sup + tb_proc2_box in one transaction.
            $saveOk = true;
            try {
                mysqli_begin_transaction($conn);

                $allBoxCon = $_POST['Decision'] ?? '';
                $insStmt = mysqli_prepare($conn,
                    "INSERT INTO `tb_proc2`
                     (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`AllBoxCon`,`PcsFromInv`,`SamplingSize`,`NGtotal`,`Status`,`Remark`)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($insStmt, "sssssisiiiss",
                    $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $date, $time, $opr,
                    $allBoxCon, $lot_amountinv, $lot_samplingsize, $ngTotal, $status, $remark);
                if (!mysqli_stmt_execute($insStmt)) {
                    throw new Exception('tb_proc2 insert failed');
                }

                $supBoxNos       = $_POST['box_subLot'] ?? [];
                $supSampledQtys  = $_POST['box_sampledqty'] ?? [];
                $supRemark       = 'sampled box';
                $supNgSumStmt = mysqli_prepare($conn,
                    "SELECT COALESCE(SUM(NGqty),0) AS ngSum FROM tb_ng WHERE ProdName = ? AND InvNo = ? AND WO = ? AND BoxNo = ?");
                $supStmt = mysqli_prepare($conn,
                    "INSERT INTO `tb_proc2_sup`
                     (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`BoxNo`,`SamplingSize`,`NGqty`,`Remark`)
                     VALUES (?,?,?,?,?,?,?,?,?,?)");
                foreach ($supBoxNos as $supIdx => $supBoxNo) {
                    $supSampledQty = (int)($supSampledQtys[$supIdx] ?? 0);

                    mysqli_stmt_bind_param($supNgSumStmt, 'ssss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $supBoxNo);
                    mysqli_stmt_execute($supNgSumStmt);
                    $supNgSumRow = mysqli_fetch_assoc(mysqli_stmt_get_result($supNgSumStmt));
                    $supNgQty = $supNgSumRow ? (int)$supNgSumRow['ngSum'] : 0;

                    mysqli_stmt_bind_param($supStmt, "sssssisiis",
                        $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $date, $time, $opr,
                        $supBoxNo, $supSampledQty, $supNgQty, $supRemark);
                    if (!mysqli_stmt_execute($supStmt)) {
                        throw new Exception('tb_proc2_sup insert failed');
                    }
                }

                // tb_proc2_box: UPDATE existing rows, INSERT new ones
                $bcExistStmt = mysqli_prepare($conn,
                    "SELECT 1 FROM tb_proc2_box WHERE ProdName = ? AND InvNo = ? AND WO = ? AND BoxNo = ? LIMIT 1");
                $bcUpdStmt = mysqli_prepare($conn,
                    "UPDATE `tb_proc2_box` SET `BoxCond` = ?, `BoxCondStatus` = ?, `Date` = ?, `Time` = ?, `Opr` = ?
                     WHERE `ProdName` = ? AND `InvNo` = ? AND `WO` = ? AND `BoxNo` = ?");
                $bcInsStmt = mysqli_prepare($conn,
                    "INSERT INTO `tb_proc2_box`
                     (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`BoxNo`,`BoxCond`,`BoxCondStatus`)
                     VALUES (?,?,?,?,?,?,?,?,?)");
                foreach ($bcBoxNos as $bcIdx => $bcBoxNo) {
                    $bcCond   = $bcConds[$bcIdx];
                    $bcStatus = $bcStatuses[$bcIdx];
                    mysqli_stmt_bind_param($bcExistStmt, 'ssss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $bcBoxNo);
                    mysqli_stmt_execute($bcExistStmt);
                    if (mysqli_fetch_assoc(mysqli_stmt_get_result($bcExistStmt))) {
                        mysqli_stmt_bind_param($bcUpdStmt, 'ssssissss',
                            $bcCond, $bcStatus, $date, $time, $opr,
                            $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $bcBoxNo);
                        $bcOk = mysqli_stmt_execute($bcUpdStmt);
                    } else {
                        mysqli_stmt_bind_param($bcInsStmt, 'sssssisss',
                            $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $date, $time, $opr,
                            $bcBoxNo, $bcCond, $bcStatus);
                        $bcOk = mysqli_stmt_execute($bcInsStmt);
                    }
                    if (!$bcOk) {
                        throw new Exception('tb_proc2_box save failed');
                    }
                }

                mysqli_commit($conn);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $saveOk = false;
            }

            if ($saveOk) {
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
      
    <form id="proc02Form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc2-g1">

        <!-- row1 -->
        <div class="pro3-proc2-g1-it-bl"></div>  
        <div class="pro3-proc2-g1-it"><label>Operator</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" id="oprDisplay" name="OprDisplay" value="<?php echo htmlspecialchars($_SESSION['us_name'] ?? ''); ?>" disabled required>
        </div>
        <div class="pro3-proc2-g1-it"><label>Date</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <!-- row2 -->
        <div class="pro3-proc2-g1-it-bl"></div>
        <div class="pro3-proc2-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_id; ?>" disabled>
        </div>
        <div class="pro3-proc2-g1-it"><label>Time</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="time" id="hdrTime" value="<?php echo date('H:i'); ?>" disabled>
        </div>        

        <!-- row3 -->
        <div class="pro3-proc2-g1-it-bl"></div>
        <div class="pro3-proc2-g1-it"><label>Product name</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_prodname; ?>" disabled>
          <input type="hidden" name="ProdName" value="<?php echo $lot_prodname; ?>">
        </div>
        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนกล่องตาม Inv</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" value="<?php echo $lot_boxcount; ?>" disabled>
        </div>        

        <!-- row4 -->
        <div class="pro3-proc2-g1-it-bl"></div>
        <div class="pro3-proc2-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_invno; ?>" disabled>
          <input type="hidden" name="InvNo" value="<?php echo $lot_invno; ?>">
        </div>
        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนชิ้นงานตาม Inv</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" value="<?php echo $lot_amountinv; ?>" min="0" disabled required>
          <input type="hidden" name="AmountInv" value="<?php echo $lot_amountinv; ?>">
        </div>
        
        <!-- row5 -->
        <div class="pro3-proc2-g1-it-bl"></div>
        <div class="pro3-proc2-g1-it"><label>WO</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_wo; ?>" disabled>
          <input type="hidden" name="WO" value="<?php echo $lot_wo; ?>">
        </div>
        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนชิ้นงานที่ถูกสุ่ม</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" value="<?php echo $lot_samplingsize; ?>" min="0" disabled required>
          <input type="hidden" name="SamplingSize" value="<?php echo $lot_samplingsize; ?>">
        </div>
        
        <!-- row6 -->
        <div class="pro3-proc2-g1-it-bl"><span class="blinkBullet">●</span></div>
        <div class="pro3-proc2-g1-it" style="color: red;"><label>Data from Lot Tag</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" id="lotTagData" autocomplete="off" placeholder="prod|wo|box|qty|mat" autofocus>
        </div>
        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนกล่องที่ถูกสุ่ม</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="incChkBox_qty" value="<?php echo $incChkBox_qty; ?>" disabled>
        </div>
      </div>

      <div id="input-boxcond">
        <div class="grid-title">เช็คสภาพกล่องทุกกล่อง (for tb_proc2_box)</div>
        <div class="boxcondbox-h">Box-no</div>
        <div class="boxcondbox-h">สภาพกล่อง</div>
        <div class="boxcondbox-h">BoxCondStatus</div>

        <?php foreach ($all_boxnos as $bcI => $abn):
          $bcRec = $boxcond_map[$abn] ?? null;
          $bcC   = $bcRec['BoxCond'] ?? '';
          $bcS   = $bcRec['BoxCondStatus'] ?? '';
        ?>
        <div class="pro3-proc2-bcond-c">
          <input type="text" name="bc_boxno[<?php echo $bcI; ?>]" value="<?php echo htmlspecialchars($abn, ENT_QUOTES); ?>" readonly>
        </div>
        <div class="pro3-proc2-bcond-c">
          <select name="bc_cond[<?php echo $bcI; ?>]" class="bc-cond" data-idx="<?php echo $bcI; ?>" onchange="handleBoxCond(this)" <?php echo $lot_saved ? 'disabled' : ''; ?>>
            <option value="" disabled <?php echo $bcC === '' ? 'selected' : ''; ?>>โปรดระบุ</option>
            <?php foreach (['ปกติ', 'ชำรุด'] as $opt): ?>
            <option value="<?php echo $opt; ?>" <?php echo $bcC === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pro3-proc2-bcond-c">
          <select name="bc_status[<?php echo $bcI; ?>]" class="bc-status" data-idx="<?php echo $bcI; ?>" <?php echo $lot_saved ? 'disabled' : ''; ?>>
            <option value="" disabled <?php echo $bcS === '' ? 'selected' : ''; ?>>โปรดระบุ</option>
            <?php foreach (['Accept', 'Reject', 'Hold', 'SpecialAccept'] as $opt): ?>
            <option value="<?php echo $opt; ?>" <?php echo $bcS === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endforeach; ?>
      </div>

      <?php
        $sorted_boxnos = $lot_boxnos;
        sort($sorted_boxnos);
        $ngSumStmt = mysqli_prepare($conn,
            "SELECT COALESCE(SUM(NGqty),0) AS ngSum FROM tb_ng WHERE ProdName = ? AND InvNo = ? AND WO = ? AND BoxNo = ?");
        $sampleResidual = $lot_samplingsize;
        foreach ($sorted_boxnos as $boxNo):
          mysqli_stmt_bind_param($ngSumStmt, 'ssss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $boxNo);
          mysqli_stmt_execute($ngSumStmt);
          $ngSumRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ngSumStmt));
          $ngSum = $ngSumRow ? (int)$ngSumRow['ngSum'] : 0;

          $boxQty = $lot_boxqty[$boxNo] ?? 0;
          $diff = $sampleResidual - $boxQty;
          $sampledQty = $diff <= 0 ? $sampleResidual : $boxQty;
          $sampleResidual = $diff;
      ?>
      <div class="pro3-proc2-qrset">
        <div class="pro3-proc2-qrset-it"><label>Box no</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" value="<?php echo htmlspecialchars($boxNo); ?>" disabled>
          <input type="hidden" name="box_subLot[]" value="<?php echo htmlspecialchars($boxNo); ?>">
        </div>

        <div class="pro3-proc2-qrset-it"><label style="font-size:0.8em;">ยิง QR ที่นี่</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" class="qr-scan-input" onkeydown="handleQrScan(event, '<?php echo htmlspecialchars($boxNo, ENT_QUOTES); ?>')">
        </div>

        <div class="pro3-proc2-qrset-it"><label style="font-size:0.8em;">เช็คการยิง QR</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" class="qr-result-input" disabled>
          <input type="hidden" class="qr-result-hidden" name="box_qrresult[]" value="">
        </div>

        <div class="pro3-proc2-qrset-it"><label style="font-size:0.8em;">จำนวนชิ้นงานที่ถูกสุ่ม</label></div>
        <div class="pro3-proc2-qrset-it">
          <input type="text" name="box_sampledqty[]" value="<?php echo $sampledQty; ?>">
        </div>

        <div class="pro3-proc2-qrset-it"><label style="font-size:0.8em;">เช็คชิ้นงาน</label></div>
        <div class="pro3-proc2-qrset-it">
          <select class="app-check-select" name="box_appcheck[]" onchange="handleAppCheck(this)" disabled>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="ผ่าน">ผ่าน</option>
            <option value="ไม่ผ่าน">ไม่ผ่าน</option>
          </select>
          <button type="button" class="ngTypeBtn" data-boxno="<?php echo htmlspecialchars($boxNo, ENT_QUOTES); ?>" style="display:none;">เลือก NG</button>
        </div>

        <div class="pro3-proc2-qrset-it"><label style="font-size:0.8em;">NG รวมของกล่อง</label></div>
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

        <div class="pro3-proc2-summary-it"><label>จำนวน NG reject</label></div>
        <div class="pro3-proc2-summary-it"><label><?php echo rejectQty($lot_amountinv); ?></label></div>

        <div class="pro3-proc2-summary-it"><label>ผลการตัดสินใจ</label></div>
        <div class="pro3-proc2-summary-it">
          <select name="Decision" id="decisionSelect" onchange="handleDecisionColor(this)">
            <option value="Accept" <?php echo $decision === 'Accept' ? 'selected' : ''; ?>>Accept</option>
            <option value="Reject" <?php echo $decision === 'Reject' ? 'selected' : ''; ?>>Reject</option>
            <option value="Hold" <?php echo $decision === 'Hold' ? 'selected' : ''; ?>>Hold</option>
            <option value="SpecialAccept" <?php echo $decision === 'SpecialAccept' ? 'selected' : ''; ?>>SpecialAccept</option>
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

  <script src="js/supportfunction.js"></script>
  <script>
    document.getElementById('lotTagData').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var lot = parseLotTagInput(this.value);
      if (!lot) {
        alert('Data from Lot Tag is error. Please re-check');
        this.value = '';
        this.focus();
        return;
      }

      var prodName = lot.prodName, wo = lot.wo, boxNo = lot.boxNo;

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
      const parts = input.value.trim().split('|').map(function (p) { return p.trim(); });
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

      const appCheckSelect = set.querySelector('.app-check-select');
      appCheckSelect.disabled = !ok;
      if (!ok) {
        appCheckSelect.value = '';
        handleAppCheck(appCheckSelect);
      }
    }

    function handleAppCheck(sel) {
      const btn = sel.parentElement.querySelector('.ngTypeBtn');
      btn.style.display = sel.value === 'ไม่ผ่าน' ? 'inline-block' : 'none';
    }

    function handleDecisionColor(sel) {
      const colors = { Accept: 'green', Reject: 'red', Hold: 'red' , SpecialAccept: 'orange' };
      sel.style.color = colors[sel.value] || '';
    }
    handleDecisionColor(document.getElementById('decisionSelect'));

    // Box condition: 'ชำรุด' cannot be Accept
    function handleBoxCond(condSel) {
      var statusSel = document.querySelector('.bc-status[data-idx="' + condSel.dataset.idx + '"]');
      var damaged = condSel.value === 'ชำรุด';
      statusSel.querySelector('option[value="Accept"]').disabled = damaged;
      if (damaged && statusSel.value === 'Accept') statusSel.value = '';
    }
    document.querySelectorAll('.bc-cond').forEach(handleBoxCond);

    document.getElementById('proc02Form').addEventListener('submit', function (e) {
      var incomplete = Array.prototype.some.call(document.querySelectorAll('.bc-cond'), function (condSel) {
        var statusSel = document.querySelector('.bc-status[data-idx="' + condSel.dataset.idx + '"]');
        return !condSel.value || !statusSel.value;
      });
      if (incomplete) {
        e.preventDefault();
        alert('กรุณาตรวจเช็คสภาพกล่องให้ครบ');
      }
    });

    var ngRedirectLotID = "<?php echo htmlspecialchars($lot_id_raw, ENT_QUOTES); ?>";

    document.querySelectorAll('.ngTypeBtn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'nie2_ng_input_proc02.php';

        function addField(name, value) {
          var inp = document.createElement('input');
          inp.type = 'hidden';
          inp.name = name;
          inp.value = value;
          form.appendChild(inp);
        }

        var qrset = btn.closest('.pro3-proc2-qrset');
        var smpPerBox = qrset ? qrset.querySelector('input[name="box_sampledqty[]"]').value : '';

        addField('sourcePathname', window.location.pathname);
        addField('lot_id_raw', ngRedirectLotID);
        addField('boxNo', btn.dataset.boxno);
        addField('selected_process', '2. Incoming');
        addField('smpPerBox', smpPerBox);

        document.body.appendChild(form);
        form.submit();
      });
    });
  </script>
</body>
</html>
