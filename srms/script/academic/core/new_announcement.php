<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/system_notifications.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$title = trim((string)($_POST['title'] ?? ''));
$audience = trim((string)($_POST['audience'] ?? ''));
$announcement = trim((string)($_POST['announcement'] ?? ''));
$post_date = date('Y-m-d G:i:s');
$legacyLevel = $audience;
$staffLevel = (string)($GLOBALS['level'] ?? '');

if ($res !== "1" || $legacyLevel === '' || !in_array($staffLevel, ['1', '0'], true)) {
	header("location:../");
	exit;
}

if ($title === '' || $announcement === '') {
	$_SESSION['reply'] = array (array("danger",'Title and announcement are required.'));
	header("location:../announcement");
	exit;
}

if (!in_array($audience, ['0', '1', '2', '3'], true)) {
	$audience = '2';
	$legacyLevel = '2';
}

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("INSERT INTO tbl_announcements (title, announcement, create_date, level) VALUES (?,?,?,?)");
$stmt->execute([$title, $announcement, $post_date, $legacyLevel]);

	if (app_table_exists($conn, 'tbl_notifications')) {
		$plainMessage = trim(strip_tags($announcement));
		if ($plainMessage === '') {
			$plainMessage = $announcement;
		}

		$audiences = [];
		switch ($audience) {
			case '0':
				$audiences = ['staff'];
				break;
			case '1':
				$audiences = ['students', 'parents'];
				break;
			case '3':
				$audiences = ['all'];
				break;
			case '2':
			default:
				$audiences = ['all'];
				break;
		}

		foreach ($audiences as $feedAudience) {
			app_system_notify($conn, $title, $plainMessage, [
				'audience' => $feedAudience,
				'class_id' => null,
				'term_id' => null,
				'link' => 'academic/announcement.php',
				'created_by' => (int)($account_id ?? 0),
				'module_name' => 'notifications',
				'type' => 'info',
				'priority' => 55,
				'email_link' => 'academic/announcement.php',
			]);
		}
	}

$_SESSION['reply'] = array (array("success",'Announcement created successfully'));
header("location:../announcement.php");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
$_SESSION['reply'] = array (array("danger",'Failed to create announcement.'));
header("location:../announcement.php");
exit;
}


}else{
header("location:../");
}
?>
