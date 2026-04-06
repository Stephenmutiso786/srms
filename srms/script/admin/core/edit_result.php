<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

$_SESSION['student_result']['student'] = $_GET['std'];
$_SESSION['student_result']['term'] = $_GET['term'];
header("location:../single_results");

}else{
header("location:../");
}
?>
