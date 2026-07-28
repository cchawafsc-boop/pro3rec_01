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

    <p style="display:flex; justify-content:center; padding:0 10px;">
      <button type="button" id="Nie2_homeBtn" onclick="window.history.back();">กลับหน้าก่อน</button>
    </p>
  </div>

  <?php mysqli_close($conn); ?>
</body>
</html>
