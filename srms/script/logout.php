<?php
session_start();
require_once('db/config.php');

$session_key = $_COOKIE['__SRMS__key'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE session_key = ?");
$stmt->execute([$session_key]);


setcookie("__SRMS__logged", "0", time() - 3600, '/');
setcookie("__SRMS__key", "0", time() - 3600, '/');

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}
header("location:./");
?>
