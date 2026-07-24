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
		$stmt = $conn->prepare("SELECT ta.subject_id, s.name AS subject_name
			FROM tbl_teacher_assignments ta
			JOIN tbl_subjects s ON s.id = ta.subject_id
			WHERE ta.teacher_id = ? AND ta.class_id = ? AND ta.status = 1
			ORDER BY ta.year DESC, ta.id DESC");
		$stmt->execute([$account_id, $id]);
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$seen = [];
		foreach ($assignments as $assignment) {
			if (!app_teacher_assignment_is_effective($conn, (int)$account_id, $id, (int)$assignment['subject_id'], $termId, (int)date('Y'))) {
				continue;
			}
			$comboId = app_get_teacher_subject_combination_id($conn, (int)$account_id, (int)$assignment['subject_id'], $id, true);
			if ($comboId > 0 && !isset($seen[$comboId])) {
				$seen[$comboId] = true;
				?><option value="<?php echo $comboId; ?>"><?php echo htmlspecialchars($assignment['subject_name']); ?></option><?php
		}
	}
	exit;
}

$stmt = $conn->prepare("SELECT tbl_subject_combinations.id, tbl_subject_combinations.class AS class_list, tbl_subjects.name AS subject_name
  FROM tbl_subject_combinations
  LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id
  WHERE tbl_subject_combinations.teacher = ?");
$stmt->execute([$account_id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($result as $rowx)
{
$cls = app_unserialize((string)($rowx['class_list'] ?? ''));

if (in_array((string)$id, array_map('strval', $cls), true))
{
?><option value="<?php echo (int)$rowx['id']; ?>"><?php echo htmlspecialchars((string)$rowx['subject_name']); ?> </option><?php
}
}
}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}
?>
