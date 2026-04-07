<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "2") { header("location:../"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../marks_entry");
	exit;
}

$term = (int)($_POST['term'] ?? 0);
$class = (int)($_POST['class'] ?? 0);
$subject = (int)($_POST['subject'] ?? 0);
$mode = ($_POST['mode'] ?? 'cbc') === 'marks' ? 'marks' : 'cbc';

if ($term < 1 || $class < 1 || $subject < 1) {
	$_SESSION['reply'] = array (array("error","Select term, class, and subject."));
	header("location:../marks_entry");
	exit;
}

$_SESSION['cbc_entry'] = [
	'term' => $term,
	'class' => $class,
	'subject' => $subject,
	'mode' => $mode,
];

header("location:../cbc_entry");
?>
