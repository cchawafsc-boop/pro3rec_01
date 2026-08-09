<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // AJAX: resolve a Lot ID from a Lot-plate scan + today's date.
    // Same LIKE-match + date-stepping pattern as nie2_proc04.php.
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

        if ($found) {
            echo json_encode([
                'status'   => 'ok',
                'lot_id'   => $found['LotID'],
                'prodname' => $found['ProdName'],
                'invno'    => $found['InvNo'],
                'wo'       => $found['WO'],
            ]);
        } else {
            echo json_encode(['status' => 'fail']);
        }
        mysqli_close($conn);
        exit;
    }

    // AJAX: find BoxNo(s) in tb_proc4 for the given ProdName+InvNo+WO+PlateNo.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_find_boxno'])) {
        header('Content-Type: application/json');

        $fProdName = $_POST['ProdName'] ?? '';
        $fInvNo    = $_POST['InvNo'] ?? '';
        $fWo       = $_POST['WO'] ?? '';
        $fPlateNo  = $_POST['PlateNo'] ?? '';

        $fstmt = mysqli_prepare($conn,
            "SELECT DISTINCT p3.BoxNo
             FROM tb_proc4 p4
             INNER JOIN tb_proc3 p3
                ON p3.ProdName = p4.ProdName AND p3.InvNo = p4.InvNo AND p3.WO = p4.WO
               AND p3.PlateNo = p4.PlateNo AND p3.RackNo = p4.RackNo
             WHERE p4.ProdName = ? AND p4.InvNo = ? AND p4.WO = ? AND p4.PlateNo = ?");
        mysqli_stmt_bind_param($fstmt, 'ssss', $fProdName, $fInvNo, $fWo, $fPlateNo);
        mysqli_stmt_execute($fstmt);
        $fres = mysqli_stmt_get_result($fstmt);

        $boxNos = [];
        while ($frow = mysqli_fetch_assoc($fres)) {
            $boxNos[] = $frow['BoxNo'];
        }

        if (empty($boxNos)) {
            echo json_encode(['status' => 'notfound']);
        } else {
            echo json_encode(['status' => 'ok', 'boxno' => implode(' or ', $boxNos)]);
        }
        mysqli_close($conn);
        exit;
    }

    // AJAX: insert a new unracking record into tb_proc5
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_insert_unrack'])) {
        header('Content-Type: application/json');

        $iProdName = $_POST['ProdName'] ?? '';
        $iInvNo    = $_POST['InvNo'] ?? '';
        $iWo       = $_POST['WO'] ?? '';
        $iDate     = $_POST['Date'] ?? '';
        $iTime     = $_POST['Time'] ?? '';
        $iOpr      = (int)($_POST['Opr'] ?? 0);
        $iBoxNo    = $_POST['BoxNo'] ?? '';
        $iLotPlate = $_POST['LotPlate'] ?? '';
        $iPlateNo  = (int)($_POST['PlateNo'] ?? 0);
        $iFGtotal  = (int)($_POST['FGtotal'] ?? 0);
        $iNGtotal  = (int)($_POST['NGtotal'] ?? 0);
        $iStatus   = $_POST['Status'] ?? '';
        $iRemark   = $_POST['Remark'] ?? '';

        $istmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc5` (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`BoxNo`,`LotPlate`,`PlateNo`,`FGtotal`,`NGtotal`,`Status`,`Remark`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($istmt, 'sssssisiiisss',
            $iProdName, $iInvNo, $iWo, $iDate, $iTime, $iOpr, $iBoxNo, $iLotPlate, $iPlateNo, $iFGtotal, $iNGtotal, $iStatus, $iRemark);
        $iok = mysqli_stmt_execute($istmt);
        echo json_encode(['status' => $iok ? 'ok' : 'fail', 'message' => $iok ? '' : mysqli_error($conn)]);
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

  <div class="form-pro3-proc5-g1">
    <h2>5 Unracking — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc5-g1">

        <div class="pro3-proc5-g1-it"><label>Operator</label></div>
        <div class="pro3-proc5-g1-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>

        <div class="pro3-proc5-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc5-g1-it">
          <input type="text" id="lotIdDisplay" disabled>
          <input type="hidden" id="lotIdHidden" value="">
        </div>

        <div class="pro3-proc5-g1-it"><label>Product name</label></div>
        <div class="pro3-proc5-g1-it">
          <input type="text" id="lotProdnameDisplay" disabled>
          <input type="hidden" id="lotProdnameHidden" name="ProdName" value="">
        </div>

        <div class="pro3-proc5-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc5-g1-it">
          <input type="text" id="lotInvnoDisplay" disabled>
          <input type="hidden" id="lotInvnoHidden" name="InvNo" value="">
        </div>

        <div class="pro3-proc5-g1-it"><label>WO</label></div>
        <div class="pro3-proc5-g1-it">
          <input type="text" id="lotWoDisplay" disabled>
          <input type="hidden" id="lotWoHidden" name="WO" value="">
        </div>

        <div class="pro3-proc5-g1-it"><label>Date</label></div>
        <div class="pro3-proc5-g1-it">
          <input type="date" id="rackDate" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc5-g1-it"><label>Time</label></div>
        <div class="pro3-proc5-g1-it">
          <input type="time" id="rackTime" name="Time" value="<?php echo date('H:i'); ?>" disabled>
        </div>

      </div>

      <p class="pro3-proc5-note">unrack ให้เสร็จ และล้างงาน ก่อนบันทึกค่าเข้าระบบ</p>

      <div id="input-unracking">
        <div class="pro3-proc5-g2-h">Lot-plate</div>
        <div class="pro3-proc5-g2-h">Plate-no</div>
        <div class="pro3-proc5-g2-h">Box-no</div>
        <div class="pro3-proc5-g2-h">FG-qty</div>
        <div class="pro3-proc5-g2-h">NG-qty</div>
        <div class="pro3-proc5-g2-h">ลง NG</div>
        <div class="pro3-proc5-g2-h">Operator</div>
        <div class="pro3-proc5-g2-h">Status</div>
        <div class="pro3-proc5-g2-h">Remark</div>
        <div class="pro3-proc5-g2-h">Action</div>

        <div class="pro3-proc5-g2-c"><input type="text" id="newLotPlate" autocomplete="off"></div>
        <div class="pro3-proc5-g2-c"><input type="text" id="newPlateNo" autocomplete="off"></div>
        <div class="pro3-proc5-g2-c"><input type="text" id="newBoxNo" autocomplete="off"></div>
        <div class="pro3-proc5-g2-c"><input type="number" id="newFGQty" min="0"></div>
        <div class="pro3-proc5-g2-c"><input type="number" id="newNGQty" min="0"></div>
        <div class="pro3-proc5-g2-c"><button type="button" id="newNgBtn">ลง NG</button></div>
        <div class="pro3-proc5-g2-c"><input type="number" id="newUnrackOpr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled></div>
        <div class="pro3-proc5-g2-c">
          <select id="newUnrackStatus">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Accept">Accept</option>
            <option value="Reject">Reject</option>
            <option value="Hold">Hold</option>
            <option value="SpecialAccept">SpecialAccept</option>
          </select>
        </div>
        <div class="pro3-proc5-g2-c"><textarea id="newUnrackRemark" rows="2"></textarea></div>
        <div class="pro3-proc5-g2-c"><button type="button" id="newUnrackSubmitBtn">บันทึกเข้าระบบ</button></div>
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
            document.getElementById('lotIdHidden').value = data.lot_id;
            document.getElementById('lotProdnameDisplay').value = data.prodname;
            document.getElementById('lotProdnameHidden').value = data.prodname;
            document.getElementById('lotInvnoDisplay').value = data.invno;
            document.getElementById('lotInvnoHidden').value = data.invno;
            document.getElementById('lotWoDisplay').value = data.wo;
            document.getElementById('lotWoHidden').value = data.wo;
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

      var plateNoVal = this.value.trim();
      if (!plateNoVal) return;

      var input = this;
      var payload = new URLSearchParams({
        ajax_find_boxno: '1',
        ProdName: document.getElementById('lotProdnameHidden').value,
        InvNo:    document.getElementById('lotInvnoHidden').value,
        WO:       document.getElementById('lotWoHidden').value,
        PlateNo:  plateNoVal
      });

      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.status === 'ok') {
            document.getElementById('newBoxNo').value = data.boxno;
            document.getElementById('newBoxNo').focus();
          } else {
            alert('ไม่พบ Plate-no ในฐานข้อมูล โปรดตรวจสอบอีกครั้ง');
            input.focus();
          }
        })
        .catch(function () {
          alert('ไม่พบ Plate-no ในฐานข้อมูล โปรดตรวจสอบอีกครั้ง');
          input.focus();
        });
    });

    document.getElementById('newBoxNo').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newFGQty').focus();
    });

    document.getElementById('newFGQty').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newNGQty').focus();
    });

    document.getElementById('newNGQty').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newUnrackStatus').focus();
    });

    document.getElementById('newNgBtn').addEventListener('click', function () {
      var form = document.createElement('form');
      form.method = 'post';
      form.action = 'nie2_ng_input.php';

      function addField(name, value) {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = name;
        inp.value = value;
        form.appendChild(inp);
      }

      addField('sourcePathname', window.location.pathname);
      addField('lot_id_raw', document.getElementById('lotIdHidden').value);
      addField('boxNo', document.getElementById('newBoxNo').value);
      addField('selected_process', '5. Unracking');

      document.body.appendChild(form);
      form.submit();
    });

    document.getElementById('newUnrackSubmitBtn').addEventListener('click', function () {
      var btn = this;
      var payload = new URLSearchParams({
        ajax_insert_unrack: '1',
        ProdName: document.getElementById('lotProdnameHidden').value,
        InvNo:    document.getElementById('lotInvnoHidden').value,
        WO:       document.getElementById('lotWoHidden').value,
        Date:     document.getElementById('rackDate').value,
        Time:     document.getElementById('rackTime').value,
        Opr:      document.getElementById('newUnrackOpr').value,
        BoxNo:    document.getElementById('newBoxNo').value,
        LotPlate: document.getElementById('newLotPlate').value,
        PlateNo:  document.getElementById('newPlateNo').value,
        FGtotal:  document.getElementById('newFGQty').value,
        NGtotal:  document.getElementById('newNGQty').value,
        Status:   document.getElementById('newUnrackStatus').value,
        Remark:   document.getElementById('newUnrackRemark').value
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

          if (data.status !== 'ok') {
            alert(data.message || 'บันทึกไม่สำเร็จ');
            return;
          }

          document.getElementById('newLotPlate').value = '';
          document.getElementById('newPlateNo').value = '';
          document.getElementById('newBoxNo').value = '';
          document.getElementById('newFGQty').value = '';
          document.getElementById('newNGQty').value = '';
          document.getElementById('newUnrackStatus').value = '';
          document.getElementById('newUnrackRemark').value = '';
          document.getElementById('newLotPlate').focus();
        })
        .catch(function () {
          btn.disabled = false;
          alert('เกิดข้อผิดพลาด');
        });
    });
  </script>
</body>
</html>
