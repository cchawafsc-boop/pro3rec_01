<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // Stage labels, keyed by the process number that owns each table.
    $stageNames = [
        1 => '1. Receiving',
        2 => '2. Incoming',
        3 => '3. Racking',
        4 => '4. Plating',
        5 => '5. Unracking',
        6 => '6. Inspection',
        7 => '7. QA Outgoing',
    ];

    // A lot is "outstanding" if it was received at proc1 but has no matching
    // proc7 record yet (ProdName+InvNo+WO is the join key used everywhere
    // else in this codebase). Its current stage is the furthest proc2-6
    // table that already has a matching row.
    $sql = "
        SELECT
            p1.ProdName, p1.InvNo, p1.WO,
            GROUP_CONCAT(DISTINCT p1.LotID ORDER BY p1.LotID SEPARATOR ', ') AS LotIDs,
            GROUP_CONCAT(DISTINCT p1.BoxNo ORDER BY p1.BoxNo SEPARATOR ', ') AS BoxNos,
            MIN(p1.Date) AS RecvDate,
            EXISTS (SELECT 1 FROM tb_proc2 t2 WHERE t2.ProdName=p1.ProdName AND t2.InvNo=p1.InvNo AND t2.WO=p1.WO) AS has2,
            EXISTS (SELECT 1 FROM tb_proc3 t3 WHERE t3.ProdName=p1.ProdName AND t3.InvNo=p1.InvNo AND t3.WO=p1.WO) AS has3,
            EXISTS (SELECT 1 FROM tb_proc4 t4 WHERE t4.ProdName=p1.ProdName AND t4.InvNo=p1.InvNo AND t4.WO=p1.WO) AS has4,
            EXISTS (SELECT 1 FROM tb_proc5 t5 WHERE t5.ProdName=p1.ProdName AND t5.InvNo=p1.InvNo AND t5.WO=p1.WO) AS has5,
            EXISTS (SELECT 1 FROM tb_proc6 t6 WHERE t6.ProdName=p1.ProdName AND t6.InvNo=p1.InvNo AND t6.WO=p1.WO) AS has6
        FROM tb_proc1 p1
        WHERE NOT EXISTS (
            SELECT 1 FROM tb_proc7 t7
            WHERE t7.ProdName=p1.ProdName AND t7.InvNo=p1.InvNo AND t7.WO=p1.WO
        )
        GROUP BY p1.ProdName, p1.InvNo, p1.WO
        ORDER BY RecvDate ASC";

    $res = mysqli_query($conn, $sql);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $stage = 1;
        foreach ([2, 3, 4, 5, 6] as $n) {
            if ($row["has$n"]) { $stage = $n; }
        }
        $row['CurrentStage'] = $stageNames[$stage];
        $row['WaitingFor']   = $stageNames[min($stage + 1, 7)];
        $rows[] = $row;
    }
    $total = count($rows);

    mysqli_close($conn);
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0">
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
  <style>
    .track-wrap {
      text-align: center;
      padding-bottom: 20px;
    }
    .track-count {
      font-weight: bold;
      color: #1a6e1a;
      margin: 4px 0 10px 0;
    }
    .track-section {
      margin: 10px 4px 4px 4px;
      overflow-x: auto;
    }
    .track-table {
      margin: 0 auto;
      border-collapse: collapse;
      font-size: 0.9em;
    }
    .track-table th,
    .track-table td {
      padding: 5px 10px;
      white-space: nowrap;
    }
    .track-table th {
      background-color: lightskyblue;
    }
    .track-table td.waiting-for {
      color: #b35900;
      font-weight: bold;
    }
    .track-empty {
      margin: 20px 0;
      font-weight: bold;
      color: #1a6e1a;
    }
  </style>
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="form-pro3-proc1 track-wrap">
    <h2>ติดตามสถานะ Lot — Ni-e Line 2</h2>
    <p class="track-count">รับเข้า (Process 1) แต่ยังไม่จบ QA Outgoing (Process 7) : <?php echo $total; ?> lot</p>

    <?php if ($total === 0): ?>
    <p class="track-empty">ไม่มี Lot ค้างอยู่ในระบบ</p>
    <?php else: ?>
    <div class="track-section">
      <table class="track-table">
        <tr>
          <th>Lot ID</th>
          <th>Box-no</th>
          <th>Product name</th>
          <th>Invoice no</th>
          <th>WO</th>
          <th>วันที่รับเข้า</th>
          <th>ผ่านล่าสุด</th>
          <th>กำลังรอ</th>
        </tr>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['LotIDs']); ?></td>
          <td><?php echo htmlspecialchars($r['BoxNos']); ?></td>
          <td><?php echo htmlspecialchars($r['ProdName']); ?></td>
          <td><?php echo htmlspecialchars($r['InvNo']); ?></td>
          <td><?php echo htmlspecialchars($r['WO']); ?></td>
          <td><?php echo htmlspecialchars($r['RecvDate']); ?></td>
          <td><?php echo htmlspecialchars($r['CurrentStage']); ?></td>
          <td class="waiting-for"><?php echo htmlspecialchars($r['WaitingFor']); ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <p>
      <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2</button>
    </p>
  </div>
</body>
</html>
