<?php
    session_start();
    require('../connect.php');
    require('../init_session.php');

    // Only superadmin (0) and admin (1) may decide Hold rows.
    $usAut = (int)($_SESSION['us_aut'] ?? -1);
    if (empty($_SESSION['us_id']) || !in_array($usAut, [0, 1], true)) {
        mysqli_close($conn);
        echo "<script>alert('ไม่มีสิทธิ์ใช้งานหน้านี้ (เฉพาะ superadmin / admin)'); location='./nie2_index.php';</script>";
        exit;
    }

    // One entry per process. Table/column names come ONLY from here, never from user input.
    // To add a process later: add an entry (its table needs a status column, Remark, DecidedBy).
    //   statusCol  : column that holds Hold / Accept / ...
    //   alsoSet    : other columns set to the same new value (optional)
    //   noAcceptIf : [column, value] -> rows with this value may not become Accept (optional)
    //   lotKey     : columns that identify one lot
    //   rowKey     : columns that identify one row inside the table
    //   cols       : columns shown in the list
    //   lotForm    : show the "decide whole lot" form (false when the table has one row per lot)
    //   unit       : word used in messages
    $procConfig = [
        'proc1' => [
            'label'     => '1. Receiving',
            'table'     => 'tb_proc1',
            'statusCol' => 'Status',
            'lotKey'    => ['ProdName', 'InvNo', 'WO'],
            'rowKey'    => ['ProdName', 'InvNo', 'WO', 'BoxNo', 'LotID'],
            'cols'      => ['BoxNo', 'BoxQty', 'Date', 'Time', 'Opr', 'Remark'],
            'order'     => 'ProdName, InvNo, WO, CAST(BoxNo AS UNSIGNED), BoxNo',
            'lotForm'   => true,
            'unit'      => 'กล่อง',
        ],
        'proc2' => [
            'label'     => '2. Incoming - Lot (tb_proc2)',
            'table'     => 'tb_proc2',
            'statusCol' => 'Status',
            'alsoSet'   => ['AllBoxCon'],
            'lotKey'    => ['ProdName', 'InvNo', 'WO'],
            'rowKey'    => ['ProdName', 'InvNo', 'WO'],
            'cols'      => ['Date', 'Time', 'Opr', 'PcsFromInv', 'SamplingSize', 'NGtotal', 'Remark'],
            'order'     => 'ProdName, InvNo, WO',
            'lotForm'   => false,
            'unit'      => 'Lot',
        ],
        'proc2box' => [
            'label'      => '2. Incoming - Box condition (tb_proc2_box)',
            'table'      => 'tb_proc2_box',
            'statusCol'  => 'BoxCondStatus',
            'noAcceptIf' => ['BoxCond', 'ชำรุด'],
            'lotKey'     => ['ProdName', 'InvNo', 'WO'],
            'rowKey'     => ['ProdName', 'InvNo', 'WO', 'BoxNo'],
            'cols'       => ['BoxNo', 'BoxCond', 'Date', 'Time', 'Opr', 'Remark'],
            'order'      => 'ProdName, InvNo, WO, CAST(BoxNo AS UNSIGNED), BoxNo',
            'lotForm'    => true,
            'unit'       => 'กล่อง',
        ],
    ];
    $decisionValues = ['Accept', 'Reject', 'SpecialAccept'];
    $reasonRequired = ['Reject', 'SpecialAccept'];
    $remarkMaxLen   = 255;

    if (empty($_SESSION['hold_csrf'])) {
        $_SESSION['hold_csrf'] = bin2hex(random_bytes(16));
    }

    // ---------- POST: save a decision (per box or per lot) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $proc      = $_POST['proc'] ?? '';
        $scope     = $_POST['scope'] ?? '';
        $newStatus = $_POST['NewStatus'] ?? '';
        $reason    = trim($_POST['Reason'] ?? '');
        $msg       = '';

        if (!hash_equals($_SESSION['hold_csrf'], $_POST['csrf'] ?? '')) {
            $msg = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
        } elseif (!isset($procConfig[$proc]) || !in_array($scope, ['box', 'lot'], true)
                  || ($scope === 'lot' && !$procConfig[$proc]['lotForm'])) {
            $msg = 'ข้อมูลไม่ถูกต้อง';
        } elseif (!in_array($newStatus, $decisionValues, true)) {
            $msg = 'โปรดเลือกผลการตัดสินใจ (Accept / Reject / SpecialAccept)';
        } elseif ($reason === '' && in_array($newStatus, $reasonRequired, true)) {
            $msg = 'โปรดระบุเหตุผล สำหรับ ' . $newStatus;
        } else {
            $cfg     = $procConfig[$proc];
            $keyCols = $scope === 'lot' ? $cfg['lotKey'] : $cfg['rowKey'];

            $where  = [];
            $params = [];
            foreach ($keyCols as $col) {
                $where[]  = "`$col` = ?";
                $params[] = (string)($_POST[$col] ?? '');
            }
            $where[]  = "`{$cfg['statusCol']}` = 'Hold'";   // only rows that are still Hold
            $whereSql = implode(' AND ', $where);

            // Text appended to Remark, e.g. "scratch | Accept: customer approved"
            $appendText = $reason === '' ? $newStatus : $newStatus . ': ' . $reason;
            $newRemark  = "CONCAT(IF(`Remark` IS NULL OR `Remark` = '', '', CONCAT(`Remark`, ' | ')), ?)";

            // Refuse (instead of cutting text) if any Remark would exceed the column length.
            $chkStmt = mysqli_prepare($conn,
                "SELECT COUNT(*) AS n FROM `{$cfg['table']}` WHERE $whereSql AND CHAR_LENGTH($newRemark) > $remarkMaxLen");
            $chkParams = array_merge($params, [$appendText]);
            mysqli_stmt_bind_param($chkStmt, str_repeat('s', count($chkParams)), ...$chkParams);
            mysqli_stmt_execute($chkStmt);
            $tooLong = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt))['n'];

            // Some rows may never become Accept (e.g. damaged box in tb_proc2_box)
            $noAccept = 0;
            if ($newStatus === 'Accept' && !empty($cfg['noAcceptIf'])) {
                [$naCol, $naVal] = $cfg['noAcceptIf'];
                $naStmt = mysqli_prepare($conn,
                    "SELECT COUNT(*) AS n FROM `{$cfg['table']}` WHERE $whereSql AND `$naCol` = ?");
                $naParams = array_merge($params, [$naVal]);
                mysqli_stmt_bind_param($naStmt, str_repeat('s', count($naParams)), ...$naParams);
                mysqli_stmt_execute($naStmt);
                $noAccept = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($naStmt))['n'];
            }

            if ($noAccept > 0) {
                $msg = 'มี ' . $noAccept . ' ' . $cfg['unit'] . ' ที่ ' . $cfg['noAcceptIf'][1]
                     . ' เลือก Accept ไม่ได้ (เลือก Reject หรือ SpecialAccept)';
            } elseif ($tooLong > 0) {
                $msg = 'Remark ยาวเกิน ' . $remarkMaxLen . ' ตัวอักษร กรุณาย่อเหตุผลให้สั้นลง';
            } else {
                $decidedBy = (int)$_SESSION['us_id'];
                $setSql    = "`{$cfg['statusCol']}` = ?";
                $setParams = [$newStatus];
                foreach ($cfg['alsoSet'] ?? [] as $col) {
                    $setSql     .= ", `$col` = ?";
                    $setParams[] = $newStatus;
                }
                $updStmt = mysqli_prepare($conn,
                    "UPDATE `{$cfg['table']}` SET $setSql, `DecidedBy` = ?, `Remark` = $newRemark WHERE $whereSql");
                $updParams = array_merge($setParams, [$decidedBy, $appendText], $params);
                $updTypes  = str_repeat('s', count($setParams)) . 'i'
                           . str_repeat('s', count($updParams) - count($setParams) - 1);
                mysqli_stmt_bind_param($updStmt, $updTypes, ...$updParams);

                if (!mysqli_stmt_execute($updStmt)) {
                    $msg = 'บันทึกไม่สำเร็จ กรุณาลองใหม่';
                } elseif (mysqli_stmt_affected_rows($updStmt) === 0) {
                    $msg = 'ไม่พบรายการ Hold นี้ (อาจถูกตัดสินใจไปแล้ว)';
                } else {
                    $msg = 'บันทึกสำเร็จ: ' . mysqli_stmt_affected_rows($updStmt) . ' ' . $cfg['unit'] . ' -> ' . $newStatus;
                }
            }
        }

        // Post/Redirect/Get: a refresh will not re-send the decision.
        mysqli_close($conn);
        $_SESSION['hold_msg'] = $msg;
        header('Location: ./nie2_hold_decision.php?proc=' . urlencode(isset($procConfig[$proc]) ? $proc : 'proc1'));
        exit;
    }

    // ---------- GET: list Hold rows of the selected process ----------
    $proc = $_GET['proc'] ?? 'proc1';
    if (!isset($procConfig[$proc])) {
        $proc = 'proc1';
    }
    $cfg = $procConfig[$proc];

    $selCols = array_unique(array_merge($cfg['lotKey'], $cfg['rowKey'], $cfg['cols']));
    $selSql  = implode(', ', array_map(fn($c) => "`$c`", $selCols));
    $res = mysqli_query($conn,
        "SELECT $selSql FROM `{$cfg['table']}` WHERE `{$cfg['statusCol']}` = 'Hold' ORDER BY {$cfg['order']}");

    // Group rows by lot
    $lots = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lotKey = implode("\x1F", array_map(fn($c) => $row[$c], $cfg['lotKey']));
        $lots[$lotKey][] = $row;
    }
    mysqli_close($conn);

    $flash = $_SESSION['hold_msg'] ?? '';
    unset($_SESSION['hold_msg']);

    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

    // Hidden inputs + decision controls shared by box and lot forms
    function decisionControls($proc, $scope, $keyCols, $row, $decisionValues) {
        $out  = '<input type="hidden" name="csrf" value="' . h($_SESSION['hold_csrf']) . '">';
        $out .= '<input type="hidden" name="proc" value="' . h($proc) . '">';
        $out .= '<input type="hidden" name="scope" value="' . h($scope) . '">';
        foreach ($keyCols as $col) {
            $out .= '<input type="hidden" name="' . h($col) . '" value="' . h($row[$col]) . '">';
        }
        $out .= '<select name="NewStatus" required><option value="">-- เลือก --</option>';
        foreach ($decisionValues as $v) {
            $out .= '<option value="' . h($v) . '">' . h($v) . '</option>';
        }
        $out .= '</select>';
        $out .= '<input type="text" name="Reason" maxlength="100" placeholder="เหตุผล">';
        $out .= '<button type="submit">บันทึก</button>';
        return $out;
    }
?>

<!doctype html>
<head>
  <meta http-equiv="Content-Type" name="viewport" content="text/html; charset=utf-8; width=device-width; initial-scale=1.0">
  <title>production record</title>
  <link rel="stylesheet" type='text/css' href="../style01.css">
  <style>
    .hold-wrap {
      text-align: center;
      padding-bottom: 20px;
    }
    .hold-proc {
      margin: 10px auto;
    }
    .hold-section {
      margin: 10px 4px;
      overflow-x: auto;
    }
    .hold-table {
      margin: 0 auto;
      border-collapse: collapse;
      font-size: 0.88em;
    }
    .hold-table th, .hold-table td {
      padding: 4px 8px;
      border: 1px solid #bbb;
      white-space: nowrap;
    }
    .hold-table th {
      background-color: lightskyblue;
    }
    .hold-lot-row td {
      background-color: #fff3cd;
      font-weight: bold;
      text-align: left;
    }
    .hold-table form {
      display: flex;
      gap: 4px;
      align-items: center;
      margin: 0;
    }
    .hold-table input[type="text"] {
      width: 140px;
    }
    .hold-table button {
      padding: 3px 10px;
      border: none;
      border-radius: 4px;
      background-color: green;
      color: white;
      cursor: pointer;
    }
    .hold-lot-row button {
      background-color: darkorange;
    }
    .hold-empty {
      margin: 20px 0;
      color: #888;
    }
    .hold-wrap button#Nie2_homeBtn {
      padding: 8px 5px;
      margin: 10px 5px;
      width: 110px;
      border-style: none;
      border-radius: 4px;
      color: white;
      background-color: blue;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  <?php require('../topbar.php'); ?>

  <div class="hold-wrap">
    <h2>ตัดสินใจ Hold — Ni-e Line 2</h2>

    <form class="hold-proc" method="get" action="<?php echo h($_SERVER['PHP_SELF']); ?>">
      <label>Process: </label>
      <select name="proc" onchange="this.form.submit()">
        <?php foreach ($procConfig as $key => $pc): ?>
          <option value="<?php echo h($key); ?>" <?php echo $key === $proc ? 'selected' : ''; ?>><?php echo h($pc['label']); ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if (!$lots): ?>
      <p class="hold-empty">ไม่มีรายการ Hold ใน <?php echo h($cfg['label']); ?></p>
    <?php else: ?>
      <div class="hold-section">
        <table class="hold-table">
          <tr>
            <?php foreach ($cfg['cols'] as $col): ?>
              <th><?php echo h($col); ?></th>
            <?php endforeach; ?>
            <th>ตัดสินใจ</th>
          </tr>
          <?php foreach ($lots as $rows): $first = $rows[0]; ?>
            <tr class="hold-lot-row">
              <td colspan="<?php echo count($cfg['cols']); ?>">
                <?php echo h(implode(' / ', array_map(fn($c) => $first[$c], $cfg['lotKey']))); ?>
                — Hold <?php echo count($rows); ?> <?php echo h($cfg['unit']); ?>
              </td>
              <td>
                <?php if ($cfg['lotForm']): ?>
                <form method="post" class="lotForm" data-count="<?php echo count($rows); ?>">
                  <?php echo decisionControls($proc, 'lot', $cfg['lotKey'], $first, $decisionValues); ?>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php foreach ($rows as $row): ?>
              <tr>
                <?php foreach ($cfg['cols'] as $col): ?>
                  <td><?php echo h($row[$col]); ?></td>
                <?php endforeach; ?>
                <td>
                  <form method="post">
                    <?php echo decisionControls($proc, 'box', $cfg['rowKey'], $row, $decisionValues); ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>

    <button type="button" id="Nie2_homeBtn" onclick="window.location.href='./nie2_index.php'">กลับหน้าหลัก<br>Ni-e line 2</button>
  </div>

  <script>
    <?php if ($flash !== ''): ?>
    alert(<?php echo json_encode($flash); ?>);
    <?php endif; ?>

    var reasonRequired = <?php echo json_encode($reasonRequired); ?>;

    document.querySelectorAll('.hold-table form').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var status = form.querySelector('select[name="NewStatus"]').value;
        var reason = form.querySelector('input[name="Reason"]').value.trim();

        if (reasonRequired.indexOf(status) !== -1 && reason === '') {
          e.preventDefault();
          alert('โปรดระบุเหตุผล สำหรับ ' + status);
          return;
        }
        if (form.classList.contains('lotForm')) {
          var n = form.getAttribute('data-count');
          if (!confirm('เปลี่ยนทุกกล่องที่ Hold ใน Lot นี้ (' + n + ' กล่อง) เป็น ' + status + ' ?')) {
            e.preventDefault();
          }
        }
      });
    });
  </script>
</body>
</html>
