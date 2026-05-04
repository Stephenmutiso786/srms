<?php
/**
 * Accounting API - Chart of Accounts and General Ledger Management
 * 
 * Endpoints:
 * - GET  /api/accounting_api.php?action=list_accounts - List all accounts
 * - GET  /api/accounting_api.php?action=get_account&id=N - Get single account
 * - POST /api/accounting_api.php?action=create_account - Create account
 * - POST /api/accounting_api.php?action=update_account&id=N - Update account
 * - DELETE /api/accounting_api.php?action=delete_account&id=N - Delete account
 * - GET  /api/accounting_api.php?action=list_entries&account_id=N - List GL entries
 * - POST /api/accounting_api.php?action=post_entry - Post GL entry
 * - GET  /api/accounting_api.php?action=trial_balance - Generate trial balance
 * - GET  /api/accounting_api.php?action=account_balance&account_id=N - Get account balance
 */

chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

// Require finance permission for most operations
if (!in_array($_REQUEST['action'] ?? '', ['account_balance', 'list_accounts', 'list_entries', 'trial_balance'])) {
	app_require_permission('finance.manage', '../../');
}

header('Content-Type: application/json');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_require_accounting_tables($conn);

	$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));

	switch ($action) {
		case 'list_accounts':
			echo json_encode(accounting_list_accounts($conn));
			break;

		case 'get_account':
			$id = (int)($_REQUEST['id'] ?? 0);
			echo json_encode(accounting_get_account($conn, $id));
			break;

		case 'create_account':
			$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
			echo json_encode(accounting_create_account($conn, $data, $account_id ?? null));
			break;

		case 'update_account':
			$id = (int)($_REQUEST['id'] ?? 0);
			$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
			echo json_encode(accounting_update_account($conn, $id, $data));
			break;

		case 'delete_account':
			$id = (int)($_REQUEST['id'] ?? 0);
			echo json_encode(accounting_delete_account($conn, $id));
			break;

		case 'list_entries':
			$accountId = (int)($_REQUEST['account_id'] ?? 0);
			$limit = (int)($_REQUEST['limit'] ?? 100);
			echo json_encode(accounting_list_entries($conn, $accountId, $limit));
			break;

		case 'post_entry':
			$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
			echo json_encode(accounting_post_entry($conn, $data, $account_id ?? null));
			break;

		case 'trial_balance':
			echo json_encode(accounting_trial_balance($conn));
			break;

		case 'account_balance':
			$accountId = (int)($_REQUEST['account_id'] ?? 0);
			$asOfDate = (string)($_REQUEST['as_of_date'] ?? date('Y-m-d'));
			echo json_encode(accounting_account_balance($conn, $accountId, $asOfDate));
			break;

		default:
			http_response_code(400);
			echo json_encode(['error' => 'Invalid action']);
			break;
	}
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Ensure accounting tables exist.
 */
function app_require_accounting_tables(PDO $conn): void
{
	if (!app_table_exists($conn, 'tbl_chart_of_accounts')) {
		throw new RuntimeException('Chart of accounts table not created. Run migration 999_general_ledger.');
	}
	if (!app_table_exists($conn, 'tbl_gl_entries')) {
		throw new RuntimeException('GL entries table not created. Run migration 999_general_ledger.');
	}
}

/**
 * List all chart of accounts.
 */
function accounting_list_accounts(PDO $conn): array
{
	$stmt = $conn->prepare("SELECT id, code, name, type, parent_id, created_at
		FROM tbl_chart_of_accounts
		ORDER BY code ASC");
	$stmt->execute();
	return ['success' => true, 'accounts' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * Get a single account by ID.
 */
function accounting_get_account(PDO $conn, int $accountId): array
{
	if ($accountId < 1) {
		return ['error' => 'Invalid account ID'];
	}
	$stmt = $conn->prepare("SELECT id, code, name, type, parent_id, created_at
		FROM tbl_chart_of_accounts
		WHERE id = ? LIMIT 1");
	$stmt->execute([$accountId]);
	$account = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$account) {
		return ['error' => 'Account not found'];
	}
	// Get balance
	$balance = accounting_account_balance($conn, $accountId);
	$account['balance'] = $balance['balance'] ?? 0.0;
	return ['success' => true, 'account' => $account];
}

/**
 * Create a new account.
 */
function accounting_create_account(PDO $conn, array $data, ?int $createdBy = null): array
{
	$code = trim((string)($data['code'] ?? ''));
	$name = trim((string)($data['name'] ?? ''));
	$type = strtolower(trim((string)($data['type'] ?? '')));
	$parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

	if ($code === '' || $name === '' || !in_array($type, ['asset', 'liability', 'equity', 'income', 'expense'], true)) {
		return ['error' => 'Code, name, and valid type (asset/liability/equity/income/expense) required'];
	}

	// Check for duplicate code
	$stmt = $conn->prepare("SELECT id FROM tbl_chart_of_accounts WHERE code = ? LIMIT 1");
	$stmt->execute([$code]);
	if ($stmt->fetchColumn()) {
		return ['error' => 'Account code already exists'];
	}

	$stmt = $conn->prepare("INSERT INTO tbl_chart_of_accounts (code, name, type, parent_id) VALUES (?, ?, ?, ?)");
	$stmt->execute([$code, $name, $type, $parentId]);
	$accountId = (int)$conn->lastInsertId();

	return ['success' => true, 'message' => 'Account created', 'account_id' => $accountId];
}

/**
 * Update an existing account.
 */
function accounting_update_account(PDO $conn, int $accountId, array $data): array
{
	if ($accountId < 1) {
		return ['error' => 'Invalid account ID'];
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_chart_of_accounts WHERE id = ? LIMIT 1");
	$stmt->execute([$accountId]);
	if (!$stmt->fetchColumn()) {
		return ['error' => 'Account not found'];
	}

	$name = trim((string)($data['name'] ?? ''));
	$type = isset($data['type']) ? strtolower(trim((string)$data['type'])) : null;
	$parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

	if ($name !== '') {
		$stmt = $conn->prepare("UPDATE tbl_chart_of_accounts SET name = ? WHERE id = ?");
		$stmt->execute([$name, $accountId]);
	}

	if ($type !== null && in_array($type, ['asset', 'liability', 'equity', 'income', 'expense'], true)) {
		$stmt = $conn->prepare("UPDATE tbl_chart_of_accounts SET type = ? WHERE id = ?");
		$stmt->execute([$type, $accountId]);
	}

	if ($parentId !== null) {
		$stmt = $conn->prepare("UPDATE tbl_chart_of_accounts SET parent_id = ? WHERE id = ?");
		$stmt->execute([$parentId, $accountId]);
	}

	return ['success' => true, 'message' => 'Account updated'];
}

/**
 * Delete an account.
 */
function accounting_delete_account(PDO $conn, int $accountId): array
{
	if ($accountId < 1) {
		return ['error' => 'Invalid account ID'];
	}

	// Check for entries
	$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_gl_entries WHERE account_id = ?");
	$stmt->execute([$accountId]);
	if ((int)$stmt->fetchColumn() > 0) {
		return ['error' => 'Cannot delete account with entries'];
	}

	$stmt = $conn->prepare("DELETE FROM tbl_chart_of_accounts WHERE id = ?");
	$stmt->execute([$accountId]);

	return ['success' => true, 'message' => 'Account deleted'];
}

/**
 * List GL entries for an account.
 */
function accounting_list_entries(PDO $conn, int $accountId, int $limit = 100): array
{
	if ($accountId < 1) {
		return ['error' => 'Invalid account ID'];
	}

	$stmt = $conn->prepare("SELECT id, account_id, date, description, debit, credit, created_by, created_at
		FROM tbl_gl_entries
		WHERE account_id = ?
		ORDER BY date DESC, id DESC
		LIMIT ?");
	$stmt->execute([$accountId, $limit]);
	$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

	return ['success' => true, 'account_id' => $accountId, 'entries' => $entries, 'count' => count($entries)];
}

/**
 * Post a GL entry.
 */
function accounting_post_entry(PDO $conn, array $data, ?int $createdBy = null): array
{
	$accountId = (int)($data['account_id'] ?? 0);
	$date = trim((string)($data['date'] ?? date('Y-m-d')));
	$description = trim((string)($data['description'] ?? ''));
	$debit = (float)($data['debit'] ?? 0);
	$credit = (float)($data['credit'] ?? 0);

	if ($accountId < 1) {
		return ['error' => 'Invalid account ID'];
	}

	// Validate date
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
		return ['error' => 'Invalid date format (YYYY-MM-DD)'];
	}

	// Check account exists
	$stmt = $conn->prepare("SELECT id FROM tbl_chart_of_accounts WHERE id = ? LIMIT 1");
	$stmt->execute([$accountId]);
	if (!$stmt->fetchColumn()) {
		return ['error' => 'Account not found'];
	}

	// Debit and credit cannot both be zero
	if ($debit <= 0 && $credit <= 0) {
		return ['error' => 'Either debit or credit must be greater than 0'];
	}

	// Both debit and credit should not be set (basic validation)
	if ($debit > 0 && $credit > 0) {
		return ['error' => 'Cannot post both debit and credit in one entry'];
	}

	$stmt = $conn->prepare("INSERT INTO tbl_gl_entries (account_id, date, description, debit, credit, created_by)
		VALUES (?, ?, ?, ?, ?, ?)");
	$stmt->execute([$accountId, $date, $description, $debit, $credit, $createdBy]);
	$entryId = (int)$conn->lastInsertId();

	return ['success' => true, 'message' => 'Entry posted', 'entry_id' => $entryId];
}

/**
 * Calculate account balance as of a given date.
 */
function accounting_account_balance(PDO $conn, int $accountId, string $asOfDate = null): array
{
	if ($accountId < 1) {
		return ['error' => 'Invalid account ID'];
	}

	if ($asOfDate === null) {
		$asOfDate = date('Y-m-d');
	}

	$stmt = $conn->prepare("SELECT id, type FROM tbl_chart_of_accounts WHERE id = ? LIMIT 1");
	$stmt->execute([$accountId]);
	$account = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$account) {
		return ['error' => 'Account not found'];
	}

	$stmt = $conn->prepare("SELECT
		COALESCE(SUM(debit), 0) as total_debit,
		COALESCE(SUM(credit), 0) as total_credit
		FROM tbl_gl_entries
		WHERE account_id = ? AND date <= ?");
	$stmt->execute([$accountId, $asOfDate]);
	$sums = $stmt->fetch(PDO::FETCH_ASSOC);

	$debit = (float)($sums['total_debit'] ?? 0);
	$credit = (float)($sums['total_credit'] ?? 0);

	// For asset accounts: balance = debit - credit
	// For liability/equity: balance = credit - debit
	// For income: balance = credit (revenue)
	// For expense: balance = debit (cost)
	$accountType = strtolower(trim((string)($account['type'] ?? 'asset')));
	if (in_array($accountType, ['asset', 'expense'], true)) {
		$balance = $debit - $credit;
	} else {
		$balance = $credit - $debit;
	}

	return [
		'success' => true,
		'account_id' => $accountId,
		'as_of_date' => $asOfDate,
		'debit' => round($debit, 2),
		'credit' => round($credit, 2),
		'balance' => round($balance, 2),
		'account_type' => $accountType,
	];
}

/**
 * Generate trial balance.
 */
function accounting_trial_balance(PDO $conn, string $asOfDate = null): array
{
	if ($asOfDate === null) {
		$asOfDate = date('Y-m-d');
	}

	$stmt = $conn->prepare("SELECT id, code, name, type FROM tbl_chart_of_accounts ORDER BY code ASC");
	$stmt->execute();
	$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$accounts_with_balance = [];
	$total_debits = 0.0;
	$total_credits = 0.0;

	foreach ($accounts as $account) {
		$balance_result = accounting_account_balance($conn, (int)$account['id'], $asOfDate);
		if (!($balance_result['success'] ?? false)) {
			continue;
		}

		$balance = (float)($balance_result['balance'] ?? 0);
		$account_type = strtolower(trim((string)($balance_result['account_type'] ?? 'asset')));

		// For trial balance: assets and expenses are debits; liabilities, equity, and income are credits
		if (in_array($account_type, ['asset', 'expense'], true)) {
			$debit = max(0, $balance);
			$credit = max(0, -$balance);
		} else {
			$credit = max(0, $balance);
			$debit = max(0, -$balance);
		}

		$total_debits += $debit;
		$total_credits += $credit;

		$accounts_with_balance[] = [
			'id' => (int)$account['id'],
			'code' => (string)$account['code'],
			'name' => (string)$account['name'],
			'type' => (string)$account['type'],
			'debit' => round($debit, 2),
			'credit' => round($credit, 2),
		];
	}

	return [
		'success' => true,
		'as_of_date' => $asOfDate,
		'accounts' => $accounts_with_balance,
		'total_debit' => round($total_debits, 2),
		'total_credit' => round($total_credits, 2),
		'balanced' => abs($total_debits - $total_credits) < 0.01,
	];
}
