<?php
/**
 * Database query optimizations with caching
 * Caches frequently accessed data to reduce DB load
 */

/**
 * Get all classes with caching (TTL: 1 hour)
 */
function app_get_classes_cached(PDO $conn): array {
	$cacheKey = 'classes_all';
	$cached = app_cache_json_get($cacheKey);
	if ($cached !== null) {
		return $cached;
	}

	try {
		$stmt = $conn->prepare("SELECT id, name, form FROM tbl_classes ORDER BY form, name");
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		app_cache_json_set($cacheKey, $rows, 3600);
		return $rows;
	} catch (Throwable $e) {
		return [];
	}
}

/**
 * Get all terms with caching (TTL: 1 hour)
 */
function app_get_terms_cached(PDO $conn): array {
	$cacheKey = 'terms_all';
	$cached = app_cache_json_get($cacheKey);
	if ($cached !== null) {
		return $cached;
	}

	try {
		$stmt = $conn->prepare("SELECT id, name, academicyear FROM tbl_term ORDER BY academicyear DESC, id DESC");
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		app_cache_json_set($cacheKey, $rows, 3600);
		return $rows;
	} catch (Throwable $e) {
		return [];
	}
}

/**
 * Get all subjects with caching (TTL: 1 hour)
 */
function app_get_subjects_cached(PDO $conn): array {
	$cacheKey = 'subjects_all';
	$cached = app_cache_json_get($cacheKey);
	if ($cached !== null) {
		return $cached;
	}

	try {
		$stmt = $conn->prepare("SELECT id, name, code FROM tbl_subjects ORDER BY name");
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		app_cache_json_set($cacheKey, $rows, 3600);
		return $rows;
	} catch (Throwable $e) {
		return [];
	}
}

/**
 * Get class by ID with caching
 */
function app_get_class_cached(PDO $conn, $classId): ?array {
	$cacheKey = 'class_' . $classId;
	$cached = app_cache_json_get($cacheKey);
	if ($cached !== null) {
		return $cached;
	}

	try {
		$stmt = $conn->prepare("SELECT id, name, form, stream FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$classId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			app_cache_json_set($cacheKey, $row, 3600);
		}
		return $row;
	} catch (Throwable $e) {
		return null;
	}
}

/**
 * Invalidate common cache keys when data changes
 */
function app_invalidate_system_cache(?string $type = null): void {
	if ($type === null || $type === 'classes') {
		app_cache_delete('classes_all');
	}
	if ($type === null || $type === 'terms') {
		app_cache_delete('terms_all');
	}
	if ($type === null || $type === 'subjects') {
		app_cache_delete('subjects_all');
	}
	if ($type === null || $type === 'exams') {
		app_cache_delete('exams_all');
	}
}

/**
 * Clear class cache by ID
 */
function app_invalidate_class_cache(string $classId): void {
	app_cache_delete('class_' . $classId);
	app_invalidate_system_cache('classes');
}

/**
 * Optimized student count by class
 */
function app_student_count_by_class(PDO $conn, $classId): int {
	$cacheKey = 'student_count_class_' . $classId;
	$cached = app_cache_get($cacheKey);
	if ($cached !== null) {
		return (int)$cached;
	}

	try {
		$count = (int)$conn->query("SELECT COUNT(*) FROM tbl_students WHERE class = " . $conn->quote($classId))->fetchColumn();
		app_cache_set($cacheKey, (string)$count, 1800);
		return $count;
	} catch (Throwable $e) {
		return 0;
	}
}
