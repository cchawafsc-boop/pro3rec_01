<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // Restore lot context after a page reload (e.g. after inspection submit)
    $lot_id       = $_GET['lot_id'] ?? '';
    $lot_prodname = $_GET['ProdName'] ?? '';
    $lot_invno    = $_GET['InvNo'] ?? '';
    $lot_wo       = $_GET['WO'] ?? '';

    // AJAX: resolve Lot ID / Product name / Invoice no from a Box Tag scan
    // (prodName+wo+boxNo), same pattern as nie2_proc03.php's GET lookup.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_resolve_lot'])) {
        header('Content-Type: application/json');

        $rProdName = $_POST['ProdName'] ?? '';
        $rWo       = $_POST['WO'] ?? '';
        $rBoxNo    = $_POST['BoxNo'] ?? '';

        $rstmt = mysqli_prepare($conn,
            "SELECT LotID, ProdName, InvNo, WO FROM tb_proc1 WHERE ProdName = ? AND WO = ? AND BoxNo = ? LIMIT 1");
        mysqli_stmt_bind_param($rstmt, 'sss', $rProdName, $rWo, $rBoxNo);
        mysqli_stmt_execute($rstmt);
        $rrow = mysqli_fetch_assoc(mysqli_stmt_get_result($rstmt));

        if ($rrow) {
            echo json_encode([
                'status'   => 'ok',
                'lot_id'   => $rrow['LotID'],
                'prodname' => $rrow['ProdName'],
                'invno'    => $rrow['InvNo'],
                'wo'       => $rrow['WO'],
            ]);
        } else {
            echo json_encode(['status' => 'fail']);
        }
        mysqli_close($conn);
        exit;
    }

    function calcSamplingSize($n) {
        /* This calculation is from AQL level II 0.65 */
        if ($n >= 1    && $n <= 20)   return $n;
        if ($n >= 21   && $n <= 280)  return 20;
        if ($n >= 281  && $n <= 1200) return 80;
        if ($n >= 1201 && $n <= 3200) return 125;
        return 0;
    }

    // AJAX: calc smpPerRack from pcsPerRack input, based on total PcsFromInv for lot
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_calc_smp'])) {
        header('Content-Type: application/json');

        $cLotId      = $_POST['lot_id'] ?? '';
        $cPcsPerRack = (int)($_POST['pcsPerRack'] ?? 0);

        $cStmt = mysqli_prepare($conn,
            "SELECT ProdName, InvNo, WO FROM tb_proc1 WHERE LotID = ? LIMIT 1");
        mysqli_stmt_bind_param($cStmt, 's', $cLotId);
        mysqli_stmt_execute($cStmt);
        $cRow = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt));

        if ($cRow && $cPcsPerRack > 0) {
            $sumStmt = mysqli_prepare($conn,
                "SELECT COALESCE(SUM(PcsFromInv),0) AS totalPcs FROM tb_proc2 WHERE ProdName = ? AND InvNo = ? AND WO = ?");
            mysqli_stmt_bind_param($sumStmt, 'sss', $cRow['ProdName'], $cRow['InvNo'], $cRow['WO']);
            mysqli_stmt_execute($sumStmt);
            $sumRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sumStmt));
            $totalPcs = (int)$sumRow['totalPcs'];

            $smpSizeFromInv = calcSamplingSize($totalPcs);
            $smpPerRack = (int)ceil($smpSizeFromInv / $cPcsPerRack);

            echo json_encode(['status' => 'ok', 'smpPerRack' => $smpPerRack]);
        } else {
            echo json_encode(['status' => 'ok', 'smpPerRack' => 0]);
        }
        mysqli_close($conn);
        exit;
    }

    // AJAX: list existing tb_proc6_box records for a ProdName+InvNo+WO,
    // with each row's Racking-Act-qty pulled live from tb_proc3_box.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_list_amount'])) {
        header('Content-Type: application/json');

        $lProdName = $_POST['ProdName'] ?? '';
        $lInvNo    = $_POST['InvNo'] ?? '';
        $lWo       = $_POST['WO'] ?? '';

        $records = [];
        $lstmt = mysqli_prepare($conn,
            "SELECT p6.BoxNo, p6.LotTagQty, p3b.ActualQty AS RackActQty, p6.ActualQty AS QCActQty, p6.ShortOver, p6.Remark
             FROM tb_proc6_box p6
             LEFT JOIN tb_proc3_box p3b
                ON p3b.ProdName = p6.ProdName AND p3b.InvNo = p6.InvNo AND p3b.WO = p6.WO AND p3b.BoxNo = p6.BoxNo
             WHERE p6.ProdName = ? AND p6.InvNo = ? AND p6.WO = ?");
        mysqli_stmt_bind_param($lstmt, 'sss', $lProdName, $lInvNo, $lWo);
        mysqli_stmt_execute($lstmt);
        $lres = mysqli_stmt_get_result($lstmt);
        while ($lrow = mysqli_fetch_assoc($lres)) {
            $records[] = $lrow;
        }

        echo json_encode(['status' => 'ok', 'records' => $records]);
        mysqli_close($conn);
        exit;
    }

    // AJAX: fetch Racking-Act-qty (tb_proc3_box.ActualQty) for a given box.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_get_rackactqty'])) {
        header('Content-Type: application/json');

        $gProdName = $_POST['ProdName'] ?? '';
        $gInvNo    = $_POST['InvNo'] ?? '';
        $gWo       = $_POST['WO'] ?? '';
        $gBoxNo    = $_POST['BoxNo'] ?? '';

        $gstmt = mysqli_prepare($conn,
            "SELECT ActualQty FROM tb_proc3_box WHERE ProdName=? AND InvNo=? AND WO=? AND BoxNo=? LIMIT 1");
        mysqli_stmt_bind_param($gstmt, 'ssss', $gProdName, $gInvNo, $gWo, $gBoxNo);
        mysqli_stmt_execute($gstmt);
        $grow = mysqli_fetch_assoc(mysqli_stmt_get_result($gstmt));

        if ($grow) {
            echo json_encode(['status' => 'ok', 'rackActQty' => (int)$grow['ActualQty']]);
        } else {
            echo json_encode(['status' => 'fail']);
        }
        mysqli_close($conn);
        exit;
    }

    // AJAX: insert a new box-amount record into tb_proc6_box
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_insert_amount'])) {
        header('Content-Type: application/json');

        $aProdName   = $_POST['ProdName'] ?? '';
        $aInvNo      = $_POST['InvNo'] ?? '';
        $aWo         = $_POST['WO'] ?? '';
        $aDate       = $_POST['Date'] ?? '';
        $aTime       = $_POST['Time'] ?? '';
        $aOpr        = (int)($_POST['Opr'] ?? 0);
        $aBoxNo      = $_POST['BoxNo'] ?? '';
        $aLotTagQty  = (int)($_POST['LotTagQty'] ?? 0);
        $aActualQty  = (int)($_POST['ActualQty'] ?? 0);
        $aShortOver  = (int)($_POST['ShortOver'] ?? 0);
        $aRemark     = $_POST['Remark'] ?? '';

        $aDupStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_proc6_box WHERE ProdName=? AND InvNo=? AND WO=? AND BoxNo=? LIMIT 1");
        mysqli_stmt_bind_param($aDupStmt, 'ssss', $aProdName, $aInvNo, $aWo, $aBoxNo);
        mysqli_stmt_execute($aDupStmt);
        $aDupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($aDupStmt));

        if ($aDupRow) {
            echo json_encode(['status' => 'dup', 'message' => 'มีข้อมูลซ้ำซ้อนในฐานข้อมูล กรุณาตรวจสอบ']);
        } else {
            $aStmt = mysqli_prepare($conn,
                "INSERT INTO `tb_proc6_box` (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`BoxNo`,`LotTagQty`,`ActualQty`,`ShortOver`,`Remark`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($aStmt, 'sssssisiiis',
                $aProdName, $aInvNo, $aWo, $aDate, $aTime, $aOpr, $aBoxNo, $aLotTagQty, $aActualQty, $aShortOver, $aRemark);
            $aok = mysqli_stmt_execute($aStmt);
            echo json_encode(['status' => $aok ? 'ok' : 'fail', 'message' => $aok ? '' : mysqli_error($conn)]);
        }
        mysqli_close($conn);
        exit;
    }

    // AJAX: delete one box-amount record from tb_proc6_box
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_amount'])) {
        header('Content-Type: application/json');

        $xProdName = $_POST['ProdName'] ?? '';
        $xInvNo    = $_POST['InvNo'] ?? '';
        $xWo       = $_POST['WO'] ?? '';
        $xBoxNo    = $_POST['BoxNo'] ?? '';

        $xstmt = mysqli_prepare($conn,
            "DELETE FROM tb_proc6_box WHERE ProdName=? AND InvNo=? AND WO=? AND BoxNo=? LIMIT 1");
        mysqli_stmt_bind_param($xstmt, 'ssss', $xProdName, $xInvNo, $xWo, $xBoxNo);
        $xok = mysqli_stmt_execute($xstmt);
        echo json_encode(['status' => $xok ? 'ok' : 'fail', 'message' => $xok ? '' : mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
    }

    // AJAX: list existing tb_proc6 inspect records for a ProdName+InvNo+WO
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_list_inspect'])) {
        header('Content-Type: application/json');

        $liProdName = $_POST['ProdName'] ?? '';
        $liInvNo    = $_POST['InvNo'] ?? '';
        $liWo       = $_POST['WO'] ?? '';

        $records = [];
        $listmt = mysqli_prepare($conn,
            "SELECT BoxNo, PlateNo, ActualQty, SmpPerRack, InspFGqty, inspNGqty, inspNGmode, Status, Remark
             FROM tb_proc6 WHERE ProdName = ? AND InvNo = ? AND WO = ?");
        mysqli_stmt_bind_param($listmt, 'sss', $liProdName, $liInvNo, $liWo);
        mysqli_stmt_execute($listmt);
        $lires = mysqli_stmt_get_result($listmt);
        while ($lirow = mysqli_fetch_assoc($lires)) {
            $records[] = $lirow;
        }

        echo json_encode(['status' => 'ok', 'records' => $records]);
        mysqli_close($conn);
        exit;
    }

    // AJAX: insert a new inspection record into tb_proc6
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_insert_inspect'])) {
        header('Content-Type: application/json');

        $iProdName    = $_POST['ProdName'] ?? '';
        $iInvNo       = $_POST['InvNo'] ?? '';
        $iWo          = $_POST['WO'] ?? '';
        $iDate        = $_POST['Date'] ?? '';
        $iTime        = $_POST['Time'] ?? '';
        $iOpr         = (int)($_POST['Opr'] ?? 0);
        $iBoxNo       = $_POST['BoxNo'] ?? '';
        $iPlateNo     = (int)($_POST['PlateNo'] ?? 0);
        $iActualQty   = (int)($_POST['ActualQty'] ?? 0);
        $iSmpPerRack  = (int)($_POST['SmpPerRack'] ?? 0);
        $iInspFGqty   = (int)($_POST['InspFGqty'] ?? 0);
        $iInspNGqty   = (int)($_POST['inspNGqty'] ?? 0);
        $iInspNGmode  = $_POST['inspNGmode'] ?? '';
        $iStatus      = $_POST['Status'] ?? '';
        $iRemark      = $_POST['Remark'] ?? '';

        $iDupStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_proc6 WHERE ProdName=? AND InvNo=? AND WO=? AND PlateNo=? LIMIT 1");
        mysqli_stmt_bind_param($iDupStmt, 'sssi', $iProdName, $iInvNo, $iWo, $iPlateNo);
        mysqli_stmt_execute($iDupStmt);
        $iDupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($iDupStmt));

        if ($iDupRow) {
            echo json_encode(['status' => 'dup', 'message' => 'มีข้อมูลซ้ำในฐานข้อมูล โปรดตรวจสอบความถูกต้อง']);
        } else {
            $iStmt = mysqli_prepare($conn,
                "INSERT INTO `tb_proc6`
                    (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`BoxNo`,`PlateNo`,`ActualQty`,`SmpPerRack`,`InspFGqty`,`inspNGqty`,`inspNGmode`,`Status`,`Remark`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($iStmt, 'sssssisiiiiisss',
                $iProdName, $iInvNo, $iWo, $iDate, $iTime, $iOpr, $iBoxNo, $iPlateNo,
                $iActualQty, $iSmpPerRack, $iInspFGqty, $iInspNGqty, $iInspNGmode, $iStatus, $iRemark);
            $iok = mysqli_stmt_execute($iStmt);
            echo json_encode(['status' => $iok ? 'ok' : 'fail', 'message' => $iok ? '' : mysqli_error($conn)]);
        }
        mysqli_close($conn);
        exit;
    }

    // AJAX: delete one inspection record from tb_proc6
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_inspect'])) {
        header('Content-Type: application/json');

        $diProdName = $_POST['ProdName'] ?? '';
        $diInvNo    = $_POST['InvNo'] ?? '';
        $diWo       = $_POST['WO'] ?? '';
        $diPlateNo  = (int)($_POST['PlateNo'] ?? 0);

        $distmt = mysqli_prepare($conn,
            "DELETE FROM tb_proc6 WHERE ProdName=? AND InvNo=? AND WO=? AND PlateNo=? LIMIT 1");
        mysqli_stmt_bind_param($distmt, 'sssi', $diProdName, $diInvNo, $diWo, $diPlateNo);
        $diok = mysqli_stmt_execute($distmt);
        echo json_encode(['status' => $diok ? 'ok' : 'fail', 'message' => $diok ? '' : mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
    }

    // AJAX: insert a new lot-level inspection summary record into tb_proc6_sum
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_insert_summary'])) {
        header('Content-Type: application/json');

        $sProdName     = $_POST['ProdName'] ?? '';
        $sInvNo        = $_POST['InvNo'] ?? '';
        $sWo           = $_POST['WO'] ?? '';
        $sDate         = $_POST['Date'] ?? '';
        $sTime         = $_POST['Time'] ?? '';
        $sOpr          = (int)($_POST['Opr'] ?? 0);
        $sLotTagQty    = (int)($_POST['LotTagQty'] ?? 0);
        $sInspFGqty    = (int)($_POST['InspFGqty'] ?? 0);
        $sInspNGqty    = (int)($_POST['InspNGqty'] ?? 0);
        $sInspFGNGqty  = (int)($_POST['InspFGNGqty'] ?? 0);
        $sShortOver    = (int)($_POST['ShortOver'] ?? 0);
        $sAmountChk    = $_POST['AmountChk'] ?? '';
        $sQCchk        = $_POST['QCchk'] ?? '';
        $sQCdone       = $_POST['QCdone'] ?? '';

        $sDupStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_proc6_sum WHERE ProdName=? AND InvNo=? AND WO=? LIMIT 1");
        mysqli_stmt_bind_param($sDupStmt, 'sss', $sProdName, $sInvNo, $sWo);
        mysqli_stmt_execute($sDupStmt);
        $sDupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($sDupStmt));

        if ($sDupRow) {
            echo json_encode(['status' => 'dup', 'message' => 'พบข้อมูลซ้ำในฐานข้อมูล กรุณาตรวจสอบความถูกต้อง']);
        } else {
            $sStmt = mysqli_prepare($conn,
                "INSERT INTO `tb_proc6_sum`
                    (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`LotTagQty`,`InspFGqty`,`InspNGqty`,`InspFGNGqty`,`ShortOver`,`AmountChk`,`QCchk`,`QCdone`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($sStmt, 'sssssiiiiiisss',
                $sProdName, $sInvNo, $sWo, $sDate, $sTime, $sOpr,
                $sLotTagQty, $sInspFGqty, $sInspNGqty, $sInspFGNGqty, $sShortOver,
                $sAmountChk, $sQCchk, $sQCdone);
            $sok = mysqli_stmt_execute($sStmt);
            echo json_encode(['status' => $sok ? 'ok' : 'fail', 'message' => $sok ? '' : mysqli_error($conn)]);
        }
        mysqli_close($conn);
        exit;
    }

    // NG mode options for inspection
    $ngModeList = [];
    $nmStmt = mysqli_prepare($conn, "SELECT NGmode FROM tb_ng_list WHERE Process = 'All'");
    mysqli_stmt_execute($nmStmt);
    $nmRes = mysqli_stmt_get_result($nmStmt);
    while ($nmRow = mysqli_fetch_assoc($nmRes)) {
        $ngModeList[] = $nmRow['NGmode'];
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
          <input type="text" id="lotIdDisplay" value="<?php echo htmlspecialchars($lot_id); ?>" disabled>
        </div>

        <div class="pro3-proc6-g1-it"><label>Product name</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="text" id="lotProdnameDisplay" value="<?php echo htmlspecialchars($lot_prodname); ?>" disabled>
          <input type="hidden" id="lotProdnameHidden" name="ProdName" value="<?php echo htmlspecialchars($lot_prodname); ?>">
        </div>

        <div class="pro3-proc6-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="text" id="lotInvnoDisplay" value="<?php echo htmlspecialchars($lot_invno); ?>" disabled>
          <input type="hidden" id="lotInvnoHidden" name="InvNo" value="<?php echo htmlspecialchars($lot_invno); ?>">
        </div>

        <div class="pro3-proc6-g1-it"><label>WO</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="text" id="lotWoDisplay" value="<?php echo htmlspecialchars($lot_wo); ?>" disabled>
          <input type="hidden" id="lotWoHidden" name="WO" value="<?php echo htmlspecialchars($lot_wo); ?>">
        </div>

        <div class="pro3-proc6-g1-it"><label>Date</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="date" id="rackDate" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc6-g1-it"><label>Time</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="time" id="rackTime" name="Time" value="<?php echo date('H:i'); ?>" disabled>
        </div>

        <div class="pro3-proc6-g1-it"><label style="font-size:0.8em;">จำนวนชิ้นต่อแร็ก</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="number" id="pcsPerRack" name="pcsPerRack" placeholder="โปรดระบุ" min="0">
        </div>

        <div class="pro3-proc6-g1-it"><label style="font-size:0.8em;">จำนวนสุ่มต่อแร็ก</label></div>
        <div class="pro3-proc6-g1-it">
          <input type="number" id="smpPerRack_F" name="smpPerRack_F" value="" disabled>
        </div>

      </div>

      <div id="input-amount">
        <div class="grid-title">ข้อมูลจำนวนของกล่อง</div>
        <div class="amtbox-h">Box-no</div>
        <div class="amtbox-h" style="font-size:0.8em;">จำนวนชิ้นตาม Lot tag</div>
        <div class="amtbox-h" style="font-size:0.8em;">จำนวนชิ้นที่นับจริงที่ Racking</div>
        <div class="amtbox-h" style="font-size:0.8em;">จำนวนชิ้นที่นับจริงที่ Inspect</div>
        <div class="amtbox-h" style="font-size:0.8em;">จำนวนขาด / เกิน</div>
        <div class="amtbox-h">Remark</div>
        <div class="amtbox-h">Action</div>

        <div class="amtbox-c" id="amtEntryRowAnchor"><input type="text" id="newAmtBoxNo" disabled></div>
        <div class="amtbox-c"><input type="number" id="newAmtLotTagQty" placeholder="LotTag-qty" min="0"></div>
        <div class="amtbox-c"><input type="number" id="newAmtRackActQty" readonly></div>
        <div class="amtbox-c"><input type="number" id="newAmtQCActQty" placeholder="QC-Act-qty" min="0"></div>
        <div class="amtbox-c"><input type="number" id="newAmtShortOver" readonly></div>
        <div class="amtbox-c"><textarea id="newAmtRemark" rows="2"></textarea></div>
        <div class="amtbox-c"><button type="button" id="newAmtSubmitBtn">บันทึก</button></div>
      </div>


      <div id="input-inspect" class="pro3-proc6-g2">
        <div class="grid-title">ข้อมูลการ Inspection ของ QC</div>
        <!-- 1 --><div class="pro3-proc6-g2-h">Box-no</div>
        <!-- 2 --><div class="pro3-proc6-g2-h">Plate-no</div>
        <!-- 3 --><div class="pro3-proc6-g2-h" style="font-size:0.8em;">จำนวนจริงต่อแร็ก</div>
        <!-- 4 --><div class="pro3-proc6-g2-h" style="font-size:0.8em;">จำนวนสุ่มต่อแร็ก</div>
        <!-- 5 --><div class="pro3-proc6-g2-h" style="font-size:0.8em;">จำนวนสุ่มเป็น FG</div>
        <!-- 6 --><div class="pro3-proc6-g2-h" style="font-size:0.8em;">จำนวนสุ่มเป็น NG</div>
        <!-- 7 --><div class="pro3-proc6-g2-h" style="font-size:0.8em;">NG-mode</div>
        <!-- 8 --><div class="pro3-proc6-g2-h">Status</div> 
        <!-- 9 --><div class="pro3-proc6-g2-h">Remark</div>
        <!--10 --><div class="pro3-proc6-g2-h">Action</div>

        <!-- 1 --><div class="pro3-proc6-g2-c" id="entryRowAnchor"><input type="text" id="newBoxNo" placeholder="Lot Tag" required></div>
        <!-- 2 --><div class="pro3-proc6-g2-c"><input type="text"   id="newPlateNo" placeholder="Plate-no" required></div>
        <!-- 3 --><div class="pro3-proc6-g2-c"><input type="number" id="newActQty"  placeholder="จำนวนจริง" min="0" required></div>
        <!-- 4 --><div class="pro3-proc6-g2-c"><input type="number" id="newSmpQty"  placeholder="จำนวนสุ่ม" min="0" required></div>
        <!-- 5 --><div class="pro3-proc6-g2-c"><input type="number" id="newFGQty"  placeholder="จำนวน FG" min="0" required></div>
        <!-- 6 --><div class="pro3-proc6-g2-c"><input type="number" id="newNGQty"  placeholder="จำนวน NG" min="0" required></div>
        <!-- 7 --><div class="pro3-proc6-g2-c">
          <select id="newNGmode">
            <option value="No NG" selected disabled>ไม่พบ NG</option>
            <?php foreach ($ngModeList as $mode): ?>
            <option value="<?php echo htmlspecialchars($mode); ?>"><?php echo htmlspecialchars($mode); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- 8 --><div class="pro3-proc6-g2-c">
          <select id="newStatus">
            <option value="" selected>โปรดระบุ</option>
            <option value="Accept">Accept</option>
            <option value="Reject">Reject</option>
            <option value="Hold">Hold</option>
            <option value="SpecialAccept">SpecialAccept</option>
          </select>
        </div>
        <!-- 9 --><div class="pro3-proc6-g2-c"><textarea id="newRemark" rows="2"></textarea></div>
        <!--10 --><div class="pro3-proc6-g2-c"><button type="button" id="newInspSubmitBtn">บันทึก</button></div>
      </div>


      <div class="pro3-proc6-summary">
        <div class="pro3-proc6-summary-title">สรุปข้อมูล Inspection</div>

        <div class="pro3-proc6-summary-it gs-lottag">
          <span class="gs-label">จำนวนชิ้นตาม Lot tag</span>
          <input type="number" name="sum_LotTagQty" id="sum_LotTagQty">
        </div>

        <div class="pro3-proc6-summary-it gs-rack">
          <span class="gs-label">จำนวนชิ้นที่นับจริงที่ Racking</span>
          <input type="number" name="sum_RackingQty" id="sum_RackingQty">
        </div>

        <div class="pro3-proc6-summary-it gs-fg">
          <span class="gs-label">จำนวน FG ที่ Inspect</span>
          <input type="number" name="sum_InspFGQty" id="sum_InspFGQty">
        </div>

        <div class="pro3-proc6-summary-it gs-ng">
          <span class="gs-label">จำนวน NG ที่ Inspect</span>
          <input type="number" name="sum_InspNGQty" id="sum_InspNGQty">
        </div>

        <div class="pro3-proc6-summary-it gs-total">
          <span class="gs-label">จำนวนชิ้นรวมที่ Inspect</span>
          <input type="number" name="sum_InpspQty" id="sum_InspQty">
        </div>

        <div class="pro3-proc6-summary-it gs-shortover">
          <span class="gs-label">ขาด/เกิน เทียบ Lot tag</span>
          <input type="number" name="sum_ShortOver" id="sum_ShortOver">
        </div>

        <div class="pro3-proc6-summary-it gs-amtjudge">
          <span class="gs-label">การตรวจเช็คจำนวน</span>
          <select name="inspAmountChk" id="inspAmountChk">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Correct">ถูกต้อง</option>
            <option value="Incorrect">ไม่ถูกต้อง</option>
          </select>
        </div>

        <div class="pro3-proc6-summary-it gs-qcjudge">
          <span class="gs-label">การตรวจเช็คคุณภาพ</span>
          <select name="inspQCJudge" id="inspQCJudge">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Pass">Pass</option>
            <option value="Fail">Fail</option>
          </select>
        </div>

        <div class="pro3-proc6-summary-it gs-status">
          <span class="gs-label">การจบขั้นตอน Inspection</span>
          <select name="inspStatus" id="inspStatus">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Done">QC Done</option>
            <option value="Hold">QC Hold</option>
            <option value="Reject">QC Reject</option>
          </select>
        </div>

        <div class="pro3-proc6-summary-it gs-submit">
          <button type="button" id="submitInspProcessBtn">บันทึก</button>
        </div>

      </div>

      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2 </button>
      </p>
    </form>
    <div id="์page-note">
      <span>หมายเหตุ</span><br>
      <span style="font-size: small;">NG ของ QC inspection ต้องถูกตัดสินใหม่จาก QC/QA แยกจาก NG ของ Production</span><br>
      <span style="font-size: small;">ต้อง<span style="color: red;"><strong>ไม่</strong></span>เอา NG ของ Production มาลงโดยทันที</span>
    </div>
  </div>

  <?php mysqli_close($conn); ?>
  <script src="js/supportfunction.js"></script>
  <script>
    document.getElementById('newBoxNo').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var lot = parseLotTagInput(this.value);
      if (!lot) {
        alert('Data from Lot Tag is error. Please re-check');
        this.value = '';
        this.focus();
        return;
      }

      var input = this;
      var payload = new URLSearchParams({
        ajax_resolve_lot: '1',
        ProdName: lot.prodName,
        WO: lot.wo,
        BoxNo: lot.boxNo
      });

      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status === 'ok') {
            document.getElementById('lotIdDisplay').value = data.lot_id;
            document.getElementById('lotProdnameDisplay').value = data.prodname;
            document.getElementById('lotProdnameHidden').value = data.prodname;
            document.getElementById('lotInvnoDisplay').value = data.invno;
            document.getElementById('lotInvnoHidden').value = data.invno;
            document.getElementById('lotWoDisplay').value = data.wo;
            document.getElementById('lotWoHidden').value = data.wo;
            input.value = lot.boxNo;

            document.getElementById('newAmtBoxNo').value = lot.boxNo;
            document.getElementById('newAmtLotTagQty').value = lot.boxQty;
            updateAmtShortOver();
            fetchRackActQty(data.prodname, data.invno, data.wo, lot.boxNo);
            fetchAndRenderAmountRows(data.prodname, data.invno, data.wo);
            fetchAndRenderInspectRows(data.prodname, data.invno, data.wo);

            document.getElementById('newPlateNo').focus();
          } else {
            alert('ไม่พบ Box-no ในฐานข้อมูล โปรดตรวจสอบอีกครั้ง');
            input.value = '';
            input.focus();
          }
        })
        .catch(function () {
          alert('เกิดข้อผิดพลาด');
          input.focus();
        });
    });

    document.getElementById('pcsPerRack').addEventListener('change', function () {
      var lotId = document.getElementById('lotIdDisplay').value;
      var pcsPerRack = this.value;
      var smpField = document.getElementById('smpPerRack_F');

      if (!lotId || !pcsPerRack) {
        smpField.value = '';
        document.getElementById('newSmpQty').value = '';
        return;
      }

      var payload = new URLSearchParams({
        ajax_calc_smp: '1',
        lot_id: lotId,
        pcsPerRack: pcsPerRack
      });

      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          smpField.value = data.status === 'ok' ? data.smpPerRack : '';
          document.getElementById('newSmpQty').value = smpField.value;
        })
        .catch(function () {
          alert('เกิดข้อผิดพลาด');
        });
    });

    document.getElementById('newPlateNo').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newActQty').focus();
    });

    document.getElementById('newActQty').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      var smpField = document.getElementById('newSmpQty');
      smpField.value = document.getElementById('smpPerRack_F').value;
      smpField.focus();
    });

    document.getElementById('newSmpQty').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newFGQty').focus();
    });

    document.getElementById('newFGQty').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newNGQty').focus();
    });

    function fetchRackActQty(prodname, invno, wo, boxNo) {
      var field = document.getElementById('newAmtRackActQty');
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          ajax_get_rackactqty: '1',
          ProdName: prodname,
          InvNo: invno,
          WO: wo,
          BoxNo: boxNo
        })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          field.value = data.status === 'ok' ? data.rackActQty : '';
        })
        .catch(function () {
          field.value = '';
        });
    }

    function buildAmountRow(rec) {
      var wrap = document.createDocumentFragment();
      [rec.BoxNo, rec.LotTagQty, rec.RackActQty, rec.QCActQty, rec.ShortOver, rec.Remark].forEach(function (val) {
        var cell = document.createElement('div');
        cell.className = 'amtbox-c pro3-amtbox-record-row';
        cell.textContent = val;
        wrap.appendChild(cell);
      });

      var actionCell = document.createElement('div');
      actionCell.className = 'amtbox-c pro3-amtbox-record-row';
      var delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.textContent = 'delete';
      delBtn.addEventListener('click', function () {
        if (!confirm('ต้องการลบรายการนี้หรือไม่')) return;

        var ctx = currentAmtLotCtx();
        delBtn.disabled = true;
        fetch(location.href, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            ajax_delete_amount: '1',
            ProdName: ctx.prodname,
            InvNo: ctx.invno,
            WO: ctx.wo,
            BoxNo: rec.BoxNo
          })
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.status === 'ok') {
              fetchAndRenderAmountRows(ctx.prodname, ctx.invno, ctx.wo);
            } else {
              alert(data.message || 'ลบไม่สำเร็จ');
              delBtn.disabled = false;
            }
          })
          .catch(function () {
            alert('เกิดข้อผิดพลาด');
            delBtn.disabled = false;
          });
      });
      actionCell.appendChild(delBtn);
      wrap.appendChild(actionCell);

      return wrap;
    }

    function renderAmountRows(records) {
      document.querySelectorAll('.pro3-amtbox-record-row').forEach(function (el) { el.remove(); });
      var anchor = document.getElementById('amtEntryRowAnchor');
      records.forEach(function (rec) {
        anchor.parentNode.insertBefore(buildAmountRow(rec), anchor);
      });
    }

    function fetchAndRenderAmountRows(prodname, invno, wo) {
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          ajax_list_amount: '1',
          ProdName: prodname,
          InvNo: invno,
          WO: wo
        })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status === 'ok') renderAmountRows(data.records || []);
        });
    }

    function currentAmtLotCtx() {
      return {
        prodname: document.getElementById('lotProdnameHidden').value,
        invno: document.getElementById('lotInvnoHidden').value,
        wo: document.getElementById('lotWoHidden').value
      };
    }

    function updateAmtShortOver() {
      var lotTagQty = parseInt(document.getElementById('newAmtLotTagQty').value, 10);
      var qcActQty = parseInt(document.getElementById('newAmtQCActQty').value, 10);
      var shortOverField = document.getElementById('newAmtShortOver');
      if (isNaN(lotTagQty) || isNaN(qcActQty)) {
        shortOverField.value = '';
        return;
      }
      shortOverField.value = lotTagQty - qcActQty;
    }
    document.getElementById('newAmtLotTagQty').addEventListener('input', updateAmtShortOver);
    document.getElementById('newAmtQCActQty').addEventListener('input', updateAmtShortOver);

    document.getElementById('newAmtSubmitBtn').addEventListener('click', function () {
      var btn = this;
      var ctx = currentAmtLotCtx();
      var payload = new URLSearchParams({
        ajax_insert_amount: '1',
        ProdName:  ctx.prodname,
        InvNo:     ctx.invno,
        WO:        ctx.wo,
        Date:      document.getElementById('rackDate').value,
        Time:      document.getElementById('rackTime').value,
        Opr:       document.querySelector('input[name="Opr"]').value,
        BoxNo:     document.getElementById('newAmtBoxNo').value,
        LotTagQty: document.getElementById('newAmtLotTagQty').value,
        ActualQty: document.getElementById('newAmtQCActQty').value,
        ShortOver: document.getElementById('newAmtShortOver').value,
        Remark:    document.getElementById('newAmtRemark').value
      });

      btn.disabled = true;
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status === 'ok') {
            document.getElementById('newAmtLotTagQty').value = '';
            document.getElementById('newAmtQCActQty').value = '';
            document.getElementById('newAmtShortOver').value = '';
            document.getElementById('newAmtRemark').value = '';
            fetchAndRenderAmountRows(ctx.prodname, ctx.invno, ctx.wo);
          } else {
            alert(data.message || 'บันทึกไม่สำเร็จ');
          }
          btn.disabled = false;
        })
        .catch(function () {
          alert('เกิดข้อผิดพลาด');
          btn.disabled = false;
        });
    });

    function buildInspectRow(rec) {
      var wrap = document.createDocumentFragment();
      [rec.BoxNo, rec.PlateNo, rec.ActualQty, rec.SmpPerRack, rec.InspFGqty, rec.inspNGqty, rec.inspNGmode, rec.Status, rec.Remark].forEach(function (val) {
        var cell = document.createElement('div');
        cell.className = 'pro3-proc6-g2-c pro3-insp-record-row';
        cell.textContent = val;
        wrap.appendChild(cell);
      });

      var actionCell = document.createElement('div');
      actionCell.className = 'pro3-proc6-g2-c pro3-insp-record-row';
      var delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.textContent = 'delete';
      delBtn.addEventListener('click', function () {
        if (!confirm('ต้องการลบรายการนี้หรือไม่')) return;

        var ctx = currentAmtLotCtx();
        delBtn.disabled = true;
        fetch(location.href, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            ajax_delete_inspect: '1',
            ProdName: ctx.prodname,
            InvNo: ctx.invno,
            WO: ctx.wo,
            PlateNo: rec.PlateNo
          })
        })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data.status === 'ok') {
              fetchAndRenderInspectRows(ctx.prodname, ctx.invno, ctx.wo);
            } else {
              alert(data.message || 'ลบไม่สำเร็จ');
              delBtn.disabled = false;
            }
          })
          .catch(function () {
            alert('เกิดข้อผิดพลาด');
            delBtn.disabled = false;
          });
      });
      actionCell.appendChild(delBtn);
      wrap.appendChild(actionCell);

      return wrap;
    }

    function renderInspectRows(records) {
      document.querySelectorAll('.pro3-insp-record-row').forEach(function (el) { el.remove(); });
      var anchor = document.getElementById('entryRowAnchor');
      records.forEach(function (rec) {
        anchor.parentNode.insertBefore(buildInspectRow(rec), anchor);
      });
    }

    function fetchAndRenderInspectRows(prodname, invno, wo) {
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          ajax_list_inspect: '1',
          ProdName: prodname,
          InvNo: invno,
          WO: wo
        })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status === 'ok') renderInspectRows(data.records || []);
        });
    }

    document.getElementById('newInspSubmitBtn').addEventListener('click', function () {
      var btn = this;
      var ctx = currentAmtLotCtx();
      var payload = new URLSearchParams({
        ajax_insert_inspect: '1',
        ProdName:    ctx.prodname,
        InvNo:       ctx.invno,
        WO:          ctx.wo,
        Date:        document.getElementById('rackDate').value,
        Time:        document.getElementById('rackTime').value,
        Opr:         document.querySelector('input[name="Opr"]').value,
        BoxNo:       document.getElementById('newBoxNo').value,
        PlateNo:     document.getElementById('newPlateNo').value,
        ActualQty:   document.getElementById('newActQty').value,
        SmpPerRack:  document.getElementById('newSmpQty').value,
        InspFGqty:   document.getElementById('newFGQty').value,
        inspNGqty:   document.getElementById('newNGQty').value,
        inspNGmode:  document.getElementById('newNGmode').value,
        Status:      document.getElementById('newStatus').value,
        Remark:      document.getElementById('newRemark').value
      });

      btn.disabled = true;
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status === 'ok') {
            var qs = new URLSearchParams({
              lot_id:   document.getElementById('lotIdDisplay').value,
              ProdName: ctx.prodname,
              InvNo:    ctx.invno,
              WO:       ctx.wo
            });
            location.href = location.pathname + '?' + qs.toString();
          } else if (data.status === 'dup') {
            alert(data.message);
            btn.disabled = false;
          } else {
            alert(data.message || 'บันทึกไม่สำเร็จ');
            btn.disabled = false;
          }
        })
        .catch(function () {
          alert('เกิดข้อผิดพลาด');
          btn.disabled = false;
        });
    });

    (function () {
      var ctx = currentAmtLotCtx();
      if (!ctx.prodname || !ctx.invno || !ctx.wo) return;
      fetchAndRenderAmountRows(ctx.prodname, ctx.invno, ctx.wo);
      fetchAndRenderInspectRows(ctx.prodname, ctx.invno, ctx.wo);
    })();

    document.getElementById('submitInspProcessBtn').addEventListener('click', function () {
      var btn = this;
      var ctx = currentAmtLotCtx();
      var payload = new URLSearchParams({
        ajax_insert_summary: '1',
        ProdName:    ctx.prodname,
        InvNo:       ctx.invno,
        WO:          ctx.wo,
        Date:        document.getElementById('rackDate').value,
        Time:        document.getElementById('rackTime').value,
        Opr:         document.querySelector('input[name="Opr"]').value,
        LotTagQty:   document.getElementById('sum_LotTagQty').value,
        InspFGqty:   document.getElementById('sum_InspFGQty').value,
        InspNGqty:   document.getElementById('sum_InspNGQty').value,
        InspFGNGqty: document.getElementById('sum_InspQty').value,
        ShortOver:   document.getElementById('sum_ShortOver').value,
        AmountChk:   document.getElementById('inspAmountChk').value,
        QCchk:       document.getElementById('inspQCJudge').value,
        QCdone:      document.getElementById('inspStatus').value
      });

      btn.disabled = true;
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status === 'ok') {
            alert('ข้อมูลบันทึกลงใน tb_proc6_sum สำเร็จ');
          } else if (data.status === 'dup') {
            alert(data.message);
          } else {
            alert(data.message || 'บันทึกไม่สำเร็จ');
          }
          btn.disabled = false;
        })
        .catch(function () {
          alert('เกิดข้อผิดพลาด');
          btn.disabled = false;
        });
    });
  </script>
</body>
</html>
