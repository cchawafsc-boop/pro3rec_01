<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodName = $_POST['ProdName'];
        $invNo    = $_POST['InvNo'];
        $wo       = $_POST['WO'];
        $lotID    = $_SESSION['lotid'] ?? '';
        $process  = $_POST['Process'];
        $date     = $_POST['Date'];
        $time     = $_POST['Time'];
        $opr      = (int)$_POST['Opr'];
        $boxNo    = $_POST['BoxNo'];

        $blister  = (int)$_POST['Blister'];
        $break_   = (int)$_POST['Break'];
        $bumps    = (int)$_POST['Bumps'];
        $burrs    = (int)$_POST['Burrs'];
        $chip     = (int)$_POST['Chip'];
        $crack    = (int)$_POST['Crack'];
        $contam   = (int)$_POST['Contam'];
        $discolor = (int)$_POST['Discolor'];
        $dent     = (int)$_POST['Dent'];
        $scratch  = (int)$_POST['Scratch'];
        $scuff    = (int)$_POST['Scuff'];
        $stain    = (int)$_POST['Stain'];
        $exposed  = (int)$_POST['Exposed'];
        $pitting  = (int)$_POST['Pitting'];
        $deform   = (int)$_POST['Deform'];
        $finger   = (int)$_POST['Finger'];

        $ngTotal  = (int)$_POST['NGtotal'];
        $remark   = $_POST['Remark'];

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_ng`
             (`ProdName`,`InvNo`,`WO`,`LotID`,`Process`,`Date`,`Time`,`Opr`,`BoxNo`,
              `Blister`,`Break`,`Bumps`,`Burrs`,`Chip`,`Crack`,`Contam`,`Discolor`,`Dent`,
              `Scratch`,`Scuff`,`Stain`,`Exposed`,`Pitting`,`Deform`,`Finger`,
              `NGtotal`,`Remark`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "sssssssisiiiiiiiiiiiiiiiiis",
            $prodName, $invNo, $wo, $lotID, $process, $date, $time, $opr, $boxNo,
            $blister, $break_, $bumps, $burrs, $chip, $crack, $contam, $discolor, $dent,
            $scratch, $scuff, $stain, $exposed, $pitting, $deform, $finger,
            $ngTotal, $remark);
        $req = mysqli_stmt_execute($stmt);
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

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc1-g">

        <div class="pro3-proc1-g-it"><label>Product name</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="ProdName" value="<?php echo $lot_prodname; ?>" autofocus required>
        </div>

        <div class="pro3-proc1-g-it"><label>Invoice no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="InvNo" value="<?php echo $lot_invno; ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>WO</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="WO" value="<?php echo $lot_wo; ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Process</label></div>
        <div class="pro3-proc1-g-it">
          <select name="Process" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="1.Receiving">1. Receiving</option>
            <option value="2.Incoming">2. Incoming</option>
            <option value="3.Racking">3. Racking</option>
            <option value="4.Plating">4. Plating</option>
            <option value="5.Inspection">5. Inspection</option>
            <option value="6.QAoutgoing">6. QAoutgoing</option>
          </select>
        </div>

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

        <div class="pro3-proc1-g-it"><label>Box no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="BoxNo" id="f_BoxNo" required>
        </div>

        <!-- Defect section header -->
        <div class="pro3-proc1-g-it" style="grid-column:1/span 2; background-color:lightskyblue; font-weight:bold; justify-content:center; margin-top:6px;">
          Defect (NG)
        </div>

        <div class="pro3-proc1-g-it"><label>Blister<br>พุพอง</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Blister" id="f_Blister" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Break<br>แตกหัก</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Break" id="f_Break" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Bumps<br>รอยกระแทก</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Bumps" id="f_Bumps" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Burrs<br>ครีบ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Burrs" id="f_Burrs" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Chip<br>เศษกระเทาะ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Chip" id="f_Chip" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Crack<br>รอยร้าว</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Crack" id="f_Crack" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Contam<br>สิ่งแปลกปลอม</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Contam" id="f_Contam" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Discolor<br>เปลี่ยนสี</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Discolor" id="f_Discolor" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Dent<br>รอยยุบ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Dent" id="f_Dent" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Scratch<br>รอยขีดข่วน</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Scratch" id="f_Scratch" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Scuff<br>รอยขูด</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Scuff" id="f_Scuff" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Stain<br>คราบ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Stain" id="f_Stain" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Exposed<br>เห็นผิวใน</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Exposed" id="f_Exposed" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Pitting<br>รูพรุน</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Pitting" id="f_Pitting" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Deform<br>เสียรูป</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Deform" id="f_Deform" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Finger<br>รอยนิ้วมือ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Finger" id="f_Finger" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it" style="background-color:lightyellow; font-weight:bold;"><label>NG รวม</label></div>
        <div class="pro3-proc1-g-it" style="background-color:lightyellow;">
          <input type="number" name="NGtotal" id="f_NGtotal" min="0" value="0" readonly required>
        </div>

        <!-- Remark -->
        <div class="pro3-proc1-g-it" style="grid-column:1/span 2; justify-content:center; margin-top:6px;">
          <label>-หมายเหตุ-</label>
        </div>
        <div class="pro3-proc1-g-it" style="grid-column:1/span 2; justify-content:center;">
          <textarea name="Remark" rows="3" style="width:270px;"></textarea>
        </div>

      </div>

      <p style="display:flex; justify-content:space-between; padding:0 10px;">
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
        <button type="submit" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php if (!isset($req)) { mysqli_close($conn); } ?>

  <script>
    const defectFields = [
      'Blister','Break','Bumps','Burrs','Chip','Crack',
      'Contam','Discolor','Dent','Scratch','Scuff','Stain',
      'Exposed','Pitting','Deform','Finger'
    ];
    function calcNG() {
      let total = 0;
      defectFields.forEach(function(name) {
        total += parseInt(document.getElementById('f_' + name).value) || 0;
      });
      document.getElementById('f_NGtotal').value = total;
    }
  </script>
</body>
</html>
