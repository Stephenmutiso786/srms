<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

// Teachers can only change their password, not other profile fields
// All teacher profile data is read-only and managed by admin
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../profile');
	exit;
}

// Verify session
if ($res !== '1' || $level !== '2') {
	header('location:../../');
	exit;
}

// Any attempt to POST to profile update is rejected
// Teachers should only use the password change form
$_SESSION['reply'] = array(array('danger', 'Your profile information is managed by the school. Only password changes are allowed.'));
header('location:../profile');
exit;
