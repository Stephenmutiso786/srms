<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$_SESSION['bulk_result_2'] = $_POST;
header("location:../save_report");

}else{
header("location:../");
}
?>
