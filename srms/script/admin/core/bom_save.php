<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../../'); exit; }
app_require_permission('bom.manage', '../bom');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../bom');
	exit;
}

$entity = trim((string)($_POST['entity'] ?? ''));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_bom_tables($conn);

	if ($entity === 'member') {
		$fullName = trim((string)($_POST['full_name'] ?? ''));
		$roleCode = trim((string)($_POST['role_code'] ?? ''));
		$representing = trim((string)($_POST['representing'] ?? ''));
		$phone = trim((string)($_POST['phone'] ?? ''));
		$email = trim((string)($_POST['email'] ?? ''));
		$termStart = trim((string)($_POST['term_start'] ?? ''));
		$termEnd = trim((string)($_POST['term_end'] ?? ''));
		if ($fullName === '' || $roleCode === '') {
			throw new RuntimeException('Missing member details.');
		}
		$stmt = $conn->prepare('INSERT INTO tbl_bom_members (full_name, role_code, representing, phone, email, term_start, term_end, status, created_by) VALUES (?, ?, ?, ?, ?, NULLIF(?,\'\'), NULLIF(?,\'\'), 1, ?)');
		$stmt->execute([$fullName, $roleCode, $representing, $phone, $email, $termStart, $termEnd, (int)$account_id]);
		$_SESSION['reply'] = array(array('success', 'BOM member saved.'));
	} elseif ($entity === 'meeting') {
		$meetingDate = trim((string)($_POST['meeting_date'] ?? ''));
		$title = trim((string)($_POST['title'] ?? ''));
		$agenda = trim((string)($_POST['agenda'] ?? ''));
		$minutes = trim((string)($_POST['minutes'] ?? ''));
		$decisions = trim((string)($_POST['decisions'] ?? ''));
		$status = trim((string)($_POST['status'] ?? 'planned'));
		if ($meetingDate === '' || $title === '' || $agenda === '') {
			throw new RuntimeException('Missing meeting details.');
		}
		$stmt = $conn->prepare('INSERT INTO tbl_bom_meetings (meeting_date, title, agenda, minutes, decisions, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$stmt->execute([$meetingDate, $title, $agenda, $minutes, $decisions, $status, (int)$account_id]);
		$_SESSION['reply'] = array(array('success', 'BOM meeting saved.'));
	} elseif ($entity === 'approval') {
		$approvalDate = trim((string)($_POST['approval_date'] ?? ''));
		$itemTitle = trim((string)($_POST['item_title'] ?? ''));
		$amount = (float)($_POST['amount'] ?? 0);
		$status = trim((string)($_POST['status'] ?? 'pending'));
		$notes = trim((string)($_POST['notes'] ?? ''));
		$approvedByMemberId = (int)($_POST['approved_by_member_id'] ?? 0);
		if ($approvalDate === '' || $itemTitle === '' || $amount < 0) {
			throw new RuntimeException('Missing approval details.');
		}
		$stmt = $conn->prepare('INSERT INTO tbl_bom_financial_approvals (approval_date, item_title, amount, status, notes, approved_by_member_id, created_by) VALUES (?, ?, ?, ?, ?, NULLIF(?,0), ?)');
		$stmt->execute([$approvalDate, $itemTitle, $amount, $status, $notes, $approvedByMemberId, (int)$account_id]);
		$_SESSION['reply'] = array(array('success', 'Financial approval saved.'));
	} else {
		throw new RuntimeException('Unknown save entity.');
	}
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to save BOM data.'));
}

header('location:../bom');
exit;
