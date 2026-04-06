<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$_SESSION['result__data'] = $_POST;

header("location:../results");


}else{
header("location:../");
}
?>
