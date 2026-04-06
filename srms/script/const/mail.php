<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_smtp");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
$smtp_server = $row[1];
$smtp_username = $row[2];
$smtp_password = $row[3];
$smtp_conn_port = $row[4];
$smtp_conn_type = $row[5];
}

}catch(PDOException $e)
{
}
?>
