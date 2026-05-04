<?php
// Minimal placeholder UI for General Ledger (Accountant)
require_once('../db/config.php');
require_once('../core/helpers.php');

$title = APP_NAME . ' - General Ledger';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($title); ?></title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body class="app sidebar-mini">
  <main class="app-content">
    <div class="tile">
      <h3>General Ledger (Placeholder)</h3>
      <p>This is a scaffold for the General Ledger UI. The database tables have been added by migration <code>999_general_ledger.sql</code>.</p>
      <p>Use the accounting API at <code>script/api/accounting_api.php</code> to query ledger entries.</p>
    </div>
  </main>
</body>
</html>
