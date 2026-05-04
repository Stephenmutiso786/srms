<?php
// Compatibility wrapper for the bulk results UI link which uses `class_id` and `term_id`.
chdir('../');
session_start();
require_once('db/config.php');

// Map legacy query param names to the new endpoint's expected names, then include.
if (isset($_GET['class_id']) && !isset($_GET['class'])) {
	$_GET['class'] = $_GET['class_id'];
}
if (isset($_GET['term_id']) && !isset($_GET['term'])) {
	$_GET['term'] = $_GET['term_id'];
}
if (isset($_GET['exam_id']) && !isset($_GET['exam'])) {
	$_GET['exam'] = $_GET['exam_id'];
}

require_once('admin/class_report_pdf.php');