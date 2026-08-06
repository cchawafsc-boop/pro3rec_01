<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');
    require('./ngmode.php');

    // Lot ID from the referrer page (nie2_proc02.php ngTypeBtn POST), or from
    // a Lot Tag scan resolved via ProdName+WO+BoxNo.
    $lot_id_raw = '';
    if (!empty($_POST['lot_id_raw'])) {
        $lot_id_raw = $_POST['lot_id_raw'];
    } elseif (!empty($_GET['prodName']) && !empty($_GET['wo']) && !empty($_GET['boxNo'])) {
        $fstmt = mysqli_prepare($conn,
            "SELECT LotID FROM tb_proc1 WHERE ProdName = ? AND WO = ? AND BoxNo = ? LIMIT 1");
        mysqli_stmt_bind_param($fstmt, 'sss', $_GET['prodName'], $_GET['wo'], $_GET['boxNo']);
        mysqli_stmt_execute($fstmt);
        $frow = mysqli_fetch_assoc(mysqli_stmt_get_result($fstmt));
        if ($frow) {
            $lot_id_raw = $frow['LotID'];
        } else {
            echo "<script>alert('Data from Lot Tag is error. Please re-check');</script>";
        }
    }

    $lot_prodname = $lot_invno = $lot_wo = '';
    $lot_prodname_raw = $lot_invno_raw = $lot_wo_raw = '';
    if (!empty($lot_id_raw)) {
        $lstmt = mysqli_prepare($conn,
            "SELECT ProdName, InvNo, WO FROM tb_proc1 WHERE LotID = ? LIMIT 1");
        mysqli_stmt_bind_param($lstmt, 's', $lot_id_raw);
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
    }

    $lot_boxnos = [];
    if (!empty($lot_id_raw)) {
        $bstmt = mysqli_prepare($conn,
            "SELECT BoxNo FROM tb_proc1 WHERE LotID = ? ORDER BY BoxNo");
        mysqli_stmt_bind_param($bstmt, 's', $lot_id_raw);
        mysqli_stmt_execute($bstmt);
        $bres = mysqli_stmt_get_result($bstmt);
        while ($brow = mysqli_fetch_assoc($bres)) {
            $lot_boxnos[] = $brow['BoxNo'];
        }
    }

    // Set by the "เลือกชนิด NG" button on nie2_proc02.php
    if (isset($_GET['process']))               { $_SESSION['process'] = $_GET['process']; }
    elseif (isset($_POST['selected_process'])) { $_SESSION['process'] = $_POST['selected_process']; }
    if (isset($_GET['boxno']))       { $_SESSION['boxno'] = $_GET['boxno']; }
    elseif (isset($_POST['boxNo']))  { $_SESSION['boxno'] = $_POST['boxNo']; }

    $pre_process = $_SESSION['process'] ?? '';
    $pre_boxno   = $_SESSION['boxno']   ?? '';

    // AJAX: update an existing tb_ng record, or delete it when NGqty = 0
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit'])) {
        header('Content-Type: application/json');

        $jobId    = (int)($_POST['JobID'] ?? 0);
        $prodName = $_POST['ProdName'] ?? '';
        $invNo    = $_POST['InvNo'] ?? '';
        $wo       = $_POST['WO'] ?? '';
        $process  = $_POST['Process'] ?? '';
        $boxNo    = $_POST['BoxNo'] ?? '';
        $ngMode   = $_POST['NGmode'] ?? '';
        $qty      = (int)($_POST['Qty'] ?? -1);
        $remark   = mb_substr($_POST['Remark'] ?? '', 0, 30);

        if ($jobId <= 0 || !in_array($ngMode, $ngModeList, true) || $qty < 0) {
            echo json_encode(['status' => 'fail', 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        if ($qty === 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM tb_ng WHERE JobID = ?");
            if (!$stmt) {
                echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)]);
                exit;
            }
            mysqli_stmt_bind_param($stmt, 'i', $jobId);
        } else {
            $dstmt = mysqli_prepare($conn,
                "SELECT 1 FROM tb_ng
                 WHERE ProdName = ? AND InvNo = ? AND WO = ? AND Process = ? AND BoxNo = ?
                   AND NGmode = ? AND JobID <> ? LIMIT 1");
            if (!$dstmt) {
                echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)]);
                exit;
            }
            mysqli_stmt_bind_param($dstmt, 'ssssssi',
                $prodName, $invNo, $wo, $process, $boxNo, $ngMode, $jobId);
            mysqli_stmt_execute($dstmt);
            $dupFound = mysqli_stmt_get_result($dstmt)->fetch_row() !== null;
            mysqli_stmt_close($dstmt);
            if ($dupFound) {
                echo json_encode(['status' => 'duplicate']);
                mysqli_close($conn);
                exit;
            }

            $stmt = mysqli_prepare($conn, "UPDATE tb_ng SET NGmode = ?, NGqty = ?, Remark = ? WHERE JobID = ?");
            if (!$stmt) {
                echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)]);
                exit;
            }
            mysqli_stmt_bind_param($stmt, 'sisi', $ngMode, $qty, $remark, $jobId);
        }

        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['status' => $ok ? 'ok' : 'fail', 'message' => $ok ? '' : mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
    }

    // AJAX: insert a new tb_ng record from the last row of the table
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_insert'])) {
        header('Content-Type: application/json');

        $prodName = $_POST['ProdName'] ?? '';
        $invNo    = $_POST['InvNo'] ?? '';
        $wo       = $_POST['WO'] ?? '';
        $boxNo    = $_POST['BoxNo'] ?? '';
        $date     = $_POST['Date'] ?? '';
        $time     = $_POST['Time'] ?? '';
        $opr      = (int)($_POST['Opr'] ?? 0);
        $process  = $_POST['Process'] ?? '';
        $ngMode   = $_POST['NGmode'] ?? '';
        $qty      = (int)($_POST['Qty'] ?? 0);
        $remark   = mb_substr($_POST['Remark'] ?? '', 0, 30);

        if ($prodName === '' || $invNo === '' || $wo === '' || $process === '' || $boxNo === ''
            || !in_array($ngMode, $ngModeList, true) || $qty <= 0) {
            echo json_encode(['status' => 'fail', 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        $dstmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_ng
             WHERE ProdName = ? AND InvNo = ? AND WO = ? AND Process = ? AND BoxNo = ?
               AND NGmode = ? LIMIT 1");
        if (!$dstmt) {
            echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)]);
            exit;
        }
        mysqli_stmt_bind_param($dstmt, 'ssssss',
            $prodName, $invNo, $wo, $process, $boxNo, $ngMode);
        mysqli_stmt_execute($dstmt);
        $dupFound = mysqli_stmt_get_result($dstmt)->fetch_row() !== null;
        mysqli_stmt_close($dstmt);
        if ($dupFound) {
            echo json_encode(['status' => 'duplicate']);
            mysqli_close($conn);
            exit;
        }

        $stmt = mysqli_prepare($conn,
            "INSERT INTO tb_ng
             (ProdName, InvNo, WO, Process, Date, Time, Opr, BoxNo, NGmode, NGqty, Remark)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        if (!$stmt) {
            echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)]);
            exit;
        }
        mysqli_stmt_bind_param($stmt, 'ssssssissis',
            $prodName, $invNo, $wo, $process, $date, $time, $opr, $boxNo, $ngMode, $qty, $remark);

        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['status' => $ok ? 'ok' : 'fail', 'message' => $ok ? '' : mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
    }

    // Existing tb_ng records for the current ProdName + InvNo + WO + Process + BoxNo
    $ngRows = [];
    if ($lot_prodname_raw !== '' && $lot_invno_raw !== '' && $lot_wo_raw !== '' && $pre_process !== '' && $pre_boxno !== '') {
        $nstmt = mysqli_prepare($conn,
            "SELECT JobID, NGmode, NGqty, Remark FROM tb_ng WHERE ProdName = ? AND InvNo = ? AND WO = ? AND Process = ? AND BoxNo = ?");
        mysqli_stmt_bind_param($nstmt, 'sssss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $pre_process, $pre_boxno);
        mysqli_stmt_execute($nstmt);
        $nres = mysqli_stmt_get_result($nstmt);
        while ($nrow = mysqli_fetch_assoc($nres)) {
            $ngRows[] = $nrow;
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

  <div class="form-pro3-ngin">
    <h2>ระบุ NG — Ni-e Line 2</h2>
    <div class="form-pro3-proc1-g">

      <div class="pro3-proc1-g-it"><label>Operator</label></div>
      <div class="pro3-proc1-g-it">
        <input type="number" id="hdrOpr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled>
      </div>

      <div class="pro3-proc1-g-it"><label>Data from Lot Tag</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" id="lotTagData" autocomplete="off" placeholder="prod|wo|box|qty|mat" autofocus>
        </div>

      <div class="pro3-proc1-g-it"><label>Product name</label></div>
      <div class="pro3-proc1-g-it">
        <input type="text" id="hdrProdName" value="<?php echo $lot_prodname; ?>" disabled>
      </div>

      <div class="pro3-proc1-g-it"><label>Invoice no</label></div>
      <div class="pro3-proc1-g-it">
        <input type="text" id="hdrInvNo" value="<?php echo $lot_invno; ?>" disabled>
      </div>

      <div class="pro3-proc1-g-it"><label>WO</label></div>
      <div class="pro3-proc1-g-it">
        <input type="text" id="hdrWO" value="<?php echo $lot_wo; ?>" disabled>
      </div>

      <div class="pro3-proc1-g-it"><label>Process</label></div>
      <div class="pro3-proc1-g-it">
        <select id="hdrProcess" required>
          <option value="" <?php echo $pre_process === '' ? 'selected' : ''; ?> disabled>โปรดระบุ</option>
          <?php
            $processOptions = [
                '1. Receiving'  => '1. Receiving',
                '2. Incoming'   => '2. Incoming',
                '3. Racking'    => '3. Racking',
                '4. Plating'    => '4. Plating',
                '5. Inspection' => '5. Inspection',
                '6. QAoutgoing' => '6. QAoutgoing',
            ];
            foreach ($processOptions as $val => $label):
          ?>
          <option value="<?php echo $val; ?>" <?php echo ($pre_process === $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pro3-proc1-g-it"><label>Date</label></div>
      <div class="pro3-proc1-g-it">
        <input type="date" id="hdrDate" value="<?php echo date('Y-m-d'); ?>" disabled>
      </div>

      <div class="pro3-proc1-g-it"><label>Time</label></div>
      <div class="pro3-proc1-g-it">
        <input type="time" id="hdrTime" value="<?php echo date('H:i'); ?>" disabled>
      </div>

      <div class="pro3-proc1-g-it"><label>Box no</label></div>
      <div class="pro3-proc1-g-it">
        <select id="hdrBoxNo">
          <option value="" <?php echo $pre_boxno === '' ? 'selected' : ''; ?> disabled>โปรดระบุ</option>
          <?php foreach ($lot_boxnos as $boxNoOpt): ?>
          <option value="<?php echo htmlspecialchars($boxNoOpt); ?>" <?php echo ($pre_boxno === $boxNoOpt) ? 'selected' : ''; ?>><?php echo htmlspecialchars($boxNoOpt); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>

    <table class="ngInputTbl">
      <thead>
        <tr>
          <th>NG mode</th>
          <th>NG (pcs)</th>
          <th>Remark</th>
          <th>Edit</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ngRows as $row): ?>
        <tr data-jobid="<?php echo (int)$row['JobID']; ?>">
          <td>
            <select class="rowNGmode">
              <?php foreach ($ngModeList as $mode): ?>
              <option value="<?php echo htmlspecialchars($mode); ?>" <?php echo ($mode === $row['NGmode']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($mode); ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" class="rowQty" min="0" value="<?php echo (int)$row['NGqty']; ?>"></td>
          <td><textarea class="rowRemark" rows="2" maxlength="30"><?php echo htmlspecialchars($row['Remark']); ?></textarea></td>
          <td><button type="button" class="rowEditBtn">แก้ไขเข้าระบบ</button></td>
        </tr>
        <?php endforeach; ?>

        <tr>
          <td>
            <select id="newNGmode">
              <option value="" selected disabled>เลือก</option>
              <?php foreach ($ngModeList as $mode): ?>
              <option value="<?php echo htmlspecialchars($mode); ?>"><?php echo htmlspecialchars($mode); ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" id="newQty" min="0" value="0"></td>
          <td><textarea id="newRemark" rows="2" maxlength="30"></textarea></td>
          <td><button type="button" id="newSubmitBtn">บันทึกเข้าระบบ</button></td>
        </tr>
      </tbody>
    </table>

    <p style="display:flex; justify-content:center; padding:0 10px;">
      <button type="button" onclick="window.history.back();">กลับหน้าก่อน</button>
    </p>
  </div>

  <div id="ngDeleteConfirm" class="ngConfirmOverlay" style="display:none;">
    <div class="ngConfirmBox">
      <p id="ngConfirmMsg">do you want to delete this NG</p>
      <button type="button" id="ngConfirmNo">No</button>
      <button type="button" id="ngConfirmYes">Yes</button>      
    </div>
  </div>

  <?php mysqli_close($conn); ?>

  <script>
    function showConfirm(message, onYes, onNo) {
      const overlay = document.getElementById('ngDeleteConfirm');
      const msgEl   = document.getElementById('ngConfirmMsg');
      const yesBtn  = document.getElementById('ngConfirmYes');
      const noBtn   = document.getElementById('ngConfirmNo');
      msgEl.textContent = message;
      overlay.style.display = 'flex';

      function cleanup() {
        overlay.style.display = 'none';
        yesBtn.removeEventListener('click', onYesClick);
        noBtn.removeEventListener('click', onNoClick);
      }
      function onYesClick() { cleanup(); onYes(); }
      function onNoClick() { cleanup(); if (onNo) onNo(); }

      yesBtn.addEventListener('click', onYesClick);
      noBtn.addEventListener('click', onNoClick);
    }

    function submitEdit(jobId, mode, qty, remark, btn) {
      const payload = new URLSearchParams({
        ajax_edit: '1',
        JobID:    jobId,
        ProdName: document.getElementById('hdrProdName').value,
        InvNo:    document.getElementById('hdrInvNo').value,
        WO:       document.getElementById('hdrWO').value,
        Process:  document.getElementById('hdrProcess').value,
        BoxNo:    document.getElementById('hdrBoxNo').value,
        NGmode:   mode,
        Qty:      qty,
        Remark:   remark
      });

      btn.disabled = true;
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(r => r.json())
        .then(data => {
          if (data.status === 'ok') {
            location.reload();
          } else if (data.status === 'duplicate') {
            alert('NG mode is repeatedly input. Please change the old NG mode value');
            btn.disabled = false;
          } else {
            alert(data.message || 'บันทึกไม่สำเร็จ');
            btn.disabled = false;
          }
        })
        .catch(() => {
          alert('เกิดข้อผิดพลาด');
          btn.disabled = false;
        });
    }

    document.querySelectorAll('.rowEditBtn').forEach(btn => {
      btn.addEventListener('click', function () {
        const tr     = btn.closest('tr');
        const jobId  = tr.dataset.jobid;
        const mode   = tr.querySelector('.rowNGmode').value;
        const qty    = parseInt(tr.querySelector('.rowQty').value, 10) || 0;
        const remark = tr.querySelector('.rowRemark').value;

        if (qty === 0) {
          showConfirm('do you want to delete this NG', () => submitEdit(jobId, mode, qty, remark, btn));
          return;
        }

        submitEdit(jobId, mode, qty, remark, btn);
      });
    });

    const newSubmitBtn = document.getElementById('newSubmitBtn');

    function submitInsert() {
      const mode   = document.getElementById('newNGmode').value;
      const qty    = parseInt(document.getElementById('newQty').value, 10) || 0;
      const remark = document.getElementById('newRemark').value;

      if (!mode || qty <= 0) {
        alert('กรุณาระบุ NG mode และจำนวนให้ถูกต้อง');
        return;
      }

      const payload = new URLSearchParams({
        ajax_insert: '1',
        ProdName: document.getElementById('hdrProdName').value,
        InvNo:    document.getElementById('hdrInvNo').value,
        WO:       document.getElementById('hdrWO').value,
        Process:  document.getElementById('hdrProcess').value,
        BoxNo:    document.getElementById('hdrBoxNo').value,
        Date:     document.getElementById('hdrDate').value,
        Time:     document.getElementById('hdrTime').value,
        Opr:      document.getElementById('hdrOpr').value,
        NGmode:   mode,
        Qty:      qty,
        Remark:   remark
      });

      newSubmitBtn.disabled = true;
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(r => r.json())
        .then(data => {
          if (data.status === 'ok') {
            location.reload();
          } else if (data.status === 'duplicate') {
            alert('NG mode is repeatedly input. Please change the old NG mode value');
            newSubmitBtn.disabled = false;
          } else {
            alert(data.message || 'บันทึกไม่สำเร็จ');
            newSubmitBtn.disabled = false;
          }
        })
        .catch(() => {
          alert('เกิดข้อผิดพลาด');
          newSubmitBtn.disabled = false;
        });
    }

    newSubmitBtn.addEventListener('click', submitInsert);
  </script>

  <?php if (empty($_POST['lot_id_raw'])): ?>
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
  </script>
  <?php endif; ?>
</body>
</html>
