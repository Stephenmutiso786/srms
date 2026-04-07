<?php
try
{
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_school LIMIT 1");
$stmt->execute();
$result = $stmt->fetchAll();
foreach($result as $row)
{
DEFINE('WBName', $row[1]);
DEFINE('WBLogo', $row[2]);
DEFINE('WBResSys', $row[3]);
DEFINE('WBResAvi', $row[4]);
}

}catch(PDOException $e)
{
// Allow pages to render even if DB is not configured yet.
if (!defined('WBName')) { DEFINE('WBName', ''); }
if (!defined('WBLogo')) { DEFINE('WBLogo', 'school_logo1711003619.png'); }
if (!defined('WBResSys')) { DEFINE('WBResSys', 1); }
if (!defined('WBResAvi')) { DEFINE('WBResAvi', 1); }
}

if (defined('APP_NAME') && (!defined('WBName') || WBName === '')) {
	DEFINE('WBName', APP_NAME);
}
?>
