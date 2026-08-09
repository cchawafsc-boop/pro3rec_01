<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // AJAX: resolve a Lot ID from a Lot-plate scan + today's date.
    // tb_proc1.LotID = "<text1>_<text2>_<text3>". prepared_LotID sent from
    // JS is "<LotPlate>_<yyyy-mm-dd>" (text1_text2). Match any LotID whose
    // first two underscore-segments equal prepared_LotID; if none, step the
    // date back one day and retry, up to 7 attempts total.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_resolve_lotid'])) {
        header('Content-Type: application/json');

        $prepared = $_POST['prepared_LotID'] ?? '';
        $found = null;

        $sep = strrpos($prepared, '_');
        if ($sep !== false) {
            $baseText = substr($prepared, 0, $sep);
            $dateObj  = DateTime::createFromFormat('Y-m-d', substr($prepared, $sep + 1));

            if ($dateObj) {
                for ($i = 0; $i < 7; $i++) {
                    $candidate = $baseText . '_' . $dateObj->format('Y-m-d');
                    $likePattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $candidate) . '\\_%';

                    $lstmt = mysqli_prepare($conn,
                        "SELECT LotID, ProdName, InvNo, WO FROM tb_proc1 WHERE LotID LIKE ? LIMIT 1");
                    mysqli_stmt_bind_param($lstmt, 's', $likePattern);
                    mysqli_stmt_execute($lstmt);
                    $lrow = mysqli_fetch_assoc(mysqli_stmt_get_result($lstmt));
                    if ($lrow) {
                        $found = $lrow;
                        break;
                    }
                    $dateObj->modify('-1 day');
                }
            }
        }

        $records = [];
        if ($found) {
            $rstmt = mysqli_prepare($conn,
                "SELECT LotPlate, PlateNo, RackNo, Qty, PltTankNo, Opr, Status, Remark
                 FROM tb_proc4 WHERE ProdName = ? AND InvNo = ? AND WO = ? ORDER BY PlateNo");
            mysqli_stmt_bind_param($rstmt, 'sss', $found['ProdName'], $found['InvNo'], $found['WO']);
            mysqli_stmt_execute($rstmt);
            $rres = mysqli_stmt_get_result($rstmt);
            while ($rrow = mysqli_fetch_assoc($rres)) {
                $records[] = $rrow;
            }
        }

        if ($found) {
            echo json_encode([
                'status'   => 'ok',
                'lot_id'   => $found['LotID'],
                'prodname' => $found['ProdName'],
                'invno'    => $found['InvNo'],
                'wo'       => $found['WO'],
                'records'  => $records,
            ]);
        } else {
            echo json_encode(['status' => 'fail']);
        }
        mysqli_close($conn);
        exit;
    }

    // AJAX: insert a new plating record into tb_proc4
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_insert_pltrec'])) {
        header('Content-Type: application/json');

        $iProdName = $_POST['ProdName'] ?? '';
        $iInvNo    = $_POST['InvNo'] ?? '';
        $iWo       = $_POST['WO'] ?? '';
        $iDate     = $_POST['Date'] ?? '';
        $iTime     = $_POST['Time'] ?? '';
        $iOpr      = (int)($_POST['Opr'] ?? 0);
        $iLotPlate = $_POST['LotPlate'] ?? '';
        $iPlateNo  = $_POST['PlateNo'] ?? '';
        $iRackNo   = $_POST['RackNo'] ?? '';
        $iQty      = (int)($_POST['Qty'] ?? 0);
        $iTankNo   = (int)($_POST['PltTankNo'] ?? 0);
        $iStatus   = $_POST['Status'] ?? '';
        $iRemark   = $_POST['Remark'] ?? '';

        $dupStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_proc4 WHERE ProdName=? AND InvNo=? AND WO=? AND LotPlate=? AND PlateNo=? LIMIT 1");
        mysqli_stmt_bind_param($dupStmt, 'sssss', $iProdName, $iInvNo, $iWo, $iLotPlate, $iPlateNo);
        mysqli_stmt_execute($dupStmt);
        $dupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));

        if ($dupRow) {
            echo json_encode(['status' => 'dup']);
            mysqli_close($conn);
            exit;
        }

        $istmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc4` (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`LotPlate`,`PlateNo`,`RackNo`,`Qty`,`PltTankNo`,`Status`,`Remark`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($istmt, 'sssssisssiss',
            $iProdName, $iInvNo, $iWo, $iDate, $iTime, $iOpr, $iLotPlate, $iPlateNo, $iRackNo, $iQty, $iTankNo, $iStatus, $iRemark);
        $iok = mysqli_stmt_execute($istmt);
        echo json_encode(['status' => $iok ? 'ok' : 'fail', 'message' => $iok ? '' : mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
    }

    // AJAX: delete one plating record from tb_proc4
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_pltrec'])) {
        header('Content-Type: application/json');

        $dProdName = $_POST['ProdName'] ?? '';
        $dInvNo    = $_POST['InvNo'] ?? '';
        $dWo       = $_POST['WO'] ?? '';
        $dLotPlate = $_POST['LotPlate'] ?? '';
        $dPlateNo  = $_POST['PlateNo'] ?? '';
        $dRackNo   = $_POST['RackNo'] ?? '';

        $dstmt = mysqli_prepare($conn,
            "DELETE FROM tb_proc4
             WHERE ProdName=? AND InvNo=? AND WO=? AND LotPlate=? AND PlateNo=? AND RackNo=? LIMIT 1");
        mysqli_stmt_bind_param($dstmt, 'ssssss',
            $dProdName, $dInvNo, $dWo, $dLotPlate, $dPlateNo, $dRackNo);
        $dok = mysqli_stmt_execute($dstmt);
        echo json_encode(['status' => $dok ? 'ok' : 'fail', 'message' => $dok ? '' : mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
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
    <h2>4 Plating — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc4-g1">

        <div class="pro3-proc4-g1-it"><label>Operator</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>

        <div class="pro3-proc4-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="text" id="lotIdDisplay" value="<?php echo $lot_id ?? ''; ?>" disabled>
        </div>

        <div class="pro3-proc4-g1-it"><label>Product name</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="text" id="lotProdnameDisplay" value="<?php echo $lot_prodname ?? ''; ?>" disabled>
          <input type="hidden" id="lotProdnameHidden" name="ProdName" value="<?php echo $lot_prodname ?? ''; ?>">
        </div>

        <div class="pro3-proc4-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="text" id="lotInvnoDisplay" value="<?php echo $lot_invno ?? ''; ?>" disabled>
          <input type="hidden" id="lotInvnoHidden" name="InvNo" value="<?php echo $lot_invno ?? ''; ?>">
        </div>

        <div class="pro3-proc4-g1-it"><label>WO</label></div>
        <div class="pro3-proc4-g1-it">
          <input type="text" id="lotWoDisplay" value="<?php echo $lot_wo ?? ''; ?>" disabled>
          <input type="hidden" id="lotWoHidden" name="WO" value="<?php echo $lot_wo ?? ''; ?>">
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
        <div class="pro3-proc4-g2-h">Lot-plate</div>
        <div class="pro3-proc4-g2-h">Plate-no</div>        
        <div class="pro3-proc4-g2-h">Rack-no</div>
        <div class="pro3-proc4-g2-h">Qty</div>
        <div class="pro3-proc4-g2-h">NieTank-no</div>
        <div class="pro3-proc4-g2-h">Operator</div>
        <div class="pro3-proc4-g2-h">Status</div>
        <div class="pro3-proc4-g2-h">Remark</div>
        <div class="pro3-proc4-g2-h">Action</div>

        <div class="pro3-proc4-g2-c" id="entryRowAnchor"><input type="text" id="newLotPlate" placeholder="Lot-plate"></div>
        <div class="pro3-proc4-g2-c"><input type="text" id="newPlateNo" placeholder="Plate-no"></div>
        <div class="pro3-proc4-g2-c"><input type="text" id="newRackNo" placeholder="Rack-no"></div>
        <div class="pro3-proc4-g2-c"><input type="number" id="newPltQty" placeholder="Qty"></div>
        <div class="pro3-proc4-g2-c"><input type="number" id="newPltTankNo" placeholder="NieTank-no"></div>
        <div class="pro3-proc4-g2-c"><input type="number" id="newPltOpr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled></div>
        <div class="pro3-proc4-g2-c">
          <select id="newPltStatus">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Accept">Accept</option>
            <option value="Reject">Reject</option>
            <option value="Hold">Hold</option>
            <option value="SpecialAccept">SpecialAccept</option>
          </select>
        </div>
        <div class="pro3-proc4-g2-c"><textarea id="newPltRemark" rows="2"></textarea></div>
        <div class="pro3-proc4-g2-c"><button type="button" id="newPltSubmitBtn">บันทึกเข้าระบบ</button></div>
      </div>

      <div class="pro3-proc4-summary">
        <div class="pro3-proc4-summary-it">Racked Box-no:</div>
        <div class="pro3-proc4-summary-it">Lot Box-no:</div>
        <div class="pro3-proc4-summary-it pro3-proc4-summary-status"></div>
      </div>

      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2 </button>
      </p>
    </form>
  </div>

  <?php mysqli_close($conn); ?>

  <script>
    window.addEventListener('DOMContentLoaded', function () {
      document.getElementById('newLotPlate').focus();
    });

    function formatDateHyphen(d) {
      var yyyy = d.getFullYear();
      var mm = String(d.getMonth() + 1).padStart(2, '0');
      var dd = String(d.getDate()).padStart(2, '0');
      return yyyy + '-' + mm + '-' + dd;
    }

    function buildRecordRow(rec, ctx) {
      var wrap = document.createElement('div');
      wrap.className = 'pro3-proc4-record-row';
      wrap.dataset.prodname = ctx.prodname;
      wrap.dataset.invno = ctx.invno;
      wrap.dataset.wo = ctx.wo;
      wrap.dataset.lotplate = rec.LotPlate;
      wrap.dataset.plateno = rec.PlateNo;
      wrap.dataset.rackno = rec.RackNo;

      [rec.LotPlate, rec.PlateNo, rec.RackNo, rec.Qty, rec.PltTankNo, rec.Opr, rec.Status, rec.Remark].forEach(function (val) {
        var cell = document.createElement('div');
        cell.className = 'pro3-proc4-g2-c';
        cell.textContent = val;
        wrap.appendChild(cell);
      });

      var actionCell = document.createElement('div');
      actionCell.className = 'pro3-proc4-g2-c';
      var delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.className = 'pltRecordDeleteBtn';
      delBtn.textContent = 'delete';
      actionCell.appendChild(delBtn);
      wrap.appendChild(actionCell);

      return wrap;
    }

    function currentLotCtx() {
      return {
        prodname: document.getElementById('lotProdnameHidden').value,
        invno: document.getElementById('lotInvnoHidden').value,
        wo: document.getElementById('lotWoHidden').value
      };
    }

    function addRecordRow(rec, ctx) {
      var anchor = document.getElementById('entryRowAnchor');
      anchor.parentNode.insertBefore(buildRecordRow(rec, ctx), anchor);
    }

    function renderRecords(records, ctx) {
      document.querySelectorAll('.pro3-proc4-record-row').forEach(function (el) { el.remove(); });
      records.forEach(function (rec) { addRecordRow(rec, ctx); });
    }

    document.getElementById('newLotPlate').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var lotPlateVal = this.value.trim();
      if (!lotPlateVal) return;

      var prepared_LotID = lotPlateVal + '_' + formatDateHyphen(new Date());
      var input = this;

      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ajax_resolve_lotid: '1', prepared_LotID: prepared_LotID })
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
            renderRecords(data.records || [], { prodname: data.prodname, invno: data.invno, wo: data.wo });
            document.getElementById('newPlateNo').focus();
          } else {
            alert('cannot file the LotID. Please input data Manually');
            input.focus();
          }
        })
        .catch(function () {
          alert('cannot file the LotID. Please input data Manually');
          input.focus();
        });
    });

    document.getElementById('newPlateNo').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newRackNo').focus();
    });

    document.getElementById('newRackNo').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newPltQty').focus();
    });

    document.getElementById('newPltSubmitBtn').addEventListener('click', function () {
      var btn = this;
      var ctx = currentLotCtx();
      var lotPlate = document.getElementById('newLotPlate').value.trim();
      var plateNo  = document.getElementById('newPlateNo').value.trim();
      var rackNo   = document.getElementById('newRackNo').value.trim();
      var qty      = document.getElementById('newPltQty').value;
      var tankNo   = document.getElementById('newPltTankNo').value;
      var opr      = document.getElementById('newPltOpr').value;
      var status   = document.getElementById('newPltStatus').value;
      var remark   = document.getElementById('newPltRemark').value;

      var payload = new URLSearchParams({
        ajax_insert_pltrec: '1',
        ProdName:  ctx.prodname,
        InvNo:     ctx.invno,
        WO:        ctx.wo,
        Date:      document.getElementById('rackDate').value,
        Time:      document.getElementById('rackTime').value,
        Opr:       opr,
        LotPlate:  lotPlate,
        PlateNo:   plateNo,
        RackNo:    rackNo,
        Qty:       qty,
        PltTankNo: tankNo,
        Status:    status,
        Remark:    remark
      });

      btn.disabled = true;
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          btn.disabled = false;

          if (data.status === 'dup') {
            alert('พบข้อมูลซ้ำในฐานขัอมูล กรุณาตรวจสอบใหม่');
            return;
          }
          if (data.status !== 'ok') {
            alert(data.message || 'บันทึกไม่สำเร็จ');
            return;
          }

          addRecordRow({
            LotPlate: lotPlate, PlateNo: plateNo, RackNo: rackNo, Qty: qty,
            PltTankNo: tankNo, Opr: opr, Status: status, Remark: remark
          }, ctx);

          document.getElementById('newLotPlate').value = '';
          document.getElementById('newPlateNo').value = '';
          document.getElementById('newRackNo').value = '';
          document.getElementById('newPltQty').value = '';
          document.getElementById('newPltTankNo').value = '';
          document.getElementById('newPltStatus').value = '';
          document.getElementById('newPltRemark').value = '';
          document.getElementById('newLotPlate').focus();
        })
        .catch(function () {
          btn.disabled = false;
          alert('เกิดข้อผิดพลาด');
        });
    });

    document.getElementById('input-plating').addEventListener('click', function (e) {
      var delBtn = e.target.closest('.pltRecordDeleteBtn');
      if (!delBtn) return;

      var row = delBtn.closest('.pro3-proc4-record-row');
      if (!confirm('ต้องการลบรายการนี้หรือไม่')) return;

      var payload = new URLSearchParams({
        ajax_delete_pltrec: '1',
        ProdName: row.dataset.prodname,
        InvNo:    row.dataset.invno,
        WO:       row.dataset.wo,
        LotPlate: row.dataset.lotplate,
        PlateNo:  row.dataset.plateno,
        RackNo:   row.dataset.rackno
      });

      delBtn.disabled = true;
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status === 'ok') {
            row.remove();
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
  </script>
</body>
</html>
