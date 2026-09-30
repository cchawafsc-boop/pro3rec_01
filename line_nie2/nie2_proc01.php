<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Operator must come from the session, never from the form.
        if (empty($_SESSION['us_id'])) {
            mysqli_close($conn);
            echo "<script>alert('โปรด Login ก่อนบันทึกข้อมูล'); location='./nie2_proc01.php';</script>";
            exit;
        }

        $prodNames = (array)($_POST['ProdName']  ?? []);
        $wos       = (array)($_POST['WO']        ?? []);
        $boxNos    = (array)($_POST['BoxNo']     ?? []);
        $boxQtys   = (array)($_POST['BoxQty']    ?? []);
        $materials = (array)($_POST['Materials'] ?? []);
        $appChecks = (array)($_POST['AppCheck']  ?? []);
        $statuses  = (array)($_POST['Status']    ?? []);
        $lotID     = trim($_POST['LotID'] ?? '');
        $remarks   = (array)($_POST['Remark']    ?? []);

        $invNo    = strtoupper(str_replace(['-', '_', ' '], '', trim($_POST['InvNo'] ?? '')));
        $date     = $_POST['Date'] ?? '';
        $opr      = $_SESSION['us_id'];

        // Required fields must be filled and every row list must have the same length; otherwise save nothing.
        $formError = '';
        $rowCount  = count($prodNames);
        $dateObj   = DateTime::createFromFormat('Y-m-d', $date);
        if ($invNo === '' || $lotID === '') {
            $formError = 'โปรดใส่ Inv. no. และ Lot ID';
        } elseif (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $formError = 'วันที่ไม่ถูกต้อง';
        } elseif ($rowCount === 0) {
            $formError = 'ไม่มีข้อมูลกล่อง กรุณาสแกน Lot Tag';
        } elseif (count($wos) !== $rowCount || count($boxNos) !== $rowCount || count($boxQtys) !== $rowCount
               || count($materials) !== $rowCount || count($appChecks) !== $rowCount
               || count($statuses) !== $rowCount || count($remarks) !== $rowCount) {
            $formError = 'ข้อมูลกล่องไม่ครบ กรุณาสแกนใหม่';
        } else {
            for ($i = 0; $i < $rowCount; $i++) {
                if (trim($prodNames[$i]) === '' || trim($wos[$i]) === '' || trim($boxNos[$i]) === ''
                    || !ctype_digit((string)$boxQtys[$i]) || (int)$boxQtys[$i] <= 0
                    || !in_array($appChecks[$i], ['pass', 'fail'], true)) {
                    $formError = 'ข้อมูลกล่องที่ ' . ($i + 1) . ' ไม่ครบหรือไม่ถูกต้อง';
                    break;
                }
            }
        }
        if ($formError !== '') {
            mysqli_close($conn);
            echo "<script>alert(" . json_encode($formError) . "); history.back();</script>";
            exit;
        }

        // Status must be one of the 4 allowed values; otherwise save nothing.
        $allowedStatus = ['Accept', 'Hold', 'Reject', 'SpecialAccept'];
        foreach ((array)$statuses as $st) {
            if (!in_array($st, $allowedStatus, true)) {
                mysqli_close($conn);
                echo "<script>alert('Status ไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง'); history.back();</script>";
                exit;
            }
        }

        // Time removed from form; keep column filled for the table.
        $time   = date('H:i:s');

        // Duplicate check: one physical box = ProdName + WO + BoxNo (InvNo is not part of the key).
        // A box may be saved again only if its latest record is Reject AND it comes with a different InvNo.
        $dupErrors = [];
        $seenBoxes = [];
        $dupStmt = mysqli_prepare($conn,
            "SELECT `InvNo`, `Status` FROM `tb_proc1` WHERE `ProdName` = ? AND `WO` = ? AND `BoxNo` = ? ORDER BY `Date` DESC, `Time` DESC LIMIT 1");
        for ($i = 0; $i < $rowCount; $i++) {
            $boxLabel = $prodNames[$i] . ' / ' . $wos[$i] . ' / Box ' . $boxNos[$i];
            $boxKey   = strtolower(trim($prodNames[$i]) . "\x1F" . trim($wos[$i]) . "\x1F" . trim($boxNos[$i]));
            if (isset($seenBoxes[$boxKey])) {
                $dupErrors[] = $boxLabel . ' : สแกนซ้ำในรายการนี้';
                continue;
            }
            $seenBoxes[$boxKey] = true;

            mysqli_stmt_bind_param($dupStmt, 'sss', $prodNames[$i], $wos[$i], $boxNos[$i]);
            mysqli_stmt_execute($dupStmt);
            $last = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));
            if (!$last) {
                continue;   // new box
            }
            if ($last['Status'] === 'Reject') {
                if ($last['InvNo'] === $invNo) {
                    $dupErrors[] = $boxLabel . ' : เคย Reject ด้วย ' . $last['InvNo'] . ' แล้ว โปรดใช้ InvNo ใหม่ (เช่น ' . $invNo . 'A)';
                }
                continue;   // re-receive with a new InvNo is OK
            }
            $dupErrors[] = $boxLabel . ' : รับเข้าแล้ว (Inv ' . $last['InvNo'] . ', ' . $last['Status'] . ')';
        }
        if ($dupErrors) {
            mysqli_close($conn);
            echo "<script>alert(" . json_encode("พบกล่องซ้ำ ไม่ได้บันทึกข้อมูล:\n" . implode("\n", $dupErrors)) . "); history.back();</script>";
            exit;
        }

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc1` (`ProdName`,`InvNo`,`WO`,`BoxNo`,`Mat`,`Date`,`Time`,`Opr`,`AppCheck`,`BoxQty`,`LotID`,`Status`,`Remark`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "sssssssssisss", $prodName, $invNo, $wo, $boxNo, $material, $date, $time, $opr, $appCheck, $boxQty, $lotIDFull, $status, $remark);

        // All-or-nothing: insert every box inside one transaction.
        $req = true;
        try {
            mysqli_begin_transaction($conn);
            for ($i = 0; $i < $rowCount; $i++) {
                $prodName  = $prodNames[$i];
                $wo        = $wos[$i];
                $boxNo     = $boxNos[$i];
                $boxQty    = (int)$boxQtys[$i];
                $material  = $materials[$i];
                $appCheck  = $appChecks[$i];
                $lotIDFull = $lotID."_".$date."_".$time;
                $status    = $statuses[$i];
                $remark    = $remarks[$i];
                if (!mysqli_stmt_execute($stmt)) {
                    $req = false;
                    break;
                }
            }
            if ($req) {
                mysqli_commit($conn);
            } else {
                mysqli_rollback($conn);
            }
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            $req = false;
        }

        if ($req) {
            echo "<script>alert('บันทึกข้อมูลสำเร็จ'); location.replace('./nie2_index.php');</script>";
        } else {
            echo "<script>alert('บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่');</script>";
        }
        mysqli_close($conn);
    }
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0">
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="form-pro3-proc1">
    <h2>1 Receiving — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc1-g pro3-proc1-g3">

        <div class="pro3-proc1-g-it pro3-proc1-g-bl"></div>
        <div class="pro3-proc1-g-it"><label>Operator</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" id="oprDisplay" value="<?php echo htmlspecialchars($_SESSION['us_name'] ?? ''); ?>" disabled>
        </div>

        <div class="pro3-proc1-g-it pro3-proc1-g-bl"><span class="blinkBullet" id="invNoBullet">●</span></div>
        <div class="pro3-proc1-g-it"><label id="invNoLabel" style="color: red;">Invoice no.</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="InvNo" id="invNo" placeholder="โปรดใส่ Inv. no." required>
        </div>

        <div class="pro3-proc1-g-it pro3-proc1-g-bl"><span class="blinkBullet" id="invQtyBullet">●</span></div>
        <div class="pro3-proc1-g-it"><label id="invQtyLabel" style="color: red; font-size:0.8em;">จำนวนตาม Inv. (pcs)</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="InvQty" id="invQty" placeholder="โปรดใส่จำนวนตาม Inv" required>
        </div>

        <div class="pro3-proc1-g-it pro3-proc1-g-bl"></div>
        <div class="pro3-proc1-g-it"><label>Date</label></div>
        <div class="pro3-proc1-g-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc1-g-it pro3-proc1-g-bl"><span class="blinkBullet" id="lotTagDataBullet">●</span></div>
        <div class="pro3-proc1-g-it"><label id="lotTagDataLabel">Data from Lot Tag</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" id="lotTagData" autocomplete="off" disabled placeholder="prod|wo|box|qty|mat">
        </div>

        <div class="pro3-proc1-g-it pro3-proc1-g-bl"></div>
        <div class="pro3-proc1-g-it"><label>สแกน QR (Lot Tag)</label></div>
        <div class="pro3-proc1-g-it" style="flex-direction:column;">
          <div id="qrScanDiv" style="width:100%;">
            <video id="qrVideo" style="width:100%;" playsinline></video>
          </div>
          <canvas id="qrCanvas" style="display:none;"></canvas>
          <button type="button" id="qrConfirmBtn">ยืนยัน QR</button>
        </div>

      </div>

      <div class="pro3-proc1-lotList">
        <div class="lotListHeader h1"><label>Prod Name</label></div>
        <div class="lotListHeader h2"><label>WO</label></div>
        <div class="lotListHeader h3"><label>Box no.</label></div>
        <div class="lotListHeader h4"><label>Box q'ty</label></div>
        <div class="lotListHeader h5"><label>Mat.</label></div>
        <div class="lotListHeader h6"><label>Box Check</label></div>
        <div class="lotListHeader h7"><label>Status</label></div>
        <div class="lotListHeader h8"><label>Remark</label></div>
        <div class="lotListHeader h9"><label>Action</label></div>
        <div id="prodNameList" class="lotDataList"></div>
        <div id="woList"       class="lotDataList"></div>
        <div id="boxNoList"    class="lotDataList"></div>
        <div id="boxQtyList"   class="lotDataList"></div>
        <div id="matList"      class="lotDataList"></div>
        <div id="appCheckList" class="appCheckList"></div>
        <div id="statusList"   class="appCheckList"></div>
        <div id="remarkList"   class="lotDataList"></div>
        <div id="DelItem"></div>
      </div>

      <div id="lotTagHidden" style="display:none"></div>

      <div class="pro3-proc1-check">
        <div class="pro3-proc1-check-it"><lable style="font-size:0.8em;">จำนวนรวม (pcs)</lable></div>
        <div class="pro3-proc1-check-it"><text id="sumPcs" readonly></text></div>
        <div class="pro3-proc1-check-it"><lable style="font-size:0.8em;">สถานะขาด/เกิน</lable></div>
        <div class="pro3-proc1-check-it"><text id="sumJudge" readonly></text></div>
        <div class="pro3-proc1-check-it"><lable style="font-size:0.8em;">ตั้ง All status เป็น</lable></div>
        <div class="pro3-proc1-check-it">
          <select id="allStatusSelect">
            <option value="Accept" selected>Accept</option>
            <option value="Reject">Reject</option>
            <option value="Hold">Hold</option>
            <option value="SpecialAccept">SpecialAccept</option>
          </select>
        </div>
        <div class="pro3-proc1-lotid-row">
          <lable style="font-size:0.8em;">ระบุ Lot ID</lable>
          <select id="LotID" name="LotID" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="A1">A1</option>
            <option value="A2">A2</option>
            <option value="A3">A3</option>
            <option value="A4">A4</option>
            <option value="A5">A5</option>
            <option value="B1">B1</option>
            <option value="B2">B2</option>
            <option value="B3">B3</option>
            <option value="B4">B4</option>
            <option value="B5">B5</option>
            <option value="C1">C1</option>
            <option value="C2">C2</option>
            <option value="C3">C3</option>
            <option value="C4">C4</option>
            <option value="C5">C5</option>
          </select>
        </div>
      </div>
      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
        <button type="submit" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php if (!isset($req)) { mysqli_close($conn); } ?>

  <script src="js/supportfunction.js"></script>
  <script>
    window.addEventListener('DOMContentLoaded', function () {
      document.getElementById('invNo').focus();
      checkInvFields();
    });

    // Blink bullets: shown on invNo/invQty after page load, hidden on blur with value
    window.addEventListener('load', function () {
      document.getElementById('invNoBullet').classList.add('show');
      document.getElementById('invQtyBullet').classList.add('show');
    });

    function updateInvBullets() {
      var invNoOk  = !document.getElementById('invNoBullet').classList.contains('show');
      var invQtyOk = !document.getElementById('invQtyBullet').classList.contains('show');
      document.getElementById('lotTagDataBullet').classList.toggle('show', invNoOk && invQtyOk);
    }

    document.getElementById('invNo').addEventListener('blur', function () {
      document.getElementById('invNoBullet').classList.toggle('show', !this.value.trim());
      updateInvBullets();
    });

    document.getElementById('invQty').addEventListener('blur', function () {
      document.getElementById('invQtyBullet').classList.toggle('show', !this.value.trim());
      updateInvBullets();
    });

    document.getElementById('invQty').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      document.getElementById('lotTagData').focus();
    });

    function checkInvFields() {
      var invNoVal  = document.getElementById('invNo').value.trim();
      var invQtyVal = document.getElementById('invQty').value.trim();

      document.getElementById('invNoLabel').style.color  = invNoVal  ? 'black' : 'red';
      document.getElementById('invQtyLabel').style.color = invQtyVal ? 'black' : 'red';

      var lotTagInput = document.getElementById('lotTagData');
      if (invNoVal && invQtyVal) {
        lotTagInput.disabled = false;
        document.getElementById('lotTagDataLabel').style.color = 'red';
      } else {
        lotTagInput.disabled = true;
        document.getElementById('lotTagDataLabel').style.color = 'black';
      }
    }

    document.getElementById('invNo').addEventListener('change', checkInvFields);
    document.getElementById('invQty').addEventListener('change', checkInvFields);

    // InvNo: keep UPPERCASE only; remove dash, underscore and spaces while typing.
    document.getElementById('invNo').addEventListener('input', function () {
      var cleaned = this.value.toUpperCase().replace(/[-_\s]/g, '');
      if (this.value !== cleaned) {
        this.value = cleaned;
      }
    });

    // Prevent double submit: disable the save button after the first click.
    var okBtn = document.getElementById('okBtn');
    okBtn.form.addEventListener('submit', function () {
      okBtn.disabled = true;
    });
    // Enable it again when the page is shown again (e.g. after history.back() from a server alert).
    window.addEventListener('pageshow', function () {
      okBtn.disabled = false;
    });

    var qrVideo  = document.getElementById('qrVideo');
    var qrCanvas = document.getElementById('qrCanvas');
    var qrCtx    = qrCanvas.getContext('2d');
    var qrLastResult = '';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(function (stream) {
        qrVideo.srcObject = stream;
        qrVideo.play();
        requestAnimationFrame(qrTick);
      })
      .catch(function (err) {
        console.error('Camera error:', err);
      });

    function qrTick() {
      if (qrVideo.readyState === qrVideo.HAVE_ENOUGH_DATA) {
        qrCanvas.width  = qrVideo.videoWidth;
        qrCanvas.height = qrVideo.videoHeight;
        qrCtx.drawImage(qrVideo, 0, 0, qrCanvas.width, qrCanvas.height);

        var imageData = qrCtx.getImageData(0, 0, qrCanvas.width, qrCanvas.height);
        var code = jsQR(imageData.data, imageData.width, imageData.height);
        if (code) {
          qrLastResult = code.data;
        }
      }
      requestAnimationFrame(qrTick);
    }

    document.getElementById('qrConfirmBtn').addEventListener('click', function () {
      if (!qrLastResult) {
        alert('ยังไม่พบข้อมูล QR code กรุณาสแกนใหม่');
        return;
      }
      var lotTagInput = document.getElementById('lotTagData');
      lotTagInput.value = qrLastResult;
      lotTagInput.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    });

    document.getElementById('lotTagData').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var invNoVal  = document.getElementById('invNo').value.trim();
      var invQtyVal = document.getElementById('invQty').value.trim();
      if (!invNoVal || !invQtyVal) {
        alert('โปรดใส่ Inv. no. และจำนวนตาม Inv.');
        return;
      }

      var lot = parseLotTagInput(this.value);
      if (!lot) {
        alert('Lot Tag ไม่ถูกต้อง\nกรุณาตรวจสอบอีกครั้ง');
        this.value = '';
        this.focus();
        return;
      }

      var isDuplicate = false;
      document.querySelectorAll('#boxNoList .dataRow').forEach(function (row) {
        if (row.textContent === lot.boxNo) isDuplicate = true;
      });
      if (isDuplicate) {
        alert('ข้อมูลถูกบันทึกแล้ว โปรดสแกนใหม่');
        return;
      }

      var prodName = lot.prodName, wo = lot.wo, boxNo = lot.boxNo, boxQty = lot.boxQty, material = lot.material;

      var firstProdNameRow = document.querySelector('#prodNameList .dataRow');
      var firstWoRow = document.querySelector('#woList .dataRow');
      if (firstProdNameRow && firstWoRow) {
        if (prodName !== firstProdNameRow.textContent || wo !== firstWoRow.textContent) {
          alert('Product name หรือ WO ไม่ถูกต้อง\nโปรดตรวจสอบ Product name และ WO อีกครั้ง');
          return;
        }
      }

      var invQtyVal = parseFloat(document.getElementById('invQty').value) || 0;
      var existingBoxQtySum = 0;
      document.querySelectorAll('#boxQtyList .dataRow').forEach(function (row) {
        existingBoxQtySum += parseFloat(row.textContent) || 0;
      });
      var newBoxQtySum = existingBoxQtySum + (parseFloat(boxQty) || 0);
      if (newBoxQtySum > invQtyVal) {
        alert('จำนวนชิ้นงานรวมจากกล่องเท่ากับหรือมากกว่าจำนวนตาม Inv.แล้ว\nโปรดตรวจสอบจำนวนชิ้นงานอีกครั้ง');
      }

      var hiddenDiv = document.createElement('div');
      [['ProdName[]', prodName], ['WO[]', wo], ['BoxNo[]', boxNo], ['BoxQty[]', boxQty], ['Materials[]', material]]
        .forEach(function (pair) {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = pair[0];
          input.value = pair[1];
          hiddenDiv.appendChild(input);
        });
      document.getElementById('lotTagHidden').appendChild(hiddenDiv);

      function addDataRow(listId, value) {
        var row = document.createElement('div');
        row.className = 'dataRow';
        row.textContent = value;
        document.getElementById(listId).appendChild(row);
        return row;
      }

      var prodNameRow = addDataRow('prodNameList', prodName);
      var woRow       = addDataRow('woList', wo);
      var boxNoRow    = addDataRow('boxNoList', boxNo);
      var boxQtyRow   = addDataRow('boxQtyList', boxQty);
      var matRow      = addDataRow('matList', material);

      var appCheckRow = document.createElement('div');
      appCheckRow.className = 'appCheckRow';
      appCheckRow.innerHTML =
        '<select name="AppCheck[]" required>' +
          '<option value="" selected disabled>โปรดระบุ</option>' +
          '<option value="pass">pass</option>' +
          '<option value="fail">fail</option>' +
        '</select>';
      document.getElementById('appCheckList').appendChild(appCheckRow);

      var statusRow = document.createElement('div');
      statusRow.className = 'appCheckRow';
      statusRow.innerHTML =
        '<select name="Status[]" required>' +
          '<option value="Accept" selected>Accept</option>' +
          '<option value="Reject">Reject</option>' +
          '<option value="Hold">Hold</option>' +
          '<option value="SpecialAccept">SpecialAccept</option>' +
        '</select>';
      document.getElementById('statusList').appendChild(statusRow);

      var remarkRow = document.createElement('div');
      remarkRow.className = 'remarkRow';
      var remarkTextarea = document.createElement('textarea');
      remarkTextarea.name = 'Remark[]';
      remarkRow.appendChild(remarkTextarea);
      document.getElementById('remarkList').appendChild(remarkRow);

      var delRow = document.createElement('div');
      delRow.className = 'delRow';
      var delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.textContent = 'ลบ';
      delBtn.addEventListener('click', function () {
        hiddenDiv.remove();
        prodNameRow.remove();
        woRow.remove();
        boxNoRow.remove();
        boxQtyRow.remove();
        matRow.remove();
        appCheckRow.remove();
        statusRow.remove();
        remarkRow.remove();
        delRow.remove();
        updateSumPcs();
        updateSumJudge();
      });
      delRow.appendChild(delBtn);
      document.getElementById('DelItem').appendChild(delRow);

      this.value = '';
      this.focus();
      updateSumPcs();
      updateSumJudge();
    });

    document.getElementById('allStatusSelect').addEventListener('change', function () {
      var val = this.value;
      document.querySelectorAll('#statusList select').forEach(function (sel) {
        sel.value = val;
      });
    });

    function updateSumPcs() {
      var sum = 0;
      document.querySelectorAll('#boxQtyList .dataRow').forEach(function (row) {
        sum += parseFloat(row.textContent) || 0;
      });
      document.getElementById('sumPcs').textContent = sum;
    }

    function updateSumJudge() {
      var sumPcsVal = parseFloat(document.getElementById('sumPcs').textContent) || 0;
      var invQtyVal = parseFloat(document.getElementById('invQty').value) || 0;
      var sumJudgeEl = document.getElementById('sumJudge');
      sumJudgeEl.style.fontWeight = 'bold';
      if (sumPcsVal === invQtyVal) {
        sumJudgeEl.textContent = 'จำนวนรวมถูกต้อง';
        sumJudgeEl.style.color = 'green';
      } else if (sumPcsVal < invQtyVal) {
        sumJudgeEl.textContent = 'จำนวนรวมขาด';
        sumJudgeEl.style.color = 'darkgoldenrod';
      } else {
        sumJudgeEl.textContent = 'จำนวนรวมเกิน';
        sumJudgeEl.style.color = 'darkgoldenrod';
      }
    }
  </script>
</body>
</html>
