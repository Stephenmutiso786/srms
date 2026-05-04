<?php
/**
 * Finance Module - API Controller
 * Handles student invoices, payments, fee management, and financial reporting
 */

require_once __DIR__ . '/base_controller.php';

class FinanceController extends BaseController {

    /**
     * List student invoices with filtering
     */
    protected function get_invoices() {
        $this->requireAuth();
        
        $pagination = $this->getPagination();
        $student_id = $this->getInput('student_id');
        $status = $this->getInput('status'); // draft, sent, partial, paid, overdue
        
        $where = [];
        if ($student_id) $where['student_id'] = $student_id;
        if ($status) $where['status'] = $status;
        
        $total = $this->db->count('tbl_student_invoices', $where);
        $invoices = $this->db->select(
            'tbl_student_invoices',
            [],
            $where,
            'invoice_date DESC',
            $pagination['per_page'],
            $pagination['offset']
        );

        // Enrich with student and line item data
        foreach ($invoices as &$invoice) {
            $student = $this->db->getOne('tbl_students', ['id' => $invoice['student_id']]);
            $invoice['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
            
            $items = $this->db->select('tbl_invoice_items', [], ['invoice_id' => $invoice['id']]);
            $invoice['items'] = $items;
        }

        $this->respondList($invoices, $total, $pagination['page'], $pagination['per_page']);
    }

    /**
     * Create student invoice
     */
    protected function post_create_invoice() {
        $this->requireAuth();
        $this->requirePermission('finance.create_invoice');
        
        $this->validateRequired(['student_id', 'total_amount']);
        
        $student_id = $this->getInput('student_id', true);
        $total_amount = floatval($this->getInput('total_amount', true));
        $due_date = $this->getInput('due_date');
        $items = $this->getInput('items', false, []);
        
        try {
            $this->db->beginTransaction();
            
            // Generate invoice number
            $invoice_number = $this->generateInvoiceNumber();
            
            $invoice_id = $this->db->insert('tbl_student_invoices', [
                'student_id' => $student_id,
                'invoice_number' => $invoice_number,
                'invoice_date' => date('Y-m-d'),
                'due_date' => $due_date ?: date('Y-m-d', strtotime('+30 days')),
                'total_amount' => $total_amount,
                'balance_due' => $total_amount,
                'status' => 'draft',
                'created_by' => $this->user_id
            ]);
            
            // Add line items
            if (!empty($items)) {
                foreach ($items as $item) {
                    $this->db->insert('tbl_invoice_items', [
                        'invoice_id' => $invoice_id,
                        'item_description' => $item['description'] ?? '',
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'line_total' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0)
                    ]);
                }
            }
            
            // Update student account
            $this->updateStudentAccount($student_id);
            
            // Log action
            $this->log('create', 'finance', 'student_invoices', $invoice_id);
            
            $this->db->commit();
            
            $this->respond([
                'invoice_id' => $invoice_id,
                'invoice_number' => $invoice_number,
                'message' => 'Invoice created successfully'
            ], 201);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondError('Failed to create invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Record student payment
     */
    protected function post_record_payment() {
        $this->requireAuth();
        $this->requirePermission('finance.record_payment');
        
        $this->validateRequired(['student_id', 'amount_paid', 'payment_method_id']);
        
        $student_id = $this->getInput('student_id', true);
        $amount_paid = floatval($this->getInput('amount_paid', true));
        $payment_method_id = $this->getInput('payment_method_id', true);
        $invoice_id = $this->getInput('invoice_id');
        $payment_reference = $this->getInput('payment_reference');
        $payer_name = $this->getInput('payer_name', false, 'Unknown');
        
        try {
            $this->db->beginTransaction();
            
            // Get student account
            $account = $this->db->getOne('tbl_student_accounts', ['student_id' => $student_id]);
            if (!$account) {
                $this->db->insert('tbl_student_accounts', ['student_id' => $student_id]);
                $account = ['total_balance' => 0];
            }
            
            // Create payment record
            $receipt_number = $this->generateReceiptNumber();
            
            $payment_id = $this->db->insert('tbl_payments', [
                'student_id' => $student_id,
                'invoice_id' => $invoice_id,
                'payment_date' => date('Y-m-d'),
                'payment_method_id' => $payment_method_id,
                'amount_paid' => $amount_paid,
                'payment_reference' => $payment_reference,
                'payer_name' => $payer_name,
                'receipt_number' => $receipt_number,
                'balance_after_payment' => max(0, $account['total_balance'] - $amount_paid),
                'recorded_by' => $this->user_id
            ]);
            
            // Update invoice if linked
            if ($invoice_id) {
                $invoice = $this->db->getOne('tbl_student_invoices', ['id' => $invoice_id]);
                $new_balance = max(0, $invoice['balance_due'] - $amount_paid);
                $new_status = ($new_balance == 0) ? 'paid' : 'partial';
                
                $this->db->update('tbl_student_invoices', [
                    'amount_paid' => ($invoice['amount_paid'] ?? 0) + $amount_paid,
                    'balance_due' => $new_balance,
                    'status' => $new_status
                ], ['id' => $invoice_id]);
            }
            
            // Update student account
            $this->updateStudentAccount($student_id);
            
            // Log action
            $this->log('create', 'finance', 'payments', $payment_id);
            
            $this->db->commit();
            
            $this->respond([
                'payment_id' => $payment_id,
                'receipt_number' => $receipt_number,
                'message' => 'Payment recorded successfully'
            ], 201);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondError('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get student financial summary
     */
    protected function get_student_summary() {
        $this->requireAuth();
        $student_id = $this->getInput('student_id', true);
        
        $account = $this->db->getOne('tbl_student_accounts', ['student_id' => $student_id]);
        $invoices = $this->db->select('tbl_student_invoices', [], ['student_id' => $student_id]);
        $payments = $this->db->select('tbl_payments', [], ['student_id' => $student_id]);
        
        $summary = [
            'student_id' => $student_id,
            'total_billed' => $account['total_billed'] ?? 0,
            'total_paid' => $account['total_paid'] ?? 0,
            'balance_due' => $account['total_balance'] ?? 0,
            'account_status' => $account['account_status'] ?? 'active',
            'invoice_count' => count($invoices),
            'payment_count' => count($payments),
            'unpaid_invoices' => array_filter($invoices, fn($i) => $i['status'] !== 'paid'),
            'recent_payment' => $account['last_payment_date'] ?? null,
            'invoices' => $invoices,
            'payments' => $payments
        ];
        
        $this->respond($summary);
    }

    /**
     * Fee structure management
     */
    protected function get_fee_structures() {
        $this->requireAuth();
        
        $structures = $this->db->select('tbl_fee_structures', [], ['is_active' => 1]);
        
        foreach ($structures as &$structure) {
            $items = $this->db->select('tbl_fee_items', [], ['fee_structure_id' => $structure['id']]);
            $structure['items'] = $items;
        }
        
        $this->respond($structures);
    }

    protected function post_create_fee_structure() {
        $this->requireAuth();
        $this->requirePermission('finance.manage_fees');
        
        $this->validateRequired(['name', 'academic_year']);
        
        $structure_id = $this->db->insert('tbl_fee_structures', [
            'name' => $this->getInput('name', true),
            'academic_year' => $this->getInput('academic_year', true),
            'is_active' => 1
        ]);
        
        $this->log('create', 'finance', 'fee_structures', $structure_id);
        $this->respond(['structure_id' => $structure_id], 201);
    }

    /**
     * Journal entry for accounting
     */
    protected function post_journal_entry() {
        $this->requireAuth();
        $this->requirePermission('finance.accounting');
        
        $this->validateRequired(['journal_date', 'entries']);
        
        $entries = $this->getInput('entries', true);
        
        try {
            $this->db->beginTransaction();
            
            $journal_id = $this->db->insert('tbl_journal_entries', [
                'journal_date' => $this->getInput('journal_date', true),
                'description' => $this->getInput('description'),
                'reference_number' => $this->getInput('reference_number'),
                'created_by' => $this->user_id,
                'status' => 'draft'
            ]);
            
            $total_debit = 0;
            $total_credit = 0;
            
            // Add line items
            foreach ($entries as $i => $entry) {
                $debit = floatval($entry['debit'] ?? 0);
                $credit = floatval($entry['credit'] ?? 0);
                
                $this->db->insert('tbl_journal_lines', [
                    'journal_entry_id' => $journal_id,
                    'account_id' => $entry['account_id'],
                    'debit_amount' => $debit,
                    'credit_amount' => $credit,
                    'description' => $entry['description'] ?? '',
                    'line_order' => $i
                ]);
                
                $total_debit += $debit;
                $total_credit += $credit;
            }
            
            // Validate balanced entry
            if (abs($total_debit - $total_credit) > 0.01) {
                throw new Exception("Journal entry out of balance. Debit: $total_debit, Credit: $total_credit");
            }
            
            $this->db->commit();
            $this->log('create', 'finance', 'journal_entries', $journal_id);
            
            $this->respond(['journal_id' => $journal_id], 201);
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->respondError($e->getMessage(), 400);
        }
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber() {
        $year = date('Y');
        $month = date('m');
        $series = $this->db->getOne('tbl_no_series', ['series_type' => 'invoices']);
        
        if (!$series) {
            $this->db->insert('tbl_no_series', [
                'series_type' => 'invoices',
                'current_value' => 1001
            ]);
            $series = ['current_value' => 1001];
        }
        
        $number = sprintf('INV%s%s%04d', $year, $month, $series['current_value']);
        
        $this->db->update('tbl_no_series', 
            ['current_value' => $series['current_value'] + 1],
            ['series_type' => 'invoices']
        );
        
        return $number;
    }

    /**
     * Generate receipt number
     */
    private function generateReceiptNumber() {
        $year = date('Y');
        $series = $this->db->getOne('tbl_no_series', ['series_type' => 'receipts']);
        
        if (!$series) {
            $this->db->insert('tbl_no_series', [
                'series_type' => 'receipts',
                'current_value' => 5001
            ]);
            $series = ['current_value' => 5001];
        }
        
        $number = sprintf('REC%s%04d', $year, $series['current_value']);
        
        $this->db->update('tbl_no_series',
            ['current_value' => $series['current_value'] + 1],
            ['series_type' => 'receipts']
        );
        
        return $number;
    }

    /**
     * Update student account summary
     */
    private function updateStudentAccount($student_id) {
        $invoices = $this->db->select('tbl_student_invoices', [], ['student_id' => $student_id]);
        $payments = $this->db->select('tbl_payments', [], ['student_id' => $student_id]);
        
        $total_billed = 0;
        $total_paid = 0;
        $last_payment = null;
        
        foreach ($invoices as $inv) {
            $total_billed += $inv['total_amount'];
        }
        
        foreach ($payments as $pmt) {
            $total_paid += $pmt['amount_paid'];
            if (!$last_payment || $pmt['payment_date'] > $last_payment) {
                $last_payment = $pmt['payment_date'];
            }
        }
        
        $account = $this->db->getOne('tbl_student_accounts', ['student_id' => $student_id]);
        if ($account) {
            $this->db->update('tbl_student_accounts', [
                'total_billed' => $total_billed,
                'total_paid' => $total_paid,
                'total_balance' => max(0, $total_billed - $total_paid),
                'number_of_invoices' => count($invoices),
                'last_payment_date' => $last_payment
            ], ['student_id' => $student_id]);
        } else {
            $this->db->insert('tbl_student_accounts', [
                'student_id' => $student_id,
                'total_billed' => $total_billed,
                'total_paid' => $total_paid,
                'total_balance' => max(0, $total_billed - $total_paid),
                'number_of_invoices' => count($invoices),
                'last_payment_date' => $last_payment
            ]);
        }
    }
}

// Route handling
$action = $_GET['action'] ?? 'invoices';
$controller = new FinanceController();
$controller->dispatch($action);
