<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT mail_server, mail_username, mail_password, mail_port, mail_security FROM tbl_smtp");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($result as $row)
{
$smtp_server = (string)($row['mail_server'] ?? '');
$smtp_username = (string)($row['mail_username'] ?? '');
$smtp_password = (string)($row['mail_password'] ?? '');
$smtp_conn_port = (string)($row['mail_port'] ?? '');
$smtp_conn_type = (string)($row['mail_security'] ?? '');
}

}catch(PDOException $e)
{
}
?>
