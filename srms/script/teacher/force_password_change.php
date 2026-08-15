<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
header("location:teacher/index");
exit;
