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

        $invNo    = $_POST['InvNo'];
        $date     = $_POST['Date'];
        $time     = $_POST['Time'];
        $opr      = $_POST['Opr'];
        $appCheck = $_POST['AppCheck'];
        $boxJudge = $_POST['BoxJudge'];
        $lotID    = $_POST['LotID'];
        $lotIDFull= $lotID."_".$date."_".$time;
        $done_f   = 'no';

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc1` (`ProdName`,`InvNo`,`WO`,`BoxNo`,`Materials`,`Date`,`Time`,`Opr`,`AppCheck`,`BoxQty`,`BoxJudge`,`LotID`,`DoneFlag`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "sssssssssisss", $prodName, $invNo, $wo, $boxNo, $material, $date, $time, $opr, $appCheck, $boxQty, $boxJudge, $lotIDFull, $done_f);

        $req = true;
        for ($i = 0; $i < count($prodNames); $i++) {
            $prodName = $prodNames[$i];
            $wo       = $wos[$i];
            $boxNo    = $boxNos[$i];
            $boxQty   = (int)$boxQtys[$i];
            $material = $materials[$i];
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

        <div class="pro3-proc1-g-it"><label>Invoice no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="InvNo" autofocus required>
        </div>

        <div class="pro3-proc1-g-it"><label>Data from Lot Tag</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" id="lotTagData" autocomplete="off">
        </div>

        <div id="lotTagBlocks" style="display:contents"></div>

        <div class="pro3-proc1-g-it"><label>Date</label></div>
        <div class="pro3-proc1-g-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Time</label></div>
        <div class="pro3-proc1-g-it">
          <input type="time" name="Time" value="<?php echo date('H:i'); ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Operator</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" readonly required>
        </div>

        <div class="pro3-proc1-g-it"><label>App check</label></div>
        <div class="pro3-proc1-g-it">
          <select name="AppCheck" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="OK">OK</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <div class="pro3-proc1-g-it"><label>Box judge</label></div>
        <div class="pro3-proc1-g-it">
          <select name="BoxJudge" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="OK">OK</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <div class="pro3-proc1-g-it"><label>Lot ID</label></div>
        <div class="pro3-proc1-g-it">
          <select name="LotID" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="L01">L01</option>
            <option value="L02">L02</option>
            <option value="L03">L03</option>
            <option value="L04">L04</option>
            <option value="L05">L05</option>
            <option value="L06">L06</option>
            <option value="L07">L07</option>
            <option value="L08">L08</option>
            <option value="L09">L09</option>
            <option value="L10">L10</option>
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

  <script>
    var lotTagCount = 0;

    document.getElementById('lotTagData').addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      var parts = this.value.split(',');
      var fields = [
        { label: "Product name", name: "ProdName[]" },
        { label: "WO",           name: "WO[]" },
        { label: "Box no",       name: "BoxNo[]" },
        { label: "Q'ty",         name: "BoxQty[]" },
        { label: "Materials",    name: "Materials[]" }
      ];

      lotTagCount++;

      var container = document.getElementById('lotTagBlocks');

      var titleDiv = document.createElement('div');
      titleDiv.className = 'pro3-proc1-g-it';
      titleDiv.innerHTML = '<label><b>data ' + lotTagCount + '</b></label>';
      container.appendChild(titleDiv);

      var titleSpacer = document.createElement('div');
      titleSpacer.className = 'pro3-proc1-g-it';
      container.appendChild(titleSpacer);

      fields.forEach(function (f, i) {
        var labelDiv = document.createElement('div');
        labelDiv.className = 'pro3-proc1-g-it';
        labelDiv.innerHTML = '<label>' + f.label + '</label>';

        var inputDiv = document.createElement('div');
        inputDiv.className = 'pro3-proc1-g-it';
        var input = document.createElement('input');
        input.type = 'text';
        input.name = f.name;
        input.required = true;
        input.value = (parts[i] || '').trim();
        inputDiv.appendChild(input);

        container.appendChild(labelDiv);
        container.appendChild(inputDiv);
      });

      this.value = '';
    });
  </script>
</body>
</html>
