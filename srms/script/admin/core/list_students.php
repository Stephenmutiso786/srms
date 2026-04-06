<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$class = $_POST['class'];
$_SESSION['student_list'] = $class;
header("location:../students");

}else{
header("location:../");
}
?>
