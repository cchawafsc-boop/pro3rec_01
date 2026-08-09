<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // AJAX: resolve a Lot ID from a Lot-plate scan + today's date.
    // tb_proc1.LotID = "<text1>_<text2>_<text3>". prepared_LotID sent from
    // JS is "<LotPlate>_<yyyy:mm:dd>" (text1_text2). Match any LotID whose
    // first two underscore-segments equal prepared_LotID; if none, step the
    // date back one day and retry, up to 7 attempts total.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_resolve_lotid'])) {
        header('Content-Type: application/json');

        $prepared = $_POST['prepared_LotID'] ?? '';
        $found = null;

        $sep = strrpos($prepared, '_');
        if ($sep !== false) {
            $baseText = substr($prepared, 0, $sep);
            $dateObj  = DateTime::createFromFormat('Y:m:d', substr($prepared, $sep + 1));

            if ($dateObj) {
                for ($i = 0; $i < 7; $i++) {
                    $candidate = $baseText . '_' . $dateObj->format('Y:m:d');
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

        <div class="pro3-proc4-g2-c"><input type="text" id="newLotPlate" placeholder="Lot-plate"></div>
        <div class="pro3-proc4-g2-c"><input type="text" id="newPlateNo" placeholder="Plate-no"></div> 
        <div class="pro3-proc4-g2-c"><input type="text" id="newRackNo" placeholder="Rack-no"></div>
        <div class="pro3-proc4-g2-c"><input type="number" id="newPltQty" placeholder="Qty"></div>
        <div class="pro3-proc4-g2-c"><input type="number" id="newPltTankNo" placeholder="NieTank-no"></div>
        <div class="pro3-proc4-g2-c"><input type="number" id="newPltOpr" placeholder="Operator"></div>
        <div class="pro3-proc4-g2-c">
          <select id="newPltStatus">
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="Accept">Accept</option>
            <option value="Reject">Reject</option>
            <option value="Hold">Hold</option>
            <option value="SpecialAccept">SpecialAccept</option>
          </select>
        </div>
        <div class="pro3-proc4-g2-c"><textarea id="newRackRemark" rows="2"></textarea></div>
        <div class="pro3-proc4-g2-c"><button type="button">บันทึกเข้าระบบ</button></div>
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

    function formatDateColon(d) {
      var yyyy = d.getFullYear();
      var mm = String(d.getMonth() + 1).padStart(2, '0');
      var dd = String(d.getDate()).padStart(2, '0');
      return yyyy + ':' + mm + ':' + dd;
    }

    document.getElementById('newLotPlate').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var lotPlateVal = this.value.trim();
      if (!lotPlateVal) return;

      var prepared_LotID = lotPlateVal + '_' + formatDateColon(new Date());
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
  </script>
</body>
</html>
