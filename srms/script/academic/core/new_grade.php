<?php
session_start();
chdir('../../');
require_once('db/config.php');
require_once('const/check_session.php');

// CBC-ONLY: Legacy grade system is deprecated. CBC grading is standardized and cannot be modified.
// Redirect to grading system page with error message.

$_SESSION['reply'] = array(array("warning", "The legacy grading system is deprecated. CBC uses standardized national grading bands (EE, ME, AE, BE) that cannot be modified. Please use the CBC Grading System page to view the current grading standards."));
header("location:../grading-system");
exit;
?>