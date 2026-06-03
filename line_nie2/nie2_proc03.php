<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prodName = $_POST['ProdName'];
        $invNo    = $_POST['InvNo'];
        $wo       = $_POST['WO'];
        $subLot   = $_POST['SubLot'];
        $date     = $_POST['Date'];
        $time     = $_POST['Time'];
        $opr      = (int)$_POST['Opr'];
        $boxNo    = $_POST['BoxNo'];
        $plateNo  = (int)$_POST['PlateNo'];
        $rackNo   = (int)$_POST['RackNo'];
        $qty      = (int)$_POST['Qty'];

        $stmt = mysqli_prepare($conn,
            "INSERT INTO `tb_proc3` (`ProdName`,`InvNo`,`WO`,`SubLot`,`Date`,`Time`,`Opr`,`BoxNo`,`PlateNo`,`RackNo`,`Qty`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "ssssssisiii",
            $prodName, $invNo, $wo, $subLot, $date, $time, $opr, $boxNo, $plateNo, $rackNo, $qty);
        $req = mysqli_stmt_execute($stmt);
        mysqli_close($conn);

        if ($req) {
            $qs = http_build_query([
                'ProdName' => $prodName,
                'InvNo'    => $invNo,
                'WO'       => $wo,
                'SubLot'   => $subLot,
            ]);
            header('Location: ./nie2_proc3.php?' . $qs);
            exit;
        } else {
            echo "<script>alert('บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่');</script>";
        }
    }

    $from_submit = isset($_GET['ProdName']);
    if ($from_submit) {
        $pre_prodname = htmlspecialchars($_GET['ProdName'] ?? '');
        $pre_invno    = htmlspecialchars($_GET['InvNo']    ?? '');
        $pre_wo       = htmlspecialchars($_GET['WO']       ?? '');
        $pre_sublot   = htmlspecialchars($_GET['SubLot']   ?? '');
    } else {
        $pre_prodname = $pre_invno = $pre_wo = $pre_sublot = '';
        if (!empty($_SESSION['lotid'])) {
            $lstmt = mysqli_prepare($conn,
                "SELECT ProdName, InvNo, WO, SubLot FROM tb_proc1 WHERE LotID = ? LIMIT 1");
            mysqli_stmt_bind_param($lstmt, 's', $_SESSION['lotid']);
            mysqli_stmt_execute($lstmt);
            $lrow = mysqli_fetch_assoc(mysqli_stmt_get_result($lstmt));
            if ($lrow) {
                $pre_prodname = htmlspecialchars($lrow['ProdName']);
                $pre_invno    = htmlspecialchars($lrow['InvNo']);
                $pre_wo       = htmlspecialchars($lrow['WO']);
                $pre_sublot   = htmlspecialchars($lrow['SubLot']);
            }
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

  <div class="form-pro3-proc1">
    <h2>3 Racking — Ni-e Line 2</h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
      <div class="form-pro3-proc1-g">

        <div class="pro3-proc1-g-it"><label>Product name</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="ProdName" value="<?php echo $pre_prodname; ?>"
            <?php if (!$from_submit) echo 'autofocus'; ?> required>
        </div>

        <div class="pro3-proc1-g-it"><label>Invoice no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="InvNo" value="<?php echo $pre_invno; ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>WO</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="WO" value="<?php echo $pre_wo; ?>" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Sub lot no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="text" name="SubLot" value="<?php echo $pre_sublot; ?>" required>
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
          <input type="text" name="BoxNo" id="f_BoxNo"
            <?php if ($from_submit) echo 'autofocus'; ?> required>
        </div>

        <div class="pro3-proc1-g-it"><label>Plate no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="PlateNo" min="0" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Rack no</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="RackNo" min="0" required>
        </div>

        <div class="pro3-proc1-g-it"><label>Q'ty</label></div>
        <div class="pro3-proc1-g-it">
          <input type="number" name="Qty" min="0" required>
        </div>

      </div>

      <p>
        <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้า<br>Ni-e line 2</button>
        <button type="submit" id="okBtn">บันทึกค่า<br>เข้าระบบ</button>
      </p>
    </form>
  </div>

  <?php if (!isset($req)) { mysqli_close($conn); } ?>
</body>
</html>
