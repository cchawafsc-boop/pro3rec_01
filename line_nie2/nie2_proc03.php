<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // AJAX: delete one racking record from tb_proc3
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_rack'])) {
        header('Content-Type: application/json');

        $dProdName = $_POST['ProdName'] ?? '';
        $dInvNo    = $_POST['InvNo'] ?? '';
        $dWo       = $_POST['WO'] ?? '';
        $dBoxNo    = $_POST['BoxNo'] ?? '';
        $dPlateNo  = $_POST['PlateNo'] ?? '';
        $dRackNo   = $_POST['RackNo'] ?? '';

        $dstmt = mysqli_prepare($conn,
            "DELETE FROM tb_proc3
             WHERE ProdName=? AND InvNo=? AND WO=? AND BoxNo=? AND PlateNo=? AND RackNo=? LIMIT 1");
        mysqli_stmt_bind_param($dstmt, 'ssssss',
            $dProdName, $dInvNo, $dWo, $dBoxNo, $dPlateNo, $dRackNo);
        $dok = mysqli_stmt_execute($dstmt);
        echo json_encode(['status' => $dok ? 'ok' : 'fail', 'message' => $dok ? '' : mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
    }

    // AJAX: load the boxes of a Lot for input-qtybox.
    // Lot already in tb_proc3_box -> mode 'existing' (saved values).
    // Lot not in tb_proc3_box yet -> mode 'new' (box list from tb_proc2_box).
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_get_qtybox'])) {
        header('Content-Type: application/json');

        $qProdName = $_POST['ProdName'] ?? '';
        $qInvNo    = $_POST['InvNo'] ?? '';
        $qWo       = $_POST['WO'] ?? '';
        $qRows     = [];
        $qMode     = 'existing';

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $qstmt = mysqli_prepare($conn,
                "SELECT BoxNo, Date, TIME_FORMAT(Time, '%H:%i') AS Time, LotTagQty, ActualQty, ShortOver, Status, Remark
                 FROM tb_proc3_box WHERE ProdName=? AND InvNo=? AND WO=?
                 ORDER BY CAST(BoxNo AS UNSIGNED), BoxNo");
            mysqli_stmt_bind_param($qstmt, 'sss', $qProdName, $qInvNo, $qWo);
            mysqli_stmt_execute($qstmt);
            $qRows = mysqli_fetch_all(mysqli_stmt_get_result($qstmt), MYSQLI_ASSOC);

            if (count($qRows) === 0) {
                $qMode = 'new';
                $qstmt = mysqli_prepare($conn,
                    "SELECT BoxNo FROM tb_proc2_box WHERE ProdName=? AND InvNo=? AND WO=?
                     GROUP BY BoxNo ORDER BY CAST(BoxNo AS UNSIGNED), BoxNo");
                mysqli_stmt_bind_param($qstmt, 'sss', $qProdName, $qInvNo, $qWo);
                mysqli_stmt_execute($qstmt);
                $qRows = mysqli_fetch_all(mysqli_stmt_get_result($qstmt), MYSQLI_ASSOC);
            }
        } catch (Throwable $e) {
            $qRows = [];
        }

        echo json_encode(count($qRows) > 0
            ? ['status' => 'ok', 'mode' => $qMode, 'rows' => $qRows]
            : ['status' => 'fail']);
        mysqli_close($conn);
        exit;
    }

    // AJAX: submitDataBtn.
    // input-qtybox rows -> tb_proc3_box (insert if the box is not there yet, else update), 1 row = 1 box.
    // input-racking new-data row -> insert into tb_proc3.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit'])) {
        header('Content-Type: application/json');

        $sProdName = $_POST['ProdName'] ?? '';
        $sInvNo    = $_POST['InvNo'] ?? '';
        $sWo       = $_POST['WO'] ?? '';
        $sScanBox  = $_POST['ScanBoxNo'] ?? '';
        $sOpr      = (int)($_SESSION['us_id'] ?? 0);
        $boxes     = json_decode($_POST['boxes'] ?? '[]', true) ?: [];
        $rack      = json_decode($_POST['rack'] ?? '{}', true) ?: [];

        // blank input -> NULL
        $nz  = function ($v) { return ($v === '' || $v === null) ? null : (string)$v; };
        $nzi = function ($v) { return ($v === '' || $v === null) ? null : (int)$v; };

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            mysqli_begin_transaction($conn);

            $chk = mysqli_prepare($conn,
                "SELECT 1 FROM tb_proc3_box WHERE ProdName=? AND InvNo=? AND WO=? AND BoxNo=? LIMIT 1");
            $ins = mysqli_prepare($conn,
                "INSERT INTO tb_proc3_box (ProdName, InvNo, WO, Date, Time, Opr, BoxNo, LotTagQty, ActualQty, ShortOver, Status, Remark)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $upd = mysqli_prepare($conn,
                "UPDATE tb_proc3_box SET Date=?, Time=?, Opr=COALESCE(?, Opr), LotTagQty=?, ActualQty=?, ShortOver=?, Status=?, Remark=?
                 WHERE ProdName=? AND InvNo=? AND WO=? AND BoxNo=? LIMIT 1");

            foreach ($boxes as $b) {
                $bNo     = (string)($b['BoxNo'] ?? '');
                $bDate   = $nz($b['Date'] ?? '');
                $bTime   = $nz($b['Time'] ?? '');
                $bLotQty = $nzi($b['LotTagQty'] ?? '');
                $bActQty = $nzi($b['ActualQty'] ?? '');
                $bShort  = $nzi($b['ShortOver'] ?? '');
                $bStatus = $nz($b['Status'] ?? '');
                $bRemark = $nz($b['Remark'] ?? '');
                $bOpr    = ($bNo === $sScanBox) ? $sOpr : null;   // only the scanned box records its operator

                mysqli_stmt_bind_param($chk, 'ssss', $sProdName, $sInvNo, $sWo, $bNo);
                mysqli_stmt_execute($chk);
                mysqli_stmt_store_result($chk);
                $exists = mysqli_stmt_num_rows($chk) > 0;
                mysqli_stmt_free_result($chk);

                if ($exists) {
                    mysqli_stmt_bind_param($upd, 'ssiiiissssss',
                        $bDate, $bTime, $bOpr, $bLotQty, $bActQty, $bShort, $bStatus, $bRemark,
                        $sProdName, $sInvNo, $sWo, $bNo);
                    mysqli_stmt_execute($upd);
                } else {
                    mysqli_stmt_bind_param($ins, 'sssssisiiiss',
                        $sProdName, $sInvNo, $sWo, $bDate, $bTime, $bOpr,
                        $bNo, $bLotQty, $bActQty, $bShort, $bStatus, $bRemark);
                    mysqli_stmt_execute($ins);
                }
            }

            $rBoxNo    = (string)($rack['BoxNo'] ?? '');
            $rDate     = $nz($rack['Date'] ?? '');
            $rTime     = $nz($rack['Time'] ?? '');
            $rLotPlate = $nz($rack['LotPlate'] ?? '');
            $rPlateNo  = $nz($rack['PlateNo'] ?? '');
            $rRackNo   = $nz($rack['RackNo'] ?? '');
            $rQty      = $nzi($rack['Qty'] ?? '');
            $rNgQty    = $nzi($rack['NGqty'] ?? '');
            $rStatus   = $nz($rack['Status'] ?? '');
            $rRemark   = $nz($rack['Remark'] ?? '');

            $rIns = mysqli_prepare($conn,
                "INSERT INTO tb_proc3 (ProdName, InvNo, WO, Date, Time, Opr, BoxNo, LotPlate, PlateNo, RackNo, Qty, NGqty, Status, Remark)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($rIns, 'sssssissssiiss',
                $sProdName, $sInvNo, $sWo, $rDate, $rTime, $sOpr,
                $rBoxNo, $rLotPlate, $rPlateNo, $rRackNo, $rQty, $rNgQty, $rStatus, $rRemark);
            mysqli_stmt_execute($rIns);

            mysqli_commit($conn);
            echo json_encode(['status' => 'ok']);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
        }
        mysqli_close($conn);
        exit;
    }

    // AJAX: NG qty of one racking piece = SUM(tb_ng.NGqty) for Box + Plate + Rack, Process '3. Racking'.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_get_ngqty'])) {
        header('Content-Type: application/json');

        $gProdName = $_POST['ProdName'] ?? '';
        $gInvNo    = $_POST['InvNo'] ?? '';
        $gWo       = $_POST['WO'] ?? '';
        $gBoxNo    = $_POST['BoxNo'] ?? '';
        $gPlateNo  = $_POST['PlateNo'] ?? '';
        $gRackNo   = $_POST['RackNo'] ?? '';

        $ngStmt = mysqli_prepare($conn,
            "SELECT COALESCE(SUM(NGqty), 0) AS ngSum FROM tb_ng
             WHERE ProdName = ? AND InvNo = ? AND WO = ? AND BoxNo = ? AND PlateNo = ? AND RackNo = ?
               AND Process = '3. Racking'");
        mysqli_stmt_bind_param($ngStmt, 'ssssss', $gProdName, $gInvNo, $gWo, $gBoxNo, $gPlateNo, $gRackNo);
        $ngOk  = mysqli_stmt_execute($ngStmt);
        $ngRow = $ngOk ? mysqli_fetch_assoc(mysqli_stmt_get_result($ngStmt)) : null;
        echo json_encode(['status' => $ngOk ? 'ok' : 'fail', 'ngSum' => (int)($ngRow['ngSum'] ?? 0)]);
        mysqli_close($conn);
        exit;
    }

    // Resolve the header (Lot ID / Product name / Invoice no / WO) from a Lot Tag
    // scan (prodName+wo+boxNo), same pattern as nie2_proc02.php.
    $lot_id = $lot_id_raw = '';
    $lot_prodname = $lot_invno = $lot_wo = '';
    $lot_prodname_raw = $lot_invno_raw = $lot_wo_raw = '';

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
        } else {
            echo "<script>alert('Error in DataBase injection. Please contack Admin\\n or Invalid lot tag. Please re-check lot tag.');</script>";
        }
    }

    // Existing racking records for every box under this Lot ID.
    $rackRows = [];
    if (!empty($lot_id_raw)) {
        $rstmt = mysqli_prepare($conn,
            "SELECT p3.ProdName, p3.InvNo, p3.WO, p3.BoxNo, p3.Date, TIME_FORMAT(p3.Time, '%H:%i') AS Time, p3.LotPlate, p3.PlateNo, p3.RackNo, p3.Qty, p3.NGqty, p3.Opr, p3.Status, p3.Remark
             FROM tb_proc3 p3
             INNER JOIN tb_proc1 p1 ON p1.ProdName = p3.ProdName AND p1.WO = p3.WO AND p1.BoxNo = p3.BoxNo
             WHERE p1.LotID = ?");
        mysqli_stmt_bind_param($rstmt, 's', $lot_id_raw);
        mysqli_stmt_execute($rstmt);
        $rres = mysqli_stmt_get_result($rstmt);
        while ($rrow = mysqli_fetch_assoc($rres)) {
            $rackRows[] = $rrow;
        }
    }

    // Racking summary: unique Box-no racked vs total Box-no under this Lot ID.
    $rackedBoxCount = count(array_unique(array_column($rackRows, 'BoxNo')));
    $lotBoxCount = 0;
    if (!empty($lot_id_raw)) {
        $bStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS boxCount FROM tb_proc1 WHERE LotID = ?");
        mysqli_stmt_bind_param($bStmt, 's', $lot_id_raw);
        mysqli_stmt_execute($bStmt);
        $bRow = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt));
        $lotBoxCount = (int)($bRow['boxCount'] ?? 0);
    }
    if ($rackedBoxCount < $lotBoxCount) {
        $rackSummaryText = 'Not complete';
    } elseif ($rackedBoxCount === $lotBoxCount) {
        $rackSummaryText = 'Completed';
    } else {
        $rackSummaryText = 'ERROR';
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

  <div class="form-pro3-proc3">
    <h2>3 Racking — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="pro3-proc3-g1">

        <!-- row1 -->
        <div class="pro3-proc3-g1-it-bl"></div>
        <div class="pro3-proc3-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc3-g1-it">
          <input type="text" value="<?php echo $lot_id; ?>" disabled>
        </div>

        <!-- row2 -->
        <div class="pro3-proc3-g1-it-bl"></div>
        <div class="pro3-proc3-g1-it"><label>Product name</label></div>
        <div class="pro3-proc3-g1-it">
          <input type="text" value="<?php echo $lot_prodname; ?>" disabled>
          <input type="hidden" name="ProdName" value="<?php echo $lot_prodname; ?>">
        </div>

        <!-- row3 -->
        <div class="pro3-proc3-g1-it-bl"></div>
        <div class="pro3-proc3-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc3-g1-it">
          <input type="text" value="<?php echo $lot_invno; ?>" disabled>
          <input type="hidden" name="InvNo" value="<?php echo $lot_invno; ?>">
        </div>

        <!-- row4 -->
        <div class="pro3-proc3-g1-it-bl"></div>
        <div class="pro3-proc3-g1-it"><label>WO</label></div>
        <div class="pro3-proc3-g1-it">
          <input type="text" value="<?php echo $lot_wo; ?>" disabled>
          <input type="hidden" name="WO" value="<?php echo $lot_wo; ?>">
        </div>

        <!-- row5 -->
        <div class="pro3-proc3-g1-it-bl"><span class="blinkBullet" id="lotTagBullet">●</span></div>
        <div class="pro3-proc3-g1-it"><label id="lotTagLabel" style="color: red;"> Data from Lot Tag </label></div>
        <div class="pro3-proc3-g1-it">
          <input type="text" id="newBoxNo" autocomplete="off" placeholder="prod|wo|box|qty|mat">
        </div>

      </div>

      <div id="input-qtybox">
        <div class="grid-title">เช็คจำนวนกล่องและชิ้นงาน</div>
        <div></div>
        <div class="qtybox-h">Box-no</div>
        <div class="qtybox-h" style="font-size: small">วัน</div>
        <div class="qtybox-h" style="font-size: small">เวลา</div>
        <div class="qtybox-h" style="font-size: small">จำนวนชิ้น<br>ตาม LotTag</div>
        <div class="qtybox-h" style="font-size: small">จำนวนชิ้น<br>นับจริง</div>
        <div class="qtybox-h" style="font-size: small">จำนวนชิ้น<br>ขาด / เกิน</div>
        <div class="qtybox-h" style="font-size: small">Status</div>
        <div class="qtybox-h">Remark</div>
        <!-- box rows are created by script after a Lot Tag scan -->
      </div>

      <div id="input-racking">
        <div class="grid-title">ข้อมูลการ racking</div>
        <div class="rack-h">Box<br>no</div>
        <div class="rack-h" style="font-size: small">วัน</div>
        <div class="rack-h" style="font-size: small">เวลา</div>
        <div class="rack-h">Lot<br>plate</div>
        <div class="rack-h">Plate<br>no</div>
        <div class="rack-h">Rack<br>no</div>
        <div class="rack-h" style="font-size:small;" >ชิ้นงาน CofC<br>ที่ใส่แร็ก</div>
        <div class="rack-h" style="font-size:small;" >ชิ้นงาน OB<br>ที่ใส่แร็ก</div>
        <div class="rack-h" style="font-size:small;" >ชิ้นงาน G<br>ที่ใส่แร็ก</div>
        <div class="rack-h" style="font-size:small;">จำนวนงาน NG<br>ที่<a style="color: red";>ไม่</a>ใส่แร็ก</div>
        <div class="rack-h">define<br>NG</div>
        <div class="rack-h">Operator</div>
        <div class="rack-h">Status</div>
        <div class="rack-h">Remark</div>
        <div class="rack-h">Action</div>

        <?php foreach ($rackRows as $rrow): ?>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['BoxNo']); ?></div>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['Date'] ?? ''); ?></div>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['Time'] ?? ''); ?></div>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['LotPlate']); ?></div>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['PlateNo']); ?></div>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['RackNo']); ?></div>
        <div class="rack-c"><?php echo (int)$rrow['Qty']; ?></div>
        <div class="rack-c"><?php echo (int)$rrow['NGqty']; ?></div>
        <div class="rack-c"></div>
        <div class="rack-c"><?php echo (int)$rrow['Opr']; ?></div>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['Status']); ?></div>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['Remark']); ?></div>
        <div class="rack-c">
          <button type="button" class="rackDeleteBtn"
            data-prodname="<?php echo htmlspecialchars($rrow['ProdName'], ENT_QUOTES); ?>"
            data-invno="<?php echo htmlspecialchars($rrow['InvNo'], ENT_QUOTES); ?>"
            data-wo="<?php echo htmlspecialchars($rrow['WO'], ENT_QUOTES); ?>"
            data-boxno="<?php echo htmlspecialchars($rrow['BoxNo'], ENT_QUOTES); ?>"
            data-plateno="<?php echo htmlspecialchars($rrow['PlateNo'], ENT_QUOTES); ?>"
            data-rackno="<?php echo htmlspecialchars($rrow['RackNo'], ENT_QUOTES); ?>">delete</button>
        </div>
        <?php endforeach; ?>

        <div class="rack-c"><input type="text" id="newRackBoxNo" disabled></div>
        <div class="rack-c"><input type="date" id="newRackDate" value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="rack-c"><input type="time" id="newRackTime" value="<?php echo date('H:i'); ?>"></div>
        <div class="rack-c"><input type="text" id="newLotPlate" autocomplete="off" placeholder="Lot-plate"></div>
        <div class="rack-c"><input type="text" id="newPlateNo" autocomplete="off" placeholder="Plate-no"></div>
        <div class="rack-c"><input type="text" id="newRackNo" autocomplete="off" placeholder="Rack-no"></div>
        <div class="rack-c"><input type="number" id="newRackQty" placeholder="Qty" min="0"></div>
        <div class="rack-c"><input type="number" id="newRackNgQty" value="0" readonly></div>
        <div class="rack-c"><button type="button" id="defineNgBtn">ระบุ NG</button></div>
        <div class="rack-c"><input type="number" id="newRackOpr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled></div>
        <div class="rack-c">
          <select id="newRackStatus">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Accept">Accept</option>
            <option value="Reject">Reject</option>
            <option value="Hold">Hold</option>
            <option value="SpecialAccept">SpecialAccept</option>
          </select>
        </div>
        <div class="rack-c"><textarea id="newRackRemark" rows="2"></textarea></div>
        <div class="rack-c"><button type="button" id="submitDataBtn">บันทึก</button></div>
      </div>

      <div class="racking-summary">
        <div class="racking-summary-it">Box-no ที่ใส่แร็กแล้ว: <?php echo $rackedBoxCount; ?></div>
        <div class="racking-summary-it">Lot Box-no: <?php echo $lotBoxCount; ?></div>
        <div class="racking-summary-it racking-summary-status"><?php echo htmlspecialchars($rackSummaryText); ?></div>
      </div>

      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2 </button>
      </p>
    </form>
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

      this.value = '';

      var url = new URL(window.location.href);
      url.searchParams.set('prodName', lot.prodName);
      url.searchParams.set('wo', lot.wo);
      url.searchParams.set('boxNo', lot.boxNo);
      url.searchParams.set('boxQty', lot.boxQty);
      window.location.href = url.toString();
    });

    // Lot resolved by PHP from the Lot Tag scan (lotId is empty when nothing is scanned yet).
    var LOT = <?php echo json_encode([
      'prodName' => $lot_prodname_raw,
      'invNo'    => $lot_invno_raw,
      'wo'       => $lot_wo_raw,
      'lotId'    => $lot_id_raw,
      'boxNo'    => $_GET['boxNo'] ?? '',
      'boxQty'   => $_GET['boxQty'] ?? '',
      'today'    => date('Y-m-d'),
      'now'      => date('H:i')
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    var STATUS_LIST  = ['Accept', 'Hold', 'Reject', 'SpecialAccept'];
    var NOT_FOUND_MSG = 'ไม่พบข้อมูล โปรดตรวจสอบ Lot Tag อีกครั้ง';
    var RACK_REQUIRED_IDS = ['newRackBoxNo', 'newRackDate', 'newRackTime', 'newLotPlate', 'newPlateNo',
                             'newRackNo', 'newRackQty', 'newRackOpr', 'newRackStatus'];
    var qtyBoxGrid = document.getElementById('input-qtybox');
    var qtyBoxRows = [];

    function makeQtyBoxCell(el) {
      var div = document.createElement('div');
      div.className = 'qtybox-c';
      if (el) div.appendChild(el);
      qtyBoxGrid.appendChild(div);
      return div;
    }

    function makeInput(type, value) {
      var inp = document.createElement('input');
      inp.type = type;
      inp.value = (value === null || value === undefined) ? '' : value;
      return inp;
    }

    // One row per box. Only the scanned box's row can be input / edited.
    function addQtyBoxRow(box, isScanned) {
      var bulletCell = makeQtyBoxCell(null);
      if (isScanned) bulletCell.innerHTML = '<span class="blinkBullet">●</span>';

      var boxNo     = makeInput('text', box.BoxNo);
      var date      = makeInput('date', box.Date);
      var time      = makeInput('time', box.Time);
      var lotTagQty = makeInput('number', box.LotTagQty);
      var actualQty = makeInput('number', box.ActualQty);
      var shortOver = makeInput('number', box.ShortOver);
      var status    = document.createElement('select');
      var remark    = document.createElement('textarea');

      boxNo.disabled     = true;
      lotTagQty.readOnly = true;
      actualQty.min      = '0';
      shortOver.readOnly = true;
      remark.rows        = 2;
      remark.value       = box.Remark || '';

      status.innerHTML = '<option value="">โปรดระบุ</option>' + STATUS_LIST.map(function (s) {
        return '<option value="' + s + '">' + s + '</option>';
      }).join('');
      status.value = box.Status || '';

      [date, time, lotTagQty, actualQty, shortOver, status, remark].forEach(function (el) {
        el.disabled = !isScanned;
      });

      // ขาด / เกิน = นับจริง - ตาม LotTag  (minus = short, plus = over)
      actualQty.addEventListener('input', function () {
        shortOver.value = (lotTagQty.value === '' || actualQty.value === '')
          ? '' : Number(actualQty.value) - Number(lotTagQty.value);
      });

      [boxNo, date, time, lotTagQty, actualQty, shortOver, status, remark].forEach(makeQtyBoxCell);

      return { isScanned: isScanned, boxNo: boxNo, date: date, time: time, lotTagQty: lotTagQty,
               actualQty: actualQty, shortOver: shortOver, status: status, remark: remark };
    }

    function loadQtyBox() {
      var payload = new URLSearchParams({
        ajax_get_qtybox: '1',
        ProdName: LOT.prodName,
        InvNo:    LOT.invNo,
        WO:       LOT.wo
      });

      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var rows = data.rows || [];
          var hasScanned = rows.some(function (b) { return b.BoxNo === LOT.boxNo; });
          if (data.status !== 'ok' || !hasScanned) {
            alert(NOT_FOUND_MSG);
            return;
          }

          var scannedRow = null;
          rows.forEach(function (b) {
            var isScanned = (b.BoxNo === LOT.boxNo);
            if (data.mode === 'new') {
              // 1st time for this Lot: only the scanned box gets today / now / Lot Tag qty
              b = {
                BoxNo:     b.BoxNo,
                Date:      isScanned ? LOT.today : '',
                Time:      isScanned ? LOT.now : '',
                LotTagQty: isScanned ? LOT.boxQty : ''
              };
            } else if (isScanned) {
              // Lot already saved: fill only what the scanned box has not saved yet (saved values are kept)
              if (!b.Date) b.Date = LOT.today;
              if (!b.Time) b.Time = LOT.now;
              // boxQty parsed from the Lot Tag by parseLotTagInput() on Enter, passed through the URL
              if (b.LotTagQty === null || b.LotTagQty === '') b.LotTagQty = LOT.boxQty;
            }
            var row = addQtyBoxRow(b, isScanned);
            qtyBoxRows.push(row);
            if (isScanned) scannedRow = row;
          });

          restoreDraftQtyBox(scannedRow);

          // Lot Tag step done: drop the header bullet, label back to black
          document.getElementById('lotTagBullet').style.display = 'none';
          document.getElementById('lotTagLabel').style.color = 'black';

          scannedRow.actualQty.focus();
        })
        .catch(function () { alert(NOT_FOUND_MSG); });
    }

    function prepareRackNewRow() {
      RACK_REQUIRED_IDS.forEach(function (id) {
        document.getElementById(id).required = true;
      });
      document.getElementById('newRackBoxNo').value = LOT.boxNo;
      document.getElementById('newLotPlate').value  = LOT.lotId.substring(0, 2);
      restoreDraftRack();
      refreshNgQty();
    }

    // NG qty of the new-data row: tb_ng sum for scanned box + Plate-no + Rack-no (0 until both are filled).
    function refreshNgQty() {
      var ngInput = document.getElementById('newRackNgQty');
      var plateNo = document.getElementById('newPlateNo').value.trim();
      var rackNo  = document.getElementById('newRackNo').value.trim();
      if (!LOT.lotId || plateNo === '' || rackNo === '') {
        ngInput.value = 0;
        return;
      }

      var payload = new URLSearchParams({
        ajax_get_ngqty: '1',
        ProdName: LOT.prodName,
        InvNo:    LOT.invNo,
        WO:       LOT.wo,
        BoxNo:    LOT.boxNo,
        PlateNo:  plateNo,
        RackNo:   rackNo
      });

      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) { ngInput.value = data.ngSum || 0; })
        .catch(function () { ngInput.value = 0; });
    }

    document.getElementById('newPlateNo').addEventListener('change', refreshNgQty);
    document.getElementById('newRackNo').addEventListener('change', refreshNgQty);

    // Draft of the unsaved inputs, kept while the user is on nie2_ng_input_proc03.php
    // ("define NG") and refilled once when coming back to the same box.
    var DRAFT_KEY = 'proc03Draft|' + [LOT.prodName, LOT.invNo, LOT.wo, LOT.boxNo].join('|');
    var DRAFT_QTYBOX_FIELDS = ['date', 'time', 'actualQty', 'shortOver', 'status', 'remark'];
    var DRAFT_RACK_IDS = ['newRackDate', 'newRackTime', 'newLotPlate', 'newPlateNo',
                          'newRackNo', 'newRackQty', 'newRackStatus', 'newRackRemark'];
    var draft = null;
    try { draft = JSON.parse(sessionStorage.getItem(DRAFT_KEY)); sessionStorage.removeItem(DRAFT_KEY); } catch (err) {}

    function saveDraft() {
      var scannedRow = qtyBoxRows.filter(function (r) { return r.isScanned; })[0];
      var d = { qtybox: {}, rack: {} };
      if (scannedRow) {
        DRAFT_QTYBOX_FIELDS.forEach(function (f) { d.qtybox[f] = scannedRow[f].value; });
      }
      DRAFT_RACK_IDS.forEach(function (id) { d.rack[id] = document.getElementById(id).value; });
      try { sessionStorage.setItem(DRAFT_KEY, JSON.stringify(d)); } catch (err) {}
    }

    function restoreDraftQtyBox(scannedRow) {
      if (!draft || !draft.qtybox) return;
      DRAFT_QTYBOX_FIELDS.forEach(function (f) {
        if (draft.qtybox[f] !== undefined) scannedRow[f].value = draft.qtybox[f];
      });
    }

    function restoreDraftRack() {
      if (!draft || !draft.rack) return;
      DRAFT_RACK_IDS.forEach(function (id) {
        if (draft.rack[id] !== undefined) document.getElementById(id).value = draft.rack[id];
      });
    }

    if (LOT.lotId) {
      loadQtyBox();
      prepareRackNewRow();
    }

    // define NG: open nie2_ng_input_proc03.php for the scanned box + Plate-no + Rack-no
    // (same POST entry as proc02 -> ng_input_proc02).
    // Its "กลับหน้าก่อน" comes back to this URL (Referer), so the Lot reloads and NG qty is refreshed.
    document.getElementById('defineNgBtn').addEventListener('click', function () {
      if (!LOT.lotId) {
        alert(NOT_FOUND_MSG);
        return;
      }
      if (!document.getElementById('newPlateNo').reportValidity()) return;
      if (!document.getElementById('newRackNo').reportValidity()) return;
      saveDraft();

      var form = document.createElement('form');
      form.method = 'post';
      form.action = 'nie2_ng_input_proc03.php';

      function addField(name, value) {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = name;
        inp.value = value;
        form.appendChild(inp);
      }

      addField('lot_id_raw', LOT.lotId);
      addField('boxNo', LOT.boxNo);
      addField('plateNo', document.getElementById('newPlateNo').value.trim());
      addField('rackNo', document.getElementById('newRackNo').value.trim());
      addField('selected_process', '3. Racking');

      document.body.appendChild(form);
      form.submit();
    });

    document.getElementById('submitDataBtn').addEventListener('click', function () {
      var btn = this;
      if (!LOT.lotId || qtyBoxRows.length === 0) {
        alert(NOT_FOUND_MSG);
        return;
      }
      for (var i = 0; i < RACK_REQUIRED_IDS.length; i++) {
        if (!document.getElementById(RACK_REQUIRED_IDS[i]).reportValidity()) return;
      }

      var boxes = qtyBoxRows.map(function (r) {
        return {
          BoxNo:     r.boxNo.value,
          Date:      r.date.value,
          Time:      r.time.value,
          LotTagQty: r.lotTagQty.value,
          ActualQty: r.actualQty.value,
          ShortOver: r.shortOver.value,
          Status:    r.status.value,
          Remark:    r.remark.value
        };
      });

      var rack = {
        BoxNo:    document.getElementById('newRackBoxNo').value,
        Date:     document.getElementById('newRackDate').value,
        Time:     document.getElementById('newRackTime').value,
        LotPlate: document.getElementById('newLotPlate').value,
        PlateNo:  document.getElementById('newPlateNo').value,
        RackNo:   document.getElementById('newRackNo').value,
        Qty:      document.getElementById('newRackQty').value,
        NGqty:    document.getElementById('newRackNgQty').value,
        Status:   document.getElementById('newRackStatus').value,
        Remark:   document.getElementById('newRackRemark').value
      };

      var payload = new URLSearchParams({
        ajax_submit: '1',
        ProdName:  LOT.prodName,
        InvNo:     LOT.invNo,
        WO:        LOT.wo,
        ScanBoxNo: LOT.boxNo,
        boxes:     JSON.stringify(boxes),
        rack:      JSON.stringify(rack)
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
            alert('บันทึกสำเร็จ');
            location.reload();
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

    document.querySelectorAll('.rackDeleteBtn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!confirm('ต้องการลบรายการนี้หรือไม่')) return;

        var payload = new URLSearchParams({
          ajax_delete_rack: '1',
          ProdName: btn.dataset.prodname,
          InvNo:    btn.dataset.invno,
          WO:       btn.dataset.wo,
          BoxNo:    btn.dataset.boxno,
          PlateNo:  btn.dataset.plateno,
          RackNo:   btn.dataset.rackno
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
              location.reload();
            } else {
              alert(data.message || 'ลบไม่สำเร็จ');
              btn.disabled = false;
            }
          })
          .catch(function () {
            alert('เกิดข้อผิดพลาด');
            btn.disabled = false;
          });
      });
    });
  </script>
</body>
</html>
