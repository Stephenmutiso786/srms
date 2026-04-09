<?php
session_start();
chdir('../../');
require_once('db/config.php');
require_once('const/check_session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$id = (int)($_POST['id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

?><option selected disabled value="">Select One</option><?php

if ($id < 1) {
	exit;
}

if ($termId > 0 && app_table_exists($conn, 'tbl_teacher_assignments')) {
	$year = (int)date('Y');
	$stmt = $conn->prepare("SELECT ta.subject_id, s.name AS subject_name
		FROM tbl_teacher_assignments ta
		JOIN tbl_subjects s ON s.id = ta.subject_id
		WHERE ta.teacher_id = ? AND ta.class_id = ? AND ta.term_id = ? AND ta.year = ? AND ta.status = 1");
	$stmt->execute([$account_id, $id, $termId, $year]);
	$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($assignments as $assignment) {
		$comboId = app_get_teacher_subject_combination_id($conn, (int)$account_id, (int)$assignment['subject_id'], $id, true);
		if ($comboId > 0) {
			?><option value="<?php echo $comboId; ?>"><?php echo htmlspecialchars($assignment['subject_name']); ?></option><?php
		}
	}
	exit;
}

$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations
  LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id WHERE tbl_subject_combinations.teacher = ?");
$stmt->execute([$account_id]);
$result = $stmt->fetchAll();
foreach($result as $rowx)
{
$cls = app_unserialize($rowx[1]);

if (in_array((string)$id, array_map('strval', $cls), true))
{
?><option value="<?php echo $rowx[0]; ?>"><?php echo htmlspecialchars($rowx[6]); ?> </option><?php
}
}
}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

}
?>
