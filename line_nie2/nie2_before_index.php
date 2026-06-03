<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // Handle lot selection — set session then redirect
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lotid'])) {
        $_SESSION['lotid'] = $_POST['lotid'];
        mysqli_close($conn);
        header('Location: ./nie2_index.php');
        exit;
    }

    $stmt = mysqli_prepare($conn,
        "SELECT ProdName, WO, InvNo, SubLot, LotID FROM tb_proc1 WHERE DoneFlag = ? ORDER BY LotID DESC");
    mysqli_stmt_bind_param($stmt, 's', $flag);
    $flag = 'no';
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_close($conn);
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0">
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
  <style>
    .bi-wrap {
      text-align: center;
      padding-bottom: 20px;
    }
    .bi-topbtn {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin: 10px 0 16px 0;
    }
    .bi-topbtn button {
      padding: 7px 22px;
      border: none;
      border-radius: 4px;
      font-size: 1em;
      cursor: pointer;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .bi-topbtn button#backBtn {
      background-color: red;
      color: white;
      font-weight: bold;
    }
    .bi-topbtn button#lcardBtn {
      background-color: mediumblue;
      color: white;
      font-weight: bold;
    }
    .bi-table {
      margin: 0 auto;
      border-collapse: collapse;
      font-size: 0.95em;
    }
    .bi-table th {
      background-color: lightskyblue;
      padding: 5px 12px;
      border: 1px solid #bbb;
    }
    .bi-table td {
      padding: 4px 12px;
      border: 1px solid #bbb;
      white-space: nowrap;
    }
    .bi-table .no-data td {
      color: #888;
      font-style: italic;
    }
    .lotid-btn {
      padding: 3px 14px;
      background-color: green;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9em;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .lotid-btn:hover {
      background-color: darkgreen;
    }
    .bi-proc-row {
      text-align: center;
      margin: 0 0 16px 0;
    }
    .bi-proc-row button {
      padding: 7px 22px;
      border: none;
      border-radius: 4px;
      font-size: 1em;
      cursor: pointer;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
      background-color: #e0e0e0;
      color: #222;
      font-weight: bold;
    }
    .bi-proc-row button:hover {
      background-color: #c8c8c8;
    }
  </style>
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="bi-wrap">
    <h2>Ni-e Line 2 (Full-Auto) — เลือก Lot</h2>

    <div class="bi-topbtn">
      <button id="backBtn"  onclick="window.location.href='../index.php'">&#8592; กลับ</button>
      <button id="lcardBtn" onclick="window.location.href='./nie2_lcard_index.php'">ประวัติ Lot Card</button>
    </div>

    <div class="bi-proc-row">
      <button type="button" id="Nie2_Proc01_Btn" onclick="goProc01()">1. Receiving</button>
    </div>

    <table class="bi-table">
      <thead>
        <tr>
          <th>Product name</th>
          <th>WO</th>
          <th>Invoice no</th>
          <th>Sub lot</th>
          <th>LotID</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr class="no-data">
            <td colspan="5">— ไม่มีข้อมูล (DoneFlag = no) —</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['ProdName']); ?></td>
            <td><?php echo htmlspecialchars($r['WO']); ?></td>
            <td><?php echo htmlspecialchars($r['InvNo']); ?></td>
            <td><?php echo htmlspecialchars($r['SubLot']); ?></td>
            <td>
              <form method="POST" action="" style="display:inline;">
                <input type="hidden" name="lotid" value="<?php echo htmlspecialchars($r['LotID']); ?>">
                <button type="submit" class="lotid-btn"><?php echo htmlspecialchars($r['LotID']); ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    function goProc01() {
      window.location.href = './nie2_proc01.php';
    }
  </script>
</body>
</html>
