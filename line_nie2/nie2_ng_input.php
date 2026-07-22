<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');
    require('./ngmode.php');

    $lot_prodname = $lot_invno = $lot_wo = '';
    $lot_prodname_raw = $lot_invno_raw = $lot_wo_raw = '';
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
    }

    $lot_boxnos = [];
    if (!empty($_SESSION['lotid'])) {
        $bstmt = mysqli_prepare($conn,
            "SELECT BoxNo FROM tb_proc1 WHERE LotID = ? ORDER BY BoxNo");
        mysqli_stmt_bind_param($bstmt, 's', $_SESSION['lotid']);
        mysqli_stmt_execute($bstmt);
        $bres = mysqli_stmt_get_result($bstmt);
        while ($brow = mysqli_fetch_assoc($bres)) {
            $lot_boxnos[] = $brow['BoxNo'];
        }
    }

    // Set by the "เลือกชนิด NG" button on nie2_proc02.php
    if (isset($_GET['process'])) { $_SESSION['process'] = $_GET['process']; }
    if (isset($_GET['boxno']))   { $_SESSION['boxno']   = $_GET['boxno']; }

    $pre_process = $_SESSION['process'] ?? '';
    $pre_boxno   = $_SESSION['boxno']   ?? '';

    // Extract existing tb_ng rows for the selected Process + Box no
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_ng'])) {
        header('Content-Type: application/json');

        $process = $_POST['Process'] ?? '';
        $boxNo   = $_POST['BoxNo'] ?? '';

        $rows = [];
        if ($process !== '' && $boxNo !== '') {
            $fstmt = mysqli_prepare($conn,
                "SELECT NGmode, NGqty, Remark FROM tb_ng WHERE ProdName = ? AND InvNo = ? AND WO = ? AND Process = ? AND BoxNo = ?");
            mysqli_stmt_bind_param($fstmt, 'sssss', $lot_prodname_raw, $lot_invno_raw, $lot_wo_raw, $process, $boxNo);
            mysqli_stmt_execute($fstmt);
            $fres = mysqli_stmt_get_result($fstmt);
            while ($frow = mysqli_fetch_assoc($fres)) {
                $rows[] = $frow;
            }
        }

        echo json_encode(['status' => 'ok', 'rows' => $rows]);
        mysqli_close($conn);
        exit;
    }

    // Per-row AJAX submit -> tb_ng (insert new record, or overwrite the matching one)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit'])) {
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

        // Key = ProdName + InvNo + WO + Process + BoxNo
        $checkStmt = mysqli_prepare($conn,
            "SELECT 1 FROM tb_ng WHERE ProdName = ? AND InvNo = ? AND WO = ? AND Process = ? AND BoxNo = ?");
        if (!$checkStmt) {
            echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)]);
            exit;
        }
        mysqli_stmt_bind_param($checkStmt, 'sssss', $prodName, $invNo, $wo, $process, $boxNo);
        mysqli_stmt_execute($checkStmt);
        $exists = mysqli_stmt_get_result($checkStmt)->fetch_row() !== null;

        if ($exists) {
            $stmt = mysqli_prepare($conn,
                "UPDATE tb_ng SET NGmode = ?, NGqty = ?, Remark = ?
                 WHERE ProdName = ? AND InvNo = ? AND WO = ? AND Process = ? AND BoxNo = ?");
            if (!$stmt) {
                echo json_encode(['status' => 'fail', 'message' => mysqli_error($conn)]);
                exit;
            }
            mysqli_stmt_bind_param($stmt, 'sissssss',
                $ngMode, $qty, $remark,
                $prodName, $invNo, $wo, $process, $boxNo);
        } else {
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
        }

        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['status' => $ok ? 'ok' : 'fail', 'message' => $ok ? '' : mysqli_error($conn)]);
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

  <div class="form-pro3-ngin">
    <h2>ระบุ NG — Ni-e Line 2</h2>

    <?php if (!empty($_SESSION['lotid'])): ?>
    <p style="color:#1a6e1a; font-weight:bold; font-size:0.95em;">
      Lot ID : <?php echo htmlspecialchars($_SESSION['lotid']); ?>
    </p>
    <?php else: ?>
    <p style="color:#b30000; font-weight:bold; font-size:0.95em;">
      กรุณาเลือก Lot ID
    </p>
    <?php endif; ?>

    <div class="form-pro3-proc1-g">

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
          <option value="<?php echo $val; ?>" <?php echo (str_replace(' ', '', $pre_process) === str_replace(' ', '', $val)) ? 'selected' : ''; ?>><?php echo $label; ?></option>
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

      <div class="pro3-proc1-g-it"><label>Operator</label></div>
      <div class="pro3-proc1-g-it">
        <input type="number" id="hdrOpr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled>
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
          <th>NGmode</th>
          <th>NGqty (pcs)</th>
          <th>Remark</th>
          <th>Submit</th>
        </tr>
      </thead>
      <tbody id="ngTblBody">
      </tbody>
    </table>

    <p style="display:flex; justify-content:center; padding:0 10px;">
      <button type="button" id="Nie2_homeBtn" onclick="window.history.back();">กลับหน้าก่อน</button>
    </p>
  </div>

  <?php mysqli_close($conn); ?>

  <script>
    const ngModeList = <?php echo json_encode($ngModeList); ?>;

    function buildModeOptions(selected) {
      let html = '<option value=""' + (selected ? '' : ' selected') + ' disabled>เลือก</option>';
      ngModeList.forEach(m => {
        html += '<option value="' + m + '"' + (m === selected ? ' selected' : '') + '>' + m + '</option>';
      });
      return html;
    }

    function buildRow(mode, qty, remark, isNew) {
      const tr = document.createElement('tr');
      tr.className = isNew ? 'new-data-row' : 'existing-data-row';

      const tdMode = document.createElement('td');
      const selMode = document.createElement('select');
      selMode.className = 'rowNGmode';
      selMode.innerHTML = buildModeOptions(mode || '');
      tdMode.appendChild(selMode);

      const tdQty = document.createElement('td');
      const inpQty = document.createElement('input');
      inpQty.type = 'number';
      inpQty.className = 'rowQty';
      inpQty.min = '0';
      inpQty.value = mode ? qty : 0;
      tdQty.appendChild(inpQty);

      const tdRemark = document.createElement('td');
      const txtRemark = document.createElement('textarea');
      txtRemark.className = 'rowRemark';
      txtRemark.rows = 2;
      txtRemark.maxLength = 30;
      txtRemark.value = remark || '';
      tdRemark.appendChild(txtRemark);

      const tdSubmit = document.createElement('td');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'rowSubmitBtn';
      btn.textContent = isNew ? 'บันทึกลงระบบ' : 'แก้ไขในระบบ';
      tdSubmit.appendChild(btn);

      tr.appendChild(tdMode);
      tr.appendChild(tdQty);
      tr.appendChild(tdRemark);
      tr.appendChild(tdSubmit);
      return tr;
    }

    function loadNGRows() {
      const process = document.getElementById('hdrProcess').value;
      const boxNo   = document.getElementById('hdrBoxNo').value;
      const tbody   = document.getElementById('ngTblBody');
      tbody.innerHTML = '';

      if (!process || !boxNo) {
        tbody.appendChild(buildRow('', 0, '', true));
        return;
      }

      const payload = new URLSearchParams({ fetch_ng: '1', Process: process, BoxNo: boxNo });
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
      })
        .then(r => r.json())
        .then(data => {
          tbody.innerHTML = '';
          const rows = (data.status === 'ok') ? data.rows : [];
          rows.forEach(r => {
            tbody.appendChild(buildRow(r.NGmode, r.NGqty, r.Remark, false));
          });
          tbody.appendChild(buildRow('', 0, '', true));
        })
        .catch(() => {
          tbody.innerHTML = '';
          tbody.appendChild(buildRow('', 0, '', true));
        });
    }

    document.getElementById('hdrProcess').addEventListener('change', loadNGRows);
    document.getElementById('hdrBoxNo').addEventListener('change', loadNGRows);

    document.getElementById('ngTblBody').addEventListener('click', function (e) {
      const btn = e.target.closest('.rowSubmitBtn');
      if (!btn) return;

      const process = document.getElementById('hdrProcess').value;
      const boxNo   = document.getElementById('hdrBoxNo').value;
      if (!process || !boxNo) {
        alert('กรุณาเลือก Process และ Box no');
        return;
      }

      const tr     = btn.closest('tr');
      const mode   = tr.querySelector('.rowNGmode').value;
      const qty    = parseInt(tr.querySelector('.rowQty').value, 10) || 0;
      const remark = tr.querySelector('.rowRemark').value;

      if (!mode || qty <= 0) {
        alert('กรุณาระบุ NGmode และจำนวนให้ถูกต้อง');
        return;
      }

      const payload = new URLSearchParams({
        ajax_submit: '1',
        ProdName: document.getElementById('hdrProdName').value,
        InvNo:    document.getElementById('hdrInvNo').value,
        WO:       document.getElementById('hdrWO').value,
        BoxNo:    boxNo,
        Date:     document.getElementById('hdrDate').value,
        Time:     document.getElementById('hdrTime').value,
        Opr:      document.getElementById('hdrOpr').value,
        Process:  process,
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
            loadNGRows();
          } else {
            alert(data.message || 'บันทึกไม่สำเร็จ');
            btn.disabled = false;
          }
        })
        .catch(() => {
          alert('เกิดข้อผิดพลาด');
          btn.disabled = false;
        });
    });

    loadNGRows();
  </script>
</body>
</html>
