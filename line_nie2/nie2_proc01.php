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

        $invNo    = $_POST['InvNo'];
        $date     = $_POST['Date'];
        $opr      = $_POST['Opr'];

        // Time, Box judge, Lot ID fields removed from form; keep columns filled for the table.
        $time      = date('H:i:s');
        $boxJudge  = '';
        $lotID     = '';
        $lotIDFull = $lotID."_".$date."_".$time;
        $done_f    = 'no';

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc1` (`ProdName`,`InvNo`,`WO`,`BoxNo`,`Mat`,`Date`,`Time`,`Opr`,`AppCheck`,`BoxQty`,`BoxJudge`,`LotID`,`DoneFlag`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "sssssssssisss", $prodName, $invNo, $wo, $boxNo, $material, $date, $time, $opr, $appCheck, $boxQty, $boxJudge, $lotIDFull, $done_f);

        $req = true;
        for ($i = 0; $i < count($prodNames); $i++) {
            $prodName = $prodNames[$i];
            $wo       = $wos[$i];
            $boxNo    = $boxNos[$i];
            $boxQty   = (int)$boxQtys[$i];
            $material = $materials[$i];
            $appCheck = $appChecks[$i];
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

        <div class="pro3-proc1-g-it"><label>Invoice no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="InvNo" id="invNo" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Date</label></div>
        <div class="pro3-proc1-g-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Data from Lot Tag</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" id="lotTagData" autocomplete="off" placeholder="prod , wo , box , qty , material">
        </div>

      </div>

      <div class="pro3-proc1-lotList">
        <div class="lotListHeader-1"><label>Lot List</label></div>
        <div class="lotListHeader-2"><label>App Check</label></div>
        <div class="lotListHeader-3"><label>Lot ID</label></div>
        <div class="lotListHeader-4"><label>Delete</label></div>
        <div><textarea id="lotListTable" class="lotListTextarea" rows="6" disabled></textarea></div>
        <div id="appCheckList" class="appCheckList"></div>
        <div id="lotIDList"></div>
        <div id="DelItem"></div>
      </div>

      <div id="lotTagHidden" style="display:none"></div>

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

    var lotTagCount = 0;
    var COL_W = 12;
    var lotRows = new Map();

    function pad(s) {
      s = (s || '').toString();
      return s.length >= COL_W ? s + ' ' : s + ' '.repeat(COL_W - s.length);
    }

    function rebuildTextarea() {
      var textarea = document.getElementById('lotListTable');
      var lines = [];
      lotRows.forEach(function (row) { lines.push(row.line); });
      textarea.value = lines.join('\n');
      textarea.rows = Math.max(6, lines.length);
    }

    document.getElementById('lotTagData').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var text = this.value.trim();
      var m = text.match(/^(\S+)\s*,\s*(\S+)\s*,\s*(\S+)\s*,\s*(\S+)\s*,\s*(\S+)$/);
      if (!m) {
        alert('invalid format');
        return;
      }

      var prodName = m[1], wo = m[2], boxNo = m[3], boxQty = m[4], material = m[5];
      var rowId = ++lotTagCount;

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

      var line = pad(prodName) + pad(wo) + pad(boxNo) + pad(boxQty) + material;
      lotRows.set(rowId, { line: line });
      rebuildTextarea();

      var appCheckRow = document.createElement('div');
      appCheckRow.className = 'appCheckRow';
      appCheckRow.innerHTML =
        '<select name="AppCheck[]" required>' +
          '<option value="" selected disabled>โปรดระบุ</option>' +
          '<option value="pass">pass</option>' +
          '<option value="fail">fail</option>' +
        '</select>';
      document.getElementById('appCheckList').appendChild(appCheckRow);

      var lotIDRow = document.createElement('div');
      lotIDRow.className = 'lotIDRow';
      var lotIDOptions = '<option value="" selected disabled>โปรดระบุ</option>';
      for (var n = 1; n <= 30; n++) {
        lotIDOptions += '<option value="' + n + '">' + n + '</option>';
      }
      lotIDRow.innerHTML = '<select name="LotID[]" required>' + lotIDOptions + '</select>';
      document.getElementById('lotIDList').appendChild(lotIDRow);

      var delRow = document.createElement('div');
      delRow.className = 'delRow';
      var delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.textContent = 'ลบ';
      delBtn.addEventListener('click', function () {
        lotRows.delete(rowId);
        hiddenDiv.remove();
        appCheckRow.remove();
        lotIDRow.remove();
        delRow.remove();
        rebuildTextarea();
      });
      delRow.appendChild(delBtn);
      document.getElementById('DelItem').appendChild(delRow);

      this.value = '';
      this.focus();
    });
  </script>
</body>
</html>
