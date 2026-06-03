<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    $lot_prodname = $lot_invno = $lot_wo = $lot_sublot = '';
    if (!empty($_SESSION['lotid'])) {
        $lstmt = mysqli_prepare($conn,
            "SELECT ProdName, InvNo, WO, SubLot FROM tb_proc1 WHERE LotID = ? LIMIT 1");
        mysqli_stmt_bind_param($lstmt, 's', $_SESSION['lotid']);
        mysqli_stmt_execute($lstmt);
        $lrow = mysqli_fetch_assoc(mysqli_stmt_get_result($lstmt));
        if ($lrow) {
            $lot_prodname = htmlspecialchars($lrow['ProdName']);
            $lot_invno    = htmlspecialchars($lrow['InvNo']);
            $lot_wo       = htmlspecialchars($lrow['WO']);
            $lot_sublot   = htmlspecialchars($lrow['SubLot']);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodName     = $_POST['ProdName'];
        $invNo        = $_POST['InvNo'];
        $wo           = $_POST['WO'];
        $subLot       = $_POST['SubLot'];
        $date         = $_POST['Date'];
        $time         = $_POST['Time'];
        $opr          = (int)$_POST['Opr'];
        $boxNo        = $_POST['BoxNo'];
        $plateNo      = (int)$_POST['PlateNo'];
        $rackNo       = (int)$_POST['RackNo'];
        $fgTotal      = (int)$_POST['FGtotal'];
        $blister      = (int)$_POST['Blister'];
        $break_       = (int)$_POST['Break'];
        $bumps        = (int)$_POST['Bumps'];
        $chip         = (int)$_POST['Chip'];
        $crack        = (int)$_POST['Crack'];
        $edgeFlowMark = (int)$_POST['EdgeFlowMark'];
        $flowMark     = (int)$_POST['FlowMark'];
        $discolor     = (int)$_POST['Discolor'];
        $contam       = (int)$_POST['Contam'];
        $dent         = (int)$_POST['Dent'];
        $scuff        = (int)$_POST['Scuff'];
        $scratch      = (int)$_POST['Scratch'];
        $stain        = (int)$_POST['Stain'];
        $exposedCu    = (int)$_POST['ExposedCu'];
        $pitting      = (int)$_POST['Pitting'];
        $finger       = (int)$_POST['Finger'];
        $deform       = (int)$_POST['Deform'];
        $incpltCNC    = (int)$_POST['IncpltCNC'];
        $kizZone      = (int)$_POST['KIZzone'];
        $ngTotal      = (int)$_POST['NGtotal'];
        $remark       = $_POST['Remark'];

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc5`
             (`ProdName`,`InvNo`,`WO`,`SubLot`,`Date`,`Time`,`Opr`,
              `BoxNo`,`PlateNo`,`RackNo`,`FGtatal`,
              `Blister`,`Break`,`Bumps`,`Chip`,`Crack`,
              `EdgeFlowMark`,`FlowMark`,`Discolor`,`Contam`,`Dent`,
              `Scuff`,`Scratch`,`Stain`,`ExposedCu`,`Pitting`,
              `Finger`,`Deform`,`IncpltCNC`,`KIZzone`,
              `NGtotal`,`Remark`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "ssssssisiiiiiiiiiiiiiiiiiiiiiis",
            $prodName, $invNo, $wo, $subLot, $date, $time, $opr,
            $boxNo, $plateNo, $rackNo, $fgTotal,
            $blister, $break_, $bumps, $chip, $crack,
            $edgeFlowMark, $flowMark, $discolor, $contam, $dent,
            $scuff, $scratch, $stain, $exposedCu, $pitting,
            $finger, $deform, $incpltCNC, $kizZone,
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
    <h2>5 Inspection — Ni-e Line 2</h2>

    <?php if (!empty($_SESSION['lotid'])): ?>
    <p style="color:#1a6e1a; font-weight:bold; font-size:0.95em;">
      Lot ID : <?php echo htmlspecialchars($_SESSION['lotid']); ?>
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

        <div class="pro3-proc1-g-it"><label>Sub lot no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="SubLot" value="<?php echo $lot_sublot; ?>" required>
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

        <div class="pro3-proc1-g-it"><label>Plate no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="PlateNo" id="f_PlateNo" min="0" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Rack no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="RackNo" id="f_RackNo" min="0" required>
        </div>

        <div class="pro3-proc1-g-it"><label>จำนวน FG (pcs)</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="FGtotal" id="f_FGtotal" min="0" required>
        </div>

        <!-- Defect section header -->
        <div class="pro3-proc1-g-it" style="grid-column:1/span 2; background-color:lightskyblue; font-weight:bold; justify-content:center; margin-top:6px;">
          Defect
        </div>

        <div class="pro3-proc1-g-it"><label>พุพอง</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Blister" id="f_Blister" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>แตกหัก</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Break" id="f_Break" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยกระแทก</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Bumps" id="f_Bumps" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>เศษกระเทาะ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Chip" id="f_Chip" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยร้าว</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Crack" id="f_Crack" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Edge Flow Mark</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="EdgeFlowMark" id="f_EdgeFlowMark" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Flow Mark</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="FlowMark" id="f_FlowMark" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>เปลี่ยนสี</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Discolor" id="f_Discolor" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>สิ่งแปลกปลอม</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Contam" id="f_Contam" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยยุบ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Dent" id="f_Dent" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยขูด</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Scuff" id="f_Scuff" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยขีดข่วน</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Scratch" id="f_Scratch" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>คราบน้ำ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Stain" id="f_Stain" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>เห็นผิวทองแดง</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="ExposedCu" id="f_ExposedCu" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Pitting</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Pitting" id="f_Pitting" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยนิ้วมือ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Finger" id="f_Finger" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>เสียรูป</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Deform" id="f_Deform" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>CNC ไม่สมบูรณ์</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="IncpltCNC" id="f_IncpltCNC" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Kiz zone</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="KIZzone" id="f_KIZzone" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it" style="background-color:lightyellow; font-weight:bold;"><label>NG รวม</label></div>
        <div class="pro3-proc1-g-it" style="background-color:lightyellow;">
          <input type="number" name="NGtotal" id="f_NGtotal" min="0" value="0" readonly required>
        </div>

        <!-- Remark -->
        <div class="pro3-proc1-g-it" style="grid-column:1/span 2; justify-content:center; margin-top:6px;">
          <label>หมายเหตุ</label>
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
      'Blister','Break','Bumps','Chip','Crack',
      'EdgeFlowMark','FlowMark','Discolor','Contam','Dent',
      'Scuff','Scratch','Stain','ExposedCu','Pitting',
      'Finger','Deform','IncpltCNC','KIZzone'
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
