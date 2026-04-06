<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$_SESSION['bulk_result'] = $_POST;
header("location:../bulk_results");

}else{
header("location:../");
}
?>
