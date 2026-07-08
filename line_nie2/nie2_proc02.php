<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    function calcSamplingSize($n) {
        if ($n >= 2   && $n <= 8)    return 2;
        if ($n >= 9   && $n <= 15)   return 3;
        if ($n >= 16  && $n <= 25)   return 5;
        if ($n >= 26  && $n <= 50)   return 8;
        if ($n >= 51  && $n <= 90)   return 13;
        if ($n >= 91  && $n <= 150)  return 20;
        if ($n >= 151 && $n <= 280)  return 32;
        if ($n >= 281 && $n <= 500)  return 50;
        if ($n >= 501 && $n <= 1000) return 80;
        return 0;
    }

    $lot_prodname = $lot_invno = $lot_wo = '';
    $lot_boxcount = 0;
    $lot_amountinv = 0;
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

        $cstmt = mysqli_prepare($conn,
            "SELECT COUNT(*) AS boxCount, COALESCE(SUM(BoxQty),0) AS totalQty FROM tb_proc1 WHERE LotID = ?");
        mysqli_stmt_bind_param($cstmt, 's', $_SESSION['lotid']);
        mysqli_stmt_execute($cstmt);
        $crow = mysqli_fetch_assoc(mysqli_stmt_get_result($cstmt));
        if ($crow) {
            $lot_boxcount  = (int)$crow['boxCount'];
            $lot_amountinv = (int)$crow['totalQty'];
        }
    }
    $lot_samplingsize = calcSamplingSize($lot_amountinv);

    $incChkBox_qty = $lot_samplingsize > 0 ? (int)ceil($lot_amountinv / $lot_samplingsize) : 0;

    $lot_boxnos = [];
    if (!empty($_SESSION['lotid']) && $incChkBox_qty > 0) {
        $bstmt = mysqli_prepare($conn,
            "SELECT BoxNo FROM tb_proc1 WHERE LotID = ? LIMIT ?");
        mysqli_stmt_bind_param($bstmt, 'si', $_SESSION['lotid'], $incChkBox_qty);
        mysqli_stmt_execute($bstmt);
        $bres = mysqli_stmt_get_result($bstmt);
        while ($brow = mysqli_fetch_assoc($bres)) {
            $lot_boxnos[] = $brow['BoxNo'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodName      = $_POST['ProdName'];
        $invNo         = $_POST['InvNo'];
        $wo            = $_POST['WO'];
        $subLot        = $_POST['SubLot'];
        $date          = $_POST['Date'];
        $time          = $_POST['Time'];
        $opr           = (int)$_POST['Opr'];
        $boxCondition  = $_POST['BoxCondition'];
        $amountInv     = (int)$_POST['AmountInv'];
        $samplingSize  = (int)$_POST['SamplingSize'];
        $break         = (int)$_POST['Break'];
        $bumps         = (int)$_POST['Bumps'];
        $burrs         = (int)$_POST['Burrs'];
        $chip          = (int)$_POST['Chip'];
        $crack         = (int)$_POST['Crack'];
        $contam        = (int)$_POST['Contam'];
        $dent          = (int)$_POST['Dent'];
        $scratch       = (int)$_POST['Scratch'];
        $scuff         = (int)$_POST['Scuff'];
        $stain         = (int)$_POST['Stain'];
        $deform        = (int)$_POST['Deform'];
        $finger        = (int)$_POST['Finger'];
        $ngTotal       = (int)$_POST['NGtotal'];
        $remark        = $_POST['Remark'];

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc2`
             (`ProdName`,`InvNo`,`WO`,`SubLot`,`Date`,`Time`,`Opr`,
              `BoxCondition`,`AmountInv`,`SamplingSize`,
              `Break`,`Bumps`,`Burrs`,`Chip`,`Crack`,`Contam`,
              `Dent`,`Scratch`,`Scuff`,`Stain`,`Deform`,`Finger`,
              `NGtotal`,`Remark`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "ssssssissiiiiiiiiiiiiiis",
            $prodName, $invNo, $wo, $subLot, $date, $time, $opr,
            $boxCondition, $amountInv, $samplingSize,
            $break, $bumps, $burrs, $chip, $crack, $contam,
            $dent, $scratch, $scuff, $stain, $deform, $finger,
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
    <h2>2 Incoming — Ni-e Line 2</h2>
      
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc2-g1">

        <div class="pro3-proc2-g1-it"><label>Lot ID</label></div>
        <div class="pro3-proc2-g1-it" style="font-size:0.8em"><label>
          <?php 
            if (!empty($_SESSION['lotid'])):
              echo htmlspecialchars($_SESSION['lotid']);
            else:
              echo "กรุณาเลือก Lot ID";
            endif;
          ?></label>
        </div>

        <div class="pro3-proc2-g1-it"><label>Operator</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="Opr" value="<?php echo htmlspecialchars($_SESSION['us_id'] ?? ''); ?>" disabled required>
        </div>
          
        <div class="pro3-proc2-g1-it"><label>Product name</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_prodname; ?>" autofocus disabled>
          <input type="hidden" name="ProdName" value="<?php echo $lot_prodname; ?>">
        </div>

        <div class="pro3-proc2-g1-it"><label>Invoice no</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_invno; ?>" disabled>
          <input type="hidden" name="InvNo" value="<?php echo $lot_invno; ?>">
        </div>

        <div class="pro3-proc2-g1-it"><label>WO</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="text" value="<?php echo $lot_wo; ?>" disabled>
          <input type="hidden" name="WO" value="<?php echo $lot_wo; ?>">
        </div>

        <div class="pro3-proc2-g1-it"><label>Date</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="date" name="Date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนตาม Inv (box)</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" value="<?php echo $lot_boxcount; ?>" disabled>
        </div>
            
        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนตาม Inv (pcs)</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="AmountInv" value="<?php echo $lot_amountinv; ?>" min="0" disabled required>
        </div>

        <div class="pro3-proc2-g1-it" style="font-size:0.8em;"><label>จำนวนสุ่ม (pcs)</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="number" name="SamplingSize" value="<?php echo $lot_samplingsize; ?>" min="0" disabled required>
        </div>

        <div class="pro3-proc2-g1-it"><label>Box no</label></div>
        <div class="pro3-proc2-g1-it">
          <select name="SubLot" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <?php foreach ($lot_boxnos as $boxNo): ?>
              <option value="<?php echo htmlspecialchars($boxNo); ?>"><?php echo htmlspecialchars($boxNo); ?></option>
            <?php endforeach; ?>
          </select>
        </div>


        <div class="pro3-proc2-g1-it"><label>Time</label></div>
        <div class="pro3-proc2-g1-it">
          <input type="time" name="Time" value="<?php echo date('H:i'); ?>" required>
        </div>

        <div class="pro3-proc2-g1-it"><label>Box check</label></div>
        <div class="pro3-proc2-g1-it">
          <select name="BoxCondition" required>
            <option value="" selected disabled>โปรดระบุ</option>
            <option value="OK">OK</option>
            <option value="FAIL">FAIL</option>
          </select>
        </div>

        <!-- Defect section header -->
        <div class="pro3-proc1-g-it" style="grid-column:1/span 2; background-color:lightskyblue; font-weight:bold; justify-content:center; margin-top:6px;">
          Defect
        </div>

        <div class="pro3-proc1-g-it"><label>แตกหัก</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Break" id="f_Break" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยกระแทก</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Bumps" id="f_Bumps" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>เศษเสี้ยน</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Burrs" id="f_Burrs" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>เศษกระเทาะ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Chip" id="f_Chip" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยร้าว</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Crack" id="f_Crack" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>สิ่งแปลกปลอม</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Contam" id="f_Contam" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยยุบ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Dent" id="f_Dent" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยขีดข่วน</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Scratch" id="f_Scratch" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยขูด</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Scuff" id="f_Scuff" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>คราบน้ำ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Stain" id="f_Stain" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>เสียรูป</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Deform" id="f_Deform" min="0" value="0" oninput="calcNG()" required>
        </div>

        <div class="pro3-proc1-g-it"><label>รอยนิ้วมือ</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Finger" id="f_Finger" min="0" value="0" oninput="calcNG()" required>
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
    const defectFields = ['Break','Bumps','Burrs','Chip','Crack','Contam','Dent','Scratch','Scuff','Stain','Deform','Finger'];
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
