<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // Collect all distinct lots ordered by latest activity
    $sql_lots = "
        SELECT ProdName, InvNo, WO, SubLot, MAX(Date) AS MaxDate
        FROM (
            SELECT ProdName, InvNo, WO, SubLot, Date FROM tb_proc1
            UNION ALL
            SELECT ProdName, InvNo, WO, SubLot, Date FROM tb_proc2
            UNION ALL
            SELECT ProdName, InvNo, WO, SubLot, Date FROM tb_proc3
        ) AS combined
        GROUP BY ProdName, InvNo, WO, SubLot
        ORDER BY MaxDate DESC, SubLot DESC";

    $res_lots = mysqli_query($conn, $sql_lots);
    $lots = [];
    while ($row = mysqli_fetch_assoc($res_lots)) {
        $lots[] = $row;
    }
    $total = count($lots);

    $idx     = isset($_GET['idx']) ? max(0, min((int)$_GET['idx'], $total - 1)) : 0;
    $current = $total > 0 ? $lots[$idx] : null;

    $proc1_rows = [];
    $proc2_rows = [];
    $proc3_rows = [];

    if ($current) {
        $pn  = $current['ProdName'];
        $inv = $current['InvNo'];
        $wo  = $current['WO'];
        $sl  = $current['SubLot'];

        $stmt1 = mysqli_prepare($conn,
            "SELECT * FROM tb_proc1 WHERE ProdName=? AND InvNo=? AND WO=? AND SubLot=? ORDER BY Date, Time");
        mysqli_stmt_bind_param($stmt1, 'ssss', $pn, $inv, $wo, $sl);
        mysqli_stmt_execute($stmt1);
        $r1 = mysqli_stmt_get_result($stmt1);
        while ($row = mysqli_fetch_assoc($r1)) { $proc1_rows[] = $row; }

        $stmt2 = mysqli_prepare($conn,
            "SELECT * FROM tb_proc2 WHERE ProdName=? AND InvNo=? AND WO=? AND SubLot=? ORDER BY Date, Time");
        mysqli_stmt_bind_param($stmt2, 'ssss', $pn, $inv, $wo, $sl);
        mysqli_stmt_execute($stmt2);
        $r2 = mysqli_stmt_get_result($stmt2);
        while ($row = mysqli_fetch_assoc($r2)) { $proc2_rows[] = $row; }

        $stmt3 = mysqli_prepare($conn,
            "SELECT * FROM tb_proc3 WHERE ProdName=? AND InvNo=? AND WO=? AND SubLot=? ORDER BY Date, Time");
        mysqli_stmt_bind_param($stmt3, 'ssss', $pn, $inv, $wo, $sl);
        mysqli_stmt_execute($stmt3);
        $r3 = mysqli_stmt_get_result($stmt3);
        while ($row = mysqli_fetch_assoc($r3)) { $proc3_rows[] = $row; }
    }

    mysqli_close($conn);

    $prev_idx = $idx - 1;
    $next_idx = $idx + 1;
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0">
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
  <style>
    .lcard-wrap {
      text-align: center;
      padding-bottom: 20px;
    }
    .lcard-nav {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px;
      margin: 10px 0;
    }
    .lcard-nav button {
      padding: 6px 18px;
      border-radius: 4px;
      border: none;
      background-color: mediumblue;
      color: white;
      font-size: 1em;
      cursor: pointer;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .lcard-nav button:disabled {
      background-color: #aaa;
      cursor: default;
    }
    .lcard-nav .lcard-counter {
      font-weight: bold;
      min-width: 90px;
    }
    .lcard-header {
      display: inline-grid;
      grid-template-columns: 110px 180px;
      gap: 2px 8px;
      text-align: left;
      margin: 8px auto;
      background: lightyellow;
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 8px 14px;
    }
    .lcard-header .lh-label { font-weight: bold; }
    .lcard-section {
      margin: 10px 4px 4px 4px;
      overflow-x: auto;
    }
    .lcard-section h3 {
      margin: 6px 0 4px 0;
      background-color: lightskyblue;
      padding: 4px 8px;
      border-radius: 4px;
      display: inline-block;
    }
    .lcard-section table {
      margin: 0 auto;
      border-collapse: collapse;
      font-size: 0.88em;
    }
    .lcard-section th, .lcard-section td {
      padding: 3px 7px;
      border: 1px solid #bbb;
      white-space: nowrap;
    }
    .lcard-section th {
      background-color: lightskyblue;
    }
    .lcard-section .no-data {
      color: #888;
      font-style: italic;
      font-size: 0.9em;
      margin: 4px 0;
    }
    .lcard-back {
      margin: 16px 0 8px 0;
    }
    .lcard-back button {
      padding: 8px 24px;
      border-radius: 4px;
      border: none;
      background-color: blue;
      color: white;
      font-size: 1em;
      cursor: pointer;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .lcard-empty {
      margin: 30px;
      color: #888;
    }
  </style>
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="lcard-wrap">
    <h2>ประวัติ Lot Card — Ni-e Line 2</h2>

    <?php if ($total === 0): ?>
      <p class="lcard-empty">ยังไม่มีข้อมูล</p>
    <?php else: ?>

      <!-- Navigation -->
      <div class="lcard-nav">
        <button onclick="window.location.href='?idx=<?php echo $prev_idx; ?>'"
          <?php if ($idx <= 0) echo 'disabled'; ?>>&#8592; ก่อนหน้า</button>
        <span class="lcard-counter"><?php echo ($idx + 1); ?> / <?php echo $total; ?></span>
        <button onclick="window.location.href='?idx=<?php echo $next_idx; ?>'"
          <?php if ($idx >= $total - 1) echo 'disabled'; ?>>ถัดไป &#8594;</button>
      </div>

      <!-- Lot header info -->
      <div class="lcard-header">
        <span class="lh-label">Product name</span>
        <span><?php echo htmlspecialchars($current['ProdName']); ?></span>
        <span class="lh-label">Invoice no</span>
        <span><?php echo htmlspecialchars($current['InvNo']); ?></span>
        <span class="lh-label">WO</span>
        <span><?php echo htmlspecialchars($current['WO']); ?></span>
        <span class="lh-label">Sub lot no</span>
        <span><?php echo htmlspecialchars($current['SubLot']); ?></span>
      </div>

      <!-- 1 Receiving -->
      <div class="lcard-section">
        <h3>1 Receiving</h3>
        <?php if (empty($proc1_rows)): ?>
          <p class="no-data">— ไม่มีข้อมูล —</p>
        <?php else: ?>
          <table>
            <tr>
              <th>Date</th><th>Time</th><th>Opr</th>
              <th>App Check</th><th>Box Qty</th><th>Box Judge</th>
            </tr>
            <?php foreach ($proc1_rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['Date']); ?></td>
              <td><?php echo htmlspecialchars($r['Time']); ?></td>
              <td><?php echo htmlspecialchars($r['Opr']); ?></td>
              <td><?php echo htmlspecialchars($r['AppCheck']); ?></td>
              <td><?php echo htmlspecialchars($r['BoxQty']); ?></td>
              <td><?php echo htmlspecialchars($r['BoxJudge']); ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

      <!-- 2 Incoming -->
      <div class="lcard-section">
        <h3>2 Incoming</h3>
        <?php if (empty($proc2_rows)): ?>
          <p class="no-data">— ไม่มีข้อมูล —</p>
        <?php else: ?>
          <table>
            <tr>
              <th>Date</th><th>Time</th><th>Opr</th>
              <th>Box Cond.</th><th>Amt Inv</th><th>Sampling</th>
              <th>NG Total</th><th>Remark</th>
            </tr>
            <?php foreach ($proc2_rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['Date']); ?></td>
              <td><?php echo htmlspecialchars($r['Time']); ?></td>
              <td><?php echo htmlspecialchars($r['Opr']); ?></td>
              <td><?php echo htmlspecialchars($r['BoxCondition']); ?></td>
              <td><?php echo htmlspecialchars($r['AmountInv']); ?></td>
              <td><?php echo htmlspecialchars($r['SamplingSize']); ?></td>
              <td><?php echo htmlspecialchars($r['NGtotal']); ?></td>
              <td><?php echo htmlspecialchars($r['Remark']); ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

      <!-- 3 Racking -->
      <div class="lcard-section">
        <h3>3 Racking</h3>
        <?php if (empty($proc3_rows)): ?>
          <p class="no-data">— ไม่มีข้อมูล —</p>
        <?php else: ?>
          <table>
            <tr>
              <th>Date</th><th>Time</th><th>Opr</th>
              <th>Box No</th><th>Plate No</th><th>Rack No</th><th>Qty</th>
            </tr>
            <?php foreach ($proc3_rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['Date']); ?></td>
              <td><?php echo htmlspecialchars($r['Time']); ?></td>
              <td><?php echo htmlspecialchars($r['Opr']); ?></td>
              <td><?php echo htmlspecialchars($r['BoxNo']); ?></td>
              <td><?php echo htmlspecialchars($r['PlateNo']); ?></td>
              <td><?php echo htmlspecialchars($r['RackNo']); ?></td>
              <td><?php echo htmlspecialchars($r['Qty']); ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

    <?php endif; ?>

    <!-- Back button -->
    <div class="lcard-back">
      <button onclick="window.location.href='./nie2_index.php'">กลับหน้า Ni-e line 2</button>
    </div>
  </div>

</body>
</html>
