<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    $lot_id = $lot_prodname = $lot_invno = $lot_wo = '';

    // AJAX: resolve Lot ID / Product name / Invoice no from a Lot Tag scan
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

    // AJAX: insert a new QA outgoing record into tb_proc7
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_insert_proc7'])) {
        header('Content-Type: application/json');

        $pProdName     = $_POST['ProdName'] ?? '';
        $pInvNo        = $_POST['InvNo'] ?? '';
        $pWo           = $_POST['WO'] ?? '';
        $pDate         = $_POST['Date'] ?? '';
        $pTime         = $_POST['Time'] ?? '';
        $pOpr          = (int)($_POST['Opr'] ?? 0);
        $pAppAQLcheck  = $_POST['AppAQLcheck'] ?? '';
        $pThickSmpSize = (int)($_POST['ThickSmpSize'] ?? 0);
        $pThickJudge   = $_POST['ThickJudge'] ?? '';
        $pGlossSmpSize = (int)($_POST['GlossSmpSize'] ?? 0);
        $pGlossJudge   = $_POST['GlossJudge'] ?? '';
        $pRemark       = $_POST['Remark'] ?? '';
        $pStatus       = 'Done';

        $pDupStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_proc7 WHERE ProdName=? AND InvNo=? AND WO=? LIMIT 1");
        mysqli_stmt_bind_param($pDupStmt, 'sss', $pProdName, $pInvNo, $pWo);
        mysqli_stmt_execute($pDupStmt);
        $pDupRow = mysqli_fetch_assoc(mysqli_stmt_get_result($pDupStmt));

        if ($pDupRow) {
            echo json_encode(['status' => 'dup', 'message' => 'พบข้อมูลซ้ำในฐานข้อมูล กรุณาตรวจสอบความถูกต้อง']);
        } else {
            $pStmt = mysqli_prepare($conn,
                "INSERT INTO `tb_proc7`
                    (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`AppAQLcheck`,`ThickSmpSize`,`ThickJudge`,`GlossSmpSize`,`GlossJudge`,`Remark`,`Status`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($pStmt, 'sssssisisisss',
                $pProdName, $pInvNo, $pWo, $pDate, $pTime, $pOpr,
                $pAppAQLcheck, $pThickSmpSize, $pThickJudge, $pGlossSmpSize, $pGlossJudge, $pRemark, $pStatus);
            $pok = mysqli_stmt_execute($pStmt);
            echo json_encode(['status' => $pok ? 'ok' : 'fail', 'message' => $pok ? '' : mysqli_error($conn)]);
        }
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

  <div class="form-pro3-proc1">
    <h2>7 QA Outgoing — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc7-g">

        <div class="pro3-proc7-g-it"><label>Operator</label></div>
        <div class="pro3-proc7-g-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>

        <div class="pro3-proc7-g-it"><label>Data fromLot tag</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" id="lotTagData" name="lotTagData" placeholder="Scan Lot tag ที่นี่">
        </div>

        <div class="pro3-proc7-g-it"><label>Lot ID</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" id="lotIdDisplay" value="<?php echo htmlspecialchars($lot_id); ?>" disabled>
        </div>

        <div class="pro3-proc7-g-it"><label>Product name</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" id="lotProdnameDisplay" value="<?php echo htmlspecialchars($lot_prodname); ?>" disabled>
          <input type="hidden" id="lotProdnameHidden" name="ProdName" value="<?php echo htmlspecialchars($lot_prodname); ?>">
        </div>

        <div class="pro3-proc7-g-it"><label>Invoice no</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" id="lotInvnoDisplay" value="<?php echo htmlspecialchars($lot_invno); ?>" disabled>
          <input type="hidden" id="lotInvnoHidden" name="InvNo" value="<?php echo htmlspecialchars($lot_invno); ?>">
        </div>

        <div class="pro3-proc7-g-it"><label>WO</label></div>
        <div class="pro3-proc7-g-it">
          <input type="text" id="lotWoDisplay" value="<?php echo htmlspecialchars($lot_wo); ?>" disabled>
          <input type="hidden" id="lotWoHidden" name="WO" value="<?php echo htmlspecialchars($lot_wo); ?>">
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
          <select name="AppAQLCheck" id="AppAQLCheck" required>
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
        <button type="button" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php if (!isset($req)) { mysqli_close($conn); } ?>

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

            input.value = '';
            document.getElementById('AppAQLCheck').focus();
          } else {
            alert('ไม่พบข้อมูลในฐานข้อมูล โปรดตรวจสอบอีกครั้ง');
            input.value = '';
            input.focus();
          }
        })
        .catch(function () {
          alert('เกิดข้อผิดพลาด');
          input.focus();
        });
    });

    document.getElementById('okBtn').addEventListener('click', function () {
      var btn = this;
      var payload = new URLSearchParams({
        ajax_insert_proc7: '1',
        ProdName:      document.getElementById('lotProdnameHidden').value,
        InvNo:         document.getElementById('lotInvnoHidden').value,
        WO:            document.getElementById('lotWoHidden').value,
        Date:          document.querySelector('input[name="Date"]').value,
        Time:          document.querySelector('input[name="Time"]').value,
        Opr:           document.querySelector('input[name="Opr"]').value,
        AppAQLcheck:   document.getElementById('AppAQLCheck').value,
        ThickSmpSize:  document.querySelector('input[name="ThickSmpSize"]').value,
        ThickJudge:    document.getElementById('ThickJudge').value,
        GlossSmpSize:  document.querySelector('input[name="GlossSmpSize"]').value,
        GlossJudge:    document.getElementById('GlossJudge').value,
        Remark:        document.querySelector('textarea[name="Remark"]').value
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
            alert('บันทึกข้อมูลเข้า tb_proc7 สำเร็จ');
          } else if (data.status === 'dup') {
            alert(data.message);
          } else {
            alert('บันทึกข้อมูลเข้าฐานข้อมูลไม่สำเร็จ');
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

