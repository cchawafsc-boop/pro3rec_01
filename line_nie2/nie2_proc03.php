<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodName = $_POST['ProdName'];
        $invNo    = $_POST['InvNo'];
        $wo       = $_POST['WO'];
        $date     = $_POST['Date'];
        $time     = $_POST['Time'];
        $opr      = (int)$_POST['Opr'];
        $boxNo    = $_POST['BoxNo'];
        $plateNo  = (int)$_POST['PlateNo'];
        $rackNo   = (int)$_POST['RackNo'];
        $qty      = (int)$_POST['Qty'];

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc3` (`ProdName`,`InvNo`,`WO`,`Date`,`Time`,`Opr`,`BoxNo`,`PlateNo`,`RackNo`,`Qty`) VALUES (?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "sssssisiii",
            $prodName, $invNo, $wo, $date, $time, $opr, $boxNo, $plateNo, $rackNo, $qty);
        $req = mysqli_stmt_execute($stmt);

        if ($req) {
            $qs = http_build_query([
                'ProdName' => $prodName,
                'InvNo'    => $invNo,
                'WO'       => $wo,
            ]);
            echo "<script>alert('บันทึกข้อมูลสำเร็จ'); window.location.href='./nie2_proc03.php?" . $qs . "';</script>";
            exit;
        } else {
            echo "<script>alert('บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่');</script>";
        }
    }

    // AJAX: delete one racking record from tb_proc3
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_rack'])) {
        header('Content-Type: application/json');

        $dProdName = $_POST['ProdName'] ?? '';
        $dInvNo    = $_POST['InvNo'] ?? '';
        $dWo       = $_POST['WO'] ?? '';
        $dBoxNo    = $_POST['BoxNo'] ?? '';
        $dPlateNo  = (int)($_POST['PlateNo'] ?? 0);
        $dRackNo   = (int)($_POST['RackNo'] ?? 0);

        $dstmt = mysqli_prepare($conn,
            "DELETE FROM tb_proc3
             WHERE ProdName=? AND InvNo=? AND WO=? AND BoxNo=? AND PlateNo=? AND RackNo=? LIMIT 1");
        mysqli_stmt_bind_param($dstmt, 'ssssii',
            $dProdName, $dInvNo, $dWo, $dBoxNo, $dPlateNo, $dRackNo);
        $dok = mysqli_stmt_execute($dstmt);
        echo json_encode(['status' => $dok ? 'ok' : 'fail', 'message' => $dok ? '' : mysqli_error($conn)]);
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
            echo "<script>alert('Error in DataBase injection. Please contack Admin');</script>";
        }
    }

    // Existing racking records for every box under this Lot ID.
    $rackRows = [];
    if (!empty($lot_id_raw)) {
        $rstmt = mysqli_prepare($conn,
            "SELECT p3.ProdName, p3.InvNo, p3.WO, p3.BoxNo, p3.PlateNo, p3.RackNo, p3.Qty, p3.Opr, p3.Remark
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
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0">
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="form-pro3-proc3-g1">
    <h2>3 Racking — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc3-g1">

        <div class="pro3-proc3-g1-it"><label>Operator</label></div>
        <div class="pro3-proc3-g1-it">
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

        <div class="pro3-proc3-g1-it"><label>Date</label></div>
        <div class="pro3-proc3-g1-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc3-g1-it"><label>Time</label></div>
        <div class="pro3-proc3-g1-it">
          <input type="time" name="Time" value="<?php echo date('H:i'); ?>" disabled>
        </div>

      </div>

      <div id="input-racking">
        <div class="rack-h">Box-no</div>
        <div class="rack-h">Plate-no</div>
        <div class="rack-h">Rack-no</div>
        <div class="rack-h">Qty</div>
        <div class="rack-h">Operator</div>
        <div class="rack-h">Remark</div>
        <div class="rack-h">Action</div>

        <?php foreach ($rackRows as $rrow): ?>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['BoxNo']); ?></div>
        <div class="rack-c"><?php echo (int)$rrow['PlateNo']; ?></div>
        <div class="rack-c"><?php echo (int)$rrow['RackNo']; ?></div>
        <div class="rack-c"><?php echo (int)$rrow['Qty']; ?></div>
        <div class="rack-c"><?php echo (int)$rrow['Opr']; ?></div>
        <div class="rack-c"><?php echo htmlspecialchars($rrow['Remark']); ?></div>
        <div class="rack-c">
          <button type="button" class="rackDeleteBtn"
            data-prodname="<?php echo htmlspecialchars($rrow['ProdName'], ENT_QUOTES); ?>"
            data-invno="<?php echo htmlspecialchars($rrow['InvNo'], ENT_QUOTES); ?>"
            data-wo="<?php echo htmlspecialchars($rrow['WO'], ENT_QUOTES); ?>"
            data-boxno="<?php echo htmlspecialchars($rrow['BoxNo'], ENT_QUOTES); ?>"
            data-plateno="<?php echo (int)$rrow['PlateNo']; ?>"
            data-rackno="<?php echo (int)$rrow['RackNo']; ?>">delete</button>
        </div>
        <?php endforeach; ?>

        <div class="rack-c"><input type="text" id="newBoxNo" autocomplete="off" placeholder="prod|wo|box|qty|mat"></div>
        <div class="rack-c"><input type="text" id="newPlateNo" autocomplete="off"></div>
        <div class="rack-c"><input type="text" id="newRackNo" autocomplete="off"></div>
        <div class="rack-c"><input type="number" id="newRackQty" min="0" value="0"></div>
        <div class="rack-c"><input type="number" id="newRackOpr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled></div>
        <div class="rack-c"><textarea id="newRackRemark" rows="2"></textarea></div>
        <div class="rack-c"><button type="button" id="newRackSubmitBtn">บันทึกเข้าระบบ</button></div>
      </div>

      <p>
        <button type="button" id="Nie2_homeBtn" onclick="<?php echo "window.location.href='"&$_SERVER['HTTP_REFERER']&"'?>">กลับหน้าก่อน<br>Ni-e line 2</button>
      </p>
    </form>
  </div>

  <?php mysqli_close($conn); ?>

  <script src="js/lotTagParser.js"></script>
  <script>
    window.addEventListener('DOMContentLoaded', function () {
      document.getElementById('invNo').focus();
    });

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

      var url = new URL(window.location.href);
      url.searchParams.set('prodName', lot.prodName);
      url.searchParams.set('wo', lot.wo);
      url.searchParams.set('boxNo', lot.boxNo);
      window.location.href = url.toString();
    });

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

      this.value = lot.boxNo;
      document.getElementById('newPlateNo').focus();
    });

    document.getElementById('newPlateNo').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newRackNo').focus();
    });

    document.getElementById('newRackNo').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('newRackQty').focus();
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
