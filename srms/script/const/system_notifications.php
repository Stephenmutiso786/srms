<?php

function app_system_notify(PDO $conn, string $title, string $message, array $options = []): bool
{
	if (!app_table_exists($conn, 'tbl_notifications')) {
		return false;
	}

	$title = trim($title);
	$message = trim($message);
	if ($title === '' || $message === '') {
		return false;
	}

	$audience = trim((string)($options['audience'] ?? 'all'));
	if ($audience === '') {
		$audience = 'all';
	}
	$classId = isset($options['class_id']) ? (int)$options['class_id'] : null;
	$termId = isset($options['term_id']) ? (int)$options['term_id'] : null;
	$link = trim((string)($options['link'] ?? 'notifications'));
	$createdBy = isset($options['created_by']) ? (int)$options['created_by'] : null;

	$columns = ['title', 'message'];
	$values = [$title, $message];

	if (app_column_exists($conn, 'tbl_notifications', 'audience')) {
		$columns[] = 'audience';
		$values[] = $audience;
	}
	if (app_column_exists($conn, 'tbl_notifications', 'class_id')) {
		$columns[] = 'class_id';
		$values[] = $classId;
	}
	if (app_column_exists($conn, 'tbl_notifications', 'term_id')) {
		$columns[] = 'term_id';
		$values[] = $termId;
	}
	if (app_column_exists($conn, 'tbl_notifications', 'link')) {
		$columns[] = 'link';
		$values[] = $link;
	}
	if (app_column_exists($conn, 'tbl_notifications', 'created_by')) {
		$columns[] = 'created_by';
		$values[] = $createdBy;
	}

	$placeholders = implode(',', array_fill(0, count($columns), '?'));
	$sql = 'INSERT INTO tbl_notifications (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
	$stmt = $conn->prepare($sql);
	return $stmt->execute($values);
}
