<?php

function app_online_column_exists_raw(PDO $conn, string $table, string $column): bool
{
    try {
        if (defined('DBDriver') && DBDriver === 'pgsql') {
            $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ? LIMIT 1");
            $stmt->execute([$table, $column]);
        } else {
            $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1");
            $stmt->execute([DBName, $table, $column]);
        }
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function app_online_prepare_schema(PDO $conn): bool
{
    static $prepared = null;
    if ($prepared !== null) {
        return $prepared;
    }

    if (!app_table_exists($conn, 'tbl_login_sessions')) {
        $prepared = false;
        return false;
    }

    if (!app_online_column_exists_raw($conn, 'tbl_login_sessions', 'last_seen')) {
        try {
            if (defined('DBDriver') && DBDriver === 'pgsql') {
                $conn->exec("ALTER TABLE tbl_login_sessions ADD COLUMN IF NOT EXISTS last_seen timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
            } else {
                $conn->exec("ALTER TABLE tbl_login_sessions ADD COLUMN last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
            }
        } catch (Throwable $e) {
            // Best effort; if this fails, online presence will be unavailable.
        }
    }

    $prepared = app_online_column_exists_raw($conn, 'tbl_login_sessions', 'last_seen');
    return $prepared;
}

function app_online_touch(PDO $conn, string $sessionKey): void
{
    if ($sessionKey === '' || !app_online_prepare_schema($conn)) {
        return;
    }

    try {
        $stmt = $conn->prepare("UPDATE tbl_login_sessions SET last_seen = CURRENT_TIMESTAMP WHERE session_key = ?");
        $stmt->execute([$sessionKey]);
    } catch (Throwable $e) {
        // Ignore touch failures.
    }
}

function app_online_fetch_maps(PDO $conn, int $windowSeconds = 180): array
{
    $out = [
        'staff' => [],
        'students' => [],
        'parents' => [],
    ];

    if (!app_online_prepare_schema($conn)) {
        return $out;
    }

    $since = date('Y-m-d H:i:s', time() - max(30, $windowSeconds));

    try {
        $stmt = $conn->prepare("SELECT DISTINCT staff FROM tbl_login_sessions WHERE staff IS NOT NULL AND last_seen >= ?");
        $stmt->execute([$since]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $out['staff'][(string)$id] = true;
        }
    } catch (Throwable $e) {
    }

    try {
        $stmt = $conn->prepare("SELECT DISTINCT student FROM tbl_login_sessions WHERE student IS NOT NULL AND last_seen >= ?");
        $stmt->execute([$since]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $out['students'][(string)$id] = true;
        }
    } catch (Throwable $e) {
    }

    if (app_online_column_exists_raw($conn, 'tbl_login_sessions', 'parent')) {
        try {
            $stmt = $conn->prepare("SELECT DISTINCT parent FROM tbl_login_sessions WHERE parent IS NOT NULL AND last_seen >= ?");
            $stmt->execute([$since]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $out['parents'][(string)$id] = true;
            }
        } catch (Throwable $e) {
        }
    }

    return $out;
}

function app_online_fetch_users(PDO $conn, string $level, string $accountId, int $limit = 120, int $windowSeconds = 180): array
{
    $result = [
        'is_admin' => ($level === '0' || $level === '9'),
        'users' => [],
        'count' => 0,
    ];

    if (!app_online_prepare_schema($conn)) {
        return $result;
    }

    $since = date('Y-m-d H:i:s', time() - max(30, $windowSeconds));
    $all = [];
    $isPgsql = defined('DBDriver') && DBDriver === 'pgsql';

    try {
        $staffSql = "SELECT ls.staff AS user_id, st.fname, st.lname, st.level
            FROM tbl_login_sessions ls
            JOIN tbl_staff st ON st.id = ls.staff
            WHERE ls.staff IS NOT NULL AND ls.last_seen >= ? AND st.status = 1";
        $stmt = $conn->prepare($staffSql);
        $stmt->execute([$since]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (string)$row['user_id'];
            if (!($result['is_admin'] || ((int)$level !== 3 && (int)$level !== 4))) {
                continue;
            }
            if (!$result['is_admin'] && $id === $accountId) {
                continue;
            }
            $all[] = [
                'id' => 'staff:' . $id,
                'name' => trim((string)$row['fname'] . ' ' . (string)$row['lname']),
                'role' => app_level_title_label((int)$row['level']),
                'scope' => 'staff',
                'status' => 'Online'
            ];
        }
    } catch (Throwable $e) {
    }

    try {
        $studentSql = "SELECT ls.student AS user_id, st.fname, st.mname, st.lname, st.class AS class_id, cl.name AS class_name
            FROM tbl_login_sessions ls
            JOIN tbl_students st ON " . ($isPgsql ? "st.id::text = ls.student::text" : "st.id = ls.student") . "
            LEFT JOIN tbl_classes cl ON cl.id = st.class
            WHERE ls.student IS NOT NULL AND ls.last_seen >= ? AND st.status = 1";
        $stmt = $conn->prepare($studentSql);
        $stmt->execute([$since]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (string)$row['user_id'];
            if (!($result['is_admin'] || (int)$level === 3)) {
                continue;
            }
            if (!$result['is_admin'] && $id === $accountId) {
                continue;
            }
            $all[] = [
                'id' => 'student:' . $id,
                'name' => trim((string)$row['fname'] . ' ' . (string)$row['mname'] . ' ' . (string)$row['lname']),
                'role' => 'Student',
                'scope' => 'student',
                'class_id' => (string)($row['class_id'] ?? ''),
                'class_name' => trim((string)($row['class_name'] ?? '')),
                'status' => 'Online'
            ];
        }
    } catch (Throwable $e) {
    }

    if (app_online_column_exists_raw($conn, 'tbl_login_sessions', 'parent') && app_table_exists($conn, 'tbl_parents')) {
        try {
            $parentSql = "SELECT ls.parent AS user_id, p.fname, p.lname
                FROM tbl_login_sessions ls
                JOIN tbl_parents p ON " . ($isPgsql ? "p.id::text = ls.parent::text" : "p.id = ls.parent") . "
                WHERE ls.parent IS NOT NULL AND ls.last_seen >= ? AND p.status = 1";
            $stmt = $conn->prepare($parentSql);
            $stmt->execute([$since]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (string)$row['user_id'];
                if (!($result['is_admin'] || (int)$level === 4)) {
                    continue;
                }
                if (!$result['is_admin'] && $id === $accountId) {
                    continue;
                }
                $all[] = [
                    'id' => 'parent:' . $id,
                    'name' => trim((string)$row['fname'] . ' ' . (string)$row['lname']),
                    'role' => 'Parent',
                    'scope' => 'parent',
                    'status' => 'Online'
                ];
            }
        } catch (Throwable $e) {
        }
    }

    usort($all, function ($a, $b) {
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });

    $result['count'] = count($all);
    $result['users'] = array_slice($all, 0, max(1, $limit));
    return $result;
}
