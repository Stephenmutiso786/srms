<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}
app_require_permission('system.manage', '../migrations');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../migrations");
	exit;
}

if (DBDriver !== 'pgsql') {
	$_SESSION['reply'] = array(array("danger", "Migrations runner is available for Postgres only."));
	header("location:../migrations");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$conn->exec("CREATE TABLE IF NOT EXISTS tbl_schema_migrations (name varchar(120) PRIMARY KEY, applied_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP)");

	$dir = dirname(__DIR__, 2).'/database/pg_migrations';
	$files = glob($dir.'/*.sql') ?: [];
	sort($files, SORT_NATURAL);

	$stmt = $conn->prepare("SELECT name FROM tbl_schema_migrations");
	$stmt->execute();
	$applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$appliedMap = array_fill_keys($applied, true);

	$appliedCount = 0;
	foreach ($files as $file) {
		$name = basename($file);
		if (isset($appliedMap[$name])) {
			continue;
		}
		$sql = file_get_contents($file);
		if ($sql === false) {
			throw new RuntimeException("Failed to read $name");
		}
		$conn->beginTransaction();
		$conn->exec($sql);
		$stmt = $conn->prepare("INSERT INTO tbl_schema_migrations (name) VALUES (?)");
		$stmt->execute([$name]);
		$conn->commit();
		$appliedCount++;
	}

	$_SESSION['reply'] = array(array("success", "Applied $appliedCount migrations."));
	header("location:../migrations");
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array(array("danger", "Migration failed: ".$e->getMessage()));
	header("location:../migrations");
}
