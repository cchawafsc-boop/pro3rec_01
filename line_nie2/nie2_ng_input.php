<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    $ngModeOptions = [
        'Blister','Break','Bumps','Burrs','Chip','Crack','Contam','Discolor',
        'Dent','Scratch','Scuff','Stain','Exposed','Pitting','Deform','Finger'
    ];

    $lot_prodname = $lot_invno = $lot_wo = '';
    if (!empty($_SESSION['lotid'])) {
        $lstmt = mysqli_prepare($conn,
            "SELECT ProdName, InvNo, WO FROM tb_proc1 WHERE LotID = ? LIMIT 1");
        mysqli_stmt_bind_param($lstmt, 's', $_SESSION['lotid']);
        mysqli_stmt_execute($lstmt);
        $lrow = mysqli_fetch_assoc(mysqli_stmt_get_result($lstmt));
        if ($lrow) {
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

    // Auto-selected from the previous page (query string or session); blank if none.
    $pre_process = $_GET['Process'] ?? $_SESSION['ng_process'] ?? '';
    $pre_boxno   = $_GET['BoxNo']   ?? $_SESSION['ng_boxno']   ?? '';

    // Per-row AJAX submit -> tb_ng
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
        $sampSize = (int)($_POST['SampSize'] ?? 0);
        $ngMode   = $_POST['NGmode'] ?? '';
        $qty      = (int)($_POST['Qty'] ?? 0);
        $remark   = mb_substr($_POST['Remark'] ?? '', 0, 30);

        if ($process === '' || $boxNo === '' || !in_array($ngMode, $ngModeOptions, true) || $qty <= 0) {
            echo json_encode(['status' => 'fail', 'message' => 'ข้อมูลไม่ครบถ้วน']);
            exit;
        }

        $defects = array_fill_keys($ngModeOptions, 0);
        $defects[$ngMode] = $qty;
        $ngTotal = array_sum($defects);

        $cols = array_merge(
            ['ProdName','InvNo','WO','BoxNo','Date','Time','Opr','Process','SampSize'],
            $ngModeOptions,
            ['NGtotal','Remark']
        );
        $colList      = '`' . implode('`,`', $cols) . '`';
        $placeholders = implode(',', array_fill(0, count($cols), '?'));

        $stmt = mysqli_prepare($conn, "INSERT INTO `tb_ng` ($colList) VALUES ($placeholders)");

        $types  = 'ssssssisi' . str_repeat('i', count($ngModeOptions)) . 'is';
        $values = array_merge(
            [$prodName, $invNo, $wo, $boxNo, $date, $time, $opr, $process, $sampSize],
            array_values($defects),
            [$ngTotal, $remark]
        );

        $bindArgs = [$stmt, $types];
        foreach ($values as $k => $v) {
            $bindArgs[] = &$values[$k];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bindArgs);

        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['status' => $ok ? 'ok' : 'fail']);
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
                '1.Receiving'  => '1. Receiving',
                '2.Incoming'   => '2. Incoming',
                '3.Racking'    => '3. Racking',
                '4.Plating'    => '4. Plating',
                '5.Inspection' => '5. Inspection',
                '6.QAoutgoing' => '6. QAoutgoing',
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

      <div class="pro3-proc1-g-it"><label>Operator</label></div>
      <div class="pro3-proc1-g-it">
        <input type="number" id="hdrOpr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled>
      </div>

      <div class="pro3-proc1-g-it"><label>Box no</label></div>
      <div class="pro3-proc1-g-it">
        <input type="text" id="hdrBoxNo" value="<?php echo htmlspecialchars($pre_boxno); ?>">
      </div>

    </div>

    <table class="ngInputTbl">
      <thead>
        <tr>
          <th>Box no</th>
          <th>Sampling size</th>
          <th>NG-mode</th>
          <th>Q'ty (pcs)</th>
          <th>Remark</th>
          <th>Submit</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lot_boxnos as $boxNo): ?>
        <tr>
          <td><?php echo htmlspecialchars($boxNo); ?></td>
          <td><input type="number" class="rowSampSize" min="0" value="0"></td>
          <td>
            <select class="rowNGmode">
              <option value="" selected disabled>เลือก</option>
              <?php foreach ($ngModeOptions as $mode): ?>
              <option value="<?php echo $mode; ?>"><?php echo $mode; ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" class="rowQty" min="0" value="0"></td>
          <td><textarea class="rowRemark" rows="2" maxlength="30"></textarea></td>
          <td><button type="button" onclick="submitRow(this, '<?php echo htmlspecialchars($boxNo, ENT_QUOTES); ?>')">บันทึกลงระบบ</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p style="display:flex; justify-content:center; padding:0 10px;">
      <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
    </p>
  </div>

  <?php mysqli_close($conn); ?>

  <script>
    function submitRow(btn, boxNo) {
      const tr       = btn.closest('tr');
      const sampSize = tr.querySelector('.rowSampSize').value;
      const ngMode   = tr.querySelector('.rowNGmode').value;
      const qty      = tr.querySelector('.rowQty').value;
      const remark   = tr.querySelector('.rowRemark').value;
      const process  = document.getElementById('hdrProcess').value;

      if (!process)               { alert('โปรดเลือก Process'); return; }
      if (!ngMode)                { alert('โปรดเลือก NG-mode'); return; }
      if (!qty || Number(qty) <= 0) { alert('โปรดระบุ Q\'ty'); return; }

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
        SampSize: sampSize,
        NGmode:   ngMode,
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
          btn.disabled = false;
          if (data.status === 'ok') {
            alert('บันทึกข้อมูลสำเร็จ');
            tr.querySelector('.rowNGmode').selectedIndex = 0;
            tr.querySelector('.rowQty').value = 0;
            tr.querySelector('.rowSampSize').value = 0;
            tr.querySelector('.rowRemark').value = '';
          } else {
            alert('บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่');
          }
        })
        .catch(() => {
          btn.disabled = false;
          alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        });
    }
  </script>
</body>
</html>
