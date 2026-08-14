<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // Filters (all optional, GET so the page is bookmarkable/shareable)
    $startDate = $_GET['start_date'] ?? '';
    $endDate   = $_GET['end_date']   ?? '';
    $fProdName = trim($_GET['prodname'] ?? '');
    $fInvNo    = trim($_GET['invno']    ?? '');
    $fWO       = trim($_GET['wo']       ?? '');

    // NG mode columns come from the full tb_ng_list (every process' modes),
    // since tb_ng itself carries a Process per record rather than being
    // scoped to a single process like the tb_proc6 inspection stage.
    $ngModeList = [];
    $nmStmt = mysqli_prepare($conn, "SELECT DISTINCT NGmode FROM tb_ng_list ORDER BY NGmode");
    mysqli_stmt_execute($nmStmt);
    $nmRes = mysqli_stmt_get_result($nmStmt);
    while ($nmRow = mysqli_fetch_assoc($nmRes)) {
        $ngModeList[] = $nmRow['NGmode'];
    }

    // Pivoted per-BoxNo query: one SUM(CASE WHEN NGmode = ? ...) column per
    // NG mode, plus a running total, grouped down to BoxNo level so the
    // group-level (ProdName+InvNo+WO+Process) totals can be rolled up in PHP.
    $aliasToMode = [];
    $selectParts = ['ProdName', 'InvNo', 'WO', 'Process', 'BoxNo'];
    $bindTypes   = '';
    $bindValues  = [];

    foreach ($ngModeList as $i => $mode) {
        $alias = "ngmode_$i";
        $aliasToMode[$alias] = $mode;
        $selectParts[] = "SUM(CASE WHEN NGmode = ? THEN NGqty ELSE 0 END) AS `$alias`";
        $bindTypes   .= 's';
        $bindValues[] = $mode;
    }
    $selectParts[] = "SUM(NGqty) AS TotalNG";

    $where = [];
    if ($startDate !== '') { $where[] = "Date >= ?"; $bindTypes .= 's'; $bindValues[] = $startDate; }
    if ($endDate   !== '') { $where[] = "Date <= ?"; $bindTypes .= 's'; $bindValues[] = $endDate; }
    if ($fProdName !== '') { $where[] = "ProdName LIKE ?"; $bindTypes .= 's'; $bindValues[] = '%' . $fProdName . '%'; }
    if ($fInvNo    !== '') { $where[] = "InvNo LIKE ?";    $bindTypes .= 's'; $bindValues[] = '%' . $fInvNo    . '%'; }
    if ($fWO       !== '') { $where[] = "WO LIKE ?";       $bindTypes .= 's'; $bindValues[] = '%' . $fWO       . '%'; }

    $sql = "SELECT " . implode(', ', $selectParts) . " FROM tb_ng";
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " GROUP BY ProdName, InvNo, WO, Process, BoxNo ORDER BY ProdName, InvNo, WO, Process, BoxNo";

    $stmt = mysqli_prepare($conn, $sql);
    if ($bindTypes !== '') {
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $boxRows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $boxRows[] = $row;
    }

    // Roll the per-BoxNo rows up into one group per ProdName+InvNo+WO+Process.
    $groups = [];
    foreach ($boxRows as $r) {
        $key = $r['ProdName'] . "\x1F" . $r['InvNo'] . "\x1F" . $r['WO'] . "\x1F" . $r['Process'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'ProdName' => $r['ProdName'],
                'InvNo'    => $r['InvNo'],
                'WO'       => $r['WO'],
                'Process'  => $r['Process'],
                'sums'     => array_fill_keys(array_keys($aliasToMode), 0),
                'TotalNG'  => 0,
                'boxes'    => [],
            ];
        }
        foreach ($aliasToMode as $alias => $mode) {
            $groups[$key]['sums'][$alias] += (int)$r[$alias];
        }
        $groups[$key]['TotalNG'] += (int)$r['TotalNG'];
        $groups[$key]['boxes'][] = $r;
    }

    mysqli_close($conn);
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0">
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
  <style>
    .ngmon-wrap {
      text-align: center;
      padding-bottom: 20px;
    }
    .ngmon-filter {
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
    .ngmon-filter div {
      display: flex;
      flex-direction: column;
      text-align: left;
      font-size: 0.9em;
    }
    .ngmon-filter label { font-weight: bold; margin-bottom: 2px; }
    .ngmon-filter button {
      padding: 6px 18px;
      border-radius: 4px;
      border: none;
      background-color: mediumblue;
      color: white;
      cursor: pointer;
      height: 30px;
    }
    .ngmon-section {
      margin: 10px 4px 4px 4px;
      overflow-x: auto;
    }
    .ngmon-table {
      margin: 0 auto;
      border-collapse: collapse;
      font-size: 0.88em;
    }
    .ngmon-table th, .ngmon-table td {
      padding: 4px 9px;
      border: 1px solid #bbb;
      white-space: nowrap;
    }
    .ngmon-table th {
      background-color: lightskyblue;
    }
    .ngmon-table td.ngmon-total {
      font-weight: bold;
      background-color: #fff3cd;
    }
    .ngmon-group-row {
      cursor: pointer;
    }
    .ngmon-group-row:hover {
      background-color: #eef6ff;
    }
    .ngmon-group-row td:first-child::before {
      content: "\25B6  ";
      font-size: 0.8em;
    }
    .ngmon-group-row.expanded td:first-child::before {
      content: "\25BC  ";
    }
    .ngmon-box-row {
      background-color: #fafafa;
      display: none;
    }
    .ngmon-box-row td:first-child {
      text-align: left;
      padding-left: 20px;
      color: #555;
    }
    .ngmon-empty {
      margin: 20px 0;
      color: #888;
    }
  </style>
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="ngmon-wrap">
    <h2>ติดตามข้อมูล NG (tb_ng) — Ni-e Line 2</h2>

    <form class="ngmon-filter" method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
      <div>
        <label>Start date</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
      </div>
      <div>
        <label>End date</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
      </div>
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

    <?php if (empty($groups)): ?>
    <p class="ngmon-empty">ไม่พบข้อมูล</p>
    <?php else: ?>
    <div class="ngmon-section">
      <table class="ngmon-table">
        <thead>
          <tr>
            <th>Product name</th>
            <th>Invoice no</th>
            <th>WO</th>
            <th>Process</th>
            <?php foreach ($ngModeList as $mode): ?>
            <th><?php echo htmlspecialchars($mode); ?></th>
            <?php endforeach; ?>
            <th>รวม NG</th>
          </tr>
        </thead>
        <tbody>
          <?php $gIdx = 0; foreach ($groups as $g): $gIdx++; ?>
          <tr class="ngmon-group-row" data-group="<?php echo $gIdx; ?>">
            <td><?php echo htmlspecialchars($g['ProdName']); ?></td>
            <td><?php echo htmlspecialchars($g['InvNo']); ?></td>
            <td><?php echo htmlspecialchars($g['WO']); ?></td>
            <td><?php echo htmlspecialchars($g['Process']); ?></td>
            <?php foreach ($aliasToMode as $alias => $mode): ?>
            <td><?php echo (int)$g['sums'][$alias]; ?></td>
            <?php endforeach; ?>
            <td class="ngmon-total"><?php echo (int)$g['TotalNG']; ?></td>
          </tr>
          <?php foreach ($g['boxes'] as $b): ?>
          <tr class="ngmon-box-row" data-group="<?php echo $gIdx; ?>">
            <td>Box-no : <?php echo htmlspecialchars($b['BoxNo']); ?></td>
            <td colspan="3"></td>
            <?php foreach ($aliasToMode as $alias => $mode): ?>
            <td><?php echo (int)$b[$alias]; ?></td>
            <?php endforeach; ?>
            <td class="ngmon-total"><?php echo (int)$b['TotalNG']; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <p>
      <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2</button>
    </p>
  </div>

  <script>
    document.querySelectorAll('.ngmon-group-row').forEach(function (row) {
      row.addEventListener('click', function () {
        var groupId = row.dataset.group;
        var expanded = row.classList.toggle('expanded');
        document.querySelectorAll('.ngmon-box-row[data-group="' + groupId + '"]').forEach(function (boxRow) {
          boxRow.style.display = expanded ? 'table-row' : 'none';
        });
      });
    });
  </script>
</body>
</html>
