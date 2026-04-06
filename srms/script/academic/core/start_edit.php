<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$_SESSION['student_result'] = $_POST;
header("location:../single_results");

}else{
header("location:../");
}
?>
