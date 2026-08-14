<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // Filters (all optional, GET so the page is bookmarkable/shareable)
    $fProdName = trim($_GET['prodname'] ?? '');
    $fInvNo    = trim($_GET['invno']    ?? '');
    $fWO       = trim($_GET['wo']       ?? '');

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
    // table that already has a matching row. Grouped by LotID: one row per
    // Lot ID, all its BoxNos rolled up for the "all" collapsed view.
    $where = [
        "NOT EXISTS (SELECT 1 FROM tb_proc7 t7 WHERE t7.ProdName=p1.ProdName AND t7.InvNo=p1.InvNo AND t7.WO=p1.WO)"
    ];
    $bindTypes  = '';
    $bindValues = [];

    if ($fProdName !== '') { $where[] = "p1.ProdName LIKE ?"; $bindTypes .= 's'; $bindValues[] = '%' . $fProdName . '%'; }
    if ($fInvNo    !== '') { $where[] = "p1.InvNo LIKE ?";    $bindTypes .= 's'; $bindValues[] = '%' . $fInvNo    . '%'; }
    if ($fWO       !== '') { $where[] = "p1.WO LIKE ?";       $bindTypes .= 's'; $bindValues[] = '%' . $fWO       . '%'; }

    $sql = "
        SELECT
            p1.LotID, p1.ProdName, p1.InvNo, p1.WO,
            GROUP_CONCAT(DISTINCT p1.BoxNo ORDER BY p1.BoxNo SEPARATOR ',') AS BoxNos,
            MIN(p1.Date) AS RecvDate,
            EXISTS (SELECT 1 FROM tb_proc2 t2 WHERE t2.ProdName=p1.ProdName AND t2.InvNo=p1.InvNo AND t2.WO=p1.WO) AS has2,
            EXISTS (SELECT 1 FROM tb_proc3 t3 WHERE t3.ProdName=p1.ProdName AND t3.InvNo=p1.InvNo AND t3.WO=p1.WO) AS has3,
            EXISTS (SELECT 1 FROM tb_proc4 t4 WHERE t4.ProdName=p1.ProdName AND t4.InvNo=p1.InvNo AND t4.WO=p1.WO) AS has4,
            EXISTS (SELECT 1 FROM tb_proc5 t5 WHERE t5.ProdName=p1.ProdName AND t5.InvNo=p1.InvNo AND t5.WO=p1.WO) AS has5,
            EXISTS (SELECT 1 FROM tb_proc6 t6 WHERE t6.ProdName=p1.ProdName AND t6.InvNo=p1.InvNo AND t6.WO=p1.WO) AS has6
        FROM tb_proc1 p1
        WHERE " . implode(' AND ', $where) . "
        GROUP BY p1.LotID, p1.ProdName, p1.InvNo, p1.WO
        ORDER BY RecvDate ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($bindTypes !== '') {
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $stage = 1;
        foreach ([2, 3, 4, 5, 6] as $n) {
            if ($row["has$n"]) { $stage = $n; }
        }
        $row['CurrentStage'] = $stageNames[$stage];
        $row['WaitingFor']   = $stageNames[min($stage + 1, 7)];
        $row['BoxNoList']    = explode(',', $row['BoxNos']);
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
    .track-filter {
      display: inline-flex;
      flex-wrap: wrap;
      gap: 10px 16px;
      align-items: flex-end;
      justify-content: center;
      background: lightyellow;
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 10px 16px;
      margin: 10px auto;
    }
    .track-filter div {
      display: flex;
      flex-direction: column;
      text-align: left;
      font-size: 0.9em;
    }
    .track-filter label { font-weight: bold; margin-bottom: 2px; }
    .track-filter button {
      padding: 6px 18px;
      border-radius: 4px;
      border: none;
      background-color: mediumblue;
      color: white;
      cursor: pointer;
      height: 30px;
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
    .track-boxno {
      cursor: pointer;
      text-decoration: underline dotted;
      color: mediumblue;
    }
    .track-boxno::before {
      content: "\25B6  ";
      font-size: 0.8em;
    }
    .track-boxno.expanded::before {
      content: "\25BC  ";
    }
    .track-box-row {
      background-color: #fafafa;
      display: none;
    }
    .track-box-row td {
      text-align: left;
      color: #555;
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

    <form class="track-filter" method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
      <div>
        <label>Product name</label>
        <input type="text" name="prodname" value="<?php echo htmlspecialchars($fProdName); ?>">
      </div>
      <div>
        <label>Invoice no</label>
        <input type="text" name="invno" value="<?php echo htmlspecialchars($fInvNo); ?>">
      </div>
      <div>
        <label>WO</label>
        <input type="text" name="wo" value="<?php echo htmlspecialchars($fWO); ?>">
      </div>
      <div>
        <button type="submit">ค้นหา</button>
      </div>
    </form>

    <?php if ($total === 0): ?>
    <p class="track-empty">ไม่มี Lot ค้างอยู่ในระบบ</p>
    <?php else: ?>
    <div class="track-section">
      <table class="track-table">
        <tr>
          <th>Lot ID</th>
          <th>Product name</th>
          <th>Invoice no</th>
          <th>WO</th>
          <th>วันที่รับเข้า</th>
          <th>ผ่านล่าสุด</th>
          <th>กำลังรอ</th>
          <th>Box-no</th>
        </tr>
        <?php $rIdx = 0; foreach ($rows as $r): $rIdx++; ?>
        <tr>
          <td><?php echo htmlspecialchars($r['LotID']); ?></td>
          <td><?php echo htmlspecialchars($r['ProdName']); ?></td>
          <td><?php echo htmlspecialchars($r['InvNo']); ?></td>
          <td><?php echo htmlspecialchars($r['WO']); ?></td>
          <td><?php echo htmlspecialchars($r['RecvDate']); ?></td>
          <td><?php echo htmlspecialchars($r['CurrentStage']); ?></td>
          <td class="waiting-for"><?php echo htmlspecialchars($r['WaitingFor']); ?></td>
          <td class="track-boxno" data-row="<?php echo $rIdx; ?>">all</td>
        </tr>
        <?php foreach ($r['BoxNoList'] as $box): ?>
        <tr class="track-box-row" data-row="<?php echo $rIdx; ?>">
          <td colspan="7"></td>
          <td>Box-no : <?php echo htmlspecialchars($box); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <p>
      <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2</button>
    </p>
  </div>

  <script>
    document.querySelectorAll('.track-boxno').forEach(function (cell) {
      cell.addEventListener('click', function () {
        var rowId = cell.dataset.row;
        var expanded = cell.classList.toggle('expanded');
        document.querySelectorAll('.track-box-row[data-row="' + rowId + '"]').forEach(function (boxRow) {
          boxRow.style.display = expanded ? 'table-row' : 'none';
        });
      });
    });
  </script>
</body>
</html>
