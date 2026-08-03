<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodNames = $_POST['ProdName'];
        $wos       = $_POST['WO'];
        $boxNos    = $_POST['BoxNo'];
        $boxQtys   = $_POST['BoxQty'];
        $materials = $_POST['Materials'];
        $appChecks = $_POST['AppCheck'];
        $lotID     = $_POST['LotID'];
        $remarks   = $_POST['Remark'];

        $invNo    = $_POST['InvNo'];
        $date     = $_POST['Date'];
        $opr      = $_POST['Opr'];

        // Time removed from form; keep column filled for the table.
        $time   = date('H:i:s');
        $status = empty($remarks) ? 'wait incoming' : $remarks;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc1` (`ProdName`,`InvNo`,`WO`,`BoxNo`,`Mat`,`Date`,`Time`,`Opr`,`AppCheck`,`BoxQty`,`BoxJudge`,`LotID`,`Status`,`Remark`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "sssssssssissss", $prodName, $invNo, $wo, $boxNo, $material, $date, $time, $opr, $appCheck, $boxQty, $boxJudge, $lotIDFull, $status, $remark);

        $req = true;
        for ($i = 0; $i < count($prodNames); $i++) {
            $prodName  = $prodNames[$i];
            $wo        = $wos[$i];
            $boxNo     = $boxNos[$i];
            $boxQty    = (int)$boxQtys[$i];
            $material  = $materials[$i];
            $appCheck  = $appChecks[$i];
            $boxJudge  = $appChecks[$i];
            $lotIDFull = $lotID."_".$date."_".$time;
            $remark    = $remarks[$i];
            $req = mysqli_stmt_execute($stmt) && $req;
        }

        if ($req) {
            echo "<script>alert('บันทึกข้อมูลสำเร็จ'); location='./nie2_index.php';</script>";
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
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="form-pro3-proc1">
    <h2>1 Receiving — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc1-g">

        <div class="pro3-proc1-g-it"><label>Operator</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" id="oprDisplay" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled>
          <input type="hidden" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>">
        </div>

        <div class="pro3-proc1-g-it"><label>Invoice no.</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="InvNo" id="invNo" required>
        </div>

        <div class="pro3-proc1-g-it"><label style="font-size:0.8em;">จำนวนตาม Inv. (pcs)</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="InvQty" id="invqty" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Date</label></div>
        <div class="pro3-proc1-g-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Data from Lot Tag</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" id="lotTagData" autocomplete="off" placeholder="prod|wo|box|qty|mat">
        </div>

      </div>

      <div class="pro3-proc1-lotList">
        <div class="lotListHeader h1"><label>Prod Name</label></div>
        <div class="lotListHeader h2"><label>WO</label></div>
        <div class="lotListHeader h3"><label>Box no.</label></div>
        <div class="lotListHeader h4"><label>Box q'ty</label></div>
        <div class="lotListHeader h5"><label>Mat.</label></div>
        <div class="lotListHeader h6"><label>App Check</label></div>
        <div class="lotListHeader h7"><label>Remark</label></div>
        <div class="lotListHeader h8"><label>Delete</label></div>
        <div id="prodNameList" class="lotDataList"></div>
        <div id="woList" class="lotDataList"></div>
        <div id="boxNoList" class="lotDataList"></div>
        <div id="boxQtyList" class="lotDataList"></div>
        <div id="matList" class="lotDataList"></div>
        <div id="appCheckList" class="appCheckList"></div>
        <div id="remarkList" class="lotDataList"></div>
        <div id="DelItem"></div>
      </div>

      <div id="lotTagHidden" style="display:none"></div>

      <div class="pro3-proc1-check">
        <div class="pro3-proc1-check-it"><lable style="font-size:0.8em;">จำนวนรวม (pcs)</lable></div>
        <div class="pro3-proc1-check-it"><text id="sumPcs" readonly></text></div>
        <div class="pro3-proc1-check-it"><lable style="font-size:0.8em;">สถานะขาด/เกิน</lable></div>
        <div class="pro3-proc1-check-it"><text id="sumJudge" readonly></text></div>
        <div class="pro3-proc1-check-it"><lable style="font-size:0.8em;">ระบุ Lot ID</lable></div>
        <div class="pro3-proc1-check-it">
          <select id="LotID" name="LotID">
            <option>โปรดระบุ</option>
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
          </text></div>
      </div>
      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
        <button type="submit" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php if (!isset($req)) { mysqli_close($conn); } ?>

  <script>
    window.addEventListener('DOMContentLoaded', function () {
      document.getElementById('invNo').focus();
    });

    document.getElementById('lotTagData').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var text = this.value.trim();
      var parts = text.split('|');
      if (parts.length !== 5) {
        alert('invalid format');
        return;
      }

      var prodName = parts[0].trim(), wo = parts[1].trim(), boxNo = parts[2].trim(), boxQty = parts[3].trim(), material = parts[4].trim();

      var firstProdNameRow = document.querySelector('#prodNameList .dataRow');
      var firstWoRow = document.querySelector('#woList .dataRow');
      if (firstProdNameRow && firstWoRow) {
        if (prodName !== firstProdNameRow.textContent || wo !== firstWoRow.textContent) {
          alert('Product name or WO is incorrect. Please re-check');
          return;
        }
      }

      var invQtyVal = parseFloat(document.getElementById('invqty').value) || 0;
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

    function updateSumPcs() {
      var sum = 0;
      document.querySelectorAll('#boxQtyList .dataRow').forEach(function (row) {
        sum += parseFloat(row.textContent) || 0;
      });
      document.getElementById('sumPcs').textContent = sum;
    }

    function updateSumJudge() {
      var sumPcsVal = parseFloat(document.getElementById('sumPcs').textContent) || 0;
      var invQtyVal = parseFloat(document.getElementById('invqty').value) || 0;
      var sumJudgeEl = document.getElementById('sumJudge');
      sumJudgeEl.style.fontWeight = 'bold';
      if (sumPcsVal === invQtyVal) {
        sumJudgeEl.textContent = 'จำนวนรวมถูกต้อง';
        sumJudgeEl.style.color = 'green';
      } else if (sumPcsVal < invQtyVal) {
        sumJudgeEl.textContent = 'จำนวนรวมขาด';
        sumJudgeEl.style.color = 'red';
      } else {
        sumJudgeEl.textContent = 'จำนวนรวมเกิน';
        sumJudgeEl.style.color = 'red';
      }
    }
  </script>
</body>
</html>
